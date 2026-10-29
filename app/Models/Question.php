<?php

namespace App\Models;

use App\Enums\QuestionType;
use Database\Factories\QuestionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['type', 'label', 'help_text', 'is_required', 'options', 'min_value', 'max_value', 'position'])]
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
     * Get the HTML input name used for the question's answer.
     */
    public function fieldName(): string
    {
        return 'answers.'.$this->id;
    }
}
