<?php

namespace App\Services\Webhooks;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use App\Models\WebhookEvent;
use App\Models\WebhookEventProcessing;

class WebhookLoggingService
{
    private array $context = [];
    private ?string $requestId = null;
    private ?string $traceId = null;
    private float $startTime;

    public function __construct()
    {
        $this->startTime = microtime(true);
        $this->requestId = (string) Str::uuid();
        $this->traceId = (string) Str::uuid();
    }

    /**
     * Set correlation IDs for request tracing.
     */
    public function setCorrelationIds(?string $requestId = null, ?string $traceId = null): self
    {
        $this->requestId = $requestId ?? $this->requestId;
        $this->traceId = $traceId ?? $this->traceId;
        
        return $this;
    }

    /**
     * Set context data for logging.
     */
    public function setContext(array $context): self
    {
        $this->context = array_merge($this->context, $context);
        
        return $this;
    }

    /**
     * Log incoming webhook event.
     */
    public function logIncomingEvent(Request $request, string $platform): void
    {
        $context = array_merge($this->context, [
            'platform' => $platform,
            'event_type' => $this->extractEventType($request, $platform),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'request_size' => strlen($request->getContent()),
            'headers' => $this->sanitizeHeaders($request->headers->all()),
        ]);

        Log::channel('webhook-events')->info('Webhook event received', array_merge($context, $this->getPerformanceContext()));
    }

    /**
     * Log event processing start.
     */
    public function logProcessingStart(WebhookEvent $event): void
    {
        $context = array_merge($this->context, [
            'platform' => $event->platform,
            'event_type' => $event->event_type,
            'event_id' => $event->event_id,
            'webhook_event_id' => $event->id,
            'account_id' => $event->social_account_id,
            'retry_count' => $event->retry_count,
        ]);

        Log::channel('webhook-processing')->info('Event processing started', array_merge($context, $this->getPerformanceContext()));
    }

    /**
     * Log event processing success.
     */
    public function logProcessingSuccess(WebhookEvent $event, float $processingTime): void
    {
        $context = array_merge($this->context, [
            'platform' => $event->platform,
            'event_type' => $event->event_type,
            'event_id' => $event->event_id,
            'webhook_event_id' => $event->id,
            'account_id' => $event->social_account_id,
            'processing_time' => $processingTime,
        ]);

        Log::channel('webhook-processing')->info('Event processing completed successfully', array_merge($context, $this->getPerformanceContext()));
    }

    /**
     * Log event processing failure.
     */
    public function logProcessingFailure(WebhookEvent $event, \Throwable $exception, float $processingTime): void
    {
        $context = array_merge($this->context, [
            'platform' => $event->platform,
            'event_type' => $event->event_type,
            'event_id' => $event->event_id,
            'webhook_event_id' => $event->id,
            'account_id' => $event->social_account_id,
            'processing_time' => $processingTime,
            'error_type' => get_class($exception),
            'error_message' => $exception->getMessage(),
            'error_code' => $exception->getCode(),
        ]);

        Log::channel('webhook-processing')->error('Event processing failed', array_merge($context, $this->getPerformanceContext()));
        Log::channel('webhook-errors')->error('Webhook processing error', array_merge($context, $this->getPerformanceContext()));
    }

    /**
     * Log security event.
     */
    public function logSecurityEvent(string $event, array $data, string $level = 'warning'): void
    {
        $context = array_merge($this->context, $data, [
            'security_event' => $event,
            'timestamp' => now()->toISOString(),
        ]);

        Log::channel('webhook-security')->log($level, "Security event: {$event}", array_merge($context, $this->getPerformanceContext()));
    }

    /**
     * Log performance metrics.
     */
    public function logPerformanceMetrics(array $metrics): void
    {
        $context = array_merge($this->context, $metrics, [
            'timestamp' => now()->toISOString(),
        ]);

        Log::channel('webhook-performance')->info('Performance metrics', array_merge($context, $this->getPerformanceContext()));
    }

    /**
     * Log queue metrics.
     */
    public function logQueueMetrics(string $queue, array $metrics): void
    {
        $context = array_merge($this->context, [
            'queue' => $queue,
            'metrics' => $metrics,
            'timestamp' => now()->toISOString(),
        ]);

        Log::channel('webhook-performance')->info('Queue metrics', array_merge($context, $this->getPerformanceContext()));
    }

    /**
     * Log API call.
     */
    public function logApiCall(string $platform, string $method, string $endpoint, array $data = [], ?\Throwable $exception = null): void
    {
        $context = array_merge($this->context, [
            'platform' => $platform,
            'api_method' => $method,
            'api_endpoint' => $endpoint,
            'api_data' => $this->sanitizeApiData($data),
        ]);

        if ($exception) {
            $context['error'] = [
                'type' => get_class($exception),
                'message' => $exception->getMessage(),
                'code' => $exception->getCode(),
            ];
            Log::channel('webhook-errors')->error("API call failed: {$method} {$endpoint}", array_merge($context, $this->getPerformanceContext()));
        } else {
            Log::channel('webhook-processing')->info("API call: {$method} {$endpoint}", array_merge($context, $this->getPerformanceContext()));
        }
    }

    /**
     * Log webhook delivery metrics.
     */
    public function logDeliveryMetrics(WebhookEventProcessing $processing): void
    {
        $context = array_merge($this->context, [
            'platform' => $processing->webhookEvent->platform,
            'event_type' => $processing->webhookEvent->event_type,
            'processing_id' => $processing->id,
            'webhook_event_id' => $processing->webhook_event_id,
            'delivery_status' => $processing->status,
            'delivery_attempts' => $processing->attempts,
            'response_code' => $processing->response_code,
            'response_time' => $processing->response_time,
        ]);

        Log::channel('webhook-performance')->info('Webhook delivery metrics', array_merge($context, $this->getPerformanceContext()));
    }

    /**
     * Log system health check.
     */
    public function logHealthCheck(string $component, bool $status, array $metrics = []): void
    {
        $context = array_merge($this->context, [
            'component' => $component,
            'status' => $status ? 'healthy' : 'unhealthy',
            'metrics' => $metrics,
            'timestamp' => now()->toISOString(),
        ]);

        $level = $status ? 'info' : 'error';
        Log::channel('webhook-performance')->log($level, "Health check: {$component}", array_merge($context, $this->getPerformanceContext()));
    }

    /**
     * Get performance context data.
     */
    private function getPerformanceContext(): array
    {
        $memoryUsage = memory_get_usage(true);
        $memoryPeak = memory_get_peak_usage(true);
        $executionTime = (microtime(true) - $this->startTime) * 1000; // Convert to milliseconds

        return [
            'request_id' => $this->requestId,
            'trace_id' => $this->traceId,
            'memory_usage' => $memoryUsage,
            'memory_usage_mb' => round($memoryUsage / 1024 / 1024, 2),
            'memory_peak' => $memoryPeak,
            'memory_peak_mb' => round($memoryPeak / 1024 / 1024, 2),
            'execution_time' => $executionTime,
            'execution_time_ms' => round($executionTime, 2),
        ];
    }

    /**
     * Extract event type from request.
     */
    private function extractEventType(Request $request, string $platform): ?string
    {
        $payload = $request->json()->all();
        
        return match ($platform) {
            'facebook' => $payload['entry'][0]['changes'][0]['field'] ?? null,
            'instagram' => $payload['entry'][0]['changes'][0]['field'] ?? null,
            'twitter' => $payload['for_user_id'] ? 'tweet_event' : null,
            'linkedin' => $payload['event'] ?? null,
            default => null,
        };
    }

    /**
     * Sanitize headers for logging.
     */
    private function sanitizeHeaders(array $headers): array
    {
        $sensitiveHeaders = ['authorization', 'x-signature', 'x-hub-signature', 'x-twitter-webhooks-signature'];
        
        return array_map(function ($values) use ($sensitiveHeaders) {
            return array_map(function ($value) use ($sensitiveHeaders) {
                foreach ($sensitiveHeaders as $header) {
                    if (str_contains(strtolower($value), strtolower($header))) {
                        return '[REDACTED]';
                    }
                }
                return $value;
            }, $values);
        }, $headers);
    }

    /**
     * Sanitize API data for logging.
     */
    private function sanitizeApiData(array $data): array
    {
        $sensitiveKeys = ['access_token', 'secret', 'password', 'key', 'token'];
        
        $sanitize = function ($value) use ($sensitiveKeys, &$sanitize) {
            if (is_array($value)) {
                return array_map($sanitize, $value);
            }
            
            if (is_string($value)) {
                foreach ($sensitiveKeys as $key) {
                    if (str_contains(strtolower($value), $key)) {
                        return '[REDACTED]';
                    }
                }
            }
            
            return $value;
        };
        
        return $sanitize($data);
    }

    /**
     * Get current request ID.
     */
    public function getRequestId(): ?string
    {
        return $this->requestId;
    }

    /**
     * Get current trace ID.
     */
    public function getTraceId(): ?string
    {
        return $this->traceId;
    }
}