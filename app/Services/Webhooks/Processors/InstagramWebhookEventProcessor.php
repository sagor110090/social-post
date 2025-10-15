<?php

namespace App\Services\Webhooks\Processors;

use App\Services\Webhooks\Normalizers\EventNormalizerInterface;
use App\Services\Webhooks\Analytics\AnalyticsUpdaterInterface;
use App\Services\Webhooks\Notifications\NotificationHandlerInterface;
use Illuminate\Support\Facades\Log;

class InstagramWebhookEventProcessor extends BaseWebhookEventProcessor
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
        $mediaId = $event['object_id'];
        $contentInfo = $event['content_info'];
        $metrics = $event['engagement_metrics'];
        
        $this->executeInTransaction(function () use ($mediaId, $contentInfo, $metrics) {
            $analytics = $this->getOrCreatePostAnalytics($mediaId);
            
            if ($analytics) {
                $this->updateAnalytics($mediaId, $this->webhookEvent->platform, [
                    'media_type' => $contentInfo['media_type'] ?? 'image',
                    'caption' => $contentInfo['text'] ?? '',
                    'media_url' => $contentInfo['media_url'] ?? '',
                    'likes' => $metrics['likes'] ?? 0,
                    'comments' => $metrics['comments'] ?? 0,
                    'media_created_at' => $event['received_at'],
                ]);
            }
        });

        Log::info('Instagram media created', [
            'media_id' => $mediaId,
            'media_type' => $contentInfo['media_type'] ?? 'unknown',
            'webhook_event_id' => $this->webhookEvent->id,
        ]);
    }

    protected function handlePostUpdated(array $event): void
    {
        $mediaId = $event['object_id'];
        $contentInfo = $event['content_info'];
        
        $this->updateAnalytics($mediaId, $this->webhookEvent->platform, [
            'caption' => $contentInfo['text'] ?? '',
            'updated_at' => now(),
            'last_update_type' => 'caption_modified',
        ]);

        Log::info('Instagram media updated', [
            'media_id' => $mediaId,
            'webhook_event_id' => $this->webhookEvent->id,
        ]);
    }

    protected function handlePostDeleted(array $event): void
    {
        $mediaId = $event['object_id'];
        
        $this->executeInTransaction(function () use ($mediaId) {
            $analytics = $this->getOrCreatePostAnalytics($mediaId);
            
            if ($analytics) {
                $analytics->update([
                    'metrics->deleted' => true,
                    'metrics->deleted_at' => now()->toISOString(),
                    'metrics->deletion_reason' => 'webhook_notification',
                ]);
            }
        });

        Log::info('Instagram media deleted', [
            'media_id' => $mediaId,
            'webhook_event_id' => $this->webhookEvent->id,
        ]);
    }

    protected function handlePostEngagement(array $event): void
    {
        $mediaId = $event['object_id'];
        $metrics = $event['engagement_metrics'];
        
        $this->updateAnalytics($mediaId, $this->webhookEvent->platform, $metrics);

        // Check for engagement milestones
        $this->checkEngagementMilestones($metrics, $event);

        // Check for viral content
        $this->checkViralContent($metrics, $event);

        Log::info('Instagram media engagement updated', [
            'media_id' => $mediaId,
            'metrics' => $metrics,
            'webhook_event_id' => $this->webhookEvent->id,
        ]);
    }

    protected function handlePostMetricsUpdated(array $event): void
    {
        $mediaId = $event['object_id'];
        $metrics = $event['engagement_metrics'];
        
        // Add Instagram-specific metrics
        $instagramMetrics = array_merge($metrics, [
            'saves' => $this->webhookEvent->getPlatformData('entry.0.changes.0.value.saved_count'),
            'carousel_album_engagement' => $this->webhookEvent->getPlatformData('entry.0.changes.0.value.carousel_album_engagement'),
            'video_views' => $this->webhookEvent->getPlatformData('entry.0.changes.0.value.video_views'),
            'video_thumbnails_played' => $this->webhookEvent->getPlatformData('entry.0.changes.0.value.video_thumbnails_played'),
            'video_avg_time_watched' => $this->webhookEvent->getPlatformData('entry.0.changes.0.value.video_avg_time_watched'),
            'reach' => $this->webhookEvent->getPlatformData('entry.0.changes.0.value.reach'),
            'impressions' => $this->webhookEvent->getPlatformData('entry.0.changes.0.value.impressions'),
        ]);

        $this->updateAnalytics($mediaId, $this->webhookEvent->platform, $instagramMetrics);

        Log::info('Instagram media metrics updated', [
            'media_id' => $mediaId,
            'metrics' => $instagramMetrics,
            'webhook_event_id' => $this->webhookEvent->id,
        ]);
    }

    protected function handleCommentCreated(array $event): void
    {
        $mediaId = $event['object_id'];
        $commentData = $event['content_info'];
        
        $this->updateAnalytics($mediaId, $this->webhookEvent->platform, [
            'comments' => 1,
            'last_comment_at' => now(),
        ]);

        // Analyze comment sentiment
        $this->analyzeCommentSentiment($commentData, $event);

        Log::info('Instagram comment created', [
            'media_id' => $mediaId,
            'comment_id' => $this->webhookEvent->getPlatformData('entry.0.changes.0.value.comment_id'),
            'webhook_event_id' => $this->webhookEvent->id,
        ]);
    }

    protected function handleCommentUpdated(array $event): void
    {
        Log::info('Instagram comment updated', [
            'webhook_event_id' => $this->webhookEvent->id,
        ]);
    }

    protected function handleCommentDeleted(array $event): void
    {
        $mediaId = $event['object_id'];
        
        $this->updateAnalytics($mediaId, $this->webhookEvent->platform, [
            'comments' => -1,
        ]);

        Log::info('Instagram comment deleted', [
            'media_id' => $mediaId,
            'webhook_event_id' => $this->webhookEvent->id,
        ]);
    }

    protected function handleCommentMention(array $event): void
    {
        $mediaId = $event['object_id'];
        
        $this->updateAnalytics($mediaId, $this->webhookEvent->platform, [
            'mentions' => 1,
        ]);

        $this->sendNotification('mention', [
            'platform' => 'instagram',
            'media_id' => $mediaId,
            'mentioner' => $event['user_info'],
            'content' => $event['content_info']['text'] ?? '',
        ]);

        Log::info('Instagram comment mention', [
            'media_id' => $mediaId,
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

        Log::info('Instagram message received', [
            'sender_id' => $senderInfo['user_id'] ?? null,
            'message_id' => $this->webhookEvent->getPlatformData('entry.0.messaging.0.message.mid'),
            'webhook_event_id' => $this->webhookEvent->id,
        ]);
    }

    protected function handleMessageSent(array $event): void
    {
        $messageData = $event['content_info'];
        $recipientInfo = $event['user_info'];
        
        $this->storeMessageData($messageData, $recipientInfo, 'sent');

        Log::info('Instagram message sent', [
            'recipient_id' => $recipientInfo['user_id'] ?? null,
            'webhook_event_id' => $this->webhookEvent->id,
        ]);
    }

    protected function handleMessageRead(array $event): void
    {
        Log::info('Instagram message read', [
            'webhook_event_id' => $this->webhookEvent->id,
        ]);
    }

    protected function handleUserFollowed(array $event): void
    {
        $this->updateSocialAccountMetadata([
            'followers_count' => $this->webhookEvent->getPlatformData('entry.0.changes.0.value.followers_count'),
            'new_follower_at' => now(),
        ]);

        $this->sendNotification('new_follower', [
            'platform' => 'instagram',
            'follower_info' => $event['user_info'],
        ]);

        Log::info('Instagram user followed', [
            'user_id' => $event['user_info']['user_id'] ?? null,
            'webhook_event_id' => $this->webhookEvent->id,
        ]);
    }

    protected function handleUserUnfollowed(array $event): void
    {
        $this->updateSocialAccountMetadata([
            'followers_count' => $this->webhookEvent->getPlatformData('entry.0.changes.0.value.followers_count'),
            'last_unfollow_at' => now(),
        ]);

        Log::info('Instagram user unfollowed', [
            'user_id' => $event['user_info']['user_id'] ?? null,
            'webhook_event_id' => $this->webhookEvent->id,
        ]);
    }

    protected function handleUserUpdated(array $event): void
    {
        $userData = $this->webhookEvent->getPlatformData('entry.0.changes.0.value', []);
        
        $this->updateSocialAccountMetadata([
            'username' => $userData['username'] ?? null,
            'account_type' => $userData['account_type'] ?? null,
            'followers_count' => $userData['followers_count'] ?? null,
            'following_count' => $userData['following_count'] ?? null,
            'media_count' => $userData['media_count'] ?? null,
            'biography' => $userData['biography'] ?? null,
            'website' => $userData['website'] ?? null,
            'profile_picture_url' => $userData['profile_picture_url'] ?? null,
        ]);

        Log::info('Instagram user updated', [
            'user_data' => $userData,
            'webhook_event_id' => $this->webhookEvent->id,
        ]);
    }

    protected function handleUserMentioned(array $event): void
    {
        $mediaId = $event['object_id'];
        
        $this->sendNotification('mention', [
            'platform' => 'instagram',
            'mentioner' => $event['user_info'],
            'content' => $event['content_info']['text'] ?? '',
            'media_id' => $mediaId,
        ]);

        Log::info('Instagram user mentioned', [
            'media_id' => $mediaId,
            'webhook_event_id' => $this->webhookEvent->id,
        ]);
    }

    protected function handleLeadGenerated(array $event): void
    {
        // Instagram doesn't have traditional lead gen forms like Facebook
        // But can handle story mentions or DM inquiries as leads
        $leadData = $event['content_info'];
        
        $this->executeInTransaction(function () use ($leadData) {
            $this->analyticsUpdater->create([
                'social_account_id' => $this->webhookEvent->social_account_id,
                'platform' => 'instagram',
                'platform_post_id' => $event['object_id'],
                'metrics' => [
                    'leads' => 1,
                    'lead_data' => $leadData,
                    'lead_source' => 'instagram_dm_inquiry',
                ],
                'recorded_at' => now(),
            ]);
        });

        $this->sendNotification('lead_generated', $leadData);

        Log::info('Instagram lead generated', [
            'lead_source' => 'instagram_dm_inquiry',
            'webhook_event_id' => $this->webhookEvent->id,
        ]);
    }

    protected function handleLeadUpdated(array $event): void
    {
        Log::info('Instagram lead updated', [
            'webhook_event_id' => $this->webhookEvent->id,
        ]);
    }

    protected function handleStoryCreated(array $event): void
    {
        $storyId = $event['object_id'];
        $contentInfo = $event['content_info'];
        
        $this->executeInTransaction(function () use ($storyId, $contentInfo) {
            $analytics = $this->getOrCreatePostAnalytics($storyId);
            
            if ($analytics) {
                $this->updateAnalytics($storyId, $this->webhookEvent->platform, [
                    'media_type' => 'story',
                    'story_type' => $contentInfo['media_type'] ?? 'image',
                    'story_created_at' => $event['received_at'],
                ]);
            }
        });

        Log::info('Instagram story created', [
            'story_id' => $storyId,
            'webhook_event_id' => $this->webhookEvent->id,
        ]);
    }

    protected function handleStoryUpdated(array $event): void
    {
        Log::info('Instagram story updated', [
            'webhook_event_id' => $this->webhookEvent->id,
        ]);
    }

    protected function handleStoryDeleted(array $event): void
    {
        $storyId = $event['object_id'];
        
        $analytics = $this->getOrCreatePostAnalytics($storyId);
        if ($analytics) {
            $analytics->update([
                'metrics->deleted' => true,
                'metrics->deleted_at' => now()->toISOString(),
            ]);
        }

        Log::info('Instagram story deleted', [
            'story_id' => $storyId,
            'webhook_event_id' => $this->webhookEvent->id,
        ]);
    }

    protected function handleStoryInsightsUpdated(array $event): void
    {
        $storyId = $event['object_id'];
        $metrics = $event['engagement_metrics'];
        
        $storyMetrics = array_merge($metrics, [
            'exits' => $this->webhookEvent->getPlatformData('entry.0.changes.0.value.exits'),
            'impressions' => $this->webhookEvent->getPlatformData('entry.0.changes.0.value.impressions'),
            'reach' => $this->webhookEvent->getPlatformData('entry.0.changes.0.value.reach'),
            'replies' => $this->webhookEvent->getPlatformData('entry.0.changes.0.value.replies'),
            'taps_forward' => $this->webhookEvent->getPlatformData('entry.0.changes.0.value.taps_forward'),
            'taps_back' => $this->webhookEvent->getPlatformData('entry.0.changes.0.value.taps_back'),
            'story_interactions' => $this->webhookEvent->getPlatformData('entry.0.changes.0.value.story_interactions'),
        ]);

        $this->updateAnalytics($storyId, $this->webhookEvent->platform, $storyMetrics);

        Log::info('Instagram story insights updated', [
            'story_id' => $storyId,
            'metrics' => $storyMetrics,
            'webhook_event_id' => $this->webhookEvent->id,
        ]);
    }

    protected function handleStoryReply(array $event): void
    {
        $storyId = $event['object_id'];
        
        $this->updateAnalytics($storyId, $this->webhookEvent->platform, [
            'replies' => 1,
            'last_reply_at' => now(),
        ]);

        Log::info('Instagram story reply', [
            'story_id' => $storyId,
            'webhook_event_id' => $this->webhookEvent->id,
        ]);
    }

    protected function handleAccountUpdated(array $event): void
    {
        $accountData = $this->webhookEvent->getPlatformData('entry.0.changes.0.value', []);
        
        $this->updateSocialAccountMetadata([
            'username' => $accountData['username'] ?? null,
            'account_type' => $accountData['account_type'] ?? null,
            'followers_count' => $accountData['followers_count'] ?? null,
            'following_count' => $accountData['following_count'] ?? null,
            'media_count' => $accountData['media_count'] ?? null,
            'biography' => $accountData['biography'] ?? null,
            'website' => $accountData['website'] ?? null,
            'profile_picture_url' => $accountData['profile_picture_url'] ?? null,
            'is_business' => $accountData['is_business'] ?? false,
            'is_verified' => $accountData['is_verified'] ?? false,
            'last_updated_at' => now(),
        ]);

        Log::info('Instagram account updated', [
            'account_data' => $accountData,
            'webhook_event_id' => $this->webhookEvent->id,
        ]);
    }

    protected function handleAccountVerified(array $event): void
    {
        $this->updateSocialAccountMetadata([
            'is_verified' => true,
            'verified_at' => now(),
        ]);

        $this->sendNotification('account_verified', [
            'platform' => 'instagram',
            'verification_date' => now(),
        ]);

        Log::info('Instagram account verified', [
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
            'platform' => 'instagram',
            'suspension_date' => now(),
        ]);

        Log::warning('Instagram account suspended', [
            'webhook_event_id' => $this->webhookEvent->id,
        ]);
    }

    protected function handleAccountInsightsUpdated(array $event): void
    {
        $insights = $this->webhookEvent->getPlatformData('entry.0.changes.0.value', []);
        
        $this->updateSocialAccountMetadata([
            'impressions' => $insights['impressions'] ?? null,
            'reach' => $insights['reach'] ?? null,
            'profile_views' => $insights['profile_views'] ?? null,
            'website_clicks' => $insights['website_clicks'] ?? null,
            'email_contacts' => $insights['email_contacts'] ?? null,
            'phone_call_clicks' => $insights['phone_call_clicks'] ?? null,
            'get_directions_clicks' => $insights['get_directions_clicks'] ?? null,
            'follower_growth' => $insights['follower_growth'] ?? null,
            'last_insights_update' => now(),
        ]);

        Log::info('Instagram account insights updated', [
            'insights' => $insights,
            'webhook_event_id' => $this->webhookEvent->id,
        ]);
    }

    /**
     * Check for engagement milestones specific to Instagram.
     */
    protected function checkEngagementMilestones(array $metrics, array $event): void
    {
        $totalEngagement = ($metrics['likes'] ?? 0) + ($metrics['comments'] ?? 0) + ($metrics['shares'] ?? 0) + ($metrics['saves'] ?? 0);
        
        $milestones = [50, 100, 500, 1000, 5000, 10000, 50000, 100000];
        
        foreach ($milestones as $milestone) {
            if ($totalEngagement >= $milestone && $totalEngagement - 1 < $milestone) {
                $this->sendNotification('engagement_milestone', [
                    'milestone' => $milestone,
                    'total_engagement' => $totalEngagement,
                    'media_id' => $event['object_id'],
                    'metrics' => $metrics,
                ]);
                break;
            }
        }
    }

    /**
     * Check for viral content specific to Instagram.
     */
    protected function checkViralContent(array $metrics, array $event): void
    {
        $totalEngagement = ($metrics['likes'] ?? 0) + ($metrics['comments'] ?? 0) + ($metrics['shares'] ?? 0) + ($metrics['saves'] ?? 0);
        $reach = $metrics['reach'] ?? 0;
        
        if ($reach > 0) {
            $engagementRate = ($totalEngagement / $reach) * 100;
            
            // Instagram viral threshold: engagement rate > 5% and reach > 5000
            if ($engagementRate > 5 && $reach > 5000) {
                $this->sendNotification('viral_content', [
                    'engagement_rate' => $engagementRate,
                    'reach' => $reach,
                    'total_engagement' => $totalEngagement,
                    'media_id' => $event['object_id'],
                    'metrics' => $metrics,
                ]);
            }
        }
    }

    /**
     * Analyze comment sentiment for Instagram.
     */
    protected function analyzeCommentSentiment(array $commentData, array $event): void
    {
        $text = $commentData['text'] ?? '';
        
        if (empty($text)) {
            return;
        }

        // Instagram-specific sentiment analysis
        $negativeKeywords = ['bad', 'terrible', 'awful', 'hate', 'worst', 'disappointed', 'fake', 'scam'];
        $positiveKeywords = ['great', 'awesome', 'amazing', 'love', 'best', 'excellent', 'beautiful', 'perfect'];
        
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
                'sentiment_score' => -0.6,
                'content' => $text,
                'media_id' => $event['object_id'],
            ]);
        }
    }

    /**
     * Store message data for Instagram.
     */
    protected function storeMessageData(array $messageData, array $userInfo, string $direction): void
    {
        $messageKey = "ig_message_{$this->webhookEvent->social_account_id}_{$direction}_" . now()->timestamp;
        
        cache()->put($messageKey, [
            'message' => $messageData,
            'user' => $userInfo,
            'direction' => $direction,
            'timestamp' => now(),
        ], now()->addDays(7));
    }

    /**
     * Check for urgent keywords in Instagram messages.
     */
    protected function checkUrgentKeywords(array $messageData, array $event): void
    {
        $text = $messageData['text'] ?? '';
        
        if (empty($text)) {
            return;
        }

        $urgentKeywords = ['urgent', 'emergency', 'asap', 'immediately', 'help', 'problem', 'issue', 'complaint'];
        $lowerText = strtolower($text);
        
        foreach ($urgentKeywords as $keyword) {
            if (str_contains($lowerText, $keyword)) {
                $this->sendNotification('urgent_message', [
                    'message' => $text,
                    'sender' => $event['user_info'],
                    'keyword' => $keyword,
                    'platform' => 'instagram',
                ]);
                break;
            }
        }
    }
}