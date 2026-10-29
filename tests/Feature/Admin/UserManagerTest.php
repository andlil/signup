<?php

namespace Tests\Feature\Admin;

use App\Enums\Role;
use App\Livewire\Admin\UserManager;
use App\Mail\AccountCreatedMail;
use App\Models\Event;
use App\Models\LoginToken;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\TestCase;

class UserManagerTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_main_admin_can_open_the_accounts_page(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.users'))
            ->assertOk()
            ->assertSeeLivewire(UserManager::class);
    }

    public function test_organizers_cannot_open_the_accounts_page(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('admin.users'))
            ->assertForbidden();
    }

    public function test_organizers_cannot_call_component_actions(): void
    {
        $other = User::factory()->create();

        Livewire::actingAs(User::factory()->create())
            ->test(UserManager::class)
            ->assertForbidden();

        $this->assertTrue($other->fresh()->is_active);
    }

    public function test_the_main_admin_can_create_an_organizer(): void
    {
        Mail::fake();

        Livewire::actingAs(User::factory()->admin()->create())
            ->test(UserManager::class)
            ->set('name', 'Bertil Berg')
            ->set('email', ' Bertil@Example.com ')
            ->set('role', Role::Organizer->value)
            ->call('create')
            ->assertHasNoErrors()
            ->assertSet('name', '')
            ->assertSee('bertil@example.com');

        $user = User::query()->where('email', 'bertil@example.com')->firstOrFail();
        $this->assertSame(Role::Organizer, $user->role);
        $this->assertTrue($user->is_active);
        Mail::assertQueued(AccountCreatedMail::class, fn (AccountCreatedMail $mail): bool => $mail->hasTo('bertil@example.com'));
    }

    public function test_an_email_address_can_only_have_one_account(): void
    {
        Mail::fake();
        User::factory()->create(['email' => 'taken@example.com']);

        Livewire::actingAs(User::factory()->admin()->create())
            ->test(UserManager::class)
            ->set('name', 'Someone')
            ->set('email', 'TAKEN@example.com')
            ->call('create')
            ->assertHasErrors(['email' => 'unique']);

        Mail::assertNothingQueued();
    }

    public function test_the_main_admin_can_deactivate_and_reactivate_an_account(): void
    {
        $organizer = User::factory()->create();
        LoginToken::issueFor($organizer);

        $component = Livewire::actingAs(User::factory()->admin()->create())
            ->test(UserManager::class)
            ->call('toggleActive', $organizer->id);

        $this->assertFalse($organizer->fresh()->is_active);
        $this->assertSame(0, $organizer->loginTokens()->count());

        $component->call('toggleActive', $organizer->id);

        $this->assertTrue($organizer->fresh()->is_active);
    }

    public function test_the_main_admin_can_delete_an_account(): void
    {
        $organizer = User::factory()->create();

        Livewire::actingAs(User::factory()->admin()->create())
            ->test(UserManager::class)
            ->call('delete', $organizer->id);

        $this->assertModelMissing($organizer);
    }

    public function test_an_account_with_events_cannot_be_deleted(): void
    {
        $event = Event::factory()->create();

        Livewire::actingAs(User::factory()->admin()->create())
            ->test(UserManager::class)
            ->call('delete', $event->user_id)
            ->assertSee(__(':email has events and cannot be deleted. Deactivate the account instead.', ['email' => $event->user->email]));

        $this->assertModelExists($event->user);
    }

    public function test_the_main_admin_cannot_deactivate_or_delete_themselves(): void
    {
        $admin = User::factory()->admin()->create();

        Livewire::actingAs($admin)
            ->test(UserManager::class)
            ->call('toggleActive', $admin->id)
            ->assertForbidden();

        Livewire::actingAs($admin)
            ->test(UserManager::class)
            ->call('delete', $admin->id)
            ->assertForbidden();

        $this->assertTrue($admin->fresh()->is_active);
    }
}
