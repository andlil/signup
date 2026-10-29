<?php

namespace Database\Factories;

use App\Enums\SignupStatus;
use App\Models\Event;
use App\Models\Signup;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Signup>
 */
class SignupFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'event_id' => Event::factory()->published(),
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'answers' => [],
            'places' => 1,
            'status' => SignupStatus::Confirmed,
            'locale' => 'sv',
            'consented_at' => now(),
            'confirmed_at' => now(),
        ];
    }

    /**
     * Indicate that the signup is on the waiting list.
     */
    public function waitlisted(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => SignupStatus::Waitlisted,
            'confirmed_at' => null,
        ]);
    }

    /**
     * Indicate that the signup has been cancelled.
     */
    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => SignupStatus::Cancelled,
            'cancelled_at' => now(),
        ]);
    }
}
