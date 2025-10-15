<?php

namespace App\Http\Controllers\Webhooks;

use App\Models\WebhookConfig;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class TwitterWebhookController extends BaseWebhookController
{
    protected string $platform = 'twitter';

    /**
     * Verify webhook signature using OAuth 1.0a or CRC token.
     */
    protected function verifySignature(Request $request, WebhookConfig $config): bool
    {
        // Handle CRC token verification for webhook setup
        if ($request->has('crc_token')) {
            return true; // CRC verification is handled in handleVerification
        }

        // For regular webhook events, verify OAuth 1.0a signature
        $signature = $request->header('X-Twitter-Webhooks-Signature');
        if (!$signature) {
            return false;
        }

        // Get raw payload
        $payload = $request->getContent();
        
        // Generate expected signature using HMAC-SHA256
        $expectedSignature = hash_hmac('sha256', $payload, $config->secret);
        
        return hash_equals($expectedSignature, $signature);
    }

    /**
     * Handle Twitter webhook verification challenge.
     */
    protected function handleVerification(Request $request): JsonResponse
    {
        $crcToken = $request->get('crc_token');

        if ($crcToken) {
            $config = $this->getWebhookConfig($request);
            if (!$config) {
                Log::warning('Twitter webhook verification attempted without config', [
                    'crc_token' => $crcToken,
                ]);
                return $this->errorResponse('Webhook not configured', 404);
            }

            // Generate response hash
            $responseToken = hash_hmac('sha256', $crcToken, $config->secret);

            $config->update(['last_verified_at' => now()]);
            
            Log::info('Twitter webhook verified successfully', [
                'config_id' => $config->id,
            ]);

            return response()->json([
                'response_token' => 'sha256=' . $responseToken
            ]);
        }

        return $this->errorResponse('Verification failed', 403);
    }

    /**
     * Extract event data from Twitter webhook payload.
     */
    protected function extractEventData(Request $request): array
    {
        $payload = $request->all();

        // Handle direct message events
        if (isset($payload['direct_message_events'])) {
            return $this->extractDirectMessageEvent($payload);
        }

        // Handle tweet create events
        if (isset($payload['tweet_create_events'])) {
            return $this->extractTweetCreateEvent($payload);
        }

        // Handle tweet delete events
        if (isset($payload['tweet_delete_events'])) {
            return $this->extractTweetDeleteEvent($payload);
        }

        // Handle favorite events
        if (isset($payload['favorite_events'])) {
            return $this->extractFavoriteEvent($payload);
        }

        // Handle follow events
        if (isset($payload['follow_events'])) {
            return $this->extractFollowEvent($payload);
        }

        // Handle tweet retweet events
        if (isset($payload['tweet_retweet_events'])) {
            return $this->extractRetweetEvent($payload);
        }

        // Handle quote tweet events
        if (isset($payload['quote_tweet_events'])) {
            return $this->extractQuoteTweetEvent($payload);
        }

        // Handle user update events
        if (isset($payload['user_update_events'])) {
            return $this->extractUserUpdateEvent($payload);
        }

        // Handle list events
        if (isset($payload['list_events'])) {
            return $this->extractListEvent($payload);
        }

        return [
            'event_type' => 'unknown',
            'event_id' => null,
            'object_type' => null,
            'object_id' => null,
        ];
    }

    /**
     * Extract direct message event.
     */
    private function extractDirectMessageEvent(array $payload): array
    {
        $event = $payload['direct_message_events'][0] ?? [];
        $messageCreate = $event['message_create'] ?? [];

        return [
            'event_type' => 'direct_message_received',
            'event_id' => $event['id'] ?? null,
            'object_type' => 'direct_message',
            'object_id' => $event['id'] ?? null,
        ];
    }

    /**
     * Extract tweet create event.
     */
    private function extractTweetCreateEvent(array $payload): array
    {
        $tweet = $payload['tweet_create_events'][0] ?? [];

        return [
            'event_type' => $this->normalizeTweetEventType($tweet),
            'event_id' => $tweet['id_str'] ?? null,
            'object_type' => 'tweet',
            'object_id' => $tweet['id_str'] ?? null,
        ];
    }

    /**
     * Extract tweet delete event.
     */
    private function extractTweetDeleteEvent(array $payload): array
    {
        $deleteEvent = $payload['tweet_delete_events'][0] ?? [];
        $tweet = $deleteEvent['tweet'] ?? [];

        return [
            'event_type' => 'tweet_deleted',
            'event_id' => $tweet['id_str'] ?? null,
            'object_type' => 'tweet',
            'object_id' => $tweet['id_str'] ?? null,
        ];
    }

    /**
     * Extract favorite event.
     */
    private function extractFavoriteEvent(array $payload): array
    {
        $favoriteEvent = $payload['favorite_events'][0] ?? [];
        $favoritedTweet = $favoriteEvent['favorited_tweet'] ?? [];

        return [
            'event_type' => $favoriteEvent['event'] === 'favorite' ? 'tweet_favorited' : 'tweet_unfavorited',
            'event_id' => $favoriteEvent['created_timestamp'] ?? null,
            'object_type' => 'tweet',
            'object_id' => $favoritedTweet['id_str'] ?? null,
        ];
    }

    /**
     * Extract follow event.
     */
    private function extractFollowEvent(array $payload): array
    {
        $followEvent = $payload['follow_events'][0] ?? [];

        return [
            'event_type' => $followEvent['event'] === 'follow' ? 'user_followed' : 'user_unfollowed',
            'event_id' => $followEvent['created_timestamp'] ?? null,
            'object_type' => 'user',
            'object_id' => $followEvent['source']['id_str'] ?? null,
        ];
    }

    /**
     * Extract retweet event.
     */
    private function extractRetweetEvent(array $payload): array
    {
        $retweetEvent = $payload['tweet_retweet_events'][0] ?? [];
        $retweetedTweet = $retweetEvent['retweeted_tweet'] ?? [];

        return [
            'event_type' => 'tweet_retweeted',
            'event_id' => $retweetEvent['created_timestamp'] ?? null,
            'object_type' => 'tweet',
            'object_id' => $retweetedTweet['id_str'] ?? null,
        ];
    }

    /**
     * Extract quote tweet event.
     */
    private function extractQuoteTweetEvent(array $payload): array
    {
        $quoteEvent = $payload['quote_tweet_events'][0] ?? [];
        $quotedTweet = $quoteEvent['quoted_tweet'] ?? [];

        return [
            'event_type' => 'tweet_quoted',
            'event_id' => $quoteEvent['created_timestamp'] ?? null,
            'object_type' => 'tweet',
            'object_id' => $quotedTweet['id_str'] ?? null,
        ];
    }

    /**
     * Extract user update event.
     */
    private function extractUserUpdateEvent(array $payload): array
    {
        $userEvent = $payload['user_update_events'][0] ?? [];

        return [
            'event_type' => 'user_updated',
            'event_id' => $userEvent['created_timestamp'] ?? null,
            'object_type' => 'user',
            'object_id' => $userEvent['id_str'] ?? null,
        ];
    }

    /**
     * Extract list event.
     */
    private function extractListEvent(array $payload): array
    {
        $listEvent = $payload['list_events'][0] ?? [];

        return [
            'event_type' => $this->normalizeListEventType($listEvent),
            'event_id' => $listEvent['created_timestamp'] ?? null,
            'object_type' => 'list',
            'object_id' => $listEvent['target']['id_str'] ?? null,
        ];
    }

    /**
     * Normalize tweet event type.
     */
    private function normalizeTweetEventType(array $tweet): string
    {
        if (isset($tweet['retweeted_status'])) {
            return 'tweet_retweeted';
        }

        if (isset($tweet['quoted_status'])) {
            return 'tweet_quoted';
        }

        if (isset($tweet['in_reply_to_status_id_str'])) {
            return 'tweet_replied';
        }

        return 'tweet_created';
    }

    /**
     * Normalize list event type.
     */
    private function normalizeListEventType(array $listEvent): string
    {
        $event = $listEvent['event'] ?? '';

        return match($event) {
            'list_member_added' => 'list_member_added',
            'list_member_removed' => 'list_member_removed',
            'list_created' => 'list_created',
            'list_updated' => 'list_updated',
            'list_destroyed' => 'list_destroyed',
            'list_user_subscribed' => 'list_user_subscribed',
            'list_user_unsubscribed' => 'list_user_unsubscribed',
            default => "list_{$event}",
        };
    }

    /**
     * Get platform-specific event mappings.
     */
    protected function getEventMappings(): array
    {
        return [
            // Direct messages
            'direct_message_events' => 'direct_message_received',
            'direct_message_indicate_typing_events' => 'direct_message_typing',
            'direct_message_mark_read_events' => 'direct_message_read',
            
            // Tweet events
            'tweet_create_events' => 'tweet_created',
            'tweet_delete_events' => 'tweet_deleted',
            'tweet_retweet_events' => 'tweet_retweeted',
            'quote_tweet_events' => 'tweet_quoted',
            
            // Engagement events
            'favorite_events' => 'tweet_favorited',
            'follow_events' => 'user_followed',
            
            // User events
            'user_update_events' => 'user_updated',
            
            // List events
            'list_events' => 'list_updated',
            
            // Account Activity API events
            'account_activity_api' => 'account_activity',
        ];
    }

    /**
     * Extract social account from webhook payload.
     */
    protected function extractSocialAccount(array $payload): ?int
    {
        // Try to extract user ID from for_user_id or target
        $twitterUserId = $payload['for_user_id'] 
            ?? $payload['target']['id_str'] 
            ?? $payload['direct_message_events'][0]['message_create']['target']['recipient_id'] 
            ?? null;
        
        if ($twitterUserId) {
            // Find social account by platform_id
            $socialAccount = \App\Models\SocialAccount::where('platform', 'twitter')
                ->where('platform_id', $twitterUserId)
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

        // Additional Twitter-specific filtering
        $payload = request()->all();
        
        // Filter out own tweets if configured
        if ($config->metadata['ignore_own_tweets'] ?? false) {
            $forUserId = $payload['for_user_id'] ?? null;
            $tweetUserId = $payload['tweet_create_events'][0]['user']['id_str'] ?? null;
            
            if ($forUserId && $tweetUserId && $forUserId === $tweetUserId) {
                return false;
            }
        }

        // Filter out retweets if configured
        if ($config->metadata['ignore_retweets'] ?? false) {
            $isRetweet = isset($payload['tweet_create_events'][0]['retweeted_status']);
            if ($isRetweet) {
                return false;
            }
        }

        return true;
    }
}