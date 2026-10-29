<?php

namespace Tests\Feature\Events;

use App\Enums\EventStatus;
use App\Enums\NotificationMode;
use App\Livewire\Events\EventEditor;
use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class EventEditorTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_organizer_can_create_an_event(): void
    {
        $organizer = User::factory()->create();

        Livewire::actingAs($organizer)
            ->test(EventEditor::class)
            ->set('title', 'Fjällvecka')
            ->set('description', 'Vi åker till **Abisko**.')
            ->set('location', 'Abisko')
            ->set('startsAt', '2027-02-20T10:00')
            ->set('endsAt', '2027-02-27T12:00')
            ->set('signupDeadline', '2027-01-31T23:59')
            ->set('capacity', '12')
            ->set('notificationMode', NotificationMode::DailySummary->value)
            ->set('retentionDays', 60)
            ->set('allowLateCancellation', true)
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect(route('events.form', Event::query()->sole()));

        $event = Event::query()->sole();
        $this->assertTrue($event->user->is($organizer));
        $this->assertSame('Fjällvecka', $event->title);
        $this->assertSame('2027-02-20 10:00', $event->starts_at->format('Y-m-d H:i'));
        $this->assertSame(12, $event->capacity);
        $this->assertSame(NotificationMode::DailySummary, $event->notification_mode);
        $this->assertSame(60, $event->retention_days);
        $this->assertTrue($event->allow_late_cancellation);
        $this->assertSame(EventStatus::Draft, $event->status);
        $this->assertMatchesRegularExpression('/^[a-z0-9]{12}$/', $event->slug);
    }

    public function test_the_event_needs_a_start_or_a_last_day_to_sign_up(): void
    {
        $message = __('Enter when the event starts, a last day to sign up, or both.');

        Livewire::actingAs(User::factory()->create())
            ->test(EventEditor::class)
            ->set('title', 'Utan datum')
            ->call('save')
            ->assertHasErrors(['startsAt' => [$message], 'signupDeadline' => [$message]]);

        $this->assertDatabaseEmpty('events');
    }

    public function test_an_event_can_have_only_a_last_day_to_sign_up(): void
    {
        $organizer = User::factory()->create();

        Livewire::actingAs($organizer)
            ->test(EventEditor::class)
            ->set('title', 'Sommarsemester')
            ->set('signupDeadline', '2027-03-31T23:59')
            ->call('save')
            ->assertHasNoErrors();

        $event = Event::query()->sole();
        $this->assertFalse($event->allow_late_cancellation);
        $this->assertNull($event->starts_at);
        $this->assertSame('2027-03-31 23:59', $event->signup_deadline->format('Y-m-d H:i'));
    }

    public function test_an_end_needs_a_start(): void
    {
        Livewire::actingAs(User::factory()->create())
            ->test(EventEditor::class)
            ->set('title', 'Fest')
            ->set('endsAt', '2027-02-20T10:00')
            ->set('signupDeadline', '2027-02-01T10:00')
            ->call('save')
            ->assertHasErrors(['startsAt' => [__('An event with an end also needs a start.')]]);
    }

    public function test_the_event_needs_a_title_and_a_start_and_cannot_end_before_it_starts(): void
    {
        Livewire::actingAs(User::factory()->create())
            ->test(EventEditor::class)
            ->set('startsAt', '2027-02-20T10:00')
            ->set('endsAt', '2027-02-19T10:00')
            ->set('capacity', '0')
            ->call('save')
            ->assertHasErrors(['title' => 'required', 'endsAt' => 'after_or_equal', 'capacity' => 'min']);

        $this->assertDatabaseEmpty('events');
    }

    public function test_the_owner_can_update_an_event(): void
    {
        $event = Event::factory()->create(['capacity' => 10]);

        Livewire::actingAs($event->user)
            ->test(EventEditor::class, ['event' => $event])
            ->assertSet('title', $event->title)
            ->set('title', 'Ny titel')
            ->set('capacity', '')
            ->call('save')
            ->assertHasNoErrors()
            ->assertSee(__('The changes have been saved.'));

        $event->refresh();
        $this->assertSame('Ny titel', $event->title);
        $this->assertNull($event->capacity);
    }

    public function test_another_organizer_cannot_edit_the_event(): void
    {
        $event = Event::factory()->create();

        $this->actingAs(User::factory()->create())
            ->get(route('events.edit', $event))
            ->assertForbidden();
    }

    public function test_the_main_admin_can_edit_any_event(): void
    {
        $event = Event::factory()->create();

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('events.edit', $event))
            ->assertOk()
            ->assertSee($event->title);
    }

    public function test_the_owner_can_delete_an_event(): void
    {
        $event = Event::factory()->create();

        Livewire::actingAs($event->user)
            ->test(EventEditor::class, ['event' => $event])
            ->call('delete')
            ->assertRedirect(route('dashboard'));

        $this->assertModelMissing($event);
    }
}
