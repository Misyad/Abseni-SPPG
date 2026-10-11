<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
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
        RateLimiter::for('employee-pin', function (Request $request) {
            $key = $request->ip().'|'.(string) $request->input('employee_id', 'unknown');

            return Limit::perMinutes(15, 5)->by($key);
        });

        RateLimiter::for('attendance-submit', function (Request $request) {
            $key = $request->ip().'|'.(string) $request->input('employee_id', 'unknown');

            return Limit::perMinutes(15, 10)->by($key);
        });

        RateLimiter::for('admin-login', function (Request $request) {
            $identity = strtolower(trim((string) $request->input('email', 'unknown')));
            $key = hash('sha256', $request->ip().'|'.$identity);

            return Limit::perMinutes(15, 5)->by($key);
        });
    }
}
