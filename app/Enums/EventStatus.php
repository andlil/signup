<?php

namespace App\Enums;

enum EventStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
    case Closed = 'closed';

    /**
     * Get the translated, human readable name of the status.
     */
    public function label(): string
    {
        return match ($this) {
            self::Draft => __('Draft'),
            self::Published => __('Published'),
            self::Closed => __('Closed'),
        };
    }
}
