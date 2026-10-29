<?php

namespace Tests\Feature\Events;

use App\Enums\EventStatus;
use App\Enums\NotificationMode;
use App\Livewire\Events\EventEditor;
use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
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
        $this->assertSame(EventStatus::Draft, $event->status);
        $this->assertMatchesRegularExpression('/^[a-z0-9]{12}$/', $event->slug);
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

    public function test_the_owner_can_update_an_event_and_upload_a_cover_image(): void
    {
        Storage::fake('public');
        $event = Event::factory()->create(['capacity' => 10]);

        Livewire::actingAs($event->user)
            ->test(EventEditor::class, ['event' => $event])
            ->assertSet('title', $event->title)
            ->set('title', 'Ny titel')
            ->set('capacity', '')
            ->set('cover', UploadedFile::fake()->image('cover.jpg', 1200, 400))
            ->call('save')
            ->assertHasNoErrors()
            ->assertSee(__('The changes have been saved.'));

        $event->refresh();
        $this->assertSame('Ny titel', $event->title);
        $this->assertNull($event->capacity);
        Storage::disk('public')->assertExists($event->cover_image_path);
    }

    public function test_the_cover_image_can_be_removed(): void
    {
        Storage::fake('public');
        $event = Event::factory()->create(['cover_image_path' => UploadedFile::fake()->image('old.jpg')->store('covers', 'public')]);
        $oldPath = $event->cover_image_path;

        Livewire::actingAs($event->user)
            ->test(EventEditor::class, ['event' => $event])
            ->set('removeCover', true)
            ->call('save');

        $this->assertNull($event->fresh()->cover_image_path);
        Storage::disk('public')->assertMissing($oldPath);
    }

    public function test_only_images_are_accepted_as_cover(): void
    {
        Storage::fake('public');
        $event = Event::factory()->create();

        Livewire::actingAs($event->user)
            ->test(EventEditor::class, ['event' => $event])
            ->set('cover', UploadedFile::fake()->create('evil.svg', 10, 'image/svg+xml'))
            ->call('save')
            ->assertHasErrors('cover');
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
