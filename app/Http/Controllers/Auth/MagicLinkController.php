<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Mail\MagicLinkMail;
use App\Models\LoginToken;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;

class MagicLinkController extends Controller
{
    /**
     * Show the form where an organizer requests a login link.
     */
    public function create(): View
    {
        return view('auth.login');
    }

    /**
     * Email a login link if the address belongs to an active account.
     *
     * The response is the same whether or not the account exists, so the form
     * cannot be used to find out which email addresses have accounts.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'string', 'email', 'max:255'],
        ]);

        $user = User::query()
            ->where('email', mb_strtolower($validated['email']))
            ->where('is_active', true)
            ->first();

        if ($user !== null) {
            $url = route('login.confirm', ['token' => LoginToken::issueFor($user)]);

            Mail::to($user)->send((new MagicLinkMail($user, $url))->afterCommit());
        }

        return redirect()
            ->route('login')
            ->with('status', __('If the address belongs to an account, a login link is on its way. Check your inbox.'));
    }

    /**
     * Show a confirmation page for a login link.
     *
     * Logging in requires a button press, so link previews and email scanners
     * that follow the link do not use up the token.
     */
    public function show(string $token): View
    {
        $loginToken = LoginToken::findUsable($token);

        if ($loginToken === null) {
            return view('auth.invalid-link');
        }

        return view('auth.confirm', [
            'token' => $token,
            'user' => $loginToken->user,
        ]);
    }

    /**
     * Log the user in with a login link.
     */
    public function consume(Request $request, string $token): RedirectResponse|View
    {
        $loginToken = LoginToken::findUsable($token);

        if ($loginToken === null || ! $loginToken->consume()) {
            return view('auth.invalid-link');
        }

        $user = $loginToken->user;

        Auth::login($user, remember: true);
        $request->session()->regenerate();

        $user->forceFill(['last_login_at' => now()])->save();

        if ($user->locale !== null) {
            $request->session()->put('locale', $user->locale);
        }

        return redirect()->intended(route('dashboard'));
    }

    /**
     * Log the user out.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
