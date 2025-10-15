<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Services\Webhooks\WebhookLoggingService;
use Symfony\Component\HttpFoundation\Response;

class RequestLoggingMiddleware
{
    private WebhookLoggingService $logger;

    public function __construct(WebhookLoggingService $logger)
    {
        $this->logger = $logger;
    }

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Only log webhook requests
        if (!$this->isWebhookRequest($request)) {
            return $next($request);
        }

        $startTime = microtime(true);
        
        // Extract platform from route
        $platform = $this->extractPlatform($request);
        
        // Set correlation IDs from headers or generate new ones
        $requestId = $request->header('X-Request-ID') ?: (string) \Str::uuid();
        $traceId = $request->header('X-Trace-ID') ?: (string) \Str::uuid();
        
        $this->logger->setCorrelationIds($requestId, $traceId);
        
        // Log incoming request
        $this->logIncomingRequest($request, $platform);
        
        // Process the request
        $response = $next($request);
        
        // Calculate processing time
        $processingTime = (microtime(true) - $startTime) * 1000;
        
        // Log response
        $this->logResponse($request, $response, $platform, $processingTime);
        
        // Add correlation headers to response
        $response->headers->set('X-Request-ID', $requestId);
        $response->headers->set('X-Trace-ID', $traceId);
        
        return $response;
    }

    /**
     * Check if this is a webhook request.
     */
    private function isWebhookRequest(Request $request): bool
    {
        $path = $request->path();
        
        return str_starts_with($path, 'webhooks/') || 
               str_starts_with($path, 'api/webhooks/') ||
               $request->header('X-Webhook-Event') ||
               $request->header('X-Hub-Signature');
    }

    /**
     * Extract platform from request.
     */
    private function extractPlatform(Request $request): ?string
    {
        // Try to get from route
        $route = $request->route();
        if ($route && $route->hasParameter('platform')) {
            return $route->parameter('platform');
        }
        
        // Try to get from path
        $path = $request->path();
        if (preg_match('/webhooks\/([a-z]+)/', $path, $matches)) {
            return $matches[1];
        }
        
        // Try to detect from headers or payload
        if ($request->header('X-Hub-Signature-256')) {
            return 'facebook'; // Facebook/Instagram use this header
        }
        
        if ($request->header('X-Twitter-Webhooks-Signature')) {
            return 'twitter';
        }
        
        if ($request->header('X-LI-Signature')) {
            return 'linkedin';
        }
        
        return null;
    }

    /**
     * Log incoming request.
     */
    private function logIncomingRequest(Request $request, ?string $platform): void
    {
        $context = [
            'platform' => $platform,
            'method' => $request->method(),
            'url' => $request->fullUrl(),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'request_size' => strlen($request->getContent()),
            'headers' => $this->sanitizeHeaders($request->headers->all()),
        ];

        // Add event type if detectable
        $eventType = $this->extractEventType($request, $platform);
        if ($eventType) {
            $context['event_type'] = $eventType;
        }

        $this->logger->logIncomingEvent($request, $platform ?? 'unknown');
    }

    /**
     * Log response.
     */
    private function logResponse(Request $request, Response $response, ?string $platform, float $processingTime): void
    {
        $context = [
            'platform' => $platform,
            'method' => $request->method(),
            'status_code' => $response->getStatusCode(),
            'response_size' => strlen($response->getContent()),
            'processing_time' => $processingTime,
        ];

        // Determine log level based on status code
        $level = $this->getLogLevel($response->getStatusCode());
        
        $message = "Webhook request processed: {$request->method()} {$request->path()} - {$response->getStatusCode()} ({$processingTime}ms)";
        
        \Log::channel('webhook-events')->log($level, $message, array_merge($context, $this->logger->getPerformanceContext()));
        
        // Record metrics
        if ($platform) {
            $this->recordMetrics($platform, $response->getStatusCode(), $processingTime);
        }
    }

    /**
     * Extract event type from request.
     */
    private function extractEventType(Request $request, ?string $platform): ?string
    {
        if (!$platform) {
            return null;
        }

        $payload = $request->json()->all();
        
        return match ($platform) {
            'facebook', 'instagram' => $payload['entry'][0]['changes'][0]['field'] ?? null,
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
     * Get log level based on status code.
     */
    private function getLogLevel(int $statusCode): string
    {
        return match (true) {
            $statusCode >= 500 => 'error',
            $statusCode >= 400 => 'warning',
            $statusCode >= 300 => 'info',
            default => 'debug',
        };
    }

    /**
     * Record metrics for the request.
     */
    private function recordMetrics(string $platform, int $statusCode, float $processingTime): void
    {
        // Record request volume
        $status = $statusCode < 400 ? 'success' : 'error';
        \App\Jobs\ProcessWebhookMetricsJob::dispatch(
            'request_volume',
            ['platform' => $platform, 'status' => $status],
            1,
            ['metric_type' => 'counter']
        );
        
        // Record response time
        \App\Jobs\ProcessWebhookMetricsJob::dispatch(
            'response_times',
            ['platform' => $platform],
            $processingTime,
            ['metric_type' => 'histogram']
        );
        
        // Record errors if applicable
        if ($statusCode >= 400) {
            $errorType = $statusCode >= 500 ? 'server_error' : 'client_error';
            \App\Jobs\ProcessWebhookMetricsJob::dispatch(
                'error_rates',
                ['platform' => $platform, 'error_type' => $errorType],
                1,
                ['metric_type' => 'counter']
            );
        }
    }
}