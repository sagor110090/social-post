<?php

namespace App\Services\Webhooks\Normalizers;

use App\Models\WebhookEvent;
use Illuminate\Support\Arr;

class InstagramEventNormalizer extends BaseEventNormalizer
{
    public function extractEventType(WebhookEvent $webhookEvent): string
    {
        $payload = $webhookEvent->payload;
        $change = Arr::get($payload, 'entry.0.changes.0');

        if (!$change) {
            return $webhookEvent->event_type;
        }

        $field = $change['field'] ?? '';
        $value = $change['value'] ?? [];

        return match ($field) {
            'media' => match (true) {
                isset($value['media_id']) && isset($value['created_time']) => 'created',
                isset($value['media_id']) && isset($value['caption']) => 'updated',
                isset($value['media_id']) && isset($value['deleted']) => 'deleted',
                isset($value['media_id']) && isset($value['like_count']) => 'engagement',
                default => 'unknown',
            },
            'comments' => match (true) {
                isset($value['comment_id']) && isset($value['created_time']) => 'created',
                isset($value['comment_id']) && isset($value['deleted']) => 'deleted',
                isset($value['comment_id']) && isset($value['text']) => 'updated',
                default => 'unknown',
            },
            'story_insights' => 'insights_updated',
            'user_insights' => 'insights_updated',
            'mentions' => 'mention',
            'business_account' => 'account_updated',
            'messaging' => 'message_received',
            'message_reactions' => 'message_reaction',
            'message_echoes' => 'message_sent',
            'message_reads' => 'message_read',
            'standby' => 'standby_event',
            default => $webhookEvent->event_type,
        };
    }

    public function extractObjectType(WebhookEvent $webhookEvent): string
    {
        $payload = $webhookEvent->payload;
        $change = Arr::get($payload, 'entry.0.changes.0');

        if (!$change) {
            return 'unknown';
        }

        $field = $change['field'] ?? '';
        $value = $change['value'] ?? [];

        return match ($field) {
            'media' => 'post',
            'comments' => 'comment',
            'story_insights' => 'story',
            'user_insights' => 'user',
            'mentions' => 'mention',
            'business_account' => 'account',
            'messaging', 'message_reactions', 'message_echoes', 'message_reads' => 'message',
            default => match (true) {
                isset($value['media_id']) => 'post',
                isset($value['comment_id']) => 'comment',
                isset($value['story_id']) => 'story',
                isset($value['user_id']) => 'user',
                default => 'unknown',
            },
        };
    }

    public function extractObjectId(WebhookEvent $webhookEvent): ?string
    {
        $payload = $webhookEvent->payload;
        
        // Try multiple paths for object ID
        $paths = [
            'entry.0.changes.0.value.media_id',
            'entry.0.changes.0.value.comment_id',
            'entry.0.changes.0.value.story_id',
            'entry.0.changes.0.value.user_id',
            'entry.0.messaging.0.message.mid',
            'entry.0.messaging.0.sender.id',
            'entry.0.id',
        ];

        foreach ($paths as $path) {
            $id = Arr::get($payload, $path);
            if ($id) {
                return $id;
            }
        }

        return null;
    }

    protected function extractPlatformSpecificMetrics(array $payload): array
    {
        $metrics = [];
        $change = Arr::get($payload, 'entry.0.changes.0.value', []);

        // Instagram-specific metrics
        $metrics['saves'] = Arr::get($change, 'saved_count');
        $metrics['carousel_album_engagement'] = Arr::get($change, 'carousel_album_engagement');
        $metrics['video_views'] = Arr::get($change, 'video_views');
        $metrics['video_thumbnails_played'] = Arr::get($change, 'video_thumbnails_played');
        $metrics['video_avg_time_watched'] = Arr::get($change, 'video_avg_time_watched');
        $metrics['video_quartile_95_percent_watched'] = Arr::get($change, 'video_quartile_95_percent_watched');
        $metrics['video_quartile_100_percent_watched'] = Arr::get($change, 'video_quartile_100_percent_watched');
        $metrics['reach'] = Arr::get($change, 'reach');
        $metrics['impressions'] = Arr::get($change, 'impressions');
        $metrics['organic_impressions'] = Arr::get($change, 'organic_impressions');
        $metrics['paid_impressions'] = Arr::get($change, 'paid_impressions');
        $metrics['discover_impressions'] = Arr::get($change, 'discover_impressions');
        $metrics['home_impressions'] => Arr::get($change, 'home_impressions');
        $metrics['profile_impressions'] => Arr::get($change, 'profile_impressions');
        $metrics['hashtag_impressions'] => Arr::get($change, 'hashtag_impressions');

        // Story metrics
        $metrics['story_exits'] = Arr::get($change, 'exits');
        $metrics['story_impressions'] = Arr::get($change, 'impressions');
        $metrics['story_reach'] = Arr::get($change, 'reach');
        $metrics['story_replies'] = Arr::get($change, 'replies');
        $metrics['story_taps_forward'] = Arr::get($change, 'taps_forward');
        $metrics['story_taps_back'] = Arr::get($change, 'taps_back');
        $metrics['story_interactions'] = Arr::get($change, 'story_interactions');

        // User/account metrics
        $metrics['followers_count'] = Arr::get($change, 'followers_count');
        $metrics['following_count'] = Arr::get($change, 'following_count');
        $metrics['media_count'] = Arr::get($change, 'media_count');
        $metrics['profile_views'] = Arr::get($change, 'profile_views');
        $metrics['website_clicks'] = Arr::get($change, 'website_clicks');
        $metrics['email_contacts'] = Arr::get($change, 'email_contacts');
        $metrics['phone_call_clicks'] = Arr::get($change, 'phone_call_clicks');
        $metrics['get_directions_clicks'] = Arr::get($change, 'get_directions_clicks');
        $metrics['follower_growth'] = Arr::get($change, 'follower_growth');

        return array_filter($metrics, fn($value) => $value !== null);
    }

    protected function extractPlatformSpecificUserInfo(array $payload): array
    {
        $userInfo = [];
        
        // From messaging events
        $messaging = Arr::get($payload, 'entry.0.messaging.0', []);
        if ($messaging) {
            $userInfo['user_id'] = Arr::get($messaging, 'sender.id');
            $userInfo['user_ref'] = Arr::get($messaging, 'sender.user_ref');
        }

        // From user insights
        $change = Arr::get($payload, 'entry.0.changes.0.value', []);
        if ($change) {
            $userInfo['user_id'] = Arr::get($change, 'user_id');
            $userInfo['username'] = Arr::get($change, 'username');
            $userInfo['account_type'] = Arr::get($change, 'account_type');
            $userInfo['is_business'] = Arr::get($change, 'is_business');
            $userInfo['is_verified'] = Arr::get($change, 'is_verified');
        }

        // From mentions
        if (Arr::get($change, 'mention_id')) {
            $userInfo['mentioner_id'] = Arr::get($change, 'user_id');
            $userInfo['mentioner_username'] = Arr::get($change, 'username');
        }

        return array_filter($userInfo, fn($value) => $value !== null);
    }

    protected function extractPlatformSpecificContentInfo(array $payload): array
    {
        $contentInfo = [];
        $change = Arr::get($payload, 'entry.0.changes.0.value', []);

        // Media content
        $contentInfo['media_type'] = Arr::get($change, 'media_type');
        $contentInfo['media_url'] = Arr::get($change, 'media_url');
        $contentInfo['thumbnail_url'] = Arr::get($change, 'thumbnail_url');
        $contentInfo['permalink'] = Arr::get($change, 'permalink');
        $contentInfo['caption'] = Arr::get($change, 'caption');
        $contentInfo['media_product_type'] = Arr::get($change, 'media_product_type');
        $contentInfo['is_comment_enabled'] = Arr::get($change, 'is_comment_enabled');
        $contentInfo['copyright'] = Arr::get($change, 'copyright');
        $contentInfo['sharing_friction_info'] = Arr::get($change, 'sharing_friction_info');
        $contentInfo['timestamp'] = Arr::get($change, 'timestamp');
        $contentInfo['children'] = Arr::get($change, 'children'); // For carousel albums

        // Comment content
        if (Arr::get($change, 'comment_id')) {
            $contentInfo['comment_id'] = Arr::get($change, 'comment_id');
            $contentInfo['comment_text'] = Arr::get($change, 'text');
            $contentInfo['comment_like_count'] = Arr::get($change, 'like_count');
            $contentInfo['comment_replies'] = Arr::get($change, 'replies');
            $contentInfo['comment_hidden'] = Arr::get($change, 'hidden');
            $contentInfo['comment_user_id'] = Arr::get($change, 'user_id');
            $contentInfo['comment_username'] = Arr::get($change, 'username');
        }

        // Story content
        if (Arr::get($change, 'story_id')) {
            $contentInfo['story_id'] = Arr::get($change, 'story_id');
            $contentInfo['story_type'] = Arr::get($change, 'story_type');
            $contentInfo['story_url'] = Arr::get($change, 'story_url');
            $contentInfo['story_expires_at'] = Arr::get($change, 'expires_at');
        }

        // Message content
        $messaging = Arr::get($payload, 'entry.0.messaging.0', []);
        if ($messaging) {
            $message = Arr::get($messaging, 'message', []);
            $contentInfo['message_text'] = Arr::get($message, 'text');
            $contentInfo['message_attachments'] = Arr::get($message, 'attachments');
            $contentInfo['message_reactions'] = Arr::get($message, 'reactions');
            $contentInfo['message_share'] = Arr::get($message, 'share');
            $contentInfo['message_sticker_id'] = Arr::get($message, 'sticker_id');
        }

        // User bio and profile info
        $contentInfo['biography'] = Arr::get($change, 'biography');
        $contentInfo['website'] = Arr::get($change, 'website');
        $contentInfo['profile_picture_url'] = Arr::get($change, 'profile_picture_url');

        return array_filter($contentInfo, fn($value) => $value !== null);
    }
}