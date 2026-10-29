<?php

namespace App\Livewire\Admin;

use App\Enums\Role;
use App\Mail\AccountCreatedMail;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app')]
class UserManager extends Component
{
    public string $name = '';

    public string $email = '';

    public string $role = Role::Organizer->value;

    public ?string $status = null;

    /**
     * Every request to the component, including actions, must come from the main admin.
     */
    public function boot(): void
    {
        Gate::authorize('manage-users');
    }

    /**
     * Create an account and tell the new user how to log in.
     */
    public function create(): void
    {
        $this->email = mb_strtolower(trim($this->email));

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique(User::class)],
            'role' => ['required', Rule::enum(Role::class)],
        ]);

        $user = User::create([
            ...$validated,
            'locale' => app()->getLocale(),
        ]);

        Mail::to($user)->send(new AccountCreatedMail($user));

        $this->reset('name', 'email', 'role');

        $this->status = __('The account for :email has been created and a welcome email has been sent.', ['email' => $user->email]);
    }

    /**
     * Deactivate an active account, or reactivate a deactivated one.
     */
    public function toggleActive(int $userId): void
    {
        $user = $this->findOtherUser($userId);

        $user->forceFill(['is_active' => ! $user->is_active])->save();

        if (! $user->is_active) {
            $user->loginTokens()->delete();
        }
    }

    /**
     * Permanently delete an account.
     */
    public function delete(int $userId): void
    {
        $user = $this->findOtherUser($userId);

        if ($user->events()->exists()) {
            $this->status = __(':email has events and cannot be deleted. Deactivate the account instead.', ['email' => $user->email]);

            return;
        }

        $user->delete();

        $this->status = __('The account for :email has been deleted.', ['email' => $user->email]);
    }

    /**
     * Find a user other than the logged in admin, who must not lock themselves out.
     */
    private function findOtherUser(int $userId): User
    {
        abort_if($userId === Auth::id(), 403, __('You cannot change your own account.'));

        return User::query()->findOrFail($userId);
    }

    public function render(): View
    {
        return view('livewire.admin.user-manager', [
            'users' => User::query()->orderBy('role')->orderBy('name')->get(),
            'roles' => Role::cases(),
        ])->title(__('Accounts'));
    }
}
