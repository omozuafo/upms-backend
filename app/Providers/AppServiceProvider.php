<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Http\Request;

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
        // Global API rate limiter per user/IP
        RateLimiter::for('api', function (Request $request) {
            $limit = (int) env('RATE_LIMIT_GLOBAL', 120);
            return Limit::perMinute($limit)
                ->by($request->user()?->id ?: $request->ip())
                ->response(function (Request $request, array $headers) {
                    $retryAfter = $headers['Retry-After'] ?? 60;
                    return response()->json([
                        'message' => 'Too many requests. Please slow down.',
                        'retry_after' => (int) $retryAfter,
                    ], 429, array_merge($headers, ['Retry-After' => (string) $retryAfter]));
                });
        });

        // Stricter rate limiter for authentication routes (login, register, check-duplicate)
        RateLimiter::for('auth', function (Request $request) {
            $limit = (int) env('RATE_LIMIT_AUTH', 10);
            return Limit::perMinute($limit)
                ->by($request->ip())
                ->response(function (Request $request, array $headers) {
                    $retryAfter = $headers['Retry-After'] ?? 60;
                    return response()->json([
                        'message' => 'Too many authentication attempts. Please try again later.',
                        'retry_after' => (int) $retryAfter,
                    ], 429, array_merge($headers, ['Retry-After' => (string) $retryAfter]));
                });
        });
    }
}
