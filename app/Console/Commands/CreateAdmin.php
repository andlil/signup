<?php

namespace App\Console\Commands;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;

#[Signature('signup:create-admin {email : The email address the admin logs in with} {--name= : The admin\'s name} {--if-none : Do nothing if a main admin already exists}')]
#[Description('Create the main admin account, or promote an existing account to main admin')]
class CreateAdmin extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $email = mb_strtolower(trim($this->argument('email')));

        if (Validator::make(['email' => $email], ['email' => ['required', 'email', 'max:255']])->fails()) {
            $this->error("\"{$email}\" is not a valid email address.");

            return self::FAILURE;
        }

        if ($this->option('if-none') && User::query()->where('role', Role::Admin)->exists()) {
            $this->line('A main admin already exists, nothing to do.');

            return self::SUCCESS;
        }

        $user = User::query()->firstOrNew(['email' => $email]);
        $wasExisting = $user->exists;

        $user->forceFill([
            'name' => $this->option('name') ?: $user->name ?? $email,
            'role' => Role::Admin,
            'is_active' => true,
        ])->save();

        $this->info($wasExisting
            ? "{$email} is now main admin."
            : "Main admin {$email} created.");
        $this->line('Log in at '.route('login'));

        return self::SUCCESS;
    }
}
