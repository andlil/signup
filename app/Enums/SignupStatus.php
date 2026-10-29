<?php

namespace App\Enums;

enum SignupStatus: string
{
    case Confirmed = 'confirmed';
    case Waitlisted = 'waitlisted';
    case Cancelled = 'cancelled';

    /**
     * Get the translated, human readable name of the status.
     */
    public function label(): string
    {
        return match ($this) {
            self::Confirmed => __('Confirmed'),
            self::Waitlisted => __('Waitlisted'),
            self::Cancelled => __('Cancelled'),
        };
    }

    /**
     * Get the translated name of the group of signups with this status, used for filtering lists.
     */
    public function groupLabel(): string
    {
        return match ($this) {
            self::Confirmed => __('Confirmed signups'),
            self::Waitlisted => __('Waiting list'),
            self::Cancelled => __('Cancelled signups'),
        };
    }
}
