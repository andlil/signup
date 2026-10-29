<?php

namespace Tests\Feature\Signups;

use App\Enums\NotificationMode;
use App\Enums\QuestionType;
use App\Enums\SignupStatus;
use App\Mail\OrganizerSignupMail;
use App\Mail\SignupReceivedMail;
use App\Models\Event;
use App\Models\Question;
use App\Models\Signup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class SignupTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function form(array $overrides = []): array
    {
        return [
            'name' => 'Anna Andersson',
            'email' => 'anna@example.com',
            'consent' => '1',
            ...$overrides,
        ];
    }

    public function test_a_visitor_can_sign_up(): void
    {
        $event = Event::factory()->published()->create();
        $food = Question::factory()->for($event)->choice(['Kött', 'Fisk', 'Vegetariskt'], QuestionType::Checkboxes)->create(['position' => 1]);
        $note = Question::factory()->for($event)->create(['position' => 2]);
        $arrival = Question::factory()->for($event)->create(['type' => QuestionType::Date, 'position' => 3]);

        $response = $this->post(route('signups.store', $event), $this->form([
            'email' => ' Anna@Example.com ',
            'answers' => [
                $food->id => ['Vegetariskt', 'Fisk'],
                $note->id => '  Kommer sent  ',
                $arrival->id => '',
            ],
        ]));

        $signup = Signup::query()->sole();
        $response->assertRedirect(route('signups.show', ['token' => $signup->manage_token]));

        $this->assertSame('anna@example.com', $signup->email);
        $this->assertSame(SignupStatus::Confirmed, $signup->status);
        $this->assertSame(1, $signup->places);
        $this->assertSame([$food->id => ['Fisk', 'Vegetariskt'], $note->id => 'Kommer sent'], $signup->answers);
        $this->assertNotNull($signup->consented_at);
        Mail::assertQueued(SignupReceivedMail::class, fn (SignupReceivedMail $mail): bool => $mail->hasTo('anna@example.com'));
    }

    public function test_answers_are_validated_against_the_questions(): void
    {
        $event = Event::factory()->published()->create();
        $required = Question::factory()->for($event)->create(['is_required' => true]);
        $choice = Question::factory()->for($event)->choice(['Ja', 'Nej'])->create();
        $boxes = Question::factory()->for($event)->choice(['A', 'B'], QuestionType::Checkboxes)->create();
        $number = Question::factory()->for($event)->number(1, 5)->create();
        $date = Question::factory()->for($event)->create(['type' => QuestionType::Date]);

        $this->post(route('signups.store', $event), $this->form([
            'answers' => [
                $choice->id => 'Kanske',
                $boxes->id => ['A', 'C'],
                $number->id => '9',
                $date->id => '31/12/2027',
            ],
        ]))->assertSessionHasErrors([
            "answers.{$required->id}",
            "answers.{$choice->id}",
            "answers.{$boxes->id}.1",
            "answers.{$number->id}",
            "answers.{$date->id}",
        ]);

        $this->assertDatabaseEmpty('signups');
    }

    public function test_error_messages_name_the_question(): void
    {
        $event = Event::factory()->published()->create();
        $question = Question::factory()->for($event)->create(['label' => 'Telefonnummer', 'is_required' => true]);

        $this->withSession(['locale' => 'sv'])
            ->post(route('signups.store', $event), $this->form())
            ->assertSessionHasErrors(["answers.{$question->id}" => 'Telefonnummer måste fyllas i.']);
    }

    public function test_consent_is_required(): void
    {
        $event = Event::factory()->published()->create();

        $this->post(route('signups.store', $event), $this->form(['consent' => null]))
            ->assertSessionHasErrors('consent');
    }

    public function test_an_email_address_can_only_sign_up_once_unless_cancelled(): void
    {
        $event = Event::factory()->published()->create();
        $existing = Signup::factory()->for($event)->create(['email' => 'anna@example.com']);

        $this->post(route('signups.store', $event), $this->form(['email' => 'ANNA@example.com']))
            ->assertSessionHasErrors(['email' => __('This email address is already signed up. Use the link in your confirmation email to change your signup.')]);

        $existing->forceFill(['status' => SignupStatus::Cancelled])->save();

        $this->post(route('signups.store', $event), $this->form())->assertSessionHasNoErrors();
        $this->assertSame(2, $event->signups()->count());
    }

    public function test_the_same_email_can_sign_up_for_different_events(): void
    {
        Signup::factory()->create(['email' => 'anna@example.com']);
        $event = Event::factory()->published()->create();

        $this->post(route('signups.store', $event), $this->form())->assertSessionHasNoErrors();
    }

    public function test_a_full_event_puts_signups_on_the_waiting_list(): void
    {
        $event = Event::factory()->published()->create(['capacity' => 1]);
        Signup::factory()->for($event)->create();

        $this->get(route('events.public', $event))->assertSee(__('Join the waiting list'));

        $this->post(route('signups.store', $event), $this->form())
            ->assertSessionHas('signup_status', SignupStatus::Waitlisted->value);

        $this->assertSame(SignupStatus::Waitlisted, Signup::query()->where('email', 'anna@example.com')->sole()->status);
    }

    public function test_places_can_be_counted_from_a_number_question(): void
    {
        $event = Event::factory()->published()->create(['capacity' => 5]);
        $people = Question::factory()->for($event)->number(1, 10)->create(['is_required' => true]);
        $event->capacityQuestion()->associate($people)->save();

        $this->post(route('signups.store', $event), $this->form(['answers' => [$people->id => '3']]));
        $this->post(route('signups.store', $event), $this->form(['email' => 'bertil@example.com', 'answers' => [$people->id => '3']]));
        $this->post(route('signups.store', $event), $this->form(['email' => 'cecilia@example.com', 'answers' => [$people->id => '2']]));

        $this->assertSame(
            ['anna@example.com' => 'confirmed', 'bertil@example.com' => 'waitlisted', 'cecilia@example.com' => 'confirmed'],
            $event->signups()->orderBy('id')->get()->mapWithKeys(fn (Signup $signup): array => [$signup->email => $signup->status->value])->all(),
        );
        $this->assertSame(5, $event->takenPlaces());
    }

    public function test_a_signup_cannot_be_larger_than_the_event(): void
    {
        $event = Event::factory()->published()->create(['capacity' => 4]);
        $people = Question::factory()->for($event)->number(1, 10)->create(['is_required' => true]);
        $event->capacityQuestion()->associate($people)->save();

        $this->post(route('signups.store', $event), $this->form(['answers' => [$people->id => '5']]))
            ->assertSessionHasErrors("answers.{$people->id}");
    }

    public function test_drafts_closed_events_and_passed_deadlines_do_not_accept_signups(): void
    {
        $draft = Event::factory()->create();
        $closed = Event::factory()->closed()->create();
        $pastDeadline = Event::factory()->published()->create(['signup_deadline' => now()->subMinute()]);

        foreach ([$draft, $closed, $pastDeadline] as $event) {
            $this->post(route('signups.store', $event), $this->form())->assertForbidden();
        }

        $this->assertDatabaseEmpty('signups');
    }

    public function test_bots_that_fill_the_hidden_field_are_ignored(): void
    {
        $event = Event::factory()->published()->create();

        $this->post(route('signups.store', $event), $this->form(['website' => 'http://spam.example']))
            ->assertRedirect(route('events.public', $event));

        $this->assertDatabaseEmpty('signups');
    }

    public function test_the_organizer_is_emailed_about_each_signup_when_they_asked_for_it(): void
    {
        $notified = Event::factory()->published()->create(['notification_mode' => NotificationMode::EachSignup]);
        $silent = Event::factory()->published()->create(['notification_mode' => NotificationMode::None]);

        $this->post(route('signups.store', $notified), $this->form());
        $this->post(route('signups.store', $silent), $this->form());

        Mail::assertQueuedCount(3);
        Mail::assertQueued(OrganizerSignupMail::class, fn (OrganizerSignupMail $mail): bool => $mail->hasTo($notified->user->email) && ! $mail->cancelled);
        Mail::assertNotQueued(OrganizerSignupMail::class, fn (OrganizerSignupMail $mail): bool => $mail->hasTo($silent->user->email));
    }

    public function test_the_participant_is_emailed_in_the_language_they_signed_up_in(): void
    {
        $event = Event::factory()->published()->create();

        $this->withSession(['locale' => 'en'])->post(route('signups.store', $event), $this->form());

        $this->assertSame('en', Signup::query()->sole()->locale);
    }

    public function test_signing_up_is_rate_limited(): void
    {
        $event = Event::factory()->published()->create();

        foreach (range(1, 10) as $attempt) {
            $this->post(route('signups.store', $event), $this->form(['email' => "person{$attempt}@example.com"]));
        }

        $this->post(route('signups.store', $event), $this->form(['email' => 'one-too-many@example.com']))->assertTooManyRequests();
    }
}
