<?php

namespace Database\Factories;

use App\Enums\QuestionType;
use App\Models\Event;
use App\Models\Question;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Question>
 */
class QuestionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'event_id' => Event::factory(),
            'type' => QuestionType::ShortText,
            'label' => fake()->sentence(4),
            'is_required' => false,
            'position' => fake()->unique()->numberBetween(1, 100000),
        ];
    }

    /**
     * Indicate that the question is a multiple choice question with the given options.
     *
     * @param  list<string>  $options
     */
    public function choice(array $options = ['Ja', 'Nej'], QuestionType $type = QuestionType::SingleChoice): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => $type,
            'options' => $options,
        ]);
    }

    /**
     * Indicate that the question asks for a number.
     */
    public function number(?int $min = null, ?int $max = null): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => QuestionType::Number,
            'min_value' => $min,
            'max_value' => $max,
        ]);
    }
}
