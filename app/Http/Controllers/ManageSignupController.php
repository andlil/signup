<?php

namespace App\Http\Controllers;

use App\Models\Signup;
use App\Services\SignupService;
use App\Support\SignupForm;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The participant's own page for a signup, reached through the secret link in their email.
 */
class ManageSignupController extends Controller
{
    /**
     * Show the signup with a form for changing it.
     */
    public function show(string $token): View
    {
        $signup = $this->findSignup($token);

        return view('signups.show', [
            'signup' => $signup,
            'event' => $signup->event,
        ]);
    }

    /**
     * Save changed answers.
     */
    public function update(Request $request, string $token, SignupService $signups): RedirectResponse
    {
        $signup = $this->findSignup($token);

        abort_unless($signup->canBeChanged(), 403, __('The signup can no longer be changed.'));

        $form = new SignupForm($signup->event, $signup);
        $data = $form->validate($request);

        $signups->update($signup, $data, $form->placesFor($data['answers']));

        return redirect()
            ->route('signups.show', ['token' => $token])
            ->with('signup_status', 'updated');
    }

    /**
     * Cancel the signup.
     */
    public function destroy(string $token, SignupService $signups): RedirectResponse
    {
        $signup = $this->findSignup($token);

        abort_unless($signup->canBeCancelled(), 403, __('The signup can no longer be cancelled.'));

        $signups->cancel($signup);

        return redirect()
            ->route('signups.show', ['token' => $token])
            ->with('signup_status', 'cancelled');
    }

    private function findSignup(string $token): Signup
    {
        $signup = Signup::findByToken($token);

        abort_if($signup === null, 404);

        $signup->load('event.questions');

        return $signup;
    }
}
