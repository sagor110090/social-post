<?php

namespace App\Jobs;

use App\Models\WebhookEvent;
use App\Services\Webhooks\WebhookEventProcessingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Exception;

class ProcessWebhookEventJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The number of seconds to wait before retrying.
     */
    public int $backoff = [5, 15, 30];

    /**
     * The webhook event instance.
     */
    public WebhookEvent $webhookEvent;

    /**
     * Create a new job instance.
     */
    public function __construct(WebhookEvent $webhookEvent)
    {
        $this->webhookEvent = $webhookEvent;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            $processingService = app(WebhookEventProcessingService::class);
            $processingService->processEvent($this->webhookEvent);

        } catch (Exception $e) {
            Log::error('Webhook event processing job failed', [
                'webhook_event_id' => $this->webhookEvent->id,
                'platform' => $this->webhookEvent->platform,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }


}