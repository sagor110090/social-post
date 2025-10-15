<?php

namespace App\Services\Webhooks\Normalizers;

use App\Models\WebhookEvent;
use Illuminate\Support\Arr;

class TwitterEventNormalizer extends BaseEventNormalizer
{
    public function extractEventType(WebhookEvent $webhookEvent): string
    {
        $payload = $webhookEvent->payload;

        // Check for different event types in Twitter webhooks
        if (Arr::has($payload, 'tweet_create_events')) {
            return 'created';
        }

        if (Arr::has($payload, 'tweet_delete_events')) {
            return 'deleted';
        }

        if (Arr::has($payload, 'favorite_events')) {
            return 'engagement';
        }

        if (Arr::has($payload, 'tweet_retweet_events')) {
            return 'engagement';
        }

        if (Arr::has($payload, 'quote_tweet_events')) {
            return 'engagement';
        }

        if (Arr::has($payload, 'follow_events')) {
            return 'followed';
        }

        if (Arr::has($payload, 'unfollow_events')) {
            return 'unfollowed';
        }

        if (Arr::has($payload, 'user_update_events')) {
            return 'updated';
        }

        if (Arr::has($payload, 'direct_message_events')) {
            return 'message_received';
        }

        if (Arr::has($payload, 'tweet_welcome_events')) {
            return 'welcome';
        }

        if (Arr::has($payload, 'tweet_welcome_message_events')) {
            return 'welcome_message';
        }

        return $webhookEvent->event_type;
    }

    public function extractObjectType(WebhookEvent $webhookEvent): string
    {
        $payload = $webhookEvent->payload;

        if (Arr::has($payload, 'tweet_create_events') || 
            Arr::has($payload, 'tweet_delete_events') ||
            Arr::has($payload, 'favorite_events') ||
            Arr::has($payload, 'tweet_retweet_events') ||
            Arr::has($payload, 'quote_tweet_events')) {
            return 'post';
        }

        if (Arr::has($payload, 'follow_events') || 
            Arr::has($payload, 'unfollow_events') ||
            Arr::has($payload, 'user_update_events')) {
            return 'user';
        }

        if (Arr::has($payload, 'direct_message_events')) {
            return 'message';
        }

        return 'unknown';
    }

    public function extractObjectId(WebhookEvent $webhookEvent): ?string
    {
        $payload = $webhookEvent->payload;
        
        // Try multiple paths for object ID
        $paths = [
            'tweet_create_events.0.id_str',
            'tweet_delete_events.0.tweet.id_str',
            'favorite_events.0.favorited_tweet.id_str',
            'tweet_retweet_events.0.retweeted_tweet.id_str',
            'quote_tweet_events.0.quoted_tweet.id_str',
            'follow_events.0.source.id_str',
            'follow_events.0.target.id_str',
            'user_update_events.0.id_str',
            'direct_message_events.0.id',
            'direct_message_events.0.message_create.sender_id',
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

        // Tweet metrics
        if ($tweet = Arr::get($payload, 'tweet_create_events.0')) {
            $metrics['retweet_count'] = Arr::get($tweet, 'retweet_count');
            $metrics['favorite_count'] = Arr::get($tweet, 'favorite_count');
            $metrics['reply_count'] = Arr::get($tweet, 'reply_count');
            $metrics['quote_count'] = Arr::get($tweet, 'quote_count');
            $metrics['lang'] = Arr::get($tweet, 'lang');
            $metrics['possibly_sensitive'] = Arr::get($tweet, 'possibly_sensitive');
            $metrics['withheld_copyright'] = Arr::get($tweet, 'withheld_copyright');
            $metrics['withheld_scope'] = Arr::get($tweet, 'withheld_scope');
            $metrics['withheld_in_countries'] = Arr::get($tweet, 'withheld_in_countries');
        }

        // Engagement metrics
        if ($favorite = Arr::get($payload, 'favorite_events.0')) {
            $metrics['favorite_count'] = Arr::get($favorite, 'favorited_tweet.favorite_count');
            $metrics['favorited_by'] = Arr::get($favorite, 'user.id_str');
        }

        if ($retweet = Arr::get($payload, 'tweet_retweet_events.0')) {
            $metrics['retweet_count'] = Arr::get($retweet, 'retweeted_tweet.retweet_count');
            $metrics['retweeted_by'] = Arr::get($retweet, 'user.id_str');
        }

        if ($quote = Arr::get($payload, 'quote_tweet_events.0')) {
            $metrics['quote_count'] = Arr::get($quote, 'quoted_tweet.quote_count');
            $metrics['quoted_by'] = Arr::get($quote, 'user.id_str');
        }

        // User metrics
        if ($user = Arr::get($payload, 'user_update_events.0')) {
            $metrics['followers_count'] = Arr::get($user, 'followers_count');
            $metrics['following_count'] = Arr::get($user, 'friends_count');
            $metrics['tweets_count'] = Arr::get($user, 'statuses_count');
            $metrics['listed_count'] = Arr::get($user, 'listed_count');
            $metrics['favourites_count'] = Arr::get($user, 'favourites_count');
            $metrics['protected'] = Arr::get($user, 'protected');
            $metrics['verified'] = Arr::get($user, 'verified');
            $metrics['default_profile'] = Arr::get($user, 'default_profile');
            $metrics['default_profile_image'] = Arr::get($user, 'default_profile_image');
        }

        // Follow metrics
        if ($follow = Arr::get($payload, 'follow_events.0')) {
            $metrics['source_followers_count'] = Arr::get($follow, 'source.followers_count');
            $metrics['target_followers_count'] = Arr::get($follow, 'target.followers_count');
            $metrics['follow_event'] = Arr::get($follow, 'event'); // 'follow' or 'unfollow'
        }

        return array_filter($metrics, fn($value) => $value !== null);
    }

    protected function extractPlatformSpecificUserInfo(array $payload): array
    {
        $userInfo = [];

        // From tweet events
        if ($tweet = Arr::get($payload, 'tweet_create_events.0')) {
            $userInfo['user_id'] = Arr::get($tweet, 'user.id_str');
            $userInfo['username'] = Arr::get($tweet, 'user.screen_name');
            $userInfo['name'] = Arr::get($tweet, 'user.name');
            $userInfo['location'] = Arr::get($tweet, 'user.location');
            $userInfo['description'] = Arr::get($tweet, 'user.description');
            $userInfo['url'] = Arr::get($tweet, 'user.url');
            $userInfo['protected'] = Arr::get($tweet, 'user.protected');
            $userInfo['verified'] = Arr::get($tweet, 'user.verified');
            $userInfo['followers_count'] = Arr::get($tweet, 'user.followers_count');
            $userInfo['following_count'] = Arr::get($tweet, 'user.friends_count');
            $userInfo['profile_image_url'] = Arr::get($tweet, 'user.profile_image_url_https');
            $userInfo['profile_banner_url'] = Arr::get($tweet, 'user.profile_banner_url');
        }

        // From follow events
        if ($follow = Arr::get($payload, 'follow_events.0')) {
            $userInfo['source_user_id'] = Arr::get($follow, 'source.id_str');
            $userInfo['source_username'] = Arr::get($follow, 'source.screen_name');
            $userInfo['source_name'] = Arr::get($follow, 'source.name');
            $userInfo['target_user_id'] = Arr::get($follow, 'target.id_str');
            $userInfo['target_username'] = Arr::get($follow, 'target.screen_name');
            $userInfo['target_name'] = Arr::get($follow, 'target.name');
        }

        // From user update events
        if ($user = Arr::get($payload, 'user_update_events.0')) {
            $userInfo['user_id'] = Arr::get($user, 'id_str');
            $userInfo['username'] = Arr::get($user, 'screen_name');
            $userInfo['name'] = Arr::get($user, 'name');
            $userInfo['location'] = Arr::get($user, 'location');
            $userInfo['description'] = Arr::get($user, 'description');
            $userInfo['url'] = Arr::get($user, 'url');
            $userInfo['protected'] = Arr::get($user, 'protected');
            $userInfo['verified'] = Arr::get($user, 'verified');
            $userInfo['followers_count'] = Arr::get($user, 'followers_count');
            $userInfo['following_count'] = Arr::get($user, 'friends_count');
            $userInfo['profile_image_url'] = Arr::get($user, 'profile_image_url_https');
            $userInfo['profile_banner_url'] = Arr::get($user, 'profile_banner_url');
        }

        // From direct message events
        if ($dm = Arr::get($payload, 'direct_message_events.0')) {
            $userInfo['sender_id'] = Arr::get($dm, 'message_create.sender_id');
            $userInfo['target_id'] = Arr::get($dm, 'message_create.target.recipient_id');
            $userInfo['sender_screen_name'] = Arr::get($dm, 'message_create.sender_screen_name');
            $userInfo['target_screen_name'] = Arr::get($dm, 'message_create.target.screen_name');
        }

        return array_filter($userInfo, fn($value) => $value !== null);
    }

    protected function extractPlatformSpecificContentInfo(array $payload): array
    {
        $contentInfo = [];

        // Tweet content
        if ($tweet = Arr::get($payload, 'tweet_create_events.0')) {
            $contentInfo['text'] = Arr::get($tweet, 'text');
            $contentInfo['created_at'] = Arr::get($tweet, 'created_at');
            $contentInfo['lang'] = Arr::get($tweet, 'lang');
            $contentInfo['source'] = Arr::get($tweet, 'source');
            $contentInfo['in_reply_to_status_id'] = Arr::get($tweet, 'in_reply_to_status_id_str');
            $contentInfo['in_reply_to_user_id'] = Arr::get($tweet, 'in_reply_to_user_id_str');
            $contentInfo['in_reply_to_screen_name'] = Arr::get($tweet, 'in_reply_to_screen_name');
            $contentInfo['quoted_status_id'] = Arr::get($tweet, 'quoted_status_id_str');
            $contentInfo['possibly_sensitive'] = Arr::get($tweet, 'possibly_sensitive');
            $contentInfo['is_quote_status'] = Arr::get($tweet, 'is_quote_status');
            $contentInfo['truncated'] = Arr::get($tweet, 'truncated');
            $contentInfo['extended_tweet'] = Arr::get($tweet, 'extended_tweet');
            $contentInfo['entities'] = Arr::get($tweet, 'entities');
            $contentInfo['extended_entities'] = Arr::get($tweet, 'extended_entities');
            $contentInfo['place'] = Arr::get($tweet, 'place');
            $contentInfo['coordinates'] = Arr::get($tweet, 'coordinates');
            $contentInfo['retweeted_status'] = Arr::get($tweet, 'retweeted_status');
            $contentInfo['quoted_status'] = Arr::get($tweet, 'quoted_status');
        }

        // Direct message content
        if ($dm = Arr::get($payload, 'direct_message_events.0')) {
            $message = Arr::get($dm, 'message_create.message_data');
            $contentInfo['message_text'] = Arr::get($message, 'text');
            $contentInfo['message_entities'] = Arr::get($message, 'entities');
            $contentInfo['message_attachment'] = Arr::get($message, 'attachment');
            $contentInfo['message_quick_reply'] = Arr::get($message, 'quick_reply');
            $contentInfo['message_created_at'] = Arr::get($dm, 'created_timestamp');
        }

        // User bio and profile info
        if ($user = Arr::get($payload, 'user_update_events.0')) {
            $contentInfo['description'] = Arr::get($user, 'description');
            $contentInfo['url'] = Arr::get($user, 'url');
            $contentInfo['location'] = Arr::get($user, 'location');
            $contentInfo['profile_image_url'] = Arr::get($user, 'profile_image_url_https');
            $contentInfo['profile_banner_url'] = Arr::get($user, 'profile_banner_url');
            $contentInfo['profile_background_color'] = Arr::get($user, 'profile_background_color');
            $contentInfo['profile_link_color'] = Arr::get($user, 'profile_link_color');
            $contentInfo['profile_sidebar_border_color'] = Arr::get($user, 'profile_sidebar_border_color');
            $contentInfo['profile_sidebar_fill_color'] = Arr::get($user, 'profile_sidebar_fill_color');
            $contentInfo['profile_text_color'] = Arr::get($user, 'profile_text_color');
            $contentInfo['profile_use_background_image'] = Arr::get($user, 'profile_use_background_image');
            $contentInfo['profile_background_image_url'] = Arr::get($user, 'profile_background_image_url_https');
            $contentInfo['profile_background_tile'] = Arr::get($user, 'profile_background_tile');
        }

        return array_filter($contentInfo, fn($value) => $value !== null);
    }
}