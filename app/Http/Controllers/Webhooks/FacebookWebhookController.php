<?php

namespace App\Http\Controllers\Webhooks;

use App\Models\WebhookConfig;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class FacebookWebhookController extends BaseWebhookController
{
    protected string $platform = 'facebook';

    /**
     * Verify webhook signature using X-Hub-Signature-256.
     */
    protected function verifySignature(Request $request, WebhookConfig $config): bool
    {
        $signature = $request->header('X-Hub-Signature-256');
        if (!$signature) {
            return false;
        }

        // Extract signature hash
        $parts = explode('=', $signature, 2);
        if (count($parts) !== 2) {
            return false;
        }

        $hashAlgorithm = $parts[0];
        $signatureHash = $parts[1];

        if ($hashAlgorithm !== 'sha256') {
            return false;
        }

        // Get raw payload
        $payload = $request->getContent();
        
        // Generate expected signature
        $expectedSignature = hash_hmac('sha256', $payload, $config->secret);
        
        return hash_equals($expectedSignature, $signatureHash);
    }

    /**
     * Handle Facebook webhook verification challenge.
     */
    protected function handleVerification(Request $request): JsonResponse
    {
        $mode = $request->get('hub_mode');
        $challenge = $request->get('hub_challenge');
        $verifyToken = $request->get('hub_verify_token');

        if ($mode === 'subscribe' && $verifyToken) {
            // Verify token matches our stored token
            $config = $this->getWebhookConfig($request);
            if (!$config) {
                Log::warning('Facebook webhook verification attempted without config', [
                    'verify_token' => $verifyToken,
                ]);
                return $this->errorResponse('Webhook not configured', 404);
            }

            $expectedToken = $config->metadata['verify_token'] ?? null;
            if ($expectedToken && hash_equals($expectedToken, $verifyToken)) {
                $config->update(['last_verified_at' => now()]);
                Log::info('Facebook webhook verified successfully', [
                    'config_id' => $config->id,
                ]);
                return $this->challengeResponse($challenge);
            }
        }

        Log::warning('Facebook webhook verification failed', [
            'mode' => $mode,
            'verify_token' => $verifyToken,
        ]);

        return $this->errorResponse('Verification failed', 403);
    }

    /**
     * Extract event data from Facebook webhook payload.
     */
    protected function extractEventData(Request $request): array
    {
        $payload = $request->all();
        $entry = $payload['entry'][0] ?? null;

        if (!$entry) {
            return [
                'event_type' => 'unknown',
                'event_id' => null,
                'object_type' => null,
                'object_id' => null,
            ];
        }

        $objectType = $entry['id'] ? 'page' : 'unknown';
        $objectId = $entry['id'] ?? null;

        // Extract changes or messaging events
        $changes = $entry['changes'][0] ?? null;
        $messaging = $entry['messaging'][0] ?? null;

        if ($messaging) {
            // Messaging events
            return [
                'event_type' => $this->normalizeMessagingEventType($messaging),
                'event_id' => $messaging['message']['mid'] ?? $messaging['sender']['id'] ?? null,
                'object_type' => 'message',
                'object_id' => $messaging['sender']['id'] ?? null,
            ];
        } elseif ($changes) {
            // Graph API changes
            return [
                'event_type' => $this->normalizeGraphEventType($changes),
                'event_id' => $changes['value']['post_id'] ?? $changes['value']['leadgen_id'] ?? null,
                'object_type' => $changes['field'] ?? 'unknown',
                'object_id' => $changes['value']['id'] ?? null,
            ];
        }

        // Standby events (for page subscriptions)
        $standby = $entry['standby'][0] ?? null;
        if ($standby) {
            return [
                'event_type' => $this->normalizeStandbyEventType($standby),
                'event_id' => $standby['post_id'] ?? null,
                'object_type' => 'post',
                'object_id' => $standby['post_id'] ?? null,
            ];
        }

        return [
            'event_type' => 'unknown',
            'event_id' => null,
            'object_type' => $objectType,
            'object_id' => $objectId,
        ];
    }

    /**
     * Normalize messaging event types.
     */
    private function normalizeMessagingEventType(array $messaging): string
    {
        if (isset($messaging['message'])) {
            return 'message_received';
        } elseif (isset($messaging['delivery'])) {
            return 'message_delivered';
        } elseif (isset($messaging['read'])) {
            return 'message_read';
        } elseif (isset($messaging['postback'])) {
            return 'postback_received';
        } elseif (isset($messaging['optin'])) {
            return 'optin_received';
        } elseif (isset($messaging['referral'])) {
            return 'referral_received';
        } elseif (isset($messaging['account_linking'])) {
            return 'account_linking';
        }

        return 'messaging_unknown';
    }

    /**
     * Normalize Graph API event types.
     */
    private function normalizeGraphEventType(array $changes): string
    {
        $field = $changes['field'] ?? '';
        $value = $changes['value'] ?? [];

        return match($field) {
            'feed' => $this->normalizeFeedEventType($value),
            'conversations' => 'conversation_updated',
            'live_videos' => 'live_video_updated',
            'ratings' => 'rating_updated',
            'mentioned_comment' => 'comment_mention',
            'mentioned_post' => 'post_mention',
            'message_reactions' => 'message_reaction_updated',
            'messaging_postbacks' => 'postback_received',
            'messaging_optins' => 'optin_received',
            'messaging_referrals' => 'referral_received',
            'leadgen' => 'lead_generated',
            default => $field,
        };
    }

    /**
     * Normalize feed event types.
     */
    private function normalizeFeedEventType(array $value): string
    {
        $verb = $value['verb'] ?? '';
        $item = $value['item'] ?? '';

        return match("{$verb}:{$item}") {
            'add:status' => 'post_created',
            'add:photo' => 'photo_added',
            'add:video' => 'video_added',
            'add:comment' => 'comment_added',
            'add:like' => 'post_liked',
            'add:share' => 'post_shared',
            'edit:status' => 'post_edited',
            'edit:photo' => 'photo_edited',
            'edit:video' => 'video_edited',
            'edit:comment' => 'comment_edited',
            'remove:status' => 'post_deleted',
            'remove:photo' => 'photo_deleted',
            'remove:video' => 'video_deleted',
            'remove:comment' => 'comment_deleted',
            'remove:like' => 'post_unliked',
            default => "feed_{$verb}_{$item}",
        };
    }

    /**
     * Normalize standby event types.
     */
    private function normalizeStandbyEventType(array $standby): string
    {
        return match($standby['field'] ?? '') {
            'feed' => 'standby_feed_update',
            'conversations' => 'standby_conversation_update',
            'message_reactions' => 'standby_message_reaction',
            default => 'standby_' . ($standby['field'] ?? 'unknown'),
        };
    }

    /**
     * Get platform-specific event mappings.
     */
    protected function getEventMappings(): array
    {
        return [
            // Feed events
            'feed' => 'post_updated',
            'add' => 'content_added',
            'edit' => 'content_edited',
            'remove' => 'content_removed',
            
            // Messaging events
            'messages' => 'message_received',
            'messaging_postbacks' => 'postback_received',
            'messaging_optins' => 'optin_received',
            'messaging_referrals' => 'referral_received',
            
            // Lead generation
            'leadgen' => 'lead_generated',
            
            // Page updates
            'ratings' => 'rating_updated',
            'live_videos' => 'live_video_updated',
        ];
    }

    /**
     * Extract social account from webhook payload.
     */
    protected function extractSocialAccount(array $payload): ?int
    {
        // Try to extract page ID from entry
        $pageId = $payload['entry'][0]['id'] ?? null;
        
        if ($pageId) {
            // Find social account by platform_id
            $socialAccount = \App\Models\SocialAccount::where('platform', 'facebook')
                ->where('platform_id', $pageId)
                ->first();
            
            return $socialAccount?->id;
        }

        return null;
    }
}