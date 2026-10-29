<?php

namespace Tests\Feature;

use App\Models\Event;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DatabaseSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_development_data_can_be_seeded(): void
    {
        $this->seed(DatabaseSeeder::class);

        $event = Event::query()->sole();
        $this->assertNotNull($event->slug);
        $this->assertSame(3, $event->questions()->count());
        $this->assertNotNull($event->capacity_question_id);
        $this->assertSame(7, $event->takenPlaces());
    }
}
