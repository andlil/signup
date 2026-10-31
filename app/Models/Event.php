<?php

namespace App\Models;

use App\Enums\EventStatus;
use App\Enums\NotificationMode;
use App\Enums\SignupStatus;
use Database\Factories\EventFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

#[Fillable([
    'title',
    'description',
    'location',
    'starts_at',
    'ends_at',
    'signup_deadline',
    'allow_late_cancellation',
    'capacity',
    'notification_mode',
    'retention_days',
])]
class Event extends Model
{
    /** @use HasFactory<EventFactory> */
    use HasFactory;

    /**
     * The model's default values for attributes.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'draft',
        'notification_mode' => 'each_signup',
        'retention_days' => 30,
        'allow_late_cancellation' => false,
    ];

    /**
     * Give every event an unguessable public link when it is created.
     */
    protected static function booted(): void
    {
        static::creating(function (Event $event): void {
            $event->slug ??= self::uniqueSlug();
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
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'signup_deadline' => 'datetime',
            'allow_late_cancellation' => 'boolean',
            'published_at' => 'datetime',
            'summary_sent_at' => 'datetime',
            'export_sent_at' => 'datetime',
            'capacity' => 'integer',
            'retention_days' => 'integer',
            'status' => EventStatus::class,
            'notification_mode' => NotificationMode::class,
        ];
    }

    /**
     * Get the organizer who owns the event.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the event's form questions in the order they are shown.
     *
     * @return HasMany<Question, $this>
     */
    public function questions(): HasMany
    {
        return $this->hasMany(Question::class)->orderBy('position');
    }

    /**
     * Get the event's signups, including cancelled ones.
     *
     * @return HasMany<Signup, $this>
     */
    public function signups(): HasMany
    {
        return $this->hasMany(Signup::class);
    }

    /**
     * Get the number of places taken by confirmed signups.
     */
    public function takenPlaces(): int
    {
        return (int) $this->signups()->where('status', SignupStatus::Confirmed)->sum('places');
    }

    /**
     * Get the number of free places, or null when the number of places is unlimited.
     */
    public function remainingPlaces(): ?int
    {
        return $this->capacity === null ? null : max(0, $this->capacity - $this->takenPlaces());
    }

    /**
     * Get the number question whose answer decides how many places a signup takes.
     *
     * @return BelongsTo<Question, $this>
     */
    public function capacityQuestion(): BelongsTo
    {
        return $this->belongsTo(Question::class, 'capacity_question_id');
    }

    /**
     * Scope the query to the events the user may see: all of them for the main admin.
     *
     * @param  Builder<self>  $query
     */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        if (! $user->isAdmin()) {
            $query->whereBelongsTo($user);
        }
    }

    /**
     * Determine whether the form questions may still be changed.
     *
     * The form is locked once the event has been published, so that every
     * signup answers the same questions.
     */
    public function hasEditableQuestions(): bool
    {
        return $this->status === EventStatus::Draft;
    }

    /**
     * Determine whether the public page can be seen by everyone.
     */
    public function isPublic(): bool
    {
        return $this->status !== EventStatus::Draft;
    }

    /**
     * Determine whether the signup deadline has passed.
     */
    public function deadlineHasPassed(): bool
    {
        return $this->signup_deadline !== null && $this->signup_deadline->isPast();
    }

    /**
     * Determine whether new signups are accepted right now.
     */
    public function acceptsSignups(): bool
    {
        return $this->status === EventStatus::Published
            && ! $this->deadlineHasPassed()
            && ! $this->hasStarted();
    }

    /**
     * Explain why a published event no longer accepts signups, or null when it does.
     */
    public function signupsClosedReason(): ?string
    {
        return match (true) {
            $this->status === EventStatus::Draft => null,
            $this->status === EventStatus::Closed => __('Closed by the organizer'),
            $this->hasStarted() => __('The event has started'),
            $this->deadlineHasPassed() => __('The last day to sign up was :date', ['date' => $this->signup_deadline->isoFormat('LLL')]),
            default => null,
        };
    }

    /**
     * Determine whether the event has a start that has passed.
     */
    public function hasStarted(): bool
    {
        return $this->starts_at !== null && $this->starts_at->isPast();
    }

    /**
     * Get the last moment participants may cancel, or null when they may always cancel.
     *
     * Cancelling stops at the signup deadline unless the organizer allows late
     * cancellations, and never continues past the start of the event.
     */
    public function cancellationDeadline(): ?Carbon
    {
        $limits = array_filter([
            $this->starts_at,
            $this->allow_late_cancellation ? null : $this->signup_deadline,
        ]);

        return $limits === [] ? null : min($limits);
    }

    /**
     * Get the moment signups close by themselves: the signup deadline or the start, whichever comes first.
     */
    public function signupsCloseAt(): ?Carbon
    {
        $moments = array_filter([$this->signup_deadline, $this->starts_at]);

        return $moments === [] ? null : min($moments);
    }

    /**
     * Get the moment the event is over: its end, its start, or its signup deadline, whichever is set first in that order.
     */
    public function concludedAt(): Carbon
    {
        return $this->ends_at ?? $this->starts_at ?? $this->signup_deadline;
    }

    /**
     * Get the date used to sort events: the start, or the signup deadline when there is no start.
     */
    public function sortDate(): Carbon
    {
        return $this->starts_at ?? $this->signup_deadline;
    }

    /**
     * Get the moment the event's signups, and the personal data in them, are deleted.
     */
    public function signupsExpireAt(): Carbon
    {
        return $this->concludedAt()->copy()->addDays($this->retention_days);
    }

    /**
     * Publish the event, which makes its page public and locks the form.
     */
    public function publish(): void
    {
        $this->forceFill([
            'status' => EventStatus::Published,
            'published_at' => $this->published_at ?? now(),
        ])->save();
    }

    /**
     * Stop accepting signups.
     */
    public function close(): void
    {
        $this->forceFill(['status' => EventStatus::Closed])->save();
    }

    /**
     * Start accepting signups again after the event was closed.
     */
    public function reopen(): void
    {
        $this->forceFill(['status' => EventStatus::Published])->save();
    }

    /**
     * Render the Markdown description as HTML, escaping any HTML the organizer typed.
     */
    public function descriptionHtml(): HtmlString
    {
        return new HtmlString(Str::markdown($this->description ?? '', [
            'html_input' => 'escape',
            'allow_unsafe_links' => false,
            'max_nesting_level' => 20,
        ]));
    }

    /**
     * Generate a random slug that no other event uses.
     */
    private static function uniqueSlug(): string
    {
        do {
            $slug = Str::lower(Str::random(12));
        } while (self::query()->where('slug', $slug)->exists());

        return $slug;
    }
}
