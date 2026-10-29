<?php

namespace App\Livewire\Events;

use App\Enums\NotificationMode;
use App\Models\Event;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

#[Layout('components.layouts.app')]
class EventEditor extends Component
{
    use WithFileUploads;

    /**
     * The format of the browser's datetime-local inputs.
     */
    private const string DATETIME_FORMAT = 'Y-m-d\TH:i';

    #[Locked]
    public ?Event $event = null;

    public string $title = '';

    public string $description = '';

    public string $location = '';

    public string $startsAt = '';

    public string $endsAt = '';

    public string $signupDeadline = '';

    public string $capacity = '';

    public string $notificationMode = NotificationMode::EachSignup->value;

    public int $retentionDays = 30;

    public ?TemporaryUploadedFile $cover = null;

    public bool $removeCover = false;

    public ?string $status = null;

    /**
     * Fill the form from an existing event, or start a new one.
     */
    public function mount(?Event $event = null): void
    {
        if ($event === null) {
            Gate::authorize('create', Event::class);

            return;
        }

        Gate::authorize('update', $event);

        $this->event = $event;
        $this->title = $event->title;
        $this->description = $event->description ?? '';
        $this->location = $event->location ?? '';
        $this->startsAt = $event->starts_at->format(self::DATETIME_FORMAT);
        $this->endsAt = $event->ends_at?->format(self::DATETIME_FORMAT) ?? '';
        $this->signupDeadline = $event->signup_deadline?->format(self::DATETIME_FORMAT) ?? '';
        $this->capacity = (string) ($event->capacity ?? '');
        $this->notificationMode = $event->notification_mode->value;
        $this->retentionDays = $event->retention_days;
    }

    /**
     * Save the event, sending new events on to the form builder.
     */
    public function save(): void
    {
        $this->event === null
            ? Gate::authorize('create', Event::class)
            : Gate::authorize('update', $this->event);

        $validated = $this->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:20000'],
            'location' => ['nullable', 'string', 'max:255'],
            'startsAt' => ['required', 'date'],
            'endsAt' => ['nullable', 'date', 'after_or_equal:startsAt'],
            'signupDeadline' => ['nullable', 'date'],
            'capacity' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'notificationMode' => ['required', Rule::enum(NotificationMode::class)],
            'retentionDays' => ['required', 'integer', 'min:1', 'max:3650'],
            'cover' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);

        $attributes = [
            'title' => $validated['title'],
            'description' => $validated['description'] ?: null,
            'location' => $validated['location'] ?: null,
            'starts_at' => $validated['startsAt'],
            'ends_at' => $validated['endsAt'] ?: null,
            'signup_deadline' => $validated['signupDeadline'] ?: null,
            'capacity' => $validated['capacity'] === '' ? null : (int) $validated['capacity'],
            'notification_mode' => $validated['notificationMode'],
            'retention_days' => $validated['retentionDays'],
        ];

        $isNew = $this->event === null;
        $event = $this->event ?? auth()->user()->events()->make();
        $event->fill($attributes);

        if ($this->cover !== null || $this->removeCover) {
            $this->deleteCoverFile($event);
            $event->cover_image_path = $this->cover?->store('covers', 'public');
        }

        $event->save();

        $this->reset('cover', 'removeCover');

        if ($isNew) {
            session()->flash('status', __('The event has been created. Now add the questions for the signup form.'));

            $this->redirectRoute('events.form', $event, navigate: false);

            return;
        }

        $this->event = $event;
        $this->status = __('The changes have been saved.');
    }

    /**
     * Permanently delete the event.
     */
    public function delete(): void
    {
        Gate::authorize('delete', $this->event);

        $this->deleteCoverFile($this->event);
        $this->event->delete();

        session()->flash('status', __('The event has been deleted.'));

        $this->redirectRoute('dashboard', navigate: false);
    }

    /**
     * Remove the event's current cover image from storage.
     */
    private function deleteCoverFile(Event $event): void
    {
        if ($event->cover_image_path !== null) {
            Storage::disk('public')->delete($event->cover_image_path);
        }
    }

    public function render(): View
    {
        return view('livewire.events.event-editor', [
            'notificationModes' => NotificationMode::cases(),
        ])->title($this->event?->title ?? __('New event'));
    }
}
