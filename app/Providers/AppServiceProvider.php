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
        RateLimiter::for('sensor-ingest', function (Request $request) {
            return Limit::perMinute(120)->by($request->ip().'|'.$request->header('X-Device-Token', 'missing'));
        });

        RateLimiter::for('chat', function (Request $request) {
            $key = $request->ip();

            return [
                Limit::perMinute(max(1, (int) config('services.ai_limits.per_minute', 10)))->by($key),
                Limit::perDay(max(1, (int) config('services.ai_limits.per_day', 100)))->by($key),
            ];
        });
    }
}
