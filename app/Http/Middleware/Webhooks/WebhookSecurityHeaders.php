<?php

namespace App\Http\Middleware\Webhooks;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class WebhookSecurityHeaders
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Add security headers to webhook responses
        $this->addSecurityHeaders($response, $request);

        // Add CORS headers for management endpoints
        if ($this->isManagementEndpoint($request)) {
            $this->addCorsHeaders($response, $request);
        }

        // Add webhook-specific headers
        $this->addWebhookHeaders($response, $request);

        return $response;
    }

    /**
     * Add security headers to response.
     */
    protected function addSecurityHeaders(Response $response, Request $request): void
    {
        $headers = config('webhooks.security.security_headers', []);

        foreach ($headers as $header => $value) {
            $response->headers->set($header, $value);
        }

        // Add additional security headers based on request context
        $this->addContextualSecurityHeaders($response, $request);
    }

    /**
     * Add contextual security headers based on request.
     */
    protected function addContextualSecurityHeaders(Response $response, Request $request): void
    {
        // Remove server information
        $response->headers->remove('Server');
        $response->headers->remove('X-Powered-By');

        // Add timing information for monitoring
        $response->headers->set('X-Response-Time', $this->getResponseTime($request));

        // Add request ID for tracing
        $requestId = $request->header('X-Request-ID') ?: uniqid('wh_', true);
        $response->headers->set('X-Request-ID', $requestId);

        // Add security policy headers
        $this->addContentSecurityPolicy($response, $request);

        // Add referrer policy
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');

        // Add permissions policy
        $this->addPermissionsPolicy($response);
    }

    /**
     * Add Content Security Policy header.
     */
    protected function addContentSecurityPolicy(Response $response, Request $request): void
    {
        if ($this->isManagementEndpoint($request)) {
            // More permissive CSP for management endpoints
            $csp = "default-src 'self'; "
                  . "script-src 'self' 'unsafe-inline' 'unsafe-eval'; "
                  . "style-src 'self' 'unsafe-inline'; "
                  . "img-src 'self' data: https:; "
                  . "font-src 'self'; "
                  . "connect-src 'self' https:; "
                  . "frame-ancestors 'none'; "
                  . "base-uri 'self'; "
                  . "form-action 'self';";
        } else {
            // Strict CSP for webhook endpoints
            $csp = "default-src 'none'; "
                  . "script-src 'none'; "
                  . "style-src 'none'; "
                  . "img-src 'none'; "
                  . "connect-src 'none'; "
                  . "font-src 'none'; "
                  . "frame-ancestors 'none'; "
                  . "base-uri 'none'; "
                  . "form-action 'none';";
        }

        $response->headers->set('Content-Security-Policy', $csp);
    }

    /**
     * Add Permissions Policy header.
     */
    protected function addPermissionsPolicy(Response $response): void
    {
        $permissions = [
            'geolocation=()',
            'microphone=()',
            'camera=()',
            'payment=()',
            'usb=()',
            'magnetometer=()',
            'gyroscope=()',
            'accelerometer=()',
            'ambient-light-sensor=()',
            'autoplay=()',
            'encrypted-media=()',
            'fullscreen=()',
            'picture-in-picture=()',
        ];

        $response->headers->set('Permissions-Policy', implode(', ', $permissions));
    }

    /**
     * Add CORS headers for management endpoints.
     */
    protected function addCorsHeaders(Response $response, Request $request): void
    {
        $corsConfig = config('webhooks.security.cors', []);

        // Handle preflight requests
        if ($request->method() === 'OPTIONS') {
            $response->setStatusCode(200);
        }

        // Add CORS headers
        if ($allowedOrigins = $corsConfig['allowed_origins'] ?? null) {
            $origin = $request->header('Origin');
            
            if ($allowedOrigins === '*' || ($origin && in_array($origin, explode(',', $allowedOrigins)))) {
                $response->headers->set('Access-Control-Allow-Origin', $origin ?: '*');
            }
        }

        if ($allowedMethods = $corsConfig['allowed_methods'] ?? null) {
            $response->headers->set('Access-Control-Allow-Methods', implode(', ', $allowedMethods));
        }

        if ($allowedHeaders = $corsConfig['allowed_headers'] ?? null) {
            $response->headers->set('Access-Control-Allow-Headers', implode(', ', $allowedHeaders));
        }

        if ($exposedHeaders = $corsConfig['exposed_headers'] ?? null) {
            $response->headers->set('Access-Control-Expose-Headers', implode(', ', $exposedHeaders));
        }

        if ($maxAge = $corsConfig['max_age'] ?? null) {
            $response->headers->set('Access-Control-Max-Age', $maxAge);
        }

        // Add credentials support
        $response->headers->set('Access-Control-Allow-Credentials', 'false');
    }

    /**
     * Add webhook-specific headers.
     */
    protected function addWebhookHeaders(Response $response, Request $request): void
    {
        // Add webhook processing information
        if ($request->attributes->get('webhook_validated')) {
            $response->headers->set('X-Webhook-Validated', 'true');
        }

        if ($platform = $request->attributes->get('webhook_platform')) {
            $response->headers->set('X-Webhook-Platform', $platform);
        }

        if ($config = $request->attributes->get('webhook_config')) {
            $response->headers->set('X-Webhook-Config-ID', $config->id);
        }

        // Add payload size information
        if ($payloadSize = $request->attributes->get('webhook_payload_size')) {
            $response->headers->set('X-Webhook-Payload-Size', $payloadSize);
        }

        // Add processing time
        $response->headers->set('X-Webhook-Processing-Time', $this->getProcessingTime($request));

        // Add security status
        $this->addSecurityStatusHeaders($response, $request);
    }

    /**
     * Add security status headers.
     */
    protected function addSecurityStatusHeaders(Response $response, Request $request): void
    {
        $securityHeaders = [
            'X-Webhook-Signature-Verified' => $request->attributes->get('webhook_signature_verified', 'false'),
            'X-Webhook-IP-Whitelisted' => $request->attributes->get('webhook_ip_whitelisted', 'false'),
            'X-Webhook-Rate-Limit-OK' => $request->attributes->get('webhook_rate_limit_ok', 'true'),
        ];

        foreach ($securityHeaders as $header => $value) {
            $response->headers->set($header, $value);
        }
    }

    /**
     * Check if this is a management endpoint.
     */
    protected function isManagementEndpoint(Request $request): bool
    {
        return str_contains($request->path(), 'webhooks/manage') ||
               str_contains($request->path(), 'webhooks/config') ||
               str_contains($request->path(), 'webhooks/events');
    }

    /**
     * Calculate response time.
     */
    protected function getResponseTime(Request $request): string
    {
        $startTime = $request->server('REQUEST_TIME_FLOAT', microtime(true));
        $elapsed = microtime(true) - $startTime;
        
        return number_format($elapsed * 1000, 2) . 'ms';
    }

    /**
     * Calculate processing time.
     */
    protected function getProcessingTime(Request $request): string
    {
        // This would typically be set by other middleware during processing
        $processingTime = $request->attributes->get('webhook_processing_time', 0);
        
        return number_format($processingTime * 1000, 2) . 'ms';
    }

    /**
     * Add cache control headers.
     */
    protected function addCacheControlHeaders(Response $response, Request $request): void
    {
        if ($this->isManagementEndpoint($request)) {
            // No caching for management endpoints
            $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, proxy-revalidate');
            $response->headers->set('Pragma', 'no-cache');
            $response->headers->set('Expires', '0');
        } else {
            // Limited caching for webhook endpoints
            $response->headers->set('Cache-Control', 'private, max-age=0, must-revalidate');
        }
    }

    /**
     * Add authentication headers.
     */
    protected function addAuthenticationHeaders(Response $response, Request $request): void
    {
        if ($this->isManagementEndpoint($request)) {
            // Indicate authentication requirements
            $response->headers->set('WWW-Authenticate', 'Bearer realm="Webhook Management"');
        }
    }

    /**
     * Add monitoring headers.
     */
    protected function addMonitoringHeaders(Response $response, Request $request): void
    {
        // Add application version
        $response->headers->set('X-App-Version', config('app.version', '1.0.0'));
        
        // Add environment (only in non-production)
        if (config('app.env') !== 'production') {
            $response->headers->set('X-Environment', config('app.env'));
        }
        
        // Add debug information (only in debug mode)
        if (config('app.debug')) {
            $response->headers->set('X-Debug', 'true');
            $response->headers->set('X-Memory-Usage', memory_get_usage(true));
            $response->headers->set('X-Peak-Memory-Usage', memory_get_peak_usage(true));
        }
    }

    /**
     * Add compression headers.
     */
    protected function addCompressionHeaders(Response $response, Request $request): void
    {
        // Indicate gzip compression support
        $acceptEncoding = $request->header('Accept-Encoding');
        
        if ($acceptEncoding && str_contains($acceptEncoding, 'gzip')) {
            $response->headers->set('Content-Encoding', 'gzip');
        }
    }

    /**
     * Add API version headers.
     */
    protected function addApiVersionHeaders(Response $response, Request $request): void
    {
        if ($this->isManagementEndpoint($request)) {
            $response->headers->set('X-API-Version', 'v1');
            $response->headers->set('X-API-Deprecated', 'false');
        }
    }

    /**
     * Add rate limit information headers.
     */
    protected function addRateLimitInfoHeaders(Response $response, Request $request): void
    {
        // These would typically be set by the rate limiting middleware
        $rateLimitHeaders = [
            'X-RateLimit-Limit',
            'X-RateLimit-Remaining',
            'X-RateLimit-Reset',
            'X-RateLimit-Hour-Limit',
            'X-RateLimit-Hour-Remaining',
            'X-RateLimit-Hour-Reset',
        ];

        foreach ($rateLimitHeaders as $header) {
            if (!$response->headers->has($header)) {
                $response->headers->set($header, '0');
            }
        }
    }
}