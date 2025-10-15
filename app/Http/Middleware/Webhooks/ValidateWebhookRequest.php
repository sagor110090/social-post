<?php

namespace App\Http\Middleware\Webhooks;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class ValidateWebhookRequest
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        try {
            // Validate basic request structure
            $this->validateBasicRequest($request);
            
            // Validate content type
            $this->validateContentType($request);
            
            // Validate payload size
            $this->validatePayloadSize($request);
            
            // Validate required headers
            $this->validateHeaders($request);
            
            // Validate JSON structure if applicable
            if ($this->isJsonRequest($request)) {
                $this->validateJsonStructure($request);
            }
            
            // Validate request timeout
            $this->validateRequestTiming($request);
            
            // Add validation metadata to request
            $request->attributes->set('webhook_validated', true);
            $request->attributes->set('webhook_payload_size', strlen($request->getContent()));
            
            return $next($request);
            
        } catch (ValidationException $e) {
            $this->logValidationFailure($request, $e);
            
            return response()->json([
                'status' => 'error',
                'message' => 'Request validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            $this->logValidationError($request, $e);
            
            return response()->json([
                'status' => 'error',
                'message' => 'Request validation error',
                'error' => config('app.debug') ? $e->getMessage() : 'Invalid request format',
            ], 400);
        }
    }

    /**
     * Validate basic request structure.
     */
    protected function validateBasicRequest(Request $request): void
    {
        $validator = Validator::make([], [], [
            'required' => 'The :attribute field is required.',
        ]);

        // Ensure request has content
        if ($request->method() === 'POST' && empty($request->getContent())) {
            $validator->errors()->add('payload', 'Request payload is required for POST requests');
            throw new ValidationException($validator);
        }

        // Validate HTTP method
        $allowedMethods = ['GET', 'POST', 'OPTIONS'];
        if (!in_array($request->method(), $allowedMethods)) {
            $validator->errors()->add('method', "HTTP method {$request->method()} is not allowed");
            throw new ValidationException($validator);
        }
    }

    /**
     * Validate content type.
     */
    protected function validateContentType(Request $request): void
    {
        $allowedTypes = config('webhooks.security.validation.allowed_content_types', [
            'application/json',
            'application/x-www-form-urlencoded',
            'multipart/form-data',
        ]);

        $contentType = $request->header('Content-Type');
        
        if ($contentType) {
            // Extract the base content type (remove charset, boundary, etc.)
            $baseContentType = explode(';', $contentType)[0];
            
            if (!in_array($baseContentType, $allowedTypes)) {
                $validator = Validator::make([], [], []);
                $validator->errors()->add('content_type', "Content-Type {$baseContentType} is not allowed");
                throw new ValidationException($validator);
            }
        }
    }

    /**
     * Validate payload size.
     */
    protected function validatePayloadSize(Request $request): void
    {
        $maxSize = config('webhooks.security.validation.max_payload_size', 1024 * 1024); // 1MB
        $payloadSize = strlen($request->getContent());
        
        if ($payloadSize > $maxSize) {
            $validator = Validator::make([], [], []);
            $validator->errors()->add('payload_size', "Payload size ({$payloadSize} bytes) exceeds maximum allowed size ({$maxSize} bytes)");
            throw new ValidationException($validator);
        }

        // Check for suspiciously small payloads that might indicate attacks
        if ($request->method() === 'POST' && $payloadSize < 10) {
            Log::warning('Suspiciously small webhook payload', [
                'payload_size' => $payloadSize,
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);
        }
    }

    /**
     * Validate required headers.
     */
    protected function validateHeaders(Request $request): void
    {
        $requiredHeaders = config('webhooks.security.validation.required_headers', [
            'User-Agent',
            'Content-Type',
        ]);

        $validator = Validator::make([], [], []);
        $missingHeaders = [];

        foreach ($requiredHeaders as $header) {
            if (!$request->header($header)) {
                $missingHeaders[] = $header;
            }
        }

        if (!empty($missingHeaders)) {
            $validator->errors()->add('headers', 'Missing required headers: ' . implode(', ', $missingHeaders));
            throw new ValidationException($validator);
        }

        // Validate User-Agent format
        $userAgent = $request->header('User-Agent');
        if ($userAgent && strlen($userAgent) > 500) {
            $validator->errors()->add('user_agent', 'User-Agent header is too long');
            throw new ValidationException($validator);
        }

        // Check for suspicious patterns in headers
        $this->validateHeaderSecurity($request);
    }

    /**
     * Validate header security.
     */
    protected function validateHeaderSecurity(Request $request): void
    {
        $suspiciousPatterns = [
            '/<script[^>]*>.*?<\/script>/i',
            '/javascript:/i',
            '/on\w+\s*=/i',
        ];

        $validator = Validator::make([], [], []);

        foreach ($request->headers->all() as $name => $values) {
            foreach ($values as $value) {
                foreach ($suspiciousPatterns as $pattern) {
                    if (preg_match($pattern, $value)) {
                        $validator->errors()->add('header_security', "Suspicious content detected in {$name} header");
                        throw new ValidationException($validator);
                    }
                }
            }
        }
    }

    /**
     * Check if request is JSON.
     */
    protected function isJsonRequest(Request $request): bool
    {
        $contentType = $request->header('Content-Type');
        return $contentType && str_contains($contentType, 'application/json');
    }

    /**
     * Validate JSON structure.
     */
    protected function validateJsonStructure(Request $request): void
    {
        $content = $request->getContent();
        
        // Check if content is valid JSON
        json_decode($content);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            $validator = Validator::make([], [], []);
            $validator->errors()->add('json', 'Invalid JSON format: ' . json_last_error_msg());
            throw new ValidationException($validator);
        }

        // Validate JSON structure based on platform
        $platform = $this->extractPlatformFromRequest($request);
        if ($platform) {
            $this->validatePlatformJsonStructure($request, $platform);
        }

        // Check for JSON security issues
        $this->validateJsonSecurity($content);
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
     * Validate platform-specific JSON structure.
     */
    protected function validatePlatformJsonStructure(Request $request, string $platform): void
    {
        $data = $request->json()->all();
        $validator = Validator::make([], [], []);

        switch ($platform) {
            case 'facebook':
            case 'instagram':
                $this->validateFacebookStructure($data, $validator);
                break;
                
            case 'twitter':
                $this->validateTwitterStructure($data, $validator);
                break;
                
            case 'linkedin':
                $this->validateLinkedInStructure($data, $validator);
                break;
        }

        if ($validator->errors()->isNotEmpty()) {
            throw new ValidationException($validator);
        }
    }

    /**
     * Validate Facebook/Instagram JSON structure.
     */
    protected function validateFacebookStructure(array $data, $validator): void
    {
        // Check for required Facebook fields
        if (isset($data['entry'])) {
            foreach ($data['entry'] as $entry) {
                if (!isset($entry['id'])) {
                    $validator->errors()->add('facebook_entry', 'Facebook entry missing required ID field');
                }
                
                if (isset($entry['changes'])) {
                    foreach ($entry['changes'] as $change) {
                        if (!isset($change['field'])) {
                            $validator->errors()->add('facebook_change', 'Facebook change missing required field');
                        }
                    }
                }
            }
        }

        // Check for object type
        if (isset($data['object']) && !in_array($data['object'], ['page', 'instagram_account', 'user'])) {
            $validator->errors()->add('facebook_object', 'Invalid Facebook object type');
        }
    }

    /**
     * Validate Twitter JSON structure.
     */
    protected function validateTwitterStructure(array $data, $validator): void
    {
        // Check for Twitter-specific fields
        if (isset($data['for_user_id']) && !is_numeric($data['for_user_id'])) {
            $validator->errors()->add('twitter_user_id', 'Invalid Twitter user ID format');
        }

        if (isset($data['tweet_create_events'])) {
            foreach ($data['tweet_create_events'] as $tweet) {
                if (!isset($tweet['id_str'])) {
                    $validator->errors()->add('twitter_tweet', 'Twitter tweet missing ID');
                }
            }
        }
    }

    /**
     * Validate LinkedIn JSON structure.
     */
    protected function validateLinkedInStructure(array $data, $validator): void
    {
        // Check for LinkedIn-specific fields
        if (isset($data['event'])) {
            if (!isset($data['event']['type'])) {
                $validator->errors()->add('linkedin_event', 'LinkedIn event missing type');
            }
        }

        // Validate person/organization updates
        if (isset($data['data'])) {
            if (!isset($data['data']['id'])) {
                $validator->errors()->add('linkedin_data', 'LinkedIn data missing ID');
            }
        }
    }

    /**
     * Validate JSON security.
     */
    protected function validateJsonSecurity(string $content): void
    {
        $validator = Validator::make([], [], []);

        // Check for JSON bomb (deeply nested objects)
        $data = json_decode($content, true);
        if ($this->isJsonBomb($data)) {
            $validator->errors()->add('json_security', 'Potential JSON bomb detected');
            throw new ValidationException($validator);
        }

        // Check for suspicious content
        $suspiciousPatterns = [
            '/<script[^>]*>.*?<\/script>/i',
            '/javascript:/i',
            '/on\w+\s*=/i',
            '/eval\s*\(/i',
        ];

        foreach ($suspiciousPatterns as $pattern) {
            if (preg_match($pattern, $content)) {
                $validator->errors()->add('json_security', 'Suspicious content detected in JSON payload');
                throw new ValidationException($validator);
            }
        }
    }

    /**
     * Check for JSON bomb (excessive nesting).
     */
    protected function isJsonBomb($data, int $depth = 0, int $maxDepth = 50): bool
    {
        if ($depth > $maxDepth) {
            return true;
        }

        if (!is_array($data)) {
            return false;
        }

        foreach ($data as $value) {
            if (is_array($value) && $this->isJsonBomb($value, $depth + 1, $maxDepth)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Validate request timing.
     */
    protected function validateRequestTiming(Request $request): void
    {
        $timeout = config('webhooks.security.validation.timeout', 30);
        
        // Check if request is taking too long (this is a rough estimate)
        $startTime = $request->server('REQUEST_TIME_FLOAT', microtime(true));
        $elapsed = microtime(true) - $startTime;
        
        if ($elapsed > $timeout) {
            Log::warning('Webhook request timeout exceeded', [
                'elapsed' => $elapsed,
                'timeout' => $timeout,
                'ip' => $request->ip(),
            ]);
        }
    }

    /**
     * Log validation failure.
     */
    protected function logValidationFailure(Request $request, ValidationException $e): void
    {
        Log::warning('Webhook request validation failed', [
            'errors' => $e->errors(),
            'path' => $request->path(),
            'method' => $request->method(),
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'content_type' => $request->header('Content-Type'),
            'payload_size' => strlen($request->getContent()),
        ]);

        // Record security violation
        $this->recordValidationViolation($request);
    }

    /**
     * Log validation error.
     */
    protected function logValidationError(Request $request, \Exception $e): void
    {
        Log::error('Webhook request validation error', [
            'error' => $e->getMessage(),
            'path' => $request->path(),
            'method' => $request->method(),
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);
    }

    /**
     * Record validation violation for monitoring.
     */
    protected function recordValidationViolation(Request $request): void
    {
        $cacheKey = 'validation_violation:' . md5($request->ip() . $request->path());
        $window = 60; // 1 minute window
        
        $count = cache()->increment($cacheKey, 1, $window);
        
        // Check if we should trigger an alert
        $thresholds = config('webhooks.security.alerting.thresholds', []);
        $thresholdKey = 'payload_size_violations_per_hour';
        
        if (isset($thresholds[$thresholdKey]) && $count >= $thresholds[$thresholdKey]) {
            $this->triggerValidationAlert($request, $count);
        }
    }

    /**
     * Trigger validation alert.
     */
    protected function triggerValidationAlert(Request $request, int $count): void
    {
        if (!config('webhooks.security.alerting.enabled', false)) {
            return;
        }

        Log::critical("Validation alert triggered", [
            'path' => $request->path(),
            'ip' => $request->ip(),
            'count' => $count,
            'alert_type' => 'validation',
            'timestamp' => now()->toISOString(),
        ]);
    }
}