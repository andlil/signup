<?php

namespace App\Http\Controllers;

use App\Enums\SignupStatus;
use App\Models\Event;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    /**
     * List the events the user organizes, or every event for the main admin.
     */
    public function __invoke(Request $request): View
    {
        $user = $request->user();

        return view('dashboard', [
            'events' => Event::query()
                ->visibleTo($user)
                ->with('user')
                ->withCount(['questions', 'signups as waitlisted_count' => fn ($query) => $query->where('status', SignupStatus::Waitlisted)])
                ->withSum(['signups as confirmed_places' => fn ($query) => $query->where('status', SignupStatus::Confirmed)], 'places')
                ->orderByRaw('coalesce(starts_at, signup_deadline) desc')
                ->get(),
            'showOrganizer' => $user->isAdmin(),
        ]);
    }
}
