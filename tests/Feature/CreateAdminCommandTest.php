<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreateAdminCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_the_main_admin(): void
    {
        $this->artisan('signup:create-admin', ['email' => 'Admin@Example.com', '--name' => 'Anders'])
            ->assertSuccessful();

        $admin = User::query()->where('email', 'admin@example.com')->firstOrFail();
        $this->assertSame('Anders', $admin->name);
        $this->assertSame(Role::Admin, $admin->role);
    }

    public function test_it_promotes_and_reactivates_an_existing_account(): void
    {
        $user = User::factory()->inactive()->create(['email' => 'anna@example.com', 'name' => 'Anna']);

        $this->artisan('signup:create-admin', ['email' => 'anna@example.com'])->assertSuccessful();

        $user->refresh();
        $this->assertSame('Anna', $user->name);
        $this->assertSame(Role::Admin, $user->role);
        $this->assertTrue($user->is_active);
    }

    public function test_it_rejects_an_invalid_email_address(): void
    {
        $this->artisan('signup:create-admin', ['email' => 'not-an-email'])->assertFailed();

        $this->assertDatabaseEmpty('users');
    }
}
