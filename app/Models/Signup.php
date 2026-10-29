<?php

namespace App\Models;

use App\Enums\QuestionType;
use App\Enums\SignupStatus;
use Database\Factories\SignupFactory;
use Illuminate\Contracts\Translation\HasLocalePreference;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

#[Fillable(['name', 'email', 'answers', 'places', 'locale'])]
#[Hidden(['manage_token', 'manage_token_hash'])]
class Signup extends Model implements HasLocalePreference
{
    /** @use HasFactory<SignupFactory> */
    use HasFactory;

    /**
     * Give every signup a secret link for changing or cancelling it.
     *
     * The token is stored encrypted so it can be emailed again, together with
     * a hash that the link is looked up by.
     */
    protected static function booted(): void
    {
        static::creating(function (Signup $signup): void {
            if ($signup->manage_token === null) {
                $signup->manage_token = Str::random(48);
            }

            $signup->manage_token_hash = self::hashToken($signup->manage_token);
        });
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'answers' => 'array',
            'places' => 'integer',
            'status' => SignupStatus::class,
            'manage_token' => 'encrypted',
            'consented_at' => 'datetime',
            'confirmed_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    /**
     * Store email addresses in lower case so duplicates are found.
     *
     * @return Attribute<string, string>
     */
    protected function email(): Attribute
    {
        return Attribute::make(
            set: fn (string $value): string => mb_strtolower(trim($value)),
        );
    }

    /**
     * Get the event the signup is for.
     *
     * @return BelongsTo<Event, $this>
     */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    /**
     * Find a signup by the token in its secret link.
     */
    public static function findByToken(string $token): ?self
    {
        return self::query()->where('manage_token_hash', self::hashToken($token))->first();
    }

    /**
     * Hash a plain text token for lookup.
     */
    public static function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }

    /**
     * Scope the query to signups that have not been cancelled.
     *
     * @param  Builder<self>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('status', '!=', SignupStatus::Cancelled);
    }

    /**
     * Get the URL of the page where the participant changes or cancels the signup.
     */
    public function manageUrl(): string
    {
        return route('signups.show', ['token' => $this->manage_token]);
    }

    /**
     * Get the signup's place in the waiting list, starting at 1.
     */
    public function waitlistPosition(): ?int
    {
        if ($this->status !== SignupStatus::Waitlisted) {
            return null;
        }

        return self::query()
            ->whereBelongsTo($this->event)
            ->where('status', SignupStatus::Waitlisted)
            ->where('id', '<', $this->id)
            ->count() + 1;
    }

    /**
     * Get the raw answer to a question.
     *
     * @return string|int|list<string>|null
     */
    public function answerTo(Question $question): string|int|array|null
    {
        return $this->answers[$question->id] ?? null;
    }

    /**
     * Get the answer to a question formatted for display.
     */
    public function displayAnswerTo(Question $question): string
    {
        $answer = $this->answerTo($question);

        return match (true) {
            $answer === null || $answer === '' || $answer === [] => '',
            is_array($answer) => implode(', ', $answer),
            $question->type === QuestionType::Date => Carbon::parse($answer)->isoFormat('LL'),
            default => (string) $answer,
        };
    }

    /**
     * Determine whether the participant may still change their answers.
     */
    public function canBeChanged(): bool
    {
        return $this->status !== SignupStatus::Cancelled && $this->event->acceptsSignups();
    }

    /**
     * Determine whether the participant may still cancel.
     */
    public function canBeCancelled(): bool
    {
        return $this->status !== SignupStatus::Cancelled
            && ($this->event->cancellationDeadline()?->isFuture() ?? true);
    }

    /**
     * Get the locale emails to the participant should be sent in.
     */
    public function preferredLocale(): ?string
    {
        return $this->locale;
    }
}
