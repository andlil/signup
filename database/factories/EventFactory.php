<?php

namespace Database\Factories;

use App\Enums\EventStatus;
use App\Models\Event;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Event>
 */
class EventFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startsAt = fake()->dateTimeBetween('+1 week', '+3 months');

        return [
            'user_id' => User::factory(),
            'title' => fake()->sentence(3),
            'description' => fake()->paragraph(),
            'location' => fake()->city(),
            'starts_at' => $startsAt,
            'ends_at' => (clone $startsAt)->modify('+2 days'),
        ];
    }

    /**
     * Indicate that the event has no start, only a last day to sign up.
     */
    public function withoutStart(): static
    {
        return $this->state(fn (array $attributes) => [
            'starts_at' => null,
            'ends_at' => null,
            'signup_deadline' => now()->addWeeks(2),
        ]);
    }

    /**
     * Indicate that the event has been published.
     */
    public function published(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => EventStatus::Published,
            'published_at' => now(),
        ]);
    }

    /**
     * Indicate that the event no longer accepts signups.
     */
    public function closed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => EventStatus::Closed,
            'published_at' => now(),
        ]);
    }
}
