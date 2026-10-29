<?php

namespace App\Models;

use App\Enums\QuestionType;
use Carbon\CarbonImmutable;
use Carbon\CarbonPeriod;
use Database\Factories\QuestionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;

#[Fillable(['type', 'label', 'help_text', 'is_required', 'options', 'min_value', 'max_value', 'period_start', 'period_end', 'weekdays_only', 'position'])]
class Question extends Model
{
    /** @use HasFactory<QuestionFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => QuestionType::class,
            'is_required' => 'boolean',
            'options' => 'array',
            'min_value' => 'integer',
            'max_value' => 'integer',
            'period_start' => 'immutable_date',
            'period_end' => 'immutable_date',
            'weekdays_only' => 'boolean',
            'position' => 'integer',
        ];
    }

    /**
     * Get the event the question belongs to.
     *
     * @return BelongsTo<Event, $this>
     */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    /**
     * The longest period a vacation planning question may cover.
     */
    public const int MAX_PERIOD_DAYS = 366;

    /**
     * Get the days a participant can pick in a vacation planning question.
     *
     * @return Collection<int, CarbonImmutable>
     */
    public function selectableDays(): Collection
    {
        if ($this->period_start === null || $this->period_end === null) {
            return collect();
        }

        return collect(CarbonPeriod::create($this->period_start, $this->period_end))
            ->map(fn ($day): CarbonImmutable => CarbonImmutable::instance($day))
            ->reject(fn (CarbonImmutable $day): bool => $this->weekdays_only && $day->isWeekend())
            ->values();
    }

    /**
     * Get the selectable days as Y-m-d strings, the format answers are stored in.
     *
     * @return list<string>
     */
    public function selectableDayValues(): array
    {
        return $this->selectableDays()->map(fn (CarbonImmutable $day): string => $day->toDateString())->all();
    }

    /**
     * Describe picked days compactly, joining days that follow each other: "1–5 juli, 8 juli (6 dagar)".
     *
     * Days next to each other among the selectable days count as following
     * each other, so with weekdays only a Friday and the next Monday are joined.
     *
     * @param  list<string>  $days
     */
    public function describeDays(array $days): string
    {
        $selectable = $this->selectableDayValues();
        $indexes = collect($days)
            ->map(fn (string $day): int|false => array_search($day, $selectable, true))
            ->filter(fn (int|false $index): bool => $index !== false)
            ->sort()
            ->values();

        if ($indexes->isEmpty()) {
            return '';
        }

        $ranges = [];
        foreach ($indexes as $index) {
            $last = array_key_last($ranges);

            if ($last !== null && $ranges[$last][1] === $index - 1) {
                $ranges[$last][1] = $index;
            } else {
                $ranges[] = [$index, $index];
            }
        }

        $parts = array_map(function (array $range) use ($selectable): string {
            $first = CarbonImmutable::parse($selectable[$range[0]]);
            $last = CarbonImmutable::parse($selectable[$range[1]]);

            if ($first->equalTo($last)) {
                return $first->isoFormat('D MMMM');
            }

            return $first->isSameMonth($last)
                ? $first->isoFormat('D').'–'.$last->isoFormat('D MMMM')
                : $first->isoFormat('D MMMM').' – '.$last->isoFormat('D MMMM');
        }, $ranges);

        return implode(', ', $parts).' ('.trans_choice(':count day|:count days', $indexes->count()).')';
    }

    /**
     * Get the HTML input name used for the question's answer.
     */
    public function fieldName(): string
    {
        return 'answers.'.$this->id;
    }
}
