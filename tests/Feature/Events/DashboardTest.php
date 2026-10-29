<?php

namespace Tests\Feature\Events;

use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_organizers_only_see_their_own_events(): void
    {
        $organizer = User::factory()->create();
        Event::factory()->for($organizer)->create(['title' => 'Mitt event']);
        Event::factory()->create(['title' => 'Någon annans event']);

        $this->actingAs($organizer)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Mitt event')
            ->assertDontSee('Någon annans event');
    }

    public function test_the_main_admin_sees_every_event_and_its_organizer(): void
    {
        $organizer = User::factory()->create(['name' => 'Cecilia']);
        Event::factory()->for($organizer)->create(['title' => 'Ceciliasevent']);

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Ceciliasevent')
            ->assertSee('Cecilia');
    }
}
