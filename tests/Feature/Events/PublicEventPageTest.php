<?php

namespace Tests\Feature\Events;

use App\Enums\QuestionType;
use App\Models\Event;
use App\Models\Question;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicEventPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_anyone_can_see_a_published_event_through_its_link(): void
    {
        $event = Event::factory()->published()->create(['title' => 'Midsommarfest', 'location' => 'Ängen']);
        Question::factory()->for($event)->choice(['Sill', 'Vegetariskt'], QuestionType::Dropdown)->create(['label' => 'Mat']);

        $this->get('/e/'.$event->slug)
            ->assertOk()
            ->assertSee('Midsommarfest')
            ->assertSee('Ängen')
            ->assertSee('Mat')
            ->assertSee('Vegetariskt');
    }

    public function test_the_link_uses_the_slug_and_not_the_id(): void
    {
        $event = Event::factory()->published()->create();

        $this->assertSame(url('/e/'.$event->slug), route('events.public', $event));
        $this->get('/e/'.$event->id)->assertNotFound();
    }

    public function test_drafts_are_hidden_from_the_public_but_previewable_by_the_owner(): void
    {
        $event = Event::factory()->create();

        $this->get(route('events.public', $event))->assertNotFound();
        $this->actingAs(User::factory()->create())->get(route('events.public', $event))->assertNotFound();

        $this->actingAs($event->user)
            ->get(route('events.public', $event))
            ->assertOk()
            ->assertSee(__('Preview. The event is a draft and is only visible to you.'));
    }

    public function test_html_in_the_description_is_escaped(): void
    {
        $event = Event::factory()->published()->create([
            'description' => "**Välkommen!**\n\n<script>alert('x')</script>\n\n[klicka](javascript:alert(1))",
        ]);

        $this->get(route('events.public', $event))
            ->assertOk()
            ->assertSee('<strong>Välkommen!</strong>', false)
            ->assertDontSee("<script>alert('x')</script>", false)
            ->assertDontSee('javascript:alert', false);
    }

    public function test_closed_events_and_passed_deadlines_do_not_accept_signups(): void
    {
        $closed = Event::factory()->closed()->create();
        $pastDeadline = Event::factory()->published()->create(['signup_deadline' => now()->subDay()]);

        $this->get(route('events.public', $closed))->assertSee(__('Signups for this event are closed.'));
        $this->get(route('events.public', $pastDeadline))->assertSee(__('Signups for this event are closed.'));
    }
}
