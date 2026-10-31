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

    public function test_published_events_show_whether_signups_are_open(): void
    {
        $organizer = User::factory()->create();
        Event::factory()->for($organizer)->create(['title' => 'Utkastet']);
        Event::factory()->for($organizer)->published()->create(['title' => 'Öppet']);
        Event::factory()->for($organizer)->published()->create(['title' => 'Passerat', 'signup_deadline' => now()->subDay()]);
        Event::factory()->for($organizer)->closed()->create(['title' => 'Stängt för hand']);

        $response = $this->withSession(['locale' => 'sv'])->actingAs($organizer)->get(route('dashboard'))->assertOk();

        $rows = collect(explode('<li>', $response->getContent()))->mapWithKeys(fn (string $row): array => [
            collect(['Utkastet', 'Öppet', 'Passerat', 'Stängt för hand'])->first(fn (string $title): bool => str_contains($row, $title)) ?? '' => $row,
        ]);

        $this->assertStringContainsString('Utkast', $rows['Utkastet']);
        $this->assertStringNotContainsString('Anmälan', $rows['Utkastet']);
        $this->assertStringContainsString('Anmälan öppen', $rows['Öppet']);
        $this->assertStringContainsString('Anmälan stängd', $rows['Passerat']);
        $this->assertStringContainsString('Sista anmälningsdag var', $rows['Passerat']);
        $this->assertStringContainsString('Anmälan stängd', $rows['Stängt för hand']);
        $this->assertStringContainsString('Stängd av arrangören', $rows['Stängt för hand']);
    }
}
