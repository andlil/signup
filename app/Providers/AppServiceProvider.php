<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $trustedProxies = (string) config('app.trusted_proxies');

        if ($trustedProxies !== '') {
            TrustProxies::at($trustedProxies === '*' ? '*' : array_map('trim', explode(',', $trustedProxies)));
        }

        Gate::define('manage-users', fn (User $user): bool => $user->isAdmin());

        RateLimiter::for('magic-link', fn (Request $request): array => [
            Limit::perMinute(3)->by(Str::lower($request->string('email')).'|'.$request->ip()),
            Limit::perMinute(20)->by($request->ip()),
        ]);

        RateLimiter::for('signups', fn (Request $request): Limit => Limit::perMinute(10)->by($request->ip()));

        RateLimiter::for('manage-signup', fn (Request $request): Limit => Limit::perMinute(30)->by($request->ip()));

        RateLimiter::for('magic-link-consume', fn (Request $request): Limit => Limit::perMinute(10)->by($request->ip()));
    }
}
