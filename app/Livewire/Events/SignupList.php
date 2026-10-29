<?php

namespace App\Livewire\Events;

use App\Enums\SignupStatus;
use App\Models\Event;
use App\Models\Signup;
use App\Services\SignupService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('components.layouts.app')]
class SignupList extends Component
{
    #[Locked]
    public Event $event;

    #[Url]
    public string $show = 'confirmed';

    public ?string $status = null;

    public function mount(Event $event): void
    {
        Gate::authorize('update', $event);

        $this->event = $event;
    }

    /**
     * Every later request to the component, including actions, must also come from someone who can edit the event.
     */
    public function hydrate(): void
    {
        Gate::authorize('update', $this->event);
    }

    /**
     * Cancel a signup on the participant's behalf, which frees its places for the waiting list.
     */
    public function cancel(int $signupId, SignupService $signups): void
    {
        $signup = $this->findSignup($signupId);

        if ($signup->status !== SignupStatus::Cancelled) {
            $signups->cancel($signup, byOrganizer: true);
            $this->status = __('The signup for :name has been cancelled and :name has been emailed.', ['name' => $signup->name]);
        }
    }

    /**
     * Permanently delete a cancelled signup and its personal data.
     */
    public function delete(int $signupId): void
    {
        $signup = $this->findSignup($signupId);

        abort_unless($signup->status === SignupStatus::Cancelled, 409);

        $signup->delete();
        $this->status = __('The signup for :name has been deleted.', ['name' => $signup->name]);
    }

    private function findSignup(int $signupId): Signup
    {
        return $this->event->signups()->findOrFail($signupId);
    }

    public function render(): View
    {
        $status = SignupStatus::tryFrom($this->show) ?? SignupStatus::Confirmed;

        $counts = $this->event->signups()
            ->toBase()
            ->selectRaw('status, count(*) as signups, sum(places) as places')
            ->groupBy('status')
            ->get()
            ->keyBy('status');

        return view('livewire.events.signup-list', [
            'signups' => $this->event->signups()->where('status', $status)->orderBy('id')->get(),
            'questions' => $this->event->questions()->get(),
            'counts' => $counts,
            'currentStatus' => $status,
            'statuses' => SignupStatus::cases(),
        ])->title(__('Signups').' · '.$this->event->title);
    }
}
