<?php

namespace App\Services\Webhooks\Processors;

use App\Services\Webhooks\Normalizers\EventNormalizerInterface;
use App\Services\Webhooks\Analytics\AnalyticsUpdaterInterface;
use App\Services\Webhooks\Notifications\NotificationHandlerInterface;
use Illuminate\Support\Facades\Log;

class TwitterWebhookEventProcessor extends BaseWebhookEventProcessor
{
    public function __construct(
        \App\Models\WebhookEvent $webhookEvent,
        EventNormalizerInterface $normalizer,
        AnalyticsUpdaterInterface $analyticsUpdater,
        NotificationHandlerInterface $notificationHandler
    ) {
        parent::__construct($webhookEvent, $normalizer, $analyticsUpdater, $notificationHandler);
    }

    protected function handlePostCreated(array $event): void
    {
        $tweetId = $event['object_id'];
        $contentInfo = $event['content_info'];
        $metrics = $event['engagement_metrics'];
        
        $this->executeInTransaction(function () use ($tweetId, $contentInfo, $metrics) {
            $analytics = $this->getOrCreatePostAnalytics($tweetId);
            
            if ($analytics) {
                $this->updateAnalytics($tweetId, $this->webhookEvent->platform, [
                    'text' => $contentInfo['text'] ?? '',
                    'tweet_type' => $this->getTweetType($contentInfo),
                    'reply_to_tweet_id' => $this->webhookEvent->getPlatformData('tweet_create_events.0.in_reply_to_status_id_str'),
                    'quoted_tweet_id' => $this->webhookEvent->getPlatformData('tweet_create_events.0.quoted_status_id_str'),
                    'retweet_count' => $metrics['shares'] ?? 0,
                    'favorite_count' => $metrics['likes'] ?? 0,
                    'reply_count' => $metrics['comments'] ?? 0,
                    'quote_count' => $metrics['quotes'] ?? 0,
                    'tweet_created_at' => $contentInfo['created_at'] ?? now(),
                ]);
            }
        });

        // Check for viral potential
        $this->checkViralPotential($contentInfo, $metrics, $event);

        Log::info('Twitter tweet created', [
            'tweet_id' => $tweetId,
            'tweet_type' => $this->getTweetType($contentInfo),
            'webhook_event_id' => $this->webhookEvent->id,
        ]);
    }

    protected function handlePostUpdated(array $event): void
    {
        // Twitter doesn't allow tweet editing, but this could handle other updates
        Log::info('Twitter post updated', [
            'webhook_event_id' => $this->webhookEvent->id,
        ]);
    }

    protected function handlePostDeleted(array $event): void
    {
        $tweetId = $event['object_id'];
        
        $this->executeInTransaction(function () use ($tweetId) {
            $analytics = $this->getOrCreatePostAnalytics($tweetId);
            
            if ($analytics) {
                $analytics->update([
                    'metrics->deleted' => true,
                    'metrics->deleted_at' => now()->toISOString(),
                    'metrics->deletion_reason' => 'webhook_notification',
                ]);
            }
        });

        Log::info('Twitter tweet deleted', [
            'tweet_id' => $tweetId,
            'webhook_event_id' => $this->webhookEvent->id,
        ]);
    }

    protected function handlePostEngagement(array $event): void
    {
        $tweetId = $event['object_id'];
        $metrics = $event['engagement_metrics'];
        
        $this->updateAnalytics($tweetId, $this->webhookEvent->platform, $metrics);

        // Check for engagement milestones
        $this->checkEngagementMilestones($metrics, $event);

        // Check for viral content
        $this->checkViralContent($metrics, $event);

        Log::info('Twitter tweet engagement updated', [
            'tweet_id' => $tweetId,
            'metrics' => $metrics,
            'webhook_event_id' => $this->webhookEvent->id,
        ]);
    }

    protected function handlePostMetricsUpdated(array $event): void
    {
        $tweetId = $event['object_id'];
        $metrics = $event['engagement_metrics'];
        
        // Add Twitter-specific metrics
        $twitterMetrics = array_merge($metrics, [
            'impressions' => $this->webhookEvent->getPlatformData('tweet_create_events.0.impressions'),
            'url_clicks' => $this->webhookEvent->getPlatformData('tweet_create_events.0.url_clicks'),
            'hashtag_clicks' => $this->webhookEvent->getPlatformData('tweet_create_events.0.hashtag_clicks'),
            'detail_expands' => $this->webhookEvent->getPlatformData('tweet_create_events.0.detail_expands'),
            'media_views' => $this->webhookEvent->getPlatformData('tweet_create_events.0.media_views'),
            'media_engagements' => $this->webhookEvent->getPlatformData('tweet_create_events.0.media_engagements'),
            'profile_clicks' => $this->webhookEvent->getPlatformData('tweet_create_events.0.profile_clicks'),
        ]);

        $this->updateAnalytics($tweetId, $this->webhookEvent->platform, $twitterMetrics);

        Log::info('Twitter tweet metrics updated', [
            'tweet_id' => $tweetId,
            'metrics' => $twitterMetrics,
            'webhook_event_id' => $this->webhookEvent->id,
        ]);
    }

    protected function handleCommentCreated(array $event): void
    {
        $tweetId = $event['object_id'];
        $commentData = $event['content_info'];
        
        $this->updateAnalytics($tweetId, $this->webhookEvent->platform, [
            'reply_count' => 1,
            'last_reply_at' => now(),
        ]);

        // Analyze reply sentiment
        $this->analyzeReplySentiment($commentData, $event);

        Log::info('Twitter reply created', [
            'tweet_id' => $tweetId,
            'reply_id' => $this->webhookEvent->getPlatformData('tweet_create_events.0.id_str'),
            'webhook_event_id' => $this->webhookEvent->id,
        ]);
    }

    protected function handleCommentUpdated(array $event): void
    {
        Log::info('Twitter reply updated', [
            'webhook_event_id' => $this->webhookEvent->id,
        ]);
    }

    protected function handleCommentDeleted(array $event): void
    {
        $tweetId = $event['object_id'];
        
        $this->updateAnalytics($tweetId, $this->webhookEvent->platform, [
            'reply_count' => -1,
        ]);

        Log::info('Twitter reply deleted', [
            'tweet_id' => $tweetId,
            'webhook_event_id' => $this->webhookEvent->id,
        ]);
    }

    protected function handleCommentMention(array $event): void
    {
        $tweetId = $event['object_id'];
        
        $this->updateAnalytics($tweetId, $this->webhookEvent->platform, [
            'mentions' => 1,
        ]);

        $this->sendNotification('mention', [
            'platform' => 'twitter',
            'tweet_id' => $tweetId,
            'mentioner' => $event['user_info'],
            'content' => $event['content_info']['text'] ?? '',
        ]);

        Log::info('Twitter mention', [
            'tweet_id' => $tweetId,
            'webhook_event_id' => $this->webhookEvent->id,
        ]);
    }

    protected function handleMessageReceived(array $event): void
    {
        $messageData = $event['content_info'];
        $senderInfo = $event['user_info'];
        
        $this->storeMessageData($messageData, $senderInfo, 'received');

        // Check for urgent keywords
        $this->checkUrgentKeywords($messageData, $event);

        Log::info('Twitter direct message received', [
            'sender_id' => $senderInfo['user_id'] ?? null,
            'message_id' => $this->webhookEvent->getPlatformData('direct_message_events.0.id'),
            'webhook_event_id' => $this->webhookEvent->id,
        ]);
    }

    protected function handleMessageSent(array $event): void
    {
        $messageData = $event['content_info'];
        $recipientInfo = $event['user_info'];
        
        $this->storeMessageData($messageData, $recipientInfo, 'sent');

        Log::info('Twitter direct message sent', [
            'recipient_id' => $recipientInfo['user_id'] ?? null,
            'webhook_event_id' => $this->webhookEvent->id,
        ]);
    }

    protected function handleMessageRead(array $event): void
    {
        Log::info('Twitter direct message read', [
            'webhook_event_id' => $this->webhookEvent->id,
        ]);
    }

    protected function handleUserFollowed(array $event): void
    {
        $followerInfo = $event['user_info'];
        $targetInfo = $this->webhookEvent->getPlatformData('follow_events.0.target', []);
        
        $this->updateSocialAccountMetadata([
            'followers_count' => $targetInfo['followers_count'] ?? null,
            'new_follower_at' => now(),
        ]);

        $this->sendNotification('new_follower', [
            'platform' => 'twitter',
            'follower_info' => $followerInfo,
        ]);

        Log::info('Twitter user followed', [
            'follower_id' => $followerInfo['user_id'] ?? null,
            'target_id' => $targetInfo['id_str'] ?? null,
            'webhook_event_id' => $this->webhookEvent->id,
        ]);
    }

    protected function handleUserUnfollowed(array $event): void
    {
        $followerInfo = $event['user_info'];
        $targetInfo = $this->webhookEvent->getPlatformData('follow_events.0.target', []);
        
        $this->updateSocialAccountMetadata([
            'followers_count' => $targetInfo['followers_count'] ?? null,
            'last_unfollow_at' => now(),
        ]);

        Log::info('Twitter user unfollowed', [
            'follower_id' => $followerInfo['user_id'] ?? null,
            'target_id' => $targetInfo['id_str'] ?? null,
            'webhook_event_id' => $this->webhookEvent->id,
        ]);
    }

    protected function handleUserUpdated(array $event): void
    {
        $userData = $this->webhookEvent->getPlatformData('user_update_events.0', []);
        
        $this->updateSocialAccountMetadata([
            'username' => $userData['screen_name'] ?? null,
            'name' => $userData['name'] ?? null,
            'description' => $userData['description'] ?? null,
            'location' => $userData['location'] ?? null,
            'url' => $userData['url'] ?? null,
            'protected' => $userData['protected'] ?? false,
            'verified' => $userData['verified'] ?? false,
            'followers_count' => $userData['followers_count'] ?? null,
            'following_count' => $userData['friends_count'] ?? null,
            'tweets_count' => $userData['statuses_count'] ?? null,
            'profile_image_url' => $userData['profile_image_url_https'] ?? null,
        ]);

        Log::info('Twitter user updated', [
            'user_data' => $userData,
            'webhook_event_id' => $this->webhookEvent->id,
        ]);
    }

    protected function handleUserMentioned(array $event): void
    {
        $tweetId = $event['object_id'];
        
        $this->sendNotification('mention', [
            'platform' => 'twitter',
            'mentioner' => $event['user_info'],
            'content' => $event['content_info']['text'] ?? '',
            'tweet_id' => $tweetId,
        ]);

        Log::info('Twitter user mentioned', [
            'tweet_id' => $tweetId,
            'webhook_event_id' => $this->webhookEvent->id,
        ]);
    }

    protected function handleLeadGenerated(array $event): void
    {
        // Twitter leads typically come from DM inquiries or tweet interactions
        $leadData = $event['content_info'];
        
        $this->executeInTransaction(function () use ($leadData) {
            $this->analyticsUpdater->create([
                'social_account_id' => $this->webhookEvent->social_account_id,
                'platform' => 'twitter',
                'platform_post_id' => $event['object_id'],
                'metrics' => [
                    'leads' => 1,
                    'lead_data' => $leadData,
                    'lead_source' => 'twitter_dm_inquiry',
                ],
                'recorded_at' => now(),
            ]);
        });

        $this->sendNotification('lead_generated', $leadData);

        Log::info('Twitter lead generated', [
            'lead_source' => 'twitter_dm_inquiry',
            'webhook_event_id' => $this->webhookEvent->id,
        ]);
    }

    protected function handleLeadUpdated(array $event): void
    {
        Log::info('Twitter lead updated', [
            'webhook_event_id' => $this->webhookEvent->id,
        ]);
    }

    protected function handleStoryCreated(array $event): void
    {
        // Twitter doesn't have stories like Instagram, but has Fleets (if still available)
        Log::info('Twitter story created', [
            'webhook_event_id' => $this->webhookEvent->id,
        ]);
    }

    protected function handleStoryUpdated(array $event): void
    {
        Log::info('Twitter story updated', [
            'webhook_event_id' => $this->webhookEvent->id,
        ]);
    }

    protected function handleStoryDeleted(array $event): void
    {
        Log::info('Twitter story deleted', [
            'webhook_event_id' => $this->webhookEvent->id,
        ]);
    }

    protected function handleStoryInsightsUpdated(array $event): void
    {
        Log::info('Twitter story insights updated', [
            'webhook_event_id' => $this->webhookEvent->id,
        ]);
    }

    protected function handleStoryReply(array $event): void
    {
        Log::info('Twitter story reply', [
            'webhook_event_id' => $this->webhookEvent->id,
        ]);
    }

    protected function handleAccountUpdated(array $event): void
    {
        $accountData = $this->webhookEvent->getPlatformData('account_update', []);
        
        $this->updateSocialAccountMetadata([
            'username' => $accountData['screen_name'] ?? null,
            'name' => $accountData['name'] ?? null,
            'description' => $accountData['description'] ?? null,
            'location' => $accountData['location'] ?? null,
            'url' => $accountData['url'] ?? null,
            'protected' => $accountData['protected'] ?? false,
            'verified' => $accountData['verified'] ?? false,
            'followers_count' => $accountData['followers_count'] ?? null,
            'following_count' => $accountData['friends_count'] ?? null,
            'tweets_count' => $accountData['statuses_count'] ?? null,
            'profile_image_url' => $accountData['profile_image_url_https'] ?? null,
            'profile_banner_url' => $accountData['profile_banner_url'] ?? null,
            'last_updated_at' => now(),
        ]);

        Log::info('Twitter account updated', [
            'account_data' => $accountData,
            'webhook_event_id' => $this->webhookEvent->id,
        ]);
    }

    protected function handleAccountVerified(array $event): void
    {
        $this->updateSocialAccountMetadata([
            'verified' => true,
            'verified_at' => now(),
        ]);

        $this->sendNotification('account_verified', [
            'platform' => 'twitter',
            'verification_date' => now(),
        ]);

        Log::info('Twitter account verified', [
            'webhook_event_id' => $this->webhookEvent->id,
        ]);
    }

    protected function handleAccountSuspended(array $event): void
    {
        $this->updateSocialAccountMetadata([
            'suspended' => true,
            'suspended_at' => now(),
        ]);

        $this->sendNotification('account_suspended', [
            'platform' => 'twitter',
            'suspension_date' => now(),
        ]);

        Log::warning('Twitter account suspended', [
            'webhook_event_id' => $this->webhookEvent->id,
        ]);
    }

    protected function handleAccountInsightsUpdated(array $event): void
    {
        $insights = $this->webhookEvent->getPlatformData('account_insights', []);
        
        $this->updateSocialAccountMetadata([
            'impressions' => $insights['impressions'] ?? null,
            'profile_views' => $insights['profile_views'] ?? null,
            'mentions' => $insights['mentions'] ?? null,
            'followers_growth' => $insights['followers_growth'] ?? null,
            'engagement_rate' => $insights['engagement_rate'] ?? null,
            'tweet_impressions' => $insights['tweet_impressions'] ?? null,
            'account_followers' => $insights['account_followers'] ?? null,
            'last_insights_update' => now(),
        ]);

        Log::info('Twitter account insights updated', [
            'insights' => $insights,
            'webhook_event_id' => $this->webhookEvent->id,
        ]);
    }

    /**
     * Get tweet type from content info.
     */
    protected function getTweetType(array $contentInfo): string
    {
        $text = $contentInfo['text'] ?? '';
        
        if (str_starts_with($text, 'RT @')) {
            return 'retweet';
        }
        
        if ($this->webhookEvent->getPlatformData('tweet_create_events.0.quoted_status_id_str')) {
            return 'quote';
        }
        
        if ($this->webhookEvent->getPlatformData('tweet_create_events.0.in_reply_to_status_id_str')) {
            return 'reply';
        }
        
        return 'original';
    }

    /**
     * Check for viral potential based on content and metrics.
     */
    protected function checkViralPotential(array $contentInfo, array $metrics, array $event): void
    {
        $text = $contentInfo['text'] ?? '';
        
        // Check for viral indicators
        $viralIndicators = [
            'hashtags' => substr_count($text, '#'),
            'mentions' => substr_count($text, '@'),
            'urls' => substr_count($text, 'http'),
            'has_media' => !empty($contentInfo['media_url']),
            'is_thread' => $this->webhookEvent->getPlatformData('tweet_create_events.0.threaded') === true,
        ];

        $viralScore = 0;
        
        // Score based on indicators
        $viralScore += min($viralIndicators['hashtags'] * 2, 10);
        $viralScore += min($viralIndicators['mentions'] * 1, 5);
        $viralScore += min($viralIndicators['urls'] * 3, 6);
        $viralScore += $viralIndicators['has_media'] ? 5 : 0;
        $viralScore += $viralIndicators['is_thread'] ? 3 : 0;

        // Check for trending hashtags
        $trendingHashtags = $this->getTrendingHashtags();
        foreach ($trendingHashtags as $hashtag) {
            if (str_contains(strtolower($text), strtolower($hashtag))) {
                $viralScore += 10;
                break;
            }
        }

        if ($viralScore > 15) {
            $this->sendNotification('viral_potential', [
                'viral_score' => $viralScore,
                'indicators' => $viralIndicators,
                'tweet_id' => $event['object_id'],
                'content' => $text,
            ]);
        }
    }

    /**
     * Check for engagement milestones specific to Twitter.
     */
    protected function checkEngagementMilestones(array $metrics, array $event): void
    {
        $totalEngagement = ($metrics['likes'] ?? 0) + ($metrics['shares'] ?? 0) + ($metrics['comments'] ?? 0) + ($metrics['quotes'] ?? 0);
        
        $milestones = [100, 500, 1000, 5000, 10000, 50000, 100000];
        
        foreach ($milestones as $milestone) {
            if ($totalEngagement >= $milestone && $totalEngagement - 1 < $milestone) {
                $this->sendNotification('engagement_milestone', [
                    'milestone' => $milestone,
                    'total_engagement' => $totalEngagement,
                    'tweet_id' => $event['object_id'],
                    'metrics' => $metrics,
                ]);
                break;
            }
        }
    }

    /**
     * Check for viral content specific to Twitter.
     */
    protected function checkViralContent(array $metrics, array $event): void
    {
        $totalEngagement = ($metrics['likes'] ?? 0) + ($metrics['shares'] ?? 0) + ($metrics['comments'] ?? 0) + ($metrics['quotes'] ?? 0);
        $impressions = $metrics['impressions'] ?? 0;
        
        if ($impressions > 0) {
            $engagementRate = ($totalEngagement / $impressions) * 100;
            
            // Twitter viral threshold: engagement rate > 3% and impressions > 10000
            if ($engagementRate > 3 && $impressions > 10000) {
                $this->sendNotification('viral_content', [
                    'engagement_rate' => $engagementRate,
                    'impressions' => $impressions,
                    'total_engagement' => $totalEngagement,
                    'tweet_id' => $event['object_id'],
                    'metrics' => $metrics,
                ]);
            }
        }
    }

    /**
     * Analyze reply sentiment for Twitter.
     */
    protected function analyzeReplySentiment(array $replyData, array $event): void
    {
        $text = $replyData['text'] ?? '';
        
        if (empty($text)) {
            return;
        }

        // Twitter-specific sentiment analysis
        $negativeKeywords = ['bad', 'terrible', 'awful', 'hate', 'worst', 'disappointed', 'fake', 'scam', 'spam'];
        $positiveKeywords = ['great', 'awesome', 'amazing', 'love', 'best', 'excellent', 'perfect', 'brilliant'];
        
        $lowerText = strtolower($text);
        $negativeCount = 0;
        $positiveCount = 0;
        
        foreach ($negativeKeywords as $keyword) {
            if (str_contains($lowerText, $keyword)) {
                $negativeCount++;
            }
        }
        
        foreach ($positiveKeywords as $keyword) {
            if (str_contains($lowerText, $keyword)) {
                $positiveCount++;
            }
        }
        
        if ($negativeCount > $positiveCount && $negativeCount > 0) {
            $this->sendNotification('negative_sentiment', [
                'sentiment_score' => -0.7,
                'content' => $text,
                'tweet_id' => $event['object_id'],
            ]);
        }
    }

    /**
     * Store message data for Twitter.
     */
    protected function storeMessageData(array $messageData, array $userInfo, string $direction): void
    {
        $messageKey = "tw_message_{$this->webhookEvent->social_account_id}_{$direction}_" . now()->timestamp;
        
        cache()->put($messageKey, [
            'message' => $messageData,
            'user' => $userInfo,
            'direction' => $direction,
            'timestamp' => now(),
        ], now()->addDays(7));
    }

    /**
     * Check for urgent keywords in Twitter messages.
     */
    protected function checkUrgentKeywords(array $messageData, array $event): void
    {
        $text = $messageData['text'] ?? '';
        
        if (empty($text)) {
            return;
        }

        $urgentKeywords = ['urgent', 'emergency', 'asap', 'immediately', 'help', 'problem', 'issue', 'complaint', 'crisis'];
        $lowerText = strtolower($text);
        
        foreach ($urgentKeywords as $keyword) {
            if (str_contains($lowerText, $keyword)) {
                $this->sendNotification('urgent_message', [
                    'message' => $text,
                    'sender' => $event['user_info'],
                    'keyword' => $keyword,
                    'platform' => 'twitter',
                ]);
                break;
            }
        }
    }

    /**
     * Get trending hashtags (mock implementation).
     */
    protected function getTrendingHashtags(): array
    {
        // In a real implementation, this would call Twitter API
        // For now, return some common trending topics
        return [
            '#trending',
            '#viral',
            '#breaking',
            '#news',
        ];
    }
}