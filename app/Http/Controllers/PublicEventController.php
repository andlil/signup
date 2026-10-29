<?php

namespace App\Http\Controllers;

use App\Models\Event;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PublicEventController extends Controller
{
    /**
     * Show the event's public page with its signup form.
     *
     * Drafts are only visible to people who can edit them, as a preview.
     */
    public function __invoke(Request $request, Event $event): View
    {
        $isPreview = ! $event->isPublic();

        abort_if($isPreview && ! $request->user()?->can('update', $event), 404);

        $event->load('questions');

        return view('events.public', [
            'event' => $event,
            'isPreview' => $isPreview,
        ]);
    }
}
