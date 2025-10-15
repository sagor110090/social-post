<?php

namespace App\Http\Controllers\Webhooks;

use App\Models\WebhookConfig;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class LinkedInWebhookController extends BaseWebhookController
{
    protected string $platform = 'linkedin';

    /**
     * Verify webhook signature using X-LI-Signature.
     */
    protected function verifySignature(Request $request, WebhookConfig $config): bool
    {
        $signature = $request->header('X-LI-Signature');
        if (!$signature) {
            return false;
        }

        // Get raw payload
        $payload = $request->getContent();
        
        // Generate expected signature
        $expectedSignature = hash_hmac('sha256', $payload, $config->secret);
        
        return hash_equals($expectedSignature, $signature);
    }

    /**
     * Handle LinkedIn webhook verification challenge.
     */
    protected function handleVerification(Request $request): JsonResponse
    {
        $challengeCode = $request->get('challenge_code');

        if ($challengeCode) {
            $config = $this->getWebhookConfig($request);
            if (!$config) {
                Log::warning('LinkedIn webhook verification attempted without config', [
                    'challenge_code' => $challengeCode,
                ]);
                return $this->errorResponse('Webhook not configured', 404);
            }

            // Generate response challenge
            $responseChallenge = hash_hmac('sha256', $challengeCode, $config->secret);

            $config->update(['last_verified_at' => now()]);
            
            Log::info('LinkedIn webhook verified successfully', [
                'config_id' => $config->id,
            ]);

            return response()->json([
                'challengeResponse' => $responseChallenge
            ]);
        }

        return $this->errorResponse('Verification failed', 403);
    }

    /**
     * Extract event data from LinkedIn webhook payload.
     */
    protected function extractEventData(Request $request): array
    {
        $payload = $request->all();

        // Handle standard webhook events
        if (isset($payload['event'])) {
            return $this->extractStandardEvent($payload);
        }

        // Handle share updates
        if (isset($payload['shareUpdate'])) {
            return $this->extractShareUpdateEvent($payload);
        }

        // Handle comment updates
        if (isset($payload['commentUpdate'])) {
            return $this->extractCommentUpdateEvent($payload);
        }

        // Handle reaction updates
        if (isset($payload['reactionUpdate'])) {
            return $this->extractReactionUpdateEvent($payload);
        }

        // Handle person updates
        if (isset($payload['personUpdate'])) {
            return $this->extractPersonUpdateEvent($payload);
        }

        // Handle organization updates
        if (isset($payload['organizationUpdate'])) {
            return $this->extractOrganizationUpdateEvent($payload);
        }

        return [
            'event_type' => 'unknown',
            'event_id' => null,
            'object_type' => null,
            'object_id' => null,
        ];
    }

    /**
     * Extract standard webhook event.
     */
    private function extractStandardEvent(array $payload): array
    {
        $event = $payload['event'] ?? [];

        return [
            'event_type' => $this->normalizeStandardEventType($event),
            'event_id' => $event['id'] ?? null,
            'object_type' => $event['object'] ?? 'unknown',
            'object_id' => $event['objectId'] ?? null,
        ];
    }

    /**
     * Extract share update event.
     */
    private function extractShareUpdateEvent(array $payload): array
    {
        $shareUpdate = $payload['shareUpdate'] ?? [];

        return [
            'event_type' => $this->normalizeShareEventType($shareUpdate),
            'event_id' => $shareUpdate['updateKey'] ?? null,
            'object_type' => 'share',
            'object_id' => $shareUpdate['shareId'] ?? null,
        ];
    }

    /**
     * Extract comment update event.
     */
    private function extractCommentUpdateEvent(array $payload): array
    {
        $commentUpdate = $payload['commentUpdate'] ?? [];

        return [
            'event_type' => $this->normalizeCommentEventType($commentUpdate),
            'event_id' => $commentUpdate['updateKey'] ?? null,
            'object_type' => 'comment',
            'object_id' => $commentUpdate['commentId'] ?? null,
        ];
    }

    /**
     * Extract reaction update event.
     */
    private function extractReactionUpdateEvent(array $payload): array
    {
        $reactionUpdate = $payload['reactionUpdate'] ?? [];

        return [
            'event_type' => $this->normalizeReactionEventType($reactionUpdate),
            'event_id' => $reactionUpdate['updateKey'] ?? null,
            'object_type' => 'reaction',
            'object_id' => $reactionUpdate['reactionId'] ?? null,
        ];
    }

    /**
     * Extract person update event.
     */
    private function extractPersonUpdateEvent(array $payload): array
    {
        $personUpdate = $payload['personUpdate'] ?? [];

        return [
            'event_type' => $this->normalizePersonEventType($personUpdate),
            'event_id' => $personUpdate['updateKey'] ?? null,
            'object_type' => 'person',
            'object_id' => $personUpdate['personId'] ?? null,
        ];
    }

    /**
     * Extract organization update event.
     */
    private function extractOrganizationUpdateEvent(array $payload): array
    {
        $organizationUpdate = $payload['organizationUpdate'] ?? [];

        return [
            'event_type' => $this->normalizeOrganizationEventType($organizationUpdate),
            'event_id' => $organizationUpdate['updateKey'] ?? null,
            'object_type' => 'organization',
            'object_id' => $organizationUpdate['organizationId'] ?? null,
        ];
    }

    /**
     * Normalize standard event type.
     */
    private function normalizeStandardEventType(array $event): string
    {
        $eventType = $event['eventType'] ?? '';
        $object = $event['object'] ?? '';

        return match("{$eventType}:{$object}") {
            'SHARE_CREATED:share' => 'share_created',
            'SHARE_UPDATED:share' => 'share_updated',
            'SHARE_DELETED:share' => 'share_deleted',
            'COMMENT_CREATED:comment' => 'comment_created',
            'COMMENT_UPDATED:comment' => 'comment_updated',
            'COMMENT_DELETED:comment' => 'comment_deleted',
            'REACTION_CREATED:reaction' => 'reaction_created',
            'REACTION_DELETED:reaction' => 'reaction_deleted',
            'PERSON_UPDATED:person' => 'person_updated',
            'ORGANIZATION_UPDATED:organization' => 'organization_updated',
            default => strtolower("{$eventType}_{$object}"),
        };
    }

    /**
     * Normalize share event type.
     */
    private function normalizeShareEventType(array $shareUpdate): string
    {
        $updateType = $shareUpdate['updateType'] ?? '';

        return match($updateType) {
            'CREATED' => 'share_created',
            'UPDATED' => 'share_updated',
            'DELETED' => 'share_deleted',
            'RESHARED' => 'share_reshared',
            'PUBLISHED' => 'share_published',
            'UNPUBLISHED' => 'share_unpublished',
            default => "share_{$updateType}",
        };
    }

    /**
     * Normalize comment event type.
     */
    private function normalizeCommentEventType(array $commentUpdate): string
    {
        $updateType = $commentUpdate['updateType'] ?? '';

        return match($updateType) {
            'CREATED' => 'comment_created',
            'UPDATED' => 'comment_updated',
            'DELETED' => 'comment_deleted',
            'HIDDEN' => 'comment_hidden',
            'UNHIDDEN' => 'comment_unhidden',
            'FLAGGED' => 'comment_flagged',
            'UNFLAGGED' => 'comment_unflagged',
            default => "comment_{$updateType}",
        };
    }

    /**
     * Normalize reaction event type.
     */
    private function normalizeReactionEventType(array $reactionUpdate): string
    {
        $updateType = $reactionUpdate['updateType'] ?? '';
        $reactionType = $reactionUpdate['reactionType'] ?? '';

        return match($updateType) {
            'CREATED' => "reaction_created_{$reactionType}",
            'DELETED' => "reaction_deleted_{$reactionType}",
            default => "reaction_{$updateType}_{$reactionType}",
        };
    }

    /**
     * Normalize person event type.
     */
    private function normalizePersonEventType(array $personUpdate): string
    {
        $updateType = $personUpdate['updateType'] ?? '';

        return match($updateType) {
            'PROFILE_UPDATED' => 'person_profile_updated',
            'POSITION_UPDATED' => 'person_position_updated',
            'EDUCATION_UPDATED' => 'person_education_updated',
            'SKILLS_UPDATED' => 'person_skills_updated',
            'PICTURE_UPDATED' => 'person_picture_updated',
            'CONNECTION_ADDED' => 'person_connection_added',
            'CONNECTION_REMOVED' => 'person_connection_removed',
            default => "person_{$updateType}",
        };
    }

    /**
     * Normalize organization event type.
     */
    private function normalizeOrganizationEventType(array $organizationUpdate): string
    {
        $updateType = $organizationUpdate['updateType'] ?? '';

        return match($updateType) {
            'COMPANY_UPDATED' => 'organization_updated',
            'EMPLOYEE_ADDED' => 'organization_employee_added',
            'EMPLOYEE_REMOVED' => 'organization_employee_removed',
            'ADMIN_ADDED' => 'organization_admin_added',
            'ADMIN_REMOVED' => 'organization_admin_removed',
            'FOLLOWER_GAINED' => 'organization_follower_gained',
            'FOLLOWER_LOST' => 'organization_follower_lost',
            'PAGE_UPDATED' => 'organization_page_updated',
            default => "organization_{$updateType}",
        };
    }

    /**
     * Get platform-specific event mappings.
     */
    protected function getEventMappings(): array
    {
        return [
            // Share events
            'SHARE_CREATED' => 'share_created',
            'SHARE_UPDATED' => 'share_updated',
            'SHARE_DELETED' => 'share_deleted',
            
            // Comment events
            'COMMENT_CREATED' => 'comment_created',
            'COMMENT_UPDATED' => 'comment_updated',
            'COMMENT_DELETED' => 'comment_deleted',
            
            // Reaction events
            'REACTION_CREATED' => 'reaction_created',
            'REACTION_DELETED' => 'reaction_deleted',
            
            // Person events
            'PERSON_UPDATED' => 'person_updated',
            
            // Organization events
            'ORGANIZATION_UPDATED' => 'organization_updated',
            
            // UGC (User Generated Content) events
            'UGC_PUBLISHED' => 'ugc_published',
            'UGC_UPDATED' => 'ugc_updated',
            'UGC_DELETED' => 'ugc_deleted',
            
            // Ad events
            'AD_CREATED' => 'ad_created',
            'AD_UPDATED' => 'ad_updated',
            'AD_DELETED' => 'ad_deleted',
        ];
    }

    /**
     * Extract social account from webhook payload.
     */
    protected function extractSocialAccount(array $payload): ?int
    {
        // Try to extract organization ID or person ID
        $linkedinId = $payload['organizationUpdate']['organizationId'] 
            ?? $payload['personUpdate']['personId']
            ?? $payload['shareUpdate']['owner']
            ?? $payload['commentUpdate']['owner']
            ?? null;
        
        if ($linkedinId) {
            // Find social account by platform_id
            $socialAccount = \App\Models\SocialAccount::where('platform', 'linkedin')
                ->where('platform_id', $linkedinId)
                ->first();
            
            return $socialAccount?->id;
        }

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

        // Additional LinkedIn-specific filtering
        $payload = request()->all();
        
        // Filter out own shares if configured
        if ($config->metadata['ignore_own_shares'] ?? false) {
            $owner = $payload['shareUpdate']['owner'] ?? null;
            $configOwnerId = $config->socialAccount->platform_id ?? null;
            
            if ($owner && $configOwnerId && $owner === $configOwnerId) {
                return false;
            }
        }

        // Filter out comments on own posts if configured
        if ($config->metadata['ignore_comments_on_own_posts'] ?? false) {
            $shareOwner = $payload['commentUpdate']['shareOwner'] ?? null;
            $configOwnerId = $config->socialAccount->platform_id ?? null;
            
            if ($shareOwner && $configOwnerId && $shareOwner === $configOwnerId) {
                return false;
            }
        }

        // Filter by reaction type if configured
        if (isset($config->metadata['allowed_reaction_types']) && isset($payload['reactionUpdate']['reactionType'])) {
            $allowedTypes = $config->metadata['allowed_reaction_types'];
            $reactionType = $payload['reactionUpdate']['reactionType'];
            
            if (!in_array($reactionType, $allowedTypes)) {
                return false;
            }
        }

        return true;
    }
}