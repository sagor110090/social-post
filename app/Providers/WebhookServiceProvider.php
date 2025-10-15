<?php

namespace App\Providers;

use App\Services\Webhooks\WebhookEventProcessingService;
use App\Services\Webhooks\Analytics\AnalyticsUpdater;
use App\Services\Webhooks\Analytics\AnalyticsUpdaterInterface;
use App\Services\Webhooks\Notifications\NotificationHandler;
use App\Services\Webhooks\Notifications\NotificationHandlerInterface;
use Illuminate\Support\ServiceProvider;

class WebhookServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // Bind interfaces to implementations
        $this->app->bind(AnalyticsUpdaterInterface::class, AnalyticsUpdater::class);
        $this->app->bind(NotificationHandlerInterface::class, NotificationHandler::class);

        // Register webhook processing service as singleton
        $this->app->singleton(WebhookEventProcessingService::class, function ($app) {
            return new WebhookEventProcessingService();
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Publish configuration
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../../config/webhooks.php' => config_path('webhooks.php'),
            ], 'webhook-config');
        }
    }
}