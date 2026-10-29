<?php

namespace Tests\Feature;

use App\Mail\AccountCreatedMail;
use App\Mail\MagicLinkMail;
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
}
