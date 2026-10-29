<?php

namespace Database\Factories;

use App\Models\LoginToken;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<LoginToken>
 */
class LoginTokenFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'token_hash' => LoginToken::hash(Str::random(64)),
            'expires_at' => now()->addMinutes(LoginToken::LIFETIME_MINUTES),
        ];
    }
}
