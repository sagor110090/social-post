<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Models\WebhookConfig;
use App\Models\WebhookEvent;
use App\Jobs\ProcessWebhookEventJob;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Exception;

abstract class BaseWebhookController extends Controller
{
    /**
     * Platform identifier.
     */
    protected string $platform;

    /**
     * Verify webhook signature.
     */
    abstract protected function verifySignature(Request $request, WebhookConfig $config): bool;

    /**
     * Handle webhook verification challenge.
     */
    abstract protected function handleVerification(Request $request): JsonResponse;

    /**
     * Extract event data from request.
     */
    abstract protected function extractEventData(Request $request): array;

    /**
     * Get webhook config for the request.
     */
    protected function getWebhookConfig(Request $request): ?WebhookConfig
    {
        // Try to get config from query params or headers
        $configId = $request->get('webhook_config_id') 
                   ?? $request->header('X-Webhook-Config-ID');

        if ($configId) {
            return WebhookConfig::find($configId);
        }

        // Fallback to active config for this platform
        return WebhookConfig::whereHas('socialAccount', function ($query) {
            $query->where('platform', $this->platform);
        })->active()->first();
    }

    /**
     * Handle incoming webhook.
     */
    public function handle(Request $request): JsonResponse
    {
        try {
            // Handle verification challenge if present
            if ($this->isVerificationRequest($request)) {
                return $this->handleVerification($request);
            }

            // Get webhook configuration
            $config = $this->getWebhookConfig($request);
            if (!$config) {
                Log::warning("No webhook config found for {$this->platform}", [
                    'platform' => $this->platform,
                    'request_data' => $request->all(),
                ]);
                return $this->errorResponse('Webhook not configured', 404);
            }

            // Verify signature
            if (!$this->verifySignature($request, $config)) {
                Log::warning("Invalid webhook signature for {$this->platform}", [
                    'config_id' => $config->id,
                    'platform' => $this->platform,
                ]);
                return $this->errorResponse('Invalid signature', 401);
            }

            // Extract and validate event data
            $eventData = $this->extractEventData($request);
            $validatedData = $this->validateEventData($eventData);

            // Store webhook event
            $webhookEvent = $this->storeWebhookEvent($request, $config, $validatedData);

            // Dispatch processing job
            ProcessWebhookEventJob::dispatch($webhookEvent);

            // Record delivery metric
            $this->recordDeliveryMetric($config, 'received', 200);

            return $this->successResponse('Webhook received');

        } catch (ValidationException $e) {
            Log::error("Webhook validation failed for {$this->platform}", [
                'errors' => $e->errors(),
                'request_data' => $request->all(),
            ]);
            return $this->errorResponse('Validation failed', 422);
        } catch (Exception $e) {
            Log::error("Webhook processing failed for {$this->platform}", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Record failure metric if we have a config
            if (isset($config)) {
                $this->recordDeliveryMetric($config, 'error', 500);
            }

            return $this->errorResponse('Internal server error', 500);
        }
    }

    /**
     * Check if this is a verification request.
     */
    protected function isVerificationRequest(Request $request): bool
    {
        return $request->hasAny(['hub_challenge', 'challenge', 'crc_token']);
    }

    /**
     * Validate extracted event data.
     */
    protected function validateEventData(array $data): array
    {
        return Validator::make($data, [
            'event_type' => 'required|string',
            'event_id' => 'nullable|string',
            'object_type' => 'nullable|string',
            'object_id' => 'nullable|string',
        ])->validate();
    }

    /**
     * Store webhook event in database.
     */
    protected function storeWebhookEvent(Request $request, WebhookConfig $config, array $eventData): WebhookEvent
    {
        return WebhookEvent::create([
            'social_account_id' => $config->social_account_id,
            'webhook_config_id' => $config->id,
            'platform' => $this->platform,
            'event_type' => $eventData['event_type'],
            'event_id' => $eventData['event_id'] ?? null,
            'object_type' => $eventData['object_type'] ?? null,
            'object_id' => $eventData['object_id'] ?? null,
            'payload' => $request->all(),
            'signature' => $this->getSignatureFromRequest($request),
            'status' => 'pending',
            'received_at' => now(),
        ]);
    }

    /**
     * Get signature from request.
     */
    protected function getSignatureFromRequest(Request $request): ?string
    {
        return $request->header('X-Hub-Signature-256')
            ?? $request->header('X-Hub-Signature')
            ?? $request->header('X-LI-Signature')
            ?? $request->header('X-Twitter-Webhooks-Signature');
    }

    /**
     * Record delivery metric.
     */
    protected function recordDeliveryMetric(WebhookConfig $config, string $status, int $statusCode): void
    {
        $config->deliveryMetrics()->create([
            'status' => $status,
            'http_status_code' => $statusCode,
            'delivered_at' => now(),
        ]);
    }

    /**
     * Success response.
     */
    protected function successResponse(string $message = 'Success', int $status = 200): JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'message' => $message,
        ], $status);
    }

    /**
     * Error response.
     */
    protected function errorResponse(string $message, int $status = 400): JsonResponse
    {
        return response()->json([
            'status' => 'error',
            'message' => $message,
        ], $status);
    }

    /**
     * Challenge response for webhook verification.
     */
    protected function challengeResponse(string $challenge): JsonResponse
    {
        return response($challenge, 200)
            ->header('Content-Type', 'text/plain');
    }

    /**
     * Get platform-specific event mappings.
     */
    protected function getEventMappings(): array
    {
        return [];
    }

    /**
     * Normalize event type.
     */
    protected function normalizeEventType(string $eventType): string
    {
        $mappings = $this->getEventMappings();
        return $mappings[$eventType] ?? $eventType;
    }

    /**
     * Extract social account from webhook payload.
     */
    protected function extractSocialAccount(array $payload): ?int
    {
        // Override in platform-specific controllers
        return null;
    }

    /**
     * Check if webhook should be processed.
     */
    protected function shouldProcessWebhook(array $eventData, WebhookConfig $config): bool
    {
        // Check if event type is subscribed
        if (!$config->isSubscribedTo($eventData['event_type'])) {
            return false;
        }

        return true;
    }
}