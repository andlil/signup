<?php

namespace App\Enums;

enum QuestionType: string
{
    case ShortText = 'short_text';
    case LongText = 'long_text';
    case SingleChoice = 'single_choice';
    case Dropdown = 'dropdown';
    case Checkboxes = 'checkboxes';
    case Date = 'date';
    case Number = 'number';
    case VacationDays = 'vacation_days';

    /**
     * Get the translated, human readable name of the type.
     */
    public function label(): string
    {
        return match ($this) {
            self::ShortText => __('Short text'),
            self::LongText => __('Long text'),
            self::SingleChoice => __('Multiple choice'),
            self::Dropdown => __('Dropdown'),
            self::Checkboxes => __('Checkboxes'),
            self::Date => __('Date'),
            self::Number => __('Number'),
            self::VacationDays => __('Vacation planning'),
        };
    }

    /**
     * Determine whether answers are picked from a list of options.
     */
    public function hasOptions(): bool
    {
        return in_array($this, [self::SingleChoice, self::Dropdown, self::Checkboxes], true);
    }

    /**
     * Determine whether the question can have a minimum and maximum value.
     */
    public function hasRange(): bool
    {
        return $this === self::Number;
    }

    /**
     * Determine whether the question asks which days of a period the participant picks.
     */
    public function hasPeriod(): bool
    {
        return $this === self::VacationDays;
    }
}
