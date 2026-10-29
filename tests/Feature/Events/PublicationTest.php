<?php

namespace Tests\Feature\Events;

use App\Enums\EventStatus;
use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicationTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_owner_can_publish_close_and_reopen_an_event(): void
    {
        $event = Event::factory()->create();

        $this->actingAs($event->user)
            ->from(route('events.edit', $event))
            ->post(route('events.publish', $event))
            ->assertRedirect(route('events.edit', $event));

        $event->refresh();
        $this->assertSame(EventStatus::Published, $event->status);
        $this->assertNotNull($event->published_at);

        $this->post(route('events.close', $event));
        $this->assertSame(EventStatus::Closed, $event->fresh()->status);

        $this->post(route('events.reopen', $event));
        $this->assertSame(EventStatus::Published, $event->fresh()->status);
    }

    public function test_an_event_cannot_be_published_twice(): void
    {
        $event = Event::factory()->published()->create();

        $this->actingAs($event->user)
            ->post(route('events.publish', $event))
            ->assertConflict();
    }

    public function test_another_organizer_cannot_publish_the_event(): void
    {
        $event = Event::factory()->create();

        $this->actingAs(User::factory()->create())
            ->post(route('events.publish', $event))
            ->assertForbidden();

        $this->assertSame(EventStatus::Draft, $event->fresh()->status);
    }
}
