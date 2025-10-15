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
        // Register webhook rate limiting
        RateLimiter::for('webhooks', function (Request $request) {
            // Different limits based on platform
            $platform = $request->segment(2); // /webhooks/{platform}
            
            return match($platform) {
                'facebook', 'instagram' => Limit::perMinute(100),
                'twitter' => Limit::perMinute(60),
                'linkedin' => Limit::perMinute(50),
                default => Limit::perMinute(30),
            };
        });
    }
}
