<?php

namespace App\Jobs;

use App\Services\Webhooks\WebhookSecurityService;
use App\Http\Middleware\Webhooks\WebhookRateLimiting;
use App\Http\Middleware\Webhooks\LogWebhookActivity;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class CleanupWebhookSecurityDataJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public $tries = 3;

    /**
     * The maximum number of seconds the job can run.
     */
    public $timeout = 300; // 5 minutes

    /**
     * Create a new job instance.
     */
    public function __construct(
        public int $retentionDays = 30
    ) {
        $this->onQueue('webhooks');
    }

    /**
     * Execute the job.
     */
    public function handle(WebhookSecurityService $securityService): void
    {
        Log::info('Starting webhook security data cleanup', [
            'retention_days' => $this->retentionDays,
            'job_id' => $this->job->getJobId(),
        ]);

        try {
            // Clean up old delivery metrics
            $this->cleanupDeliveryMetrics();
            
            // Clean up old rate limit keys
            $this->cleanupRateLimitKeys();
            
            // Clean up old security violation records
            $this->cleanupSecurityViolations();
            
            // Clean up old replay protection keys
            $this->cleanupReplayProtectionKeys();
            
            // Clean up blocked IPs that have expired
            $this->cleanupExpiredBlockedIps();
            
            Log::info('Webhook security data cleanup completed', [
                'retention_days' => $this->retentionDays,
                'job_id' => $this->job->getJobId(),
            ]);
            
        } catch (\Exception $e) {
            Log::error('Webhook security data cleanup failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'retention_days' => $this->retentionDays,
                'job_id' => $this->job->getJobId(),
            ]);
            
            throw $e;
        }
    }

    /**
     * Clean up old delivery metrics.
     */
    protected function cleanupDeliveryMetrics(): void
    {
        $cutoffDate = now()->subDays($this->retentionDays);
        
        $deleted = \App\Models\WebhookDeliveryMetric::where('delivered_at', '<', $cutoffDate)->delete();
        
        Log::info('Cleaned up old delivery metrics', [
            'deleted_count' => $deleted,
            'cutoff_date' => $cutoffDate->toISOString(),
        ]);
    }

    /**
     * Clean up old rate limit keys.
     */
    protected function cleanupRateLimitKeys(): void
    {
        $rateLimiting = new WebhookRateLimiting();
        $rateLimiting->cleanupExpiredKeys();
        
        Log::info('Cleaned up expired rate limit keys');
    }

    /**
     * Clean up old security violation records.
     */
    protected function cleanupSecurityViolations(): void
    {
        // Get all security violation keys
        $redis = \Illuminate\Support\Facades\Redis::connection();
        $keys = $redis->keys('security_violation:*');
        
        $cleaned = 0;
        foreach ($keys as $key) {
            $ttl = $redis->ttl($key);
            
            // Remove keys that have expired or have no TTL
            if ($ttl <= 0) {
                $redis->del($key);
                $cleaned++;
            }
        }
        
        Log::info('Cleaned up expired security violation keys', [
            'keys_cleaned' => $cleaned,
            'total_keys' => count($keys),
        ]);
    }

    /**
     * Clean up old replay protection keys.
     */
    protected function cleanupReplayProtectionKeys(): void
    {
        $redis = \Illuminate\Support\Facades\Redis::connection();
        $keys = $redis->keys('webhook_replay:*');
        
        $cleaned = 0;
        foreach ($keys as $key) {
            $ttl = $redis->ttl($key);
            
            // Remove keys that have expired or have no TTL
            if ($ttl <= 0) {
                $redis->del($key);
                $cleaned++;
            }
        }
        
        Log::info('Cleaned up expired replay protection keys', [
            'keys_cleaned' => $cleaned,
            'total_keys' => count($keys),
        ]);
    }

    /**
     * Clean up expired blocked IPs.
     */
    protected function cleanupExpiredBlockedIps(): void
    {
        $redis = \Illuminate\Support\Facades\Redis::connection();
        $keys = $redis->keys('blocked_ip:*');
        
        $cleaned = 0;
        foreach ($keys as $key) {
            $ttl = $redis->ttl($key);
            
            // Remove keys that have expired
            if ($ttl <= 0) {
                $ip = str_replace('blocked_ip:', '', $key);
                $redis->del($key);
                $cleaned++;
                
                Log::info('Removed expired IP block', [
                    'ip' => $ip,
                ]);
            }
        }
        
        Log::info('Cleaned up expired blocked IP keys', [
            'keys_cleaned' => $cleaned,
            'total_keys' => count($keys),
        ]);
    }

    /**
     * Get the tags that should be assigned to the job.
     */
    public function tags(): array
    {
        return ['webhook', 'security', 'cleanup'];
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('Webhook security cleanup job failed', [
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
            'retention_days' => $this->retentionDays,
            'job_id' => $this->job->getJobId(),
        ]);
    }
}