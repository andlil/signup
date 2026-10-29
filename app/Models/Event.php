<?php

namespace App\Models;

use App\Enums\EventStatus;
use App\Enums\NotificationMode;
use Database\Factories\EventFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

#[Fillable([
    'title',
    'description',
    'location',
    'starts_at',
    'ends_at',
    'signup_deadline',
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
            'published_at' => 'datetime',
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
        return $this->status === EventStatus::Published && ! $this->deadlineHasPassed();
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
     * Get the public URL of the cover image, if there is one.
     */
    public function coverImageUrl(): ?string
    {
        return $this->cover_image_path === null
            ? null
            : Storage::disk('public')->url($this->cover_image_path);
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
