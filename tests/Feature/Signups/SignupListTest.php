<?php

namespace Tests\Feature\Signups;

use App\Enums\SignupStatus;
use App\Livewire\Events\SignupList;
use App\Mail\OrganizerSignupMail;
use App\Mail\SignupCancelledMail;
use App\Models\Event;
use App\Models\Question;
use App\Models\Signup;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\TestCase;

class SignupListTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_organizer_sees_signups_and_their_answers(): void
    {
        $event = Event::factory()->published()->create(['capacity' => 10]);
        $question = Question::factory()->for($event)->create(['label' => 'Allergier']);
        Signup::factory()->for($event)->create(['name' => 'Anna', 'answers' => [$question->id => 'Nötter']]);
        Signup::factory()->for($event)->waitlisted()->create(['name' => 'Bertil']);

        Livewire::actingAs($event->user)
            ->test(SignupList::class, ['event' => $event])
            ->assertSee('Allergier')
            ->assertSee('Anna')
            ->assertSee('Nötter')
            ->assertDontSee('Bertil')
            ->set('show', SignupStatus::Waitlisted->value)
            ->assertSee('Bertil')
            ->assertDontSee('Anna');
    }

    public function test_the_organizer_can_cancel_a_signup_which_promotes_the_waiting_list(): void
    {
        Mail::fake();
        $event = Event::factory()->published()->create(['capacity' => 1]);
        $signup = Signup::factory()->for($event)->create();
        $waiting = Signup::factory()->for($event)->waitlisted()->create();

        Livewire::actingAs($event->user)
            ->test(SignupList::class, ['event' => $event])
            ->call('cancel', $signup->id);

        $this->assertSame(SignupStatus::Cancelled, $signup->fresh()->status);
        $this->assertSame(SignupStatus::Confirmed, $waiting->fresh()->status);
        Mail::assertQueued(SignupCancelledMail::class, fn (SignupCancelledMail $mail): bool => $mail->byOrganizer);
        Mail::assertNotQueued(OrganizerSignupMail::class);
    }

    public function test_only_cancelled_signups_can_be_deleted(): void
    {
        $event = Event::factory()->published()->create();
        $active = Signup::factory()->for($event)->create();
        $cancelled = Signup::factory()->for($event)->cancelled()->create();

        $component = Livewire::actingAs($event->user)->test(SignupList::class, ['event' => $event]);

        $component->call('delete', $cancelled->id);
        $this->assertModelMissing($cancelled);

        $component->call('delete', $active->id)->assertStatus(409);
        $this->assertModelExists($active);
    }

    public function test_signups_of_another_event_cannot_be_touched(): void
    {
        $event = Event::factory()->published()->create();
        $otherSignup = Signup::factory()->create();

        Livewire::actingAs($event->user)
            ->test(SignupList::class, ['event' => $event])
            ->call('cancel', $otherSignup->id)
            ->assertNotFound();
    }

    public function test_other_organizers_cannot_see_the_signups(): void
    {
        $event = Event::factory()->published()->create();

        $this->actingAs(User::factory()->create())
            ->get(route('events.signups', $event))
            ->assertForbidden();
    }

    public function test_actions_are_authorized_on_every_request(): void
    {
        $event = Event::factory()->published()->create();
        $signup = Signup::factory()->for($event)->create();
        $component = Livewire::actingAs($event->user)->test(SignupList::class, ['event' => $event]);

        $event->user->update(['role' => 'organizer']);
        $event->forceFill(['user_id' => User::factory()->create()->id])->save();

        $component->call('cancel', $signup->id)->assertForbidden();
        $this->assertSame(SignupStatus::Confirmed, $signup->fresh()->status);
    }
}
