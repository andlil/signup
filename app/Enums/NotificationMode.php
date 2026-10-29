<?php

namespace App\Enums;

enum NotificationMode: string
{
    case EachSignup = 'each_signup';
    case DailySummary = 'daily_summary';
    case None = 'none';

    /**
     * Get the translated, human readable description of the mode.
     */
    public function label(): string
    {
        return match ($this) {
            self::EachSignup => __('An email for every signup and cancellation'),
            self::DailySummary => __('A daily summary'),
            self::None => __('No emails'),
        };
    }
}
