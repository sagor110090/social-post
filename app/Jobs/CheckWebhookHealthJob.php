<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use App\Services\Webhooks\WebhookMonitoringService;
use App\Services\Webhooks\WebhookLoggingService;

class CheckWebhookHealthJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 60;

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        $this->onQueue('monitoring');
    }

    /**
     * Execute the job.
     */
    public function handle(WebhookMonitoringService $monitoring, WebhookLoggingService $logger): void
    {
        try {
            $results = $monitoring->runHealthChecks();
            
            // Cache results for alert evaluation
            Cache::put('webhook_health_results', $results, 300); // 5 minutes
            
            // Log health check completion
            $logger->logHealthCheck('health_check_batch', true, [
                'checks_run' => count($results),
                'healthy_count' => collect($results)->where('status', 'healthy')->count(),
                'unhealthy_count' => collect($results)->where('status', '!=', 'healthy')->count(),
            ]);
            
        } catch (\Throwable $e) {
            $logger->logSecurityEvent('health_check_error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
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
        $logger->logSecurityEvent('health_check_job_failed', [
            'error' => $exception->getMessage(),
        ], 'error');
    }
}