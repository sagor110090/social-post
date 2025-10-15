<?php

namespace App\Services\Webhooks\Processors;

use App\Services\Webhooks\Normalizers\EventNormalizerInterface;
use App\Services\Webhooks\Normalizers\FacebookEventNormalizer;
use App\Services\Webhooks\Normalizers\InstagramEventNormalizer;
use App\Services\Webhooks\Normalizers\TwitterEventNormalizer;
use App\Services\Webhooks\Normalizers\LinkedInEventNormalizer;
use App\Services\Webhooks\Analytics\AnalyticsUpdaterInterface;
use App\Services\Webhooks\Analytics\AnalyticsUpdater;
use App\Services\Webhooks\Notifications\NotificationHandlerInterface;
use App\Services\Webhooks\Notifications\NotificationHandler;
use App\Models\WebhookEvent;
use Illuminate\Support\Facades\Log;

class WebhookEventProcessorFactory
{
    /**
     * Create appropriate processor for the webhook event.
     */
    public static function create(WebhookEvent $webhookEvent): BaseWebhookEventProcessor
    {
        $normalizer = self::createNormalizer($webhookEvent->platform);
        $analyticsUpdater = app(AnalyticsUpdaterInterface::class);
        $notificationHandler = app(NotificationHandlerInterface::class);

        return match ($webhookEvent->platform) {
            'facebook' => new FacebookWebhookEventProcessor(
                $webhookEvent,
                $normalizer,
                $analyticsUpdater,
                $notificationHandler
            ),
            'instagram' => new InstagramWebhookEventProcessor(
                $webhookEvent,
                $normalizer,
                $analyticsUpdater,
                $notificationHandler
            ),
            'twitter' => new TwitterWebhookEventProcessor(
                $webhookEvent,
                $normalizer,
                $analyticsUpdater,
                $notificationHandler
            ),
            'linkedin' => new LinkedInWebhookEventProcessor(
                $webhookEvent,
                $normalizer,
                $analyticsUpdater,
                $notificationHandler
            ),
            default => throw new \InvalidArgumentException("Unsupported platform: {$webhookEvent->platform}"),
        };
    }

    /**
     * Create appropriate normalizer for the platform.
     */
    private static function createNormalizer(string $platform): EventNormalizerInterface
    {
        return match ($platform) {
            'facebook' => new FacebookEventNormalizer(),
            'instagram' => new InstagramEventNormalizer(),
            'twitter' => new TwitterEventNormalizer(),
            'linkedin' => new LinkedInEventNormalizer(),
            default => throw new \InvalidArgumentException("Unsupported platform: {$platform}"),
        };
    }

    /**
     * Get list of supported platforms.
     */
    public static function getSupportedPlatforms(): array
    {
        return ['facebook', 'instagram', 'twitter', 'linkedin'];
    }

    /**
     * Check if platform is supported.
     */
    public static function isPlatformSupported(string $platform): bool
    {
        return in_array($platform, self::getSupportedPlatforms());
    }
}