<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

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
        // Auto-provision Super Admin and Admin if they don't exist
        try {
            if (\Schema::hasTable('users')) {
                if (!\App\Models\User::where('email', 'superadmin@upms.com')->exists()) {
                    \App\Models\User::create([
                        'name' => 'Super Admin',
                        'email' => 'superadmin@upms.com',
                        'password' => \Hash::make('asdfghj69.'),
                        'role' => 'super_admin',
                    ]);
                }
                
                if (!\App\Models\User::where('email', 'admin@example.com')->exists()) {
                    \App\Models\User::create([
                        'name' => 'Admin User',
                        'email' => 'admin@example.com',
                        'password' => \Hash::make('password'),
                        'role' => 'admin',
                    ]);
                }
            }
        } catch (\Exception $e) {
            // Silence exceptions in case migrations haven't run yet
        }
    }
}
