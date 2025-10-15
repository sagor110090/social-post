<?php

namespace App\Http\Middleware\Webhooks;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Symfony\Component\HttpFoundation\Response;

class WhitelistWebhookIps
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (!config('webhooks.security.ip_whitelist.enabled', true)) {
            return $next($request);
        }

        $platform = $this->extractPlatformFromRequest($request);
        $clientIp = $this->getClientIp($request);
        
        if (!$platform) {
            return $next($request);
        }

        // Skip IP check for webhook verification challenges (they might come from different IPs)
        if ($this->isVerificationRequest($request)) {
            return $next($request);
        }

        // Update IP ranges if needed
        $this->updateIpRangesIfNeeded($platform);

        // Check if IP is whitelisted
        if (!$this->isIpWhitelisted($clientIp, $platform)) {
            $this->handleIpViolation($request, $platform, $clientIp);
            
            if (config('webhooks.security.ip_whitelist.strict_mode', false)) {
                return $this->ipBlockedResponse($clientIp, $platform);
            }
            
            // Log warning but allow request in non-strict mode
            Log::warning('Webhook request from non-whitelisted IP', [
                'platform' => $platform,
                'client_ip' => $clientIp,
                'user_agent' => $request->userAgent(),
                'path' => $request->path(),
            ]);
        }

        return $next($request);
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
        // Check for Cloudflare
        $cfConnectingIp = $request->header('CF-Connecting-IP');
        if ($cfConnectingIp) {
            return $cfConnectingIp;
        }

        // Check for standard proxy headers
        $forwardedFor = $request->header('X-Forwarded-For');
        if ($forwardedFor) {
            return explode(',', $forwardedFor)[0];
        }

        $realIp = $request->header('X-Real-IP');
        if ($realIp) {
            return $realIp;
        }

        return $request->ip();
    }

    /**
     * Check if this is a webhook verification request.
     */
    protected function isVerificationRequest(Request $request): bool
    {
        return $request->hasAny(['hub_challenge', 'challenge', 'crc_token']);
    }

    /**
     * Update IP ranges if needed.
     */
    protected function updateIpRangesIfNeeded(string $platform): void
    {
        if (!config('webhooks.security.ip_whitelist.auto_update', true)) {
            return;
        }

        $cacheKey = "webhook_ip_ranges:{$platform}";
        $lastUpdate = Cache::get($cacheKey . '_updated', 0);
        $updateInterval = config('webhooks.security.ip_whitelist.update_interval', 86400);

        if (now()->timestamp - $lastUpdate < $updateInterval) {
            return;
        }

        try {
            $ranges = $this->fetchOfficialIpRanges($platform);
            
            if ($ranges) {
                $config = config('webhooks.security.ip_whitelist.platforms.' . $platform);
                $config['ranges'] = $ranges;
                $config['last_updated'] = now()->toISOString();
                
                Cache::put($cacheKey, $ranges, $updateInterval * 2);
                Cache::put($cacheKey . '_updated', now()->timestamp, $updateInterval * 2);
                
                Log::info("Updated IP ranges for {$platform}", [
                    'platform' => $platform,
                    'ranges_count' => count($ranges),
                ]);
            }
        } catch (\Exception $e) {
            Log::error("Failed to update IP ranges for {$platform}", [
                'platform' => $platform,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Fetch official IP ranges from platform APIs.
     */
    protected function fetchOfficialIpRanges(string $platform): ?array
    {
        return match ($platform) {
            'facebook', 'instagram' => $this->fetchFacebookIpRanges(),
            'twitter' => $this->fetchTwitterIpRanges(),
            'linkedin' => $this->fetchLinkedInIpRanges(),
            default => null,
        };
    }

    /**
     * Fetch Facebook/Instagram IP ranges.
     */
    protected function fetchFacebookIpRanges(): ?array
    {
        try {
            $response = Http::timeout(10)->get('https://developers.facebook.com/docs/graph-api/overview/ip-ranges');
            
            if ($response->successful()) {
                // Parse the response to extract IP ranges
                // This is a simplified example - you'd need to parse the actual response format
                $content = $response->body();
                
                // Extract CIDR blocks from the response
                preg_match_all('/\b(?:[0-9]{1,3}\.){3}[0-9]{1,3}\/[0-9]{1,2}\b/', $content, $matches);
                
                return array_unique($matches[0]);
            }
        } catch (\Exception $e) {
            Log::error('Failed to fetch Facebook IP ranges', [
                'error' => $e->getMessage(),
            ]);
        }

        return null;
    }

    /**
     * Fetch Twitter IP ranges.
     */
    protected function fetchTwitterIpRanges(): ?array
    {
        try {
            // Twitter doesn't provide a direct API for IP ranges
            // Use their documented ranges or DNS approach
            $domains = [
                'api.twitter.com',
                'webhooks.twitter.com',
                'upload.twitter.com',
            ];

            $ranges = [];
            foreach ($domains as $domain) {
                $ips = gethostbynamel($domain);
                if ($ips) {
                    foreach ($ips as $ip) {
                        // Convert single IP to /32 CIDR
                        $ranges[] = $ip . '/32';
                    }
                }
            }

            return array_unique($ranges);
        } catch (\Exception $e) {
            Log::error('Failed to fetch Twitter IP ranges', [
                'error' => $e->getMessage(),
            ]);
        }

        return null;
    }

    /**
     * Fetch LinkedIn IP ranges.
     */
    protected function fetchLinkedInIpRanges(): ?array
    {
        try {
            $response = Http::timeout(10)->get('https://www.linkedin.com/help/linkedin/answer/a548834');
            
            if ($response->successful()) {
                $content = $response->body();
                
                // Extract CIDR blocks from the response
                preg_match_all('/\b(?:[0-9]{1,3}\.){3}[0-9]{1,3}\/[0-9]{1,2}\b/', $content, $matches);
                
                return array_unique($matches[0]);
            }
        } catch (\Exception $e) {
            Log::error('Failed to fetch LinkedIn IP ranges', [
                'error' => $e->getMessage(),
            ]);
        }

        return null;
    }

    /**
     * Check if IP is whitelisted for the platform.
     */
    protected function isIpWhitelisted(string $ip, string $platform): bool
    {
        $ranges = $this->getWhitelistedRanges($platform);
        
        foreach ($ranges as $range) {
            if ($this->ipInRange($ip, $range)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get whitelisted IP ranges for a platform.
     */
    protected function getWhitelistedRanges(string $platform): array
    {
        // Try cache first
        $cacheKey = "webhook_ip_ranges:{$platform}";
        $cachedRanges = Cache::get($cacheKey);
        
        if ($cachedRanges) {
            return $cachedRanges;
        }

        // Fallback to config
        $config = config("webhooks.security.ip_whitelist.platforms.{$platform}.ranges", []);
        
        // Cache the config ranges
        Cache::put($cacheKey, $config, 3600);
        
        return $config;
    }

    /**
     * Check if IP is in CIDR range.
     */
    protected function ipInRange(string $ip, string $range): bool
    {
        [$subnet, $mask] = explode('/', $range);
        
        // Convert IP addresses to long integers
        $ipLong = ip2long($ip);
        $subnetLong = ip2long($subnet);
        
        if ($ipLong === false || $subnetLong === false) {
            return false;
        }
        
        // Create subnet mask
        $maskLong = -1 << (32 - $mask);
        
        // Check if IP is in subnet
        return ($ipLong & $maskLong) === ($subnetLong & $maskLong);
    }

    /**
     * Handle IP violation.
     */
    protected function handleIpViolation(Request $request, string $platform, string $clientIp): void
    {
        if (!config('webhooks.security.logging.log_ip_violations', true)) {
            return;
        }

        Log::warning('Webhook IP violation detected', [
            'platform' => $platform,
            'client_ip' => $clientIp,
            'user_agent' => $request->userAgent(),
            'path' => $request->path(),
            'method' => $request->method(),
            'headers' => $request->headers->all(),
        ]);

        // Record security violation
        $this->recordIpViolation($platform, $clientIp);
    }

    /**
     * Record IP violation for monitoring.
     */
    protected function recordIpViolation(string $platform, string $clientIp): void
    {
        $cacheKey = "ip_violation:{$platform}:" . md5($clientIp);
        $window = 60; // 1 minute window
        
        $count = Cache::increment($cacheKey, 1, $window);
        
        // Check if we should trigger an alert
        $thresholds = config('webhooks.security.alerting.thresholds', []);
        $thresholdKey = 'ip_violations_per_minute';
        
        if (isset($thresholds[$thresholdKey]) && $count >= $thresholds[$thresholdKey]) {
            $this->triggerIpViolationAlert($platform, $clientIp, $count);
        }
    }

    /**
     * Trigger IP violation alert.
     */
    protected function triggerIpViolationAlert(string $platform, string $clientIp, int $count): void
    {
        if (!config('webhooks.security.alerting.enabled', false)) {
            return;
        }

        Log::critical("IP violation alert triggered", [
            'platform' => $platform,
            'client_ip' => $clientIp,
            'count' => $count,
            'alert_type' => 'ip_violation',
            'timestamp' => now()->toISOString(),
        ]);
    }

    /**
     * Return IP blocked response.
     */
    protected function ipBlockedResponse(string $clientIp, string $platform): Response
    {
        return response()->json([
            'status' => 'error',
            'message' => 'Access denied from your IP address',
            'client_ip' => $clientIp,
            'platform' => $platform,
        ], 403);
    }

    /**
     * Get IP geolocation information (optional).
     */
    protected function getIpGeoInfo(string $ip): ?array
    {
        try {
            $response = Http::timeout(5)->get("http://ip-api.com/json/{$ip}");
            
            if ($response->successful()) {
                return $response->json();
            }
        } catch (\Exception $e) {
            // Silently fail for geo info
        }

        return null;
    }

    /**
     * Validate IP range format.
     */
    protected function validateIpRange(string $range): bool
    {
        if (!str_contains($range, '/')) {
            return false;
        }

        [$ip, $mask] = explode('/', $range);
        
        // Validate IP
        if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return false;
        }
        
        // Validate mask
        $maskInt = (int) $mask;
        return $maskInt >= 0 && $maskInt <= 32;
    }

    /**
     * Clean up and validate IP ranges.
     */
    public function cleanIpRanges(array $ranges): array
    {
        $cleaned = [];
        
        foreach ($ranges as $range) {
            if ($this->validateIpRange(trim($range))) {
                $cleaned[] = trim($range);
            }
        }
        
        return array_unique($cleaned);
    }
}