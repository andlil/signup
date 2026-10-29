<?php

namespace App\Http\Controllers;

use App\Enums\EventStatus;
use App\Models\Event;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class EventPublicationController extends Controller
{
    /**
     * Publish a draft event, which locks its form.
     */
    public function publish(Event $event): RedirectResponse
    {
        Gate::authorize('update', $event);
        abort_unless($event->status === EventStatus::Draft, 409);

        $event->publish();

        return back()->with('status', __('The event is published. Share the link below to let people sign up.'));
    }

    /**
     * Stop accepting signups.
     */
    public function close(Event $event): RedirectResponse
    {
        Gate::authorize('update', $event);
        abort_unless($event->status === EventStatus::Published, 409);

        $event->close();

        return back()->with('status', __('The event is closed for signups.'));
    }

    /**
     * Accept signups again.
     */
    public function reopen(Event $event): RedirectResponse
    {
        Gate::authorize('update', $event);
        abort_unless($event->status === EventStatus::Closed, 409);

        $event->reopen();

        return back()->with('status', __('The event accepts signups again.'));
    }
}
