<?php

namespace App\Http\Middleware\Webhooks;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class WebhookRateLimiting
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $platform = $this->extractPlatformFromRequest($request);
        $clientIp = $this->getClientIp($request);
        
        if (!$platform) {
            return $next($request);
        }

        // Get rate limits for this platform
        $rateLimits = $this->getRateLimits($platform);
        
        // Check various rate limits
        $violations = [];
        
        // Per-minute rate limit
        if (!$this->checkRateLimit($clientIp, $platform, 'minute', $rateLimits['requests_per_minute'])) {
            $violations[] = 'minute';
        }
        
        // Per-hour rate limit
        if (!$this->checkRateLimit($clientIp, $platform, 'hour', $rateLimits['requests_per_hour'])) {
            $violations[] = 'hour';
        }
        
        // Burst rate limit
        if (!$this->checkBurstLimit($clientIp, $platform, $rateLimits['burst_limit'])) {
            $violations[] = 'burst';
        }

        // Global rate limit (across all platforms)
        if (!$this->checkGlobalRateLimit($clientIp)) {
            $violations[] = 'global';
        }

        if (!empty($violations)) {
            $this->handleRateLimitViolation($request, $platform, $clientIp, $violations);
            return $this->rateLimitResponse($violations, $rateLimits);
        }

        // Add rate limit headers to response
        $response = $next($request);
        $this->addRateLimitHeaders($response, $clientIp, $platform, $rateLimits);

        return $response;
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
     * Get client IP address considering proxies.
     */
    protected function getClientIp(Request $request): string
    {
        return $request->header('X-Forwarded-For') 
            ? explode(',', $request->header('X-Forwarded-For'))[0]
            : $request->ip();
    }

    /**
     * Get rate limits for a platform.
     */
    protected function getRateLimits(string $platform): array
    {
        $limits = config("webhooks.security.rate_limits.{$platform}");
        
        if (!$limits) {
            $limits = config('webhooks.security.rate_limits.default');
        }

        return $limits;
    }

    /**
     * Check rate limit for a specific time window.
     */
    protected function checkRateLimit(string $clientIp, string $platform, string $window, int $limit): bool
    {
        $key = "webhook_rate_limit:{$platform}:{$window}:{$clientIp}";
        $ttl = $window === 'minute' ? 60 : 3600; // 1 minute or 1 hour
        
        $current = $this->incrementCounter($key, $ttl);
        
        return $current <= $limit;
    }

    /**
     * Check burst rate limit using sliding window.
     */
    protected function checkBurstLimit(string $clientIp, string $platform, int $limit): bool
    {
        $key = "webhook_burst:{$platform}:{$clientIp}";
        $window = 10; // 10 second window for burst detection
        $ttl = $window;
        
        $current = $this->incrementCounter($key, $ttl);
        
        return $current <= $limit;
    }

    /**
     * Check global rate limit across all platforms.
     */
    protected function checkGlobalRateLimit(string $clientIp): bool
    {
        $key = "webhook_global:{$clientIp}";
        $limit = config('webhooks.security.rate_limits.default.requests_per_minute', 60);
        $ttl = 60;
        
        $current = $this->incrementCounter($key, $ttl);
        
        return $current <= $limit;
    }

    /**
     * Increment counter with TTL using Redis.
     */
    protected function incrementCounter(string $key, int $ttl): int
    {
        $redis = Redis::connection();
        
        // Use Redis pipeline for atomic operations
        $result = $redis->pipeline(function ($pipe) use ($key, $ttl) {
            $pipe->incr($key);
            $pipe->expire($key, $ttl);
        });
        
        return $result[0];
    }

    /**
     * Get current rate limit status.
     */
    protected function getRateLimitStatus(string $clientIp, string $platform): array
    {
        $rateLimits = $this->getRateLimits($platform);
        
        return [
            'minute' => [
                'limit' => $rateLimits['requests_per_minute'],
                'remaining' => max(0, $rateLimits['requests_per_minute'] - $this->getCurrentCount($clientIp, $platform, 'minute')),
                'reset' => $this->getResetTime($clientIp, $platform, 'minute'),
            ],
            'hour' => [
                'limit' => $rateLimits['requests_per_hour'],
                'remaining' => max(0, $rateLimits['requests_per_hour'] - $this->getCurrentCount($clientIp, $platform, 'hour')),
                'reset' => $this->getResetTime($clientIp, $platform, 'hour'),
            ],
        ];
    }

    /**
     * Get current count for a rate limit window.
     */
    protected function getCurrentCount(string $clientIp, string $platform, string $window): int
    {
        $key = "webhook_rate_limit:{$platform}:{$window}:{$clientIp}";
        
        try {
            return (int) Redis::connection()->get($key) ?: 0;
        } catch (\Exception $e) {
            Log::error('Failed to get rate limit count', [
                'key' => $key,
                'error' => $e->getMessage(),
            ]);
            return 0;
        }
    }

    /**
     * Get reset time for a rate limit window.
     */
    protected function getResetTime(string $clientIp, string $platform, string $window): int
    {
        $key = "webhook_rate_limit:{$platform}:{$window}:{$clientIp}";
        
        try {
            $ttl = Redis::connection()->ttl($key);
            return $ttl > 0 ? time() + $ttl : time() + ($window === 'minute' ? 60 : 3600);
        } catch (\Exception $e) {
            Log::error('Failed to get rate limit reset time', [
                'key' => $key,
                'error' => $e->getMessage(),
            ]);
            return time() + ($window === 'minute' ? 60 : 3600);
        }
    }

    /**
     * Add rate limit headers to response.
     */
    protected function addRateLimitHeaders(Response $response, string $clientIp, string $platform, array $rateLimits): void
    {
        $status = $this->getRateLimitStatus($clientIp, $platform);
        
        $response->headers->set('X-RateLimit-Limit', $rateLimits['requests_per_minute']);
        $response->headers->set('X-RateLimit-Remaining', $status['minute']['remaining']);
        $response->headers->set('X-RateLimit-Reset', $status['minute']['reset']);
        $response->headers->set('X-RateLimit-Hour-Limit', $rateLimits['requests_per_hour']);
        $response->headers->set('X-RateLimit-Hour-Remaining', $status['hour']['remaining']);
        $response->headers->set('X-RateLimit-Hour-Reset', $status['hour']['reset']);
    }

    /**
     * Handle rate limit violation.
     */
    protected function handleRateLimitViolation(Request $request, string $platform, string $clientIp, array $violations): void
    {
        if (!config('webhooks.security.logging.log_rate_limit_violations', true)) {
            return;
        }

        Log::warning('Webhook rate limit violation', [
            'platform' => $platform,
            'client_ip' => $clientIp,
            'violations' => $violations,
            'user_agent' => $request->userAgent(),
            'path' => $request->path(),
            'method' => $request->method(),
        ]);

        // Record security violation
        $this->recordRateLimitViolation($platform, $clientIp, $violations);
    }

    /**
     * Record rate limit violation for monitoring.
     */
    protected function recordRateLimitViolation(string $platform, string $clientIp, array $violations): void
    {
        foreach ($violations as $type) {
            $cacheKey = "rate_limit_violation:{$platform}:{$type}:" . md5($clientIp);
            $window = 60; // 1 minute window
            
            $count = Redis::connection()->incrby($cacheKey, 1);
            Redis::connection()->expire($cacheKey, $window);
            
            // Check if we should trigger an alert
            $thresholds = config('webhooks.security.alerting.thresholds', []);
            $thresholdKey = 'rate_limit_violations_per_minute';
            
            if (isset($thresholds[$thresholdKey]) && $count >= $thresholds[$thresholdKey]) {
                $this->triggerRateLimitAlert($platform, $clientIp, $type, $count);
            }
        }
    }

    /**
     * Trigger rate limit alert.
     */
    protected function triggerRateLimitAlert(string $platform, string $clientIp, string $type, int $count): void
    {
        if (!config('webhooks.security.alerting.enabled', false)) {
            return;
        }

        Log::critical("Rate limit alert triggered", [
            'platform' => $platform,
            'client_ip' => $clientIp,
            'violation_type' => $type,
            'count' => $count,
            'alert_type' => 'rate_limit',
            'timestamp' => now()->toISOString(),
        ]);
    }

    /**
     * Return rate limit response.
     */
    protected function rateLimitResponse(array $violations, array $rateLimits): Response
    {
        $status = 429; // Too Many Requests
        $retryAfter = $this->calculateRetryAfter($violations);
        
        $response = response()->json([
            'status' => 'error',
            'message' => 'Rate limit exceeded',
            'violations' => $violations,
            'retry_after' => $retryAfter,
        ], $status);

        $response->headers->set('Retry-After', $retryAfter);
        $response->headers->set('X-RateLimit-Limit', $rateLimits['requests_per_minute']);
        $response->headers->set('X-RateLimit-Remaining', 0);
        $response->headers->set('X-RateLimit-Reset', time() + $retryAfter);

        return $response;
    }

    /**
     * Calculate retry after seconds based on violation type.
     */
    protected function calculateRetryAfter(array $violations): int
    {
        if (in_array('minute', $violations)) {
            return 60;
        }
        
        if (in_array('burst', $violations)) {
            return 10;
        }
        
        if (in_array('hour', $violations)) {
            return 3600;
        }
        
        return 60; // Default
    }

    /**
     * Clean up expired rate limit keys (should be called periodically).
     */
    public function cleanupExpiredKeys(): void
    {
        // This method can be called by a scheduled job to clean up
        // any orphaned keys that might exist in Redis
        $patterns = [
            'webhook_rate_limit:*',
            'webhook_burst:*',
            'webhook_global:*',
            'rate_limit_violation:*',
        ];

        foreach ($patterns as $pattern) {
            $keys = Redis::connection()->keys($pattern);
            
            if (!empty($keys)) {
                // Check TTL and remove expired keys
                foreach ($keys as $key) {
                    $ttl = Redis::connection()->ttl($key);
                    if ($ttl === -1) { // No expiry set, set a default
                        Redis::connection()->expire($key, 3600);
                    }
                }
            }
        }
    }
}