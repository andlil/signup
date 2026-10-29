<?php

namespace Tests\Feature\Auth;

use App\Mail\MagicLinkMail;
use App\Models\LoginToken;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class MagicLinkTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_active_user_is_emailed_a_login_link(): void
    {
        Mail::fake();
        $user = User::factory()->create(['email' => 'anna@example.com']);

        $this->post(route('login.send'), ['email' => 'Anna@Example.com'])
            ->assertRedirect(route('login'))
            ->assertSessionHas('status');

        Mail::assertQueued(MagicLinkMail::class, function (MagicLinkMail $mail) use ($user): bool {
            return $mail->hasTo($user->email)
                && str_starts_with($mail->url, url('/login/'));
        });
        $this->assertSame(1, $user->loginTokens()->count());
    }

    public function test_unknown_and_inactive_addresses_get_the_same_response_but_no_email(): void
    {
        Mail::fake();
        User::factory()->inactive()->create(['email' => 'inactive@example.com']);

        $unknownResponse = $this->post(route('login.send'), ['email' => 'nobody@example.com']);
        $inactiveResponse = $this->post(route('login.send'), ['email' => 'inactive@example.com']);

        $unknownResponse->assertRedirect(route('login'))->assertSessionHas('status');
        $inactiveResponse->assertRedirect(route('login'))->assertSessionHas('status');
        Mail::assertNothingQueued();
        $this->assertDatabaseEmpty('login_tokens');
    }

    public function test_requesting_links_is_rate_limited(): void
    {
        Mail::fake();

        foreach (range(1, 3) as $attempt) {
            $this->post(route('login.send'), ['email' => 'anna@example.com'])->assertRedirect();
        }

        $this->post(route('login.send'), ['email' => 'anna@example.com'])->assertTooManyRequests();
    }

    public function test_opening_the_link_asks_for_confirmation_without_logging_in(): void
    {
        $user = User::factory()->create(['name' => 'Anna']);
        $token = LoginToken::issueFor($user);

        $this->get(route('login.confirm', ['token' => $token]))
            ->assertOk()
            ->assertSee('Anna')
            ->assertSee(route('login.consume', ['token' => $token]));

        $this->assertGuest();
        $this->assertNull($user->loginTokens()->first()->used_at);
    }

    public function test_confirming_the_link_logs_the_user_in(): void
    {
        $user = User::factory()->create(['locale' => 'en']);
        $token = LoginToken::issueFor($user);

        $this->post(route('login.consume', ['token' => $token]))
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas('locale', 'en');

        $this->assertAuthenticatedAs($user);
        $this->assertNotNull($user->fresh()->last_login_at);
        $this->assertNotNull($user->loginTokens()->first()->used_at);
    }

    public function test_a_link_can_only_be_used_once(): void
    {
        $user = User::factory()->create();
        $token = LoginToken::issueFor($user);

        $this->post(route('login.consume', ['token' => $token]))->assertRedirect(route('dashboard'));
        $this->post(route('logout'));

        $this->post(route('login.consume', ['token' => $token]))
            ->assertOk()
            ->assertViewIs('auth.invalid-link');
        $this->assertGuest();
    }

    public function test_an_expired_link_does_not_work(): void
    {
        $user = User::factory()->create();
        $token = LoginToken::issueFor($user);

        $this->travel(LoginToken::LIFETIME_MINUTES + 1)->minutes();

        $this->get(route('login.confirm', ['token' => $token]))->assertViewIs('auth.invalid-link');
        $this->post(route('login.consume', ['token' => $token]))->assertViewIs('auth.invalid-link');
        $this->assertGuest();
    }

    public function test_a_link_stops_working_when_the_account_is_deactivated(): void
    {
        $user = User::factory()->create();
        $token = LoginToken::issueFor($user);

        $user->update(['is_active' => false]);

        $this->post(route('login.consume', ['token' => $token]))->assertViewIs('auth.invalid-link');
        $this->assertGuest();
    }

    public function test_a_made_up_token_does_not_work(): void
    {
        $this->get(route('login.confirm', ['token' => str_repeat('a', 64)]))->assertViewIs('auth.invalid-link');
    }

    public function test_a_deactivated_user_is_logged_out_on_their_next_request(): void
    {
        $user = User::factory()->inactive()->create();

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertRedirect(route('login'))
            ->assertSessionHas('error');

        $this->assertGuest();
    }

    public function test_guests_are_sent_to_the_login_page(): void
    {
        $this->get(route('dashboard'))->assertRedirect(route('login'));
    }

    public function test_a_user_can_log_out(): void
    {
        $this->actingAs(User::factory()->create())
            ->post(route('logout'))
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }
}
