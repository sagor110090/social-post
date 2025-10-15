<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Services\Webhooks\WebhookAlertingService;
use App\Services\Webhooks\WebhookLoggingService;

class SendWebhookAlertJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public array $alert;

    public int $tries = 3;
    public int $backoff = [5, 15, 30];
    public int $timeout = 30;

    /**
     * Create a new job instance.
     */
    public function __construct(array $alert)
    {
        $this->alert = $alert;
        
        $this->onQueue('alerts');
    }

    /**
     * Execute the job.
     */
    public function handle(WebhookAlertingService $alerting, WebhookLoggingService $logger): void
    {
        try {
            $alerting->sendAlert($this->alert);
            
            $logger->logPerformanceMetrics([
                'operation' => 'alert_sent',
                'alert_rule' => $this->alert['rule'],
                'severity' => $this->alert['severity'],
                'timestamp' => now()->toISOString(),
            ]);
            
        } catch (\Throwable $e) {
            $logger->logSecurityEvent('alert_send_error', [
                'alert_rule' => $this->alert['rule'],
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
        $logger->logSecurityEvent('alert_job_failed', [
            'alert_rule' => $this->alert['rule'],
            'error' => $exception->getMessage(),
        ], 'error');
    }
}