<?php

namespace App\Http\Controllers\Webhooks;

use App\Models\WebhookConfig;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class InstagramWebhookController extends BaseWebhookController
{
    protected string $platform = 'instagram';

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
     * Handle Instagram webhook verification challenge.
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
                Log::warning('Instagram webhook verification attempted without config', [
                    'verify_token' => $verifyToken,
                ]);
                return $this->errorResponse('Webhook not configured', 404);
            }

            $expectedToken = $config->metadata['verify_token'] ?? null;
            if ($expectedToken && hash_equals($expectedToken, $verifyToken)) {
                $config->update(['last_verified_at' => now()]);
                Log::info('Instagram webhook verified successfully', [
                    'config_id' => $config->id,
                ]);
                return $this->challengeResponse($challenge);
            }
        }

        Log::warning('Instagram webhook verification failed', [
            'mode' => $mode,
            'verify_token' => $verifyToken,
        ]);

        return $this->errorResponse('Verification failed', 403);
    }

    /**
     * Extract event data from Instagram webhook payload.
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

        $objectType = $entry['object'] ?? 'unknown';
        $objectId = $entry['id'] ?? null;

        // Extract changes
        $changes = $entry['changes'][0] ?? null;

        if ($changes) {
            return [
                'event_type' => $this->normalizeInstagramEventType($changes),
                'event_id' => $this->extractEventId($changes),
                'object_type' => $this->normalizeObjectType($changes['field'] ?? $objectType),
                'object_id' => $this->extractObjectId($changes) ?? $objectId,
            ];
        }

        // Handle direct messaging (if enabled)
        $messaging = $entry['messaging'][0] ?? null;
        if ($messaging) {
            return [
                'event_type' => $this->normalizeMessagingEventType($messaging),
                'event_id' => $messaging['message']['mid'] ?? $messaging['sender']['id'] ?? null,
                'object_type' => 'message',
                'object_id' => $messaging['sender']['id'] ?? null,
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
     * Normalize Instagram event types.
     */
    private function normalizeInstagramEventType(array $changes): string
    {
        $field = $changes['field'] ?? '';
        $value = $changes['value'] ?? [];

        return match($field) {
            'media' => $this->normalizeMediaEventType($value),
            'comments' => $this->normalizeCommentEventType($value),
            'mentions' => 'user_mentioned',
            'story_insights' => 'story_insights_updated',
            'user_insights' => 'user_insights_updated',
            'mentions_comment' => 'comment_mention',
            'mentions_media_comment' => 'media_comment_mention',
            'mentions_user_bio' => 'bio_mention',
            'business_account' => 'business_account_updated',
            'messaging_handover' => 'messaging_handover',
            'messaging_referrals' => 'messaging_referral',
            'messaging_postbacks' => 'messaging_postback',
            'messaging_optins' => 'messaging_optin',
            'standby' => $this->normalizeStandbyEventType($value),
            default => $field,
        };
    }

    /**
     * Normalize media event types.
     */
    private function normalizeMediaEventType(array $value): string
    {
        $verb = $value['verb'] ?? '';

        return match($verb) {
            'added' => 'media_added',
            'updated' => 'media_updated',
            'removed' => 'media_removed',
            'published' => 'media_published',
            'archived' => 'media_archived',
            'unarchived' => 'media_unarchived',
            'commented' => 'media_commented',
            'liked' => 'media_liked',
            'shared' => 'media_shared',
            'saved' => 'media_saved',
            default => "media_{$verb}",
        };
    }

    /**
     * Normalize comment event types.
     */
    private function normalizeCommentEventType(array $value): string
    {
        $verb = $value['verb'] ?? '';

        return match($verb) {
            'added' => 'comment_added',
            'updated' => 'comment_updated',
            'removed' => 'comment_removed',
            'hidden' => 'comment_hidden',
            'unhidden' => 'comment_unhidden',
            'liked' => 'comment_liked',
            'replied' => 'comment_replied',
            default => "comment_{$verb}",
        };
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
        } elseif (isset($messaging['story_reply'])) {
            return 'story_reply_received';
        }

        return 'messaging_unknown';
    }

    /**
     * Normalize standby event types.
     */
    private function normalizeStandbyEventType(array $value): string
    {
        $field = $value['field'] ?? '';
        
        return match($field) {
            'media' => 'standby_media_update',
            'comments' => 'standby_comment_update',
            'mentions' => 'standby_mention',
            'story_insights' => 'standby_story_insights',
            'user_insights' => 'standby_user_insights',
            default => "standby_{$field}",
        };
    }

    /**
     * Normalize object type.
     */
    private function normalizeObjectType(string $field): string
    {
        return match($field) {
            'media' => 'media',
            'comments' => 'comment',
            'mentions' => 'mention',
            'story_insights' => 'story_insights',
            'user_insights' => 'user_insights',
            'business_account' => 'business_account',
            'messaging_handover' => 'messaging',
            'messaging_referrals' => 'messaging',
            'messaging_postbacks' => 'messaging',
            'messaging_optins' => 'messaging',
            default => $field,
        };
    }

    /**
     * Extract event ID from changes.
     */
    private function extractEventId(array $changes): ?string
    {
        $value = $changes['value'] ?? [];

        return $value['media_id'] 
            ?? $value['comment_id'] 
            ?? $value['media_comment_id']
            ?? $value['story_id']
            ?? $value['user_id']
            ?? null;
    }

    /**
     * Extract object ID from changes.
     */
    private function extractObjectId(array $changes): ?string
    {
        $value = $changes['value'] ?? [];

        return $value['media_id'] 
            ?? $value['comment_id'] 
            ?? $value['media_comment_id']
            ?? $value['story_id']
            ?? $value['user_id']
            ?? null;
    }

    /**
     * Get platform-specific event mappings.
     */
    protected function getEventMappings(): array
    {
        return [
            // Media events
            'media' => 'media_updated',
            'added' => 'content_added',
            'updated' => 'content_updated',
            'removed' => 'content_removed',
            'published' => 'content_published',
            'archived' => 'content_archived',
            
            // Comment events
            'comments' => 'comment_updated',
            'mentions_comment' => 'comment_mention',
            'mentions_media_comment' => 'media_comment_mention',
            
            // Mention events
            'mentions' => 'user_mentioned',
            'mentions_user_bio' => 'bio_mentioned',
            
            // Insights
            'story_insights' => 'story_insights_updated',
            'user_insights' => 'user_insights_updated',
            
            // Business account
            'business_account' => 'business_account_updated',
            
            // Messaging
            'messaging_handover' => 'messaging_handover',
            'messaging_referrals' => 'messaging_referral',
            'messaging_postbacks' => 'messaging_postback',
            'messaging_optins' => 'messaging_optin',
        ];
    }

    /**
     * Extract social account from webhook payload.
     */
    protected function extractSocialAccount(array $payload): ?int
    {
        // Try to extract Instagram business account ID from entry
        $instagramId = $payload['entry'][0]['id'] ?? null;
        
        if ($instagramId) {
            // Find social account by platform_id
            $socialAccount = \App\Models\SocialAccount::where('platform', 'instagram')
                ->where('platform_id', $instagramId)
                ->first();
            
            return $socialAccount?->id;
        }

        return null;
    }
}