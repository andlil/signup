<?php

namespace Database\Seeders;

use App\Enums\QuestionType;
use App\Models\Event;
use App\Models\Question;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database with a main admin and an organizer for local development.
     */
    public function run(): void
    {
        User::factory()->admin()->create([
            'name' => 'Admin',
            'email' => 'admin@example.com',
        ]);

        $organizer = User::factory()->create([
            'name' => 'Arrangör',
            'email' => 'arrangor@example.com',
        ]);

        $event = Event::factory()->for($organizer)->create([
            'title' => 'Fjällvecka i Abisko',
            'description' => "En vecka med skidor, bastu och norrsken.\n\n- Boende i stugor\n- Gemensam middag varje kväll\n\nLäs mer på [Abisko.net](https://www.abisko.net).",
            'location' => 'Abisko turiststation',
            'starts_at' => now()->addMonths(3)->setTime(15, 0),
            'ends_at' => now()->addMonths(3)->addDays(7)->setTime(11, 0),
            'signup_deadline' => now()->addMonths(2),
            'capacity' => 20,
        ]);

        $people = Question::factory()->for($event)->number(1, 8)->create([
            'label' => 'Antal personer',
            'is_required' => true,
            'position' => 1,
        ]);
        $event->capacityQuestion()->associate($people)->save();

        Question::factory()->for($event)->choice(['Kött', 'Fisk', 'Vegetariskt'], QuestionType::Checkboxes)->create([
            'label' => 'Vilken mat äter ni?',
            'position' => 2,
        ]);
        Question::factory()->for($event)->create([
            'type' => QuestionType::LongText,
            'label' => 'Allergier eller annat vi bör veta?',
            'position' => 3,
        ]);
    }
}
