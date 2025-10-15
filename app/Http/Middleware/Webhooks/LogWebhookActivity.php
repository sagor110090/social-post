<?php

namespace App\Http\Middleware\Webhooks;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use App\Models\WebhookDeliveryMetric;
use Symfony\Component\HttpFoundation\Response;

class LogWebhookActivity
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $startTime = microtime(true);
        
        // Generate unique request ID
        $requestId = $this->generateRequestId();
        $request->attributes->set('webhook_request_id', $requestId);
        
        // Log incoming request
        $this->logIncomingRequest($request, $requestId);
        
        // Process request
        $response = $next($request);
        
        // Calculate processing time
        $processingTime = microtime(true) - $startTime;
        $request->attributes->set('webhook_processing_time', $processingTime);
        
        // Log response
        $this->logResponse($request, $response, $requestId, $processingTime);
        
        // Record metrics
        $this->recordMetrics($request, $response, $processingTime);
        
        // Check for suspicious activity
        $this->checkSuspiciousActivity($request, $response, $processingTime);
        
        return $response;
    }

    /**
     * Generate unique request ID.
     */
    protected function generateRequestId(): string
    {
        return uniqid('wh_', true) . '_' . bin2hex(random_bytes(4));
    }

    /**
     * Log incoming webhook request.
     */
    protected function logIncomingRequest(Request $request, string $requestId): void
    {
        if (!config('webhooks.security.logging.enabled', true)) {
            return;
        }

        if (!config('webhooks.security.logging.log_all_requests', false) && 
            !config('webhooks.security.logging.log_failed_requests', true)) {
            return;
        }

        $logData = [
            'request_id' => $requestId,
            'method' => $request->method(),
            'path' => $request->path(),
            'ip' => $this->getClientIp($request),
            'user_agent' => $request->userAgent(),
            'content_type' => $request->header('Content-Type'),
            'content_length' => $request->header('Content-Length'),
            'payload_size' => strlen($request->getContent()),
            'platform' => $this->extractPlatformFromRequest($request),
            'headers' => $this->sanitizeHeaders($request->headers->all()),
            'timestamp' => now()->toISOString(),
        ];

        // Add signature information
        $this->addSignatureInfo($request, $logData);

        // Add rate limit information
        $this->addRateLimitInfo($request, $logData);

        // Add IP whitelist information
        $this->addIpWhitelistInfo($request, $logData);

        $logLevel = config('webhooks.security.logging.log_level', 'info');
        
        Log::log($logLevel, 'Webhook request received', $logData);
    }

    /**
     * Log webhook response.
     */
    protected function logResponse(Request $request, Response $response, string $requestId, float $processingTime): void
    {
        if (!config('webhooks.security.logging.enabled', true)) {
            return;
        }

        $logData = [
            'request_id' => $requestId,
            'status_code' => $response->getStatusCode(),
            'processing_time_ms' => round($processingTime * 1000, 2),
            'response_size' => strlen($response->getContent()),
            'platform' => $this->extractPlatformFromRequest($request),
            'timestamp' => now()->toISOString(),
        ];

        // Add response headers (sanitized)
        $logData['response_headers'] = $this->sanitizeHeaders($response->headers->all());

        // Determine if this was a successful response
        $isSuccess = $response->isSuccessful();
        $logLevel = $isSuccess ? 'info' : 'warning';

        // Log failures with more detail
        if (!$isSuccess) {
            $logData['error_details'] = [
                'status_code' => $response->getStatusCode(),
                'reason_phrase' => Response::$statusTexts[$response->getStatusCode()] ?? 'Unknown',
            ];
            
            if ($response->headers->has('X-Error-Message')) {
                $logData['error_details']['message'] = $response->headers->get('X-Error-Message');
            }
        }

        Log::log($logLevel, 'Webhook response sent', $logData);
    }

    /**
     * Record webhook metrics.
     */
    protected function recordMetrics(Request $request, Response $response, float $processingTime): void
    {
        $platform = $this->extractPlatformFromRequest($request);
        $config = $request->attributes->get('webhook_config');
        
        if (!$config) {
            return;
        }

        try {
            WebhookDeliveryMetric::create([
                'webhook_config_id' => $config->id,
                'status' => $response->isSuccessful() ? 'delivered' : 'failed',
                'http_status_code' => $response->getStatusCode(),
                'processing_time_ms' => round($processingTime * 1000, 2),
                'payload_size_bytes' => strlen($request->getContent()),
                'response_size_bytes' => strlen($response->getContent()),
                'delivered_at' => now(),
                'metadata' => [
                    'request_id' => $request->attributes->get('webhook_request_id'),
                    'platform' => $platform,
                    'ip' => $this->getClientIp($request),
                    'user_agent' => $request->userAgent(),
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to record webhook metrics', [
                'error' => $e->getMessage(),
                'config_id' => $config->id,
                'request_id' => $request->attributes->get('webhook_request_id'),
            ]);
        }
    }

    /**
     * Check for suspicious activity.
     */
    protected function checkSuspiciousActivity(Request $request, Response $response, float $processingTime): void
    {
        $suspiciousIndicators = [];

        // Check for unusual processing time
        if ($processingTime > 5.0) { // 5 seconds
            $suspiciousIndicators[] = 'slow_processing';
        }

        // Check for unusual payload size
        $payloadSize = strlen($request->getContent());
        if ($payloadSize > 500000) { // 500KB
            $suspiciousIndicators[] = 'large_payload';
        }

        // Check for failed authentication
        if ($response->getStatusCode() === 401) {
            $suspiciousIndicators[] = 'auth_failure';
        }

        // Check for rate limiting
        if ($response->getStatusCode() === 429) {
            $suspiciousIndicators[] = 'rate_limited';
        }

        // Check for validation errors
        if ($response->getStatusCode() === 422) {
            $suspiciousIndicators[] = 'validation_error';
        }

        // Check for suspicious user agents
        if ($this->isSuspiciousUserAgent($request->userAgent())) {
            $suspiciousIndicators[] = 'suspicious_user_agent';
        }

        // Check for unusual request patterns
        if ($this->isUnusualRequestPattern($request)) {
            $suspiciousIndicators[] = 'unusual_pattern';
        }

        if (!empty($suspiciousIndicators)) {
            $this->logSuspiciousActivity($request, $response, $suspiciousIndicators, $processingTime);
        }
    }

    /**
     * Log suspicious activity.
     */
    protected function logSuspiciousActivity(Request $request, Response $response, array $indicators, float $processingTime): void
    {
        Log::warning('Suspicious webhook activity detected', [
            'request_id' => $request->attributes->get('webhook_request_id'),
            'indicators' => $indicators,
            'platform' => $this->extractPlatformFromRequest($request),
            'ip' => $this->getClientIp($request),
            'user_agent' => $request->userAgent(),
            'status_code' => $response->getStatusCode(),
            'processing_time_ms' => round($processingTime * 1000, 2),
            'payload_size' => strlen($request->getContent()),
            'timestamp' => now()->toISOString(),
        ]);

        // Trigger security alert if needed
        $this->triggerSuspiciousActivityAlert($request, $indicators);
    }

    /**
     * Trigger suspicious activity alert.
     */
    protected function triggerSuspiciousActivityAlert(Request $request, array $indicators): void
    {
        if (!config('webhooks.security.alerting.enabled', false)) {
            return;
        }

        $cacheKey = 'suspicious_activity:' . md5($this->getClientIp($request) . ':' . implode(',', $indicators));
        $window = 300; // 5 minutes
        
        $count = cache()->increment($cacheKey, 1, $window);
        
        // Alert if this is the first occurrence or if it's happening frequently
        if ($count === 1 || $count > 5) {
            Log::critical('Suspicious activity alert triggered', [
                'request_id' => $request->attributes->get('webhook_request_id'),
                'indicators' => $indicators,
                'ip' => $this->getClientIp($request),
                'count' => $count,
                'alert_type' => 'suspicious_activity',
                'timestamp' => now()->toISOString(),
            ]);
        }
    }

    /**
     * Get client IP address.
     */
    protected function getClientIp(Request $request): string
    {
        return $request->header('CF-Connecting-IP') 
            ?? $request->header('X-Forwarded-For')
            ?? $request->header('X-Real-IP')
            ?? $request->ip();
    }

    /**
     * Extract platform from request path.
     */
    protected function extractPlatformFromRequest(Request $request): ?string
    {
        $path = $request->path();
        
        if (preg_match('/webhooks\/([a-z]+)/', $path, $matches)) {
            return $matches[1];
        }

        return null;
    }

    /**
     * Sanitize headers for logging.
     */
    protected function sanitizeHeaders(array $headers): array
    {
        $sensitiveHeaders = [
            'authorization',
            'x-api-key',
            'x-webhook-signature',
            'x-hub-signature',
            'x-hub-signature-256',
            'x-li-signature',
            'x-twitter-webhooks-signature',
            'cookie',
            'set-cookie',
        ];

        $sanitized = [];
        
        foreach ($headers as $name => $values) {
            $lowerName = strtolower($name);
            
            if (in_array($lowerName, $sensitiveHeaders)) {
                $sanitized[$name] = ['[REDACTED]'];
            } else {
                $sanitized[$name] = $values;
            }
        }

        return $sanitized;
    }

    /**
     * Add signature information to log data.
     */
    protected function addSignatureInfo(Request $request, array &$logData): void
    {
        $signatureHeaders = [
            'X-Hub-Signature-256',
            'X-Hub-Signature',
            'X-LI-Signature',
            'X-Twitter-Webhooks-Signature',
        ];

        foreach ($signatureHeaders as $header) {
            if ($request->hasHeader($header)) {
                $logData['signature_headers'][$header] = 'PRESENT';
            }
        }
    }

    /**
     * Add rate limit information to log data.
     */
    protected function addRateLimitInfo(Request $request, array &$logData): void
    {
        $rateLimitHeaders = [
            'X-RateLimit-Limit',
            'X-RateLimit-Remaining',
            'X-RateLimit-Reset',
        ];

        foreach ($rateLimitHeaders as $header) {
            if ($request->hasHeader($header)) {
                $logData['rate_limit_info'][$header] = $request->header($header);
            }
        }
    }

    /**
     * Add IP whitelist information to log data.
     */
    protected function addIpWhitelistInfo(Request $request, array &$logData): void
    {
        $logData['ip_whitelist_info'] = [
            'ip' => $this->getClientIp($request),
            'whitelist_enabled' => config('webhooks.security.ip_whitelist.enabled', true),
            'strict_mode' => config('webhooks.security.ip_whitelist.strict_mode', false),
        ];
    }

    /**
     * Check if user agent is suspicious.
     */
    protected function isSuspiciousUserAgent(?string $userAgent): bool
    {
        if (!$userAgent) {
            return true;
        }

        $suspiciousPatterns = [
            '/bot/i',
            '/crawler/i',
            '/scanner/i',
            '/curl/i',
            '/wget/i',
            '/python/i',
            '/perl/i',
            '/java/i',
            '/go-http/i',
            '/postman/i',
            '/insomnia/i',
        ];

        foreach ($suspiciousPatterns as $pattern) {
            if (preg_match($pattern, $userAgent)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check for unusual request patterns.
     */
    protected function isUnusualRequestPattern(Request $request): bool
    {
        // Check for missing common headers
        $commonHeaders = ['User-Agent', 'Accept', 'Content-Type'];
        $missingHeaders = 0;
        
        foreach ($commonHeaders as $header) {
            if (!$request->hasHeader($header)) {
                $missingHeaders++;
            }
        }

        if ($missingHeaders >= 2) {
            return true;
        }

        // Check for unusual header combinations
        $hasAuth = $request->hasHeader('Authorization') || 
                  $request->hasHeader('X-API-Key');
        $hasSignature = $request->hasHeader('X-Hub-Signature') || 
                       $request->hasHeader('X-LI-Signature') ||
                       $request->hasHeader('X-Twitter-Webhooks-Signature');

        // Webhook should have signature but not necessarily auth
        if (!$hasSignature && $request->method() === 'POST') {
            return true;
        }

        return false;
    }

    /**
     * Clean up old log entries.
     */
    public function cleanupOldLogs(): void
    {
        $retentionDays = config('webhooks.security.logging.retention_days', 30);
        $cutoffDate = now()->subDays($retentionDays);

        try {
            // Clean up old delivery metrics
            WebhookDeliveryMetric::where('delivered_at', '<', $cutoffDate)->delete();
            
            Log::info('Cleaned up old webhook logs', [
                'cutoff_date' => $cutoffDate->toISOString(),
                'retention_days' => $retentionDays,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to cleanup old webhook logs', [
                'error' => $e->getMessage(),
            ]);
        }
    }
}