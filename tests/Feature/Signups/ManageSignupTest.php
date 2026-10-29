<?php

namespace Tests\Feature\Signups;

use App\Enums\NotificationMode;
use App\Enums\SignupStatus;
use App\Mail\OrganizerSignupMail;
use App\Mail\SignupCancelledMail;
use App\Mail\SignupPromotedMail;
use App\Models\Event;
use App\Models\Question;
use App\Models\Signup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ManageSignupTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
    }

    public function test_the_secret_link_shows_the_signup(): void
    {
        $signup = Signup::factory()->create(['name' => 'Anna']);

        $this->get($signup->manageUrl())
            ->assertOk()
            ->assertSee('Anna')
            ->assertSee($signup->event->title);
    }

    public function test_a_wrong_token_is_not_found(): void
    {
        Signup::factory()->create();

        $this->get(route('signups.show', ['token' => str_repeat('x', 48)]))->assertNotFound();
    }

    public function test_the_token_is_stored_encrypted_and_hashed(): void
    {
        $signup = Signup::factory()->create();
        $row = Signup::query()->toBase()->where('id', $signup->id)->first();

        $this->assertNotSame($signup->manage_token, $row->manage_token);
        $this->assertSame(hash('sha256', $signup->manage_token), $row->manage_token_hash);
    }

    public function test_the_participant_can_change_their_answers(): void
    {
        $event = Event::factory()->published()->create();
        $question = Question::factory()->for($event)->create();
        $signup = Signup::factory()->for($event)->create(['answers' => [$question->id => 'Före']]);

        $this->put(route('signups.update', ['token' => $signup->manage_token]), [
            'name' => 'Nytt Namn',
            'email' => $signup->email,
            'answers' => [$question->id => 'Efter'],
        ])->assertRedirect($signup->manageUrl())->assertSessionHas('signup_status', 'updated');

        $signup->refresh();
        $this->assertSame('Nytt Namn', $signup->name);
        $this->assertSame([$question->id => 'Efter'], $signup->answers);
    }

    public function test_a_confirmed_signup_cannot_grow_beyond_the_free_places(): void
    {
        $event = Event::factory()->published()->create(['capacity' => 5]);
        $people = Question::factory()->for($event)->number(1, 10)->create(['is_required' => true]);
        $event->capacityQuestion()->associate($people)->save();
        Signup::factory()->for($event)->create(['places' => 2, 'answers' => [$people->id => 2]]);
        $signup = Signup::factory()->for($event)->create(['places' => 2, 'answers' => [$people->id => 2]]);

        $this->put(route('signups.update', ['token' => $signup->manage_token]), [
            'name' => $signup->name,
            'email' => $signup->email,
            'answers' => [$people->id => '4'],
        ])->assertSessionHasErrors("answers.{$people->id}");

        $this->assertSame(2, $signup->fresh()->places);

        $this->put(route('signups.update', ['token' => $signup->manage_token]), [
            'name' => $signup->name,
            'email' => $signup->email,
            'answers' => [$people->id => '3'],
        ])->assertSessionHasNoErrors();

        $this->assertSame(3, $signup->fresh()->places);
        $this->assertSame(SignupStatus::Confirmed, $signup->fresh()->status);
    }

    public function test_fewer_places_let_the_waiting_list_move_up(): void
    {
        $event = Event::factory()->published()->create(['capacity' => 4]);
        $people = Question::factory()->for($event)->number(1, 10)->create(['is_required' => true]);
        $event->capacityQuestion()->associate($people)->save();
        $signup = Signup::factory()->for($event)->create(['places' => 4, 'answers' => [$people->id => 4]]);
        $waiting = Signup::factory()->for($event)->waitlisted()->create(['places' => 2, 'answers' => [$people->id => 2]]);

        $this->put(route('signups.update', ['token' => $signup->manage_token]), [
            'name' => $signup->name,
            'email' => $signup->email,
            'answers' => [$people->id => '2'],
        ]);

        $this->assertSame(SignupStatus::Confirmed, $waiting->fresh()->status);
        Mail::assertQueued(SignupPromotedMail::class, fn (SignupPromotedMail $mail): bool => $mail->hasTo($waiting->email));
    }

    public function test_cancelling_gives_the_place_to_the_first_on_the_waiting_list(): void
    {
        $event = Event::factory()->published()->create(['capacity' => 1, 'notification_mode' => NotificationMode::EachSignup]);
        $signup = Signup::factory()->for($event)->create();
        $first = Signup::factory()->for($event)->waitlisted()->create();
        $second = Signup::factory()->for($event)->waitlisted()->create();

        $this->delete(route('signups.destroy', ['token' => $signup->manage_token]))
            ->assertRedirect($signup->manageUrl())
            ->assertSessionHas('signup_status', 'cancelled');

        $this->assertSame(SignupStatus::Cancelled, $signup->fresh()->status);
        $this->assertSame(SignupStatus::Confirmed, $first->fresh()->status);
        $this->assertSame(SignupStatus::Waitlisted, $second->fresh()->status);
        $this->assertSame(1, $second->fresh()->waitlistPosition());

        Mail::assertQueued(SignupCancelledMail::class, fn (SignupCancelledMail $mail): bool => $mail->hasTo($signup->email) && ! $mail->byOrganizer);
        Mail::assertQueued(SignupPromotedMail::class, fn (SignupPromotedMail $mail): bool => $mail->hasTo($first->email));
        Mail::assertQueued(OrganizerSignupMail::class, fn (OrganizerSignupMail $mail): bool => $mail->cancelled);
    }

    public function test_after_the_deadline_answers_cannot_be_changed_and_by_default_the_signup_cannot_be_cancelled(): void
    {
        $event = Event::factory()->published()->create(['signup_deadline' => now()->subDay()]);
        $signup = Signup::factory()->for($event)->create();

        $this->get($signup->manageUrl())
            ->assertOk()
            ->assertSee(__('Signups for the event are closed, so the answers can no longer be changed.'))
            ->assertSee(__('The last day to cancel has passed. Contact the organizer if you can no longer come.'))
            ->assertDontSee(__('Cancel my signup'));

        $this->put(route('signups.update', ['token' => $signup->manage_token]), [
            'name' => 'Ändrat',
            'email' => $signup->email,
        ])->assertForbidden();

        $this->delete(route('signups.destroy', ['token' => $signup->manage_token]))->assertForbidden();
        $this->assertSame(SignupStatus::Confirmed, $signup->fresh()->status);
    }

    public function test_the_organizer_can_allow_cancelling_until_the_event_starts(): void
    {
        Mail::fake();
        $event = Event::factory()->published()->create([
            'signup_deadline' => now()->subDay(),
            'starts_at' => now()->addWeek(),
            'allow_late_cancellation' => true,
        ]);
        $signup = Signup::factory()->for($event)->create();

        $this->withSession(['locale' => 'sv'])
            ->get($signup->manageUrl())
            ->assertSee(__('Cancel my signup'))
            ->assertSee('Du kan avboka fram till');

        $this->delete(route('signups.destroy', ['token' => $signup->manage_token]))->assertRedirect();
        $this->assertSame(SignupStatus::Cancelled, $signup->fresh()->status);
    }

    public function test_late_cancellation_never_continues_past_the_start(): void
    {
        $event = Event::factory()->published()->create([
            'signup_deadline' => now()->subDays(2),
            'starts_at' => now()->subHour(),
            'allow_late_cancellation' => true,
        ]);
        $signup = Signup::factory()->for($event)->create();

        $this->delete(route('signups.destroy', ['token' => $signup->manage_token]))->assertForbidden();
    }

    public function test_cancelling_closes_at_the_start_if_it_comes_before_the_deadline(): void
    {
        $event = Event::factory()->published()->create([
            'starts_at' => now()->subHour(),
            'signup_deadline' => now()->addDay(),
        ]);
        $signup = Signup::factory()->for($event)->create();

        $this->delete(route('signups.destroy', ['token' => $signup->manage_token]))->assertForbidden();
    }

    public function test_a_signup_cannot_be_cancelled_once_the_event_has_started(): void
    {
        $event = Event::factory()->published()->create(['starts_at' => now()->subHour()]);
        $signup = Signup::factory()->for($event)->create();

        $this->delete(route('signups.destroy', ['token' => $signup->manage_token]))->assertForbidden();
        $this->assertSame(SignupStatus::Confirmed, $signup->fresh()->status);
    }

    public function test_a_cancelled_signup_cannot_be_changed_or_cancelled_again(): void
    {
        $signup = Signup::factory()->cancelled()->create();

        $this->put(route('signups.update', ['token' => $signup->manage_token]), ['name' => 'X', 'email' => $signup->email])->assertForbidden();
        $this->delete(route('signups.destroy', ['token' => $signup->manage_token]))->assertForbidden();
    }
}
