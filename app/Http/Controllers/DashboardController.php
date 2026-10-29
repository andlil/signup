<?php

namespace App\Http\Controllers;

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
                ->withCount('questions')
                ->orderByDesc('starts_at')
                ->get(),
            'showOrganizer' => $user->isAdmin(),
        ]);
    }
}
