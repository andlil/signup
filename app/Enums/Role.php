<?php

namespace App\Enums;

enum Role: string
{
    case Admin = 'admin';
    case Organizer = 'organizer';

    /**
     * Get the translated, human readable name of the role.
     */
    public function label(): string
    {
        return match ($this) {
            self::Admin => __('Main admin'),
            self::Organizer => __('Organizer'),
        };
    }
}
