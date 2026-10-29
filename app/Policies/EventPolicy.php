<?php

namespace App\Policies;

use App\Models\Event;
use App\Models\User;

class EventPolicy
{
    /**
     * The main admin may do everything an owner may do with any event.
     */
    public function before(User $user, string $ability): ?bool
    {
        if ($user->isAdmin() && $ability !== 'editQuestions') {
            return true;
        }

        return null;
    }

    /**
     * Determine whether the user can list events.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can see the event's admin pages.
     */
    public function view(User $user, Event $event): bool
    {
        return $event->user()->is($user);
    }

    /**
     * Determine whether the user can create events.
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can change the event.
     */
    public function update(User $user, Event $event): bool
    {
        return $event->user()->is($user);
    }

    /**
     * Determine whether the user can change the event's form questions.
     */
    public function editQuestions(User $user, Event $event): bool
    {
        return ($user->isAdmin() || $event->user()->is($user)) && $event->hasEditableQuestions();
    }

    /**
     * Determine whether the user can delete the event.
     */
    public function delete(User $user, Event $event): bool
    {
        return $event->user()->is($user);
    }
}
