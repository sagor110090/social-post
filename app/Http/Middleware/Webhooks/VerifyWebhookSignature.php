<?php

namespace App\Http\Middleware\Webhooks;

use App\Models\WebhookConfig;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class VerifyWebhookSignature
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $platform = $this->extractPlatformFromRequest($request);
        
        if (!$platform) {
            Log::warning('Unable to determine platform from webhook request', [
                'path' => $request->path(),
                'headers' => $request->headers->all(),
            ]);
            
            return $this->errorResponse('Unable to determine platform', 400);
        }

        // Skip signature verification for webhook verification challenges
        if ($this->isVerificationRequest($request)) {
            return $next($request);
        }

        $config = $this->getWebhookConfig($request, $platform);
        
        if (!$config) {
            Log::warning('No webhook configuration found for platform', [
                'platform' => $platform,
                'path' => $request->path(),
            ]);
            
            return $this->errorResponse('Webhook not configured', 404);
        }

        if (!$this->verifySignature($request, $config, $platform)) {
            $this->logSignatureFailure($request, $config, $platform);
            
            // Record security violation
            $this->recordSecurityViolation('signature_failure', [
                'platform' => $platform,
                'config_id' => $config->id,
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            return $this->errorResponse('Invalid signature', 401);
        }

        // Add verified signature info to request for downstream use
        $request->attributes->set('webhook_config', $config);
        $request->attributes->set('webhook_platform', $platform);

        return $next($request);
    }

    /**
     * Extract platform from request path or headers.
     */
    protected function extractPlatformFromRequest(Request $request): ?string
    {
        // Extract from path (e.g., /webhooks/facebook)
        $path = $request->path();
        
        if (preg_match('/webhooks\/([a-z]+)/', $path, $matches)) {
            return $matches[1];
        }

        // Extract from custom header if available
        $platformHeader = $request->header('X-Webhook-Platform');
        if ($platformHeader && in_array($platformHeader, ['facebook', 'instagram', 'twitter', 'linkedin'])) {
            return $platformHeader;
        }

        return null;
    }

    /**
     * Get webhook configuration for the request.
     */
    protected function getWebhookConfig(Request $request, string $platform): ?WebhookConfig
    {
        // Try to get config from query params or headers
        $configId = $request->get('webhook_config_id') 
                   ?? $request->header('X-Webhook-Config-ID');

        if ($configId) {
            $config = WebhookConfig::find($configId);
            if ($config && $config->socialAccount->platform === $platform) {
                return $config;
            }
        }

        // Fallback to active config for this platform
        return WebhookConfig::whereHas('socialAccount', function ($query) use ($platform) {
            $query->where('platform', $platform);
        })->active()->first();
    }

    /**
     * Verify webhook signature based on platform.
     */
    protected function verifySignature(Request $request, WebhookConfig $config, string $platform): bool
    {
        $payload = $request->getContent();
        $signature = $this->getSignatureFromRequest($request, $platform);
        
        if (!$signature) {
            Log::warning('No signature found in webhook request', [
                'platform' => $platform,
                'config_id' => $config->id,
                'headers' => $request->headers->all(),
            ]);
            return false;
        }

        // Check timestamp to prevent replay attacks
        if (!$this->validateTimestamp($request, $platform)) {
            Log::warning('Webhook timestamp validation failed', [
                'platform' => $platform,
                'config_id' => $config->id,
                'timestamp' => $request->header('X-Webhook-Timestamp'),
            ]);
            return false;
        }

        // Check for replay attacks
        if ($this->isReplayAttack($request, $config)) {
            Log::warning('Potential replay attack detected', [
                'platform' => $platform,
                'config_id' => $config->id,
                'signature' => $signature,
            ]);
            return false;
        }

        return match ($platform) {
            'facebook', 'instagram' => $this->verifyFacebookSignature($payload, $signature, $config->secret),
            'twitter' => $this->verifyTwitterSignature($request, $signature, $config->secret),
            'linkedin' => $this->verifyLinkedInSignature($payload, $signature, $config->secret),
            default => false,
        };
    }

    /**
     * Get signature from request headers based on platform.
     */
    protected function getSignatureFromRequest(Request $request, string $platform): ?string
    {
        $headers = config('webhooks.security.signature.headers');
        
        return $request->header($headers[$platform] ?? null);
    }

    /**
     * Validate timestamp to prevent replay attacks.
     */
    protected function validateTimestamp(Request $request, string $platform): bool
    {
        $timestampHeader = match ($platform) {
            'twitter' => 'X-Twitter-Webhooks-Timestamp',
            default => 'X-Webhook-Timestamp',
        };

        $timestamp = $request->header($timestampHeader);
        
        if (!$timestamp) {
            // Some platforms don't send timestamps, so we'll allow it
            return true;
        }

        $tolerance = config('webhooks.security.signature.tolerance', 300);
        $now = time();
        $requestTime = is_numeric($timestamp) ? (int) $timestamp : strtotime($timestamp);

        return abs($now - $requestTime) <= $tolerance;
    }

    /**
     * Check if this is a replay attack.
     */
    protected function isReplayAttack(Request $request, WebhookConfig $config): bool
    {
        if (!config('webhooks.security.replay_protection.enabled', true)) {
            return false;
        }

        $signature = $this->getSignatureFromRequest($request, $config->socialAccount->platform);
        $cacheKey = 'webhook_replay:' . md5($signature . $config->id);
        
        if (Cache::has($cacheKey)) {
            return true;
        }

        // Store signature for replay window
        $window = config('webhooks.security.replay_protection.window', 300);
        Cache::put($cacheKey, true, $window);

        return false;
    }

    /**
     * Verify Facebook/Instagram signature.
     */
    protected function verifyFacebookSignature(string $payload, string $signature, string $secret): bool
    {
        // Facebook signature format: sha256=hash
        if (!str_starts_with($signature, 'sha256=')) {
            return false;
        }

        $expectedHash = hash_hmac('sha256', $payload, $secret);
        $providedHash = substr($signature, 7); // Remove 'sha256=' prefix

        return hash_equals($expectedHash, $providedHash);
    }

    /**
     * Verify Twitter signature.
     */
    protected function verifyTwitterSignature(Request $request, string $signature, string $secret): bool
    {
        // Twitter uses OAuth 1.0a signature
        $timestamp = $request->header('X-Twitter-Webhooks-Timestamp');
        $nonce = $request->header('X-Twitter-Webhooks-Nonce');
        
        if (!$timestamp || !$nonce) {
            return false;
        }

        $baseString = $timestamp . $nonce . $request->getContent();
        $expectedSignature = base64_encode(hash_hmac('sha256', $baseString, $secret, true));

        return hash_equals($expectedSignature, $signature);
    }

    /**
     * Verify LinkedIn signature.
     */
    protected function verifyLinkedInSignature(string $payload, string $signature, string $secret): bool
    {
        // LinkedIn signature format: hash
        $expectedHash = base64_encode(hash_hmac('sha256', $payload, $secret, true));
        
        return hash_equals($expectedHash, $signature);
    }

    /**
     * Check if this is a webhook verification request.
     */
    protected function isVerificationRequest(Request $request): bool
    {
        return $request->hasAny(['hub_challenge', 'challenge', 'crc_token']);
    }

    /**
     * Log signature failure for security monitoring.
     */
    protected function logSignatureFailure(Request $request, WebhookConfig $config, string $platform): void
    {
        if (!config('webhooks.security.logging.log_signature_failures', true)) {
            return;
        }

        Log::warning('Webhook signature verification failed', [
            'platform' => $platform,
            'config_id' => $config->id,
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'signature' => $this->getSignatureFromRequest($request, $platform),
            'payload_size' => strlen($request->getContent()),
            'headers' => $request->headers->all(),
        ]);
    }

    /**
     * Record security violation for monitoring.
     */
    protected function recordSecurityViolation(string $type, array $context): void
    {
        $cacheKey = "security_violation:{$type}:" . md5(json_encode($context));
        $window = 60; // 1 minute window
        
        $count = Cache::increment($cacheKey, 1, $window);
        
        // Log the violation
        Log::warning("Security violation recorded: {$type}", array_merge($context, [
            'count' => $count,
            'window' => $window,
        ]));

        // Check if we should trigger an alert
        $thresholds = config('webhooks.security.alerting.thresholds', []);
        $thresholdKey = "{$type}_per_minute";
        
        if (isset($thresholds[$thresholdKey]) && $count >= $thresholds[$thresholdKey]) {
            $this->triggerSecurityAlert($type, $context, $count);
        }
    }

    /**
     * Trigger security alert.
     */
    protected function triggerSecurityAlert(string $type, array $context, int $count): void
    {
        if (!config('webhooks.security.alerting.enabled', false)) {
            return;
        }

        Log::critical("Security alert triggered: {$type}", array_merge($context, [
            'count' => $count,
            'alert_type' => $type,
            'timestamp' => now()->toISOString(),
        ]));

        // Here you could integrate with external alerting systems
        // like Slack, email, SMS, etc.
    }

    /**
     * Return error response.
     */
    protected function errorResponse(string $message, int $status): Response
    {
        return response()->json([
            'status' => 'error',
            'message' => $message,
        ], $status);
    }
}