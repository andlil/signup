<?php

namespace App\Models;

use Database\Factories\LoginTokenFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

#[Fillable(['token_hash', 'expires_at'])]
class LoginToken extends Model
{
    /** @use HasFactory<LoginTokenFactory> */
    use HasFactory;

    /**
     * How many minutes a magic link stays valid.
     */
    public const int LIFETIME_MINUTES = 15;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'used_at' => 'datetime',
        ];
    }

    /**
     * Get the user the token was issued to.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Issue a new token for the user and return the plain text value.
     *
     * Only a hash of the token is stored, so a leaked database cannot be used to log in.
     */
    public static function issueFor(User $user): string
    {
        $plainToken = Str::random(64);

        $user->loginTokens()->create([
            'token_hash' => self::hash($plainToken),
            'expires_at' => now()->addMinutes(self::LIFETIME_MINUTES),
        ]);

        return $plainToken;
    }

    /**
     * Find an unused, unexpired token belonging to an active user.
     */
    public static function findUsable(string $plainToken): ?self
    {
        return self::query()
            ->usable()
            ->where('token_hash', self::hash($plainToken))
            ->whereRelation('user', 'is_active', true)
            ->first();
    }

    /**
     * Hash a plain text token for storage and lookup.
     */
    public static function hash(string $plainToken): string
    {
        return hash('sha256', $plainToken);
    }

    /**
     * Scope the query to tokens that have not been used and have not expired.
     *
     * @param  Builder<self>  $query
     */
    public function scopeUsable(Builder $query): void
    {
        $query->whereNull('used_at')->where('expires_at', '>', now());
    }

    /**
     * Mark the token as used, returning false if another request already used it.
     */
    public function consume(): bool
    {
        return self::query()
            ->whereKey($this->getKey())
            ->whereNull('used_at')
            ->update(['used_at' => now()]) === 1;
    }
}
