<?php

namespace App\Services\Webhooks;

use App\Services\Webhooks\Processors\WebhookEventProcessorFactory;
use App\Services\Webhooks\Processors\BaseWebhookEventProcessor;
use App\Models\WebhookEvent;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use App\Jobs\ProcessWebhookEventJob;

class WebhookEventProcessingService
{
    /**
     * Process a webhook event using the appropriate processor.
     */
    public function processEvent(WebhookEvent $webhookEvent): void
    {
        try {
            // Check if platform is supported
            if (!WebhookEventProcessorFactory::isPlatformSupported($webhookEvent->platform)) {
                $webhookEvent->markAsIgnored();
                Log::warning('Unsupported platform for webhook event', [
                    'platform' => $webhookEvent->platform,
                    'event_id' => $webhookEvent->id,
                ]);
                return;
            }

            // Create and use the appropriate processor
            $processor = WebhookEventProcessorFactory::create($webhookEvent);
            $processor->process();

        } catch (\Exception $e) {
            Log::error('Webhook event processing service failed', [
                'webhook_event_id' => $webhookEvent->id,
                'platform' => $webhookEvent->platform,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $webhookEvent->markAsFailed($e->getMessage());
            throw $e;
        }
    }

    /**
     * Queue a webhook event for processing.
     */
    public function queueEvent(WebhookEvent $webhookEvent): void
    {
        try {
            ProcessWebhookEventJob::dispatch($webhookEvent)
                ->onQueue('webhooks')
                ->delay(now()->addSeconds(5)); // Small delay to avoid race conditions
        } catch (\Exception $e) {
            Log::error('Failed to queue webhook event', [
                'webhook_event_id' => $webhookEvent->id,
                'error' => $e->getMessage(),
            ]);

            $webhookEvent->markAsFailed($e->getMessage());
            throw $e;
        }
    }

    /**
     * Process multiple webhook events in batch.
     */
    public function processBatch(array $webhookEvents): void
    {
        $processed = 0;
        $failed = 0;

        foreach ($webhookEvents as $webhookEvent) {
            try {
                $this->processEvent($webhookEvent);
                $processed++;
            } catch (\Exception $e) {
                $failed++;
                Log::error('Batch webhook event processing failed', [
                    'webhook_event_id' => $webhookEvent->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        Log::info('Batch webhook event processing completed', [
            'total' => count($webhookEvents),
            'processed' => $processed,
            'failed' => $failed,
        ]);
    }

    /**
     * Get processing statistics for a social account.
     */
    public function getProcessingStats(int $socialAccountId): array
    {
        $events = WebhookEvent::where('social_account_id', $socialAccountId);

        return [
            'total_events' => $events->count(),
            'pending_events' => $events->where('status', 'pending')->count(),
            'processing_events' => $events->where('status', 'processing')->count(),
            'processed_events' => $events->where('status', 'processed')->count(),
            'failed_events' => $events->where('status', 'failed')->count(),
            'ignored_events' => $events->where('status', 'ignored')->count(),
            'average_processing_time' => $this->calculateAverageProcessingTime($socialAccountId),
            'success_rate' => $this->calculateSuccessRate($socialAccountId),
        ];
    }

    /**
     * Retry failed webhook events.
     */
    public function retryFailedEvents(int $socialAccountId, int $limit = 50): int
    {
        $failedEvents = WebhookEvent::where('social_account_id', $socialAccountId)
            ->where('status', 'failed')
            ->where('retry_count', '<', 5)
            ->orderBy('updated_at', 'asc')
            ->limit($limit)
            ->get();

        $retried = 0;

        foreach ($failedEvents as $event) {
            try {
                $event->update([
                    'status' => 'pending',
                    'error_message' => null,
                ]);

                $this->queueEvent($event);
                $retried++;
            } catch (\Exception $e) {
                Log::error('Failed to retry webhook event', [
                    'webhook_event_id' => $event->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $retried;
    }

    /**
     * Clean up old processed events.
     */
    public function cleanupOldEvents(int $days = 30): int
    {
        $cutoffDate = now()->subDays($days);
        
        $deleted = WebhookEvent::where('status', 'processed')
            ->where('processed_at', '<', $cutoffDate)
            ->delete();

        Log::info('Cleaned up old webhook events', [
            'days' => $days,
            'deleted_count' => $deleted,
        ]);

        return $deleted;
    }

    /**
     * Calculate average processing time for events.
     */
    private function calculateAverageProcessingTime(int $socialAccountId): float
    {
        $events = WebhookEvent::where('social_account_id', $socialAccountId)
            ->where('status', 'processed')
            ->whereNotNull('received_at')
            ->whereNotNull('processed_at')
            ->get();

        if ($events->isEmpty()) {
            return 0.0;
        }

        $totalTime = $events->sum(function ($event) {
            return $event->processed_at->diffInSeconds($event->received_at);
        });

        return round($totalTime / $events->count(), 2);
    }

    /**
     * Calculate success rate for event processing.
     */
    private function calculateSuccessRate(int $socialAccountId): float
    {
        $totalEvents = WebhookEvent::where('social_account_id', $socialAccountId)->count();
        
        if ($totalEvents === 0) {
            return 100.0;
        }

        $successfulEvents = WebhookEvent::where('social_account_id', $socialAccountId)
            ->whereIn('status', ['processed', 'ignored'])
            ->count();

        return round(($successfulEvents / $totalEvents) * 100, 2);
    }
}