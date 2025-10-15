<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Services\Webhooks\WebhookMetricsService;
use App\Services\Webhooks\WebhookLoggingService;

class ProcessWebhookMetricsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public string $type;
    public array $dimensions;
    public float $value;
    public array $tags;

    public int $tries = 3;
    public int $backoff = [5, 15, 30];

    /**
     * Create a new job instance.
     */
    public function __construct(string $type, array $dimensions, float $value, array $tags = [])
    {
        $this->type = $type;
        $this->dimensions = $dimensions;
        $this->value = $value;
        $this->tags = $tags;
        
        $this->onQueue('metrics');
    }

    /**
     * Execute the job.
     */
    public function handle(WebhookMetricsService $metrics, WebhookLoggingService $logger): void
    {
        try {
            $metrics->recordMetric($this->type, $this->dimensions, $this->value, $this->tags);
        } catch (\Throwable $e) {
            $logger->logSecurityEvent('metrics_processing_error', [
                'type' => $this->type,
                'dimensions' => $this->dimensions,
                'value' => $this->value,
                'error' => $e->getMessage(),
            ], 'error');
            
            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        $logger = app(WebhookLoggingService::class);
        $logger->logSecurityEvent('metrics_job_failed', [
            'type' => $this->type,
            'dimensions' => $this->dimensions,
            'error' => $exception->getMessage(),
        ], 'error');
    }
}