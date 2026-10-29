<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Services\SignupService;
use App\Support\SignupForm;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SignupController extends Controller
{
    /**
     * Sign up for an event from its public page.
     */
    public function store(Request $request, Event $event, SignupService $signups): RedirectResponse
    {
        abort_unless($event->acceptsSignups(), 403, __('Signups for this event are closed.'));

        // Bots fill in every field, including this one that people never see.
        if ($request->filled('website')) {
            return redirect()->route('events.public', $event);
        }

        $event->load('questions');
        $form = new SignupForm($event);
        $data = $form->validate($request);

        $signup = $signups->register($event, $data, $form->placesFor($data['answers']), app()->getLocale());

        return redirect()
            ->route('signups.show', ['token' => $signup->manage_token])
            ->with('signup_status', $signup->status->value);
    }
}
