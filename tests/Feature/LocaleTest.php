<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LocaleTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_interface_is_swedish_by_default(): void
    {
        $this->get(route('login'), ['Accept-Language' => 'de-DE,de;q=0.9'])->assertSee('Skicka inloggningslänk');
    }

    public function test_the_browser_language_is_used_when_supported(): void
    {
        $this->get(route('login'), ['Accept-Language' => 'de-DE,sv;q=0.8'])->assertSee('Skicka inloggningslänk');
        $this->get(route('login'), ['Accept-Language' => 'en-GB,en;q=0.9'])->assertSee('Send login link');
    }

    public function test_a_visitor_can_switch_language(): void
    {
        $this->from(route('login'))
            ->post(route('locale.update'), ['locale' => 'en'])
            ->assertRedirect(route('login'));

        $this->get(route('login'))->assertSee('Send login link');
    }

    public function test_a_logged_in_users_choice_is_remembered_on_their_account(): void
    {
        $user = User::factory()->create(['locale' => 'sv']);

        $this->actingAs($user)->post(route('locale.update'), ['locale' => 'en']);

        $this->assertSame('en', $user->fresh()->locale);
    }

    public function test_unsupported_languages_are_rejected(): void
    {
        $this->post(route('locale.update'), ['locale' => 'xx'])->assertSessionHasErrors('locale');
    }
}
