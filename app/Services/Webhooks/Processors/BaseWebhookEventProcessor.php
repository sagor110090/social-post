<?php

namespace App\Services\Webhooks\Processors;

use App\Models\WebhookEvent;
use App\Models\PostAnalytics;
use App\Models\SocialAccount;
use App\Models\Post;
use App\Services\Webhooks\Normalizers\EventNormalizerInterface;
use App\Services\Webhooks\Analytics\AnalyticsUpdaterInterface;
use App\Services\Webhooks\Notifications\NotificationHandlerInterface;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Exception;

abstract class BaseWebhookEventProcessor
{
    protected WebhookEvent $webhookEvent;
    protected EventNormalizerInterface $normalizer;
    protected AnalyticsUpdaterInterface $analyticsUpdater;
    protected NotificationHandlerInterface $notificationHandler;

    public function __construct(
        WebhookEvent $webhookEvent,
        EventNormalizerInterface $normalizer,
        AnalyticsUpdaterInterface $analyticsUpdater,
        NotificationHandlerInterface $notificationHandler
    ) {
        $this->webhookEvent = $webhookEvent;
        $this->normalizer = $normalizer;
        $this->analyticsUpdater = $analyticsUpdater;
        $this->notificationHandler = $notificationHandler;
    }

    /**
     * Process the webhook event.
     */
    public function process(): void
    {
        try {
            // Check for duplicate events
            if ($this->isDuplicateEvent()) {
                $this->webhookEvent->markAsIgnored();
                Log::info('Duplicate webhook event ignored', [
                    'event_id' => $this->webhookEvent->id,
                    'platform_event_id' => $this->webhookEvent->event_id,
                ]);
                return;
            }

            // Mark as processing
            $this->webhookEvent->update(['status' => 'processing']);

            // Normalize the event data
            $normalizedEvent = $this->normalizer->normalize($this->webhookEvent);

            // Process the normalized event
            $this->processNormalizedEvent($normalizedEvent);

            // Mark as processed
            $this->webhookEvent->markAsProcessed();

            // Cache the event ID to prevent duplicates
            $this->cacheEventId();

        } catch (Exception $e) {
            $this->handleProcessingError($e);
        }
    }

    /**
     * Process the normalized event data.
     */
    protected function processNormalizedEvent(array $normalizedEvent): void
    {
        $eventType = $normalizedEvent['event_type'];
        $objectType = $normalizedEvent['object_type'];

        // Route to appropriate handler
        match ($objectType) {
            'post' => $this->handlePostEvent($eventType, $normalizedEvent),
            'comment' => $this->handleCommentEvent($eventType, $normalizedEvent),
            'message' => $this->handleMessageEvent($eventType, $normalizedEvent),
            'user' => $this->handleUserEvent($eventType, $normalizedEvent),
            'lead' => $this->handleLeadEvent($eventType, $normalizedEvent),
            'story' => $this->handleStoryEvent($eventType, $normalizedEvent),
            'account' => $this->handleAccountEvent($eventType, $normalizedEvent),
            default => $this->handleUnknownEvent($eventType, $normalizedEvent),
        };
    }

    /**
     * Handle post-related events.
     */
    protected function handlePostEvent(string $eventType, array $event): void
    {
        match ($eventType) {
            'created' => $this->handlePostCreated($event),
            'updated' => $this->handlePostUpdated($event),
            'deleted' => $this->handlePostDeleted($event),
            'engagement' => $this->handlePostEngagement($event),
            'metrics_updated' => $this->handlePostMetricsUpdated($event),
            default => $this->logUnhandledEvent('post', $eventType),
        };
    }

    /**
     * Handle comment-related events.
     */
    protected function handleCommentEvent(string $eventType, array $event): void
    {
        match ($eventType) {
            'created' => $this->handleCommentCreated($event),
            'updated' => $this->handleCommentUpdated($event),
            'deleted' => $this->handleCommentDeleted($event),
            'mention' => $this->handleCommentMention($event),
            default => $this->logUnhandledEvent('comment', $eventType),
        };
    }

    /**
     * Handle message-related events.
     */
    protected function handleMessageEvent(string $eventType, array $event): void
    {
        match ($eventType) {
            'received' => $this->handleMessageReceived($event),
            'sent' => $this->handleMessageSent($event),
            'read' => $this->handleMessageRead($event),
            default => $this->logUnhandledEvent('message', $eventType),
        };
    }

    /**
     * Handle user-related events.
     */
    protected function handleUserEvent(string $eventType, array $event): void
    {
        match ($eventType) {
            'followed' => $this->handleUserFollowed($event),
            'unfollowed' => $this->handleUserUnfollowed($event),
            'updated' => $this->handleUserUpdated($event),
            'mentioned' => $this->handleUserMentioned($event),
            default => $this->logUnhandledEvent('user', $eventType),
        };
    }

    /**
     * Handle lead-related events.
     */
    protected function handleLeadEvent(string $eventType, array $event): void
    {
        match ($eventType) {
            'generated' => $this->handleLeadGenerated($event),
            'updated' => $this->handleLeadUpdated($event),
            default => $this->logUnhandledEvent('lead', $eventType),
        };
    }

    /**
     * Handle story-related events.
     */
    protected function handleStoryEvent(string $eventType, array $event): void
    {
        match ($eventType) {
            'created' => $this->handleStoryCreated($event),
            'updated' => $this->handleStoryUpdated($event),
            'deleted' => $this->handleStoryDeleted($event),
            'insights_updated' => $this->handleStoryInsightsUpdated($event),
            'reply' => $this->handleStoryReply($event),
            default => $this->logUnhandledEvent('story', $eventType),
        };
    }

    /**
     * Handle account-related events.
     */
    protected function handleAccountEvent(string $eventType, array $event): void
    {
        match ($eventType) {
            'updated' => $this->handleAccountUpdated($event),
            'verified' => $this->handleAccountVerified($event),
            'suspended' => $this->handleAccountSuspended($event),
            'insights_updated' => $this->handleAccountInsightsUpdated($event),
            default => $this->logUnhandledEvent('account', $eventType),
        };
    }

    /**
     * Handle unknown events.
     */
    protected function handleUnknownEvent(string $eventType, array $event): void
    {
        $this->logUnhandledEvent('unknown', $eventType);
    }

    // Abstract methods that must be implemented by platform-specific processors
    abstract protected function handlePostCreated(array $event): void;
    abstract protected function handlePostUpdated(array $event): void;
    abstract protected function handlePostDeleted(array $event): void;
    abstract protected function handlePostEngagement(array $event): void;
    abstract protected function handlePostMetricsUpdated(array $event): void;
    abstract protected function handleCommentCreated(array $event): void;
    abstract protected function handleCommentUpdated(array $event): void;
    abstract protected function handleCommentDeleted(array $event): void;
    abstract protected function handleCommentMention(array $event): void;
    abstract protected function handleMessageReceived(array $event): void;
    abstract protected function handleMessageSent(array $event): void;
    abstract protected function handleMessageRead(array $event): void;
    abstract protected function handleUserFollowed(array $event): void;
    abstract protected function handleUserUnfollowed(array $event): void;
    abstract protected function handleUserUpdated(array $event): void;
    abstract protected function handleUserMentioned(array $event): void;
    abstract protected function handleLeadGenerated(array $event): void;
    abstract protected function handleLeadUpdated(array $event): void;
    abstract protected function handleStoryCreated(array $event): void;
    abstract protected function handleStoryUpdated(array $event): void;
    abstract protected function handleStoryDeleted(array $event): void;
    abstract protected function handleStoryInsightsUpdated(array $event): void;
    abstract protected function handleStoryReply(array $event): void;
    abstract protected function handleAccountUpdated(array $event): void;
    abstract protected function handleAccountVerified(array $event): void;
    abstract protected function handleAccountSuspended(array $event): void;
    abstract protected function handleAccountInsightsUpdated(array $event): void;

    /**
     * Check if this is a duplicate event.
     */
    protected function isDuplicateEvent(): bool
    {
        $cacheKey = "webhook_event_{$this->webhookEvent->platform}_{$this->webhookEvent->event_id}";
        return Cache::has($cacheKey);
    }

    /**
     * Cache the event ID to prevent duplicates.
     */
    protected function cacheEventId(): void
    {
        $cacheKey = "webhook_event_{$this->webhookEvent->platform}_{$this->webhookEvent->event_id}";
        Cache::put($cacheKey, true, now()->addHours(24));
    }

    /**
     * Get or create post analytics record.
     */
    protected function getOrCreatePostAnalytics(string $platformPostId): ?PostAnalytics
    {
        // First try to find existing analytics
        $analytics = PostAnalytics::where('platform', $this->webhookEvent->platform)
            ->where('platform_post_id', $platformPostId)
            ->first();

        if ($analytics) {
            return $analytics;
        }

        // Try to find the associated post
        $post = Post::where('platform', $this->webhookEvent->platform)
            ->where('platform_post_id', $platformPostId)
            ->first();

        // Create new analytics record
        return PostAnalytics::create([
            'post_id' => $post?->id,
            'social_account_id' => $this->webhookEvent->social_account_id,
            'platform' => $this->webhookEvent->platform,
            'platform_post_id' => $platformPostId,
            'metrics' => [],
            'recorded_at' => now(),
        ]);
    }

    /**
     * Update social account metadata.
     */
    protected function updateSocialAccountMetadata(array $metadata): void
    {
        $socialAccount = $this->webhookEvent->socialAccount;
        if ($socialAccount) {
            $currentMetadata = $socialAccount->metadata ?? [];
            $updatedMetadata = array_merge($currentMetadata, $metadata);
            
            $socialAccount->update([
                'metadata' => $updatedMetadata,
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * Send notification for important events.
     */
    protected function sendNotification(string $type, array $data): void
    {
        $this->notificationHandler->handle($type, $data, $this->webhookEvent);
    }

    /**
     * Update analytics with new metrics.
     */
    protected function updateAnalytics(string $platformPostId, array $metrics): void
    {
        $this->analyticsUpdater->update($platformPostId, $this->webhookEvent->platform, $metrics);
    }

    /**
     * Handle processing errors.
     */
    protected function handleProcessingError(Exception $e): void
    {
        Log::error('Webhook event processing failed', [
            'webhook_event_id' => $this->webhookEvent->id,
            'platform' => $this->webhookEvent->platform,
            'event_type' => $this->webhookEvent->event_type,
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
        ]);

        $this->webhookEvent->markAsFailed($e->getMessage());
        
        // Send notification for critical failures
        if ($this->webhookEvent->retry_count >= 2) {
            $this->sendNotification('webhook_processing_failed', [
                'error' => $e->getMessage(),
                'retry_count' => $this->webhookEvent->retry_count,
            ]);
        }

        throw $e;
    }

    /**
     * Log unhandled events.
     */
    protected function logUnhandledEvent(string $objectType, string $eventType): void
    {
        Log::info("Unhandled {$objectType} event", [
            'platform' => $this->webhookEvent->platform,
            'event_type' => $eventType,
            'webhook_event_id' => $this->webhookEvent->id,
        ]);

        $this->webhookEvent->update([
            'status' => 'ignored',
            'error_message' => "Unhandled {$objectType} event: {$eventType}",
            'processed_at' => now(),
        ]);
    }

    /**
     * Execute database transaction safely.
     */
    protected function executeInTransaction(callable $callback): mixed
    {
        try {
            return DB::transaction($callback);
        } catch (Exception $e) {
            Log::error('Database transaction failed', [
                'webhook_event_id' => $this->webhookEvent->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Get platform-specific configuration.
     */
    protected function getPlatformConfig(string $key, mixed $default = null): mixed
    {
        return config("webhooks.platforms.{$this->webhookEvent->platform}.{$key}", $default);
    }
}