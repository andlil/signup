<?php

namespace Tests\Feature;

use App\Mail\AccountCreatedMail;
use App\Mail\MagicLinkMail;
use App\Mail\SignupCancelledMail;
use App\Mail\SignupReceivedMail;
use App\Models\Event;
use App\Models\Question;
use App\Models\Signup;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MailContentTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_login_link_email_contains_the_link_in_the_users_language(): void
    {
        $user = User::factory()->make(['name' => 'Anna', 'locale' => 'sv']);

        $mail = (new MagicLinkMail($user, 'https://signup.test/login/abc'))->locale('sv');

        $mail->assertHasSubject('Din inloggningslänk till '.config('app.name'));
        $mail->assertSeeInHtml('https://signup.test/login/abc');
        $mail->assertSeeInText('Hej Anna!');
        $mail->assertSeeInText('Länken gäller i 15 minuter');
    }

    public function test_the_account_created_email_links_to_the_login_page(): void
    {
        $user = User::factory()->make(['name' => 'Bertil']);

        $mail = (new AccountCreatedMail($user))->locale('en');

        $mail->assertHasSubject('You have an account on '.config('app.name'));
        $mail->assertSeeInHtml(route('login'));
        $mail->assertSeeInText('Hi Bertil!');
    }

    public function test_the_signup_confirmation_lists_the_answers_and_the_personal_link(): void
    {
        $event = Event::factory()->published()->create(['title' => 'Kräftskiva']);
        $question = Question::factory()->for($event)->create(['label' => 'Allergier']);
        $signup = Signup::factory()->for($event)->create(['name' => 'Anna', 'answers' => [$question->id => 'Nötter']]);

        $mail = (new SignupReceivedMail($signup))->locale('sv');

        $mail->assertHasSubject('Din anmälan till Kräftskiva');
        $mail->assertSeeInText('Din plats är bekräftad.');
        $mail->assertSeeInText('Allergier');
        $mail->assertSeeInText('Nötter');
        $mail->assertSeeInHtml($signup->manageUrl());
    }

    public function test_the_waitlist_confirmation_says_which_place_in_line(): void
    {
        $event = Event::factory()->published()->create(['capacity' => 1]);
        Signup::factory()->for($event)->waitlisted()->create();
        $signup = Signup::factory()->for($event)->waitlisted()->create();

        (new SignupReceivedMail($signup))->locale('en')->assertSeeInText('you are number 2 on the waiting list');
    }

    public function test_the_cancellation_subject_says_who_cancelled(): void
    {
        $signup = Signup::factory()->cancelled()->create();
        $signup->event->update(['title' => 'Kräftskiva']);
        $signup->refresh();

        (new SignupCancelledMail($signup))->locale('sv')
            ->assertHasSubject('Din anmälan till Kräftskiva har avbokats');

        (new SignupCancelledMail($signup, byOrganizer: true))->locale('sv')
            ->assertHasSubject('Arrangören har avbokat din anmälan till Kräftskiva');
    }
}
