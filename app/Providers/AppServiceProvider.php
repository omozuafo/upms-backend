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
        // Resilient database connectivity fallback
        if (config('database.default') === 'pgsql') {
            try {
                \Illuminate\Support\Facades\DB::connection('pgsql')->getPdo();
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Config::set('database.default', 'sqlite');
                \Illuminate\Support\Facades\DB::purge('pgsql');

                $sqlitePath = database_path('database.sqlite');
                if (!file_exists($sqlitePath)) {
                    touch($sqlitePath);
                }

                try {
                    if (!\Illuminate\Support\Facades\Schema::connection('sqlite')->hasTable('users')) {
                        \Illuminate\Support\Facades\Artisan::call('migrate', ['--force' => true]);
                        \Illuminate\Support\Facades\Artisan::call('admin:ensure');
                    }
                } catch (\Throwable $migrationError) {
                    \Illuminate\Support\Facades\Log::warning('SQLite auto-migration notice: ' . $migrationError->getMessage());
                }
            }
        }
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
