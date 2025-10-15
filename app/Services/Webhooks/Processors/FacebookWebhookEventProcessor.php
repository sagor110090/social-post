<?php

namespace App\Services\Webhooks\Processors;

use App\Services\Webhooks\Normalizers\EventNormalizerInterface;
use App\Services\Webhooks\Analytics\AnalyticsUpdaterInterface;
use App\Services\Webhooks\Notifications\NotificationHandlerInterface;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

class FacebookWebhookEventProcessor extends BaseWebhookEventProcessor
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
        $postId = $event['object_id'];
        $metrics = $event['engagement_metrics'];
        
        $this->executeInTransaction(function () use ($postId, $metrics, $event) {
            $analytics = $this->getOrCreatePostAnalytics($postId);
            
            if ($analytics) {
                $this->updateAnalytics($postId, $this->webhookEvent->platform, [
                    'likes' => $metrics['likes'] ?? 0,
                    'comments' => $metrics['comments'] ?? 0,
                    'shares' => $metrics['shares'] ?? 0,
                    'content_type' => $event['content_info']['media_type'] ?? 'status',
                    'post_created_at' => $event['received_at'],
                ]);
            }

            // Check for immediate engagement milestones
            $this->checkEngagementMilestones($metrics, $event);
        });

        Log::info('Facebook post created', [
            'post_id' => $postId,
            'webhook_event_id' => $this->webhookEvent->id,
        ]);
    }

    protected function handlePostUpdated(array $event): void
    {
        $postId = $event['object_id'];
        
        $this->updateAnalytics($postId, $this->webhookEvent->platform, [
            'updated_at' => now(),
            'last_update_type' => 'content_modified',
        ]);

        Log::info('Facebook post updated', [
            'post_id' => $postId,
            'webhook_event_id' => $this->webhookEvent->id,
        ]);
    }

    protected function handlePostDeleted(array $event): void
    {
        $postId = $event['object_id'];
        
        $this->executeInTransaction(function () use ($postId) {
            $analytics = $this->getOrCreatePostAnalytics($postId);
            
            if ($analytics) {
                $analytics->update([
                    'metrics->deleted' => true,
                    'metrics->deleted_at' => now()->toISOString(),
                    'metrics->deletion_reason' => 'webhook_notification',
                ]);
            }
        });

        Log::info('Facebook post deleted', [
            'post_id' => $postId,
            'webhook_event_id' => $this->webhookEvent->id,
        ]);
    }

    protected function handlePostEngagement(array $event): void
    {
        $postId = $event['object_id'];
        $metrics = $event['engagement_metrics'];
        
        $this->updateAnalytics($postId, $this->webhookEvent->platform, $metrics);

        // Check for engagement milestones
        $this->checkEngagementMilestones($metrics, $event);

        // Check for viral content
        $this->checkViralContent($metrics, $event);

        Log::info('Facebook post engagement updated', [
            'post_id' => $postId,
            'metrics' => $metrics,
            'webhook_event_id' => $this->webhookEvent->id,
        ]);
    }

    protected function handlePostMetricsUpdated(array $event): void
    {
        $postId = $event['object_id'];
        $metrics = $event['engagement_metrics'];
        
        // Add Facebook-specific metrics
        $facebookMetrics = array_merge($metrics, [
            'video_views' => $this->webhookEvent->getPlatformData('entry.0.changes.0.value.video_views'),
            'video_avg_watch_time' => $this->webhookEvent->getPlatformData('entry.0.changes.0.value.video_avg_watch_time'),
            'page_impressions' => $this->webhookEvent->getPlatformData('entry.0.changes.0.value.page_impressions'),
            'post_clicks' => $this->webhookEvent->getPlatformData('entry.0.changes.0.value.post_clicks'),
        ]);

        $this->updateAnalytics($postId, $this->webhookEvent->platform, $facebookMetrics);

        Log::info('Facebook post metrics updated', [
            'post_id' => $postId,
            'metrics' => $facebookMetrics,
            'webhook_event_id' => $this->webhookEvent->id,
        ]);
    }

    protected function handleCommentCreated(array $event): void
    {
        $postId = $event['object_id'];
        $commentData = $event['content_info'];
        
        $this->updateAnalytics($postId, $this->webhookEvent->platform, [
            'comments' => 1, // Increment comment count
            'last_comment_at' => now(),
        ]);

        // Analyze comment sentiment
        $this->analyzeCommentSentiment($commentData, $event);

        Log::info('Facebook comment created', [
            'post_id' => $postId,
            'comment_id' => $this->webhookEvent->getPlatformData('entry.0.changes.0.value.comment_id'),
            'webhook_event_id' => $this->webhookEvent->id,
        ]);
    }

    protected function handleCommentUpdated(array $event): void
    {
        // Comments are typically not updated in Facebook, but handle if needed
        Log::info('Facebook comment updated', [
            'webhook_event_id' => $this->webhookEvent->id,
        ]);
    }

    protected function handleCommentDeleted(array $event): void
    {
        $postId = $event['object_id'];
        
        $this->updateAnalytics($postId, $this->webhookEvent->platform, [
            'comments' => -1, // Decrement comment count
        ]);

        Log::info('Facebook comment deleted', [
            'post_id' => $postId,
            'webhook_event_id' => $this->webhookEvent->id,
        ]);
    }

    protected function handleCommentMention(array $event): void
    {
        $postId = $event['object_id'];
        
        $this->updateAnalytics($postId, $this->webhookEvent->platform, [
            'mentions' => 1,
        ]);

        // Send notification for mentions
        $this->sendNotification('mention', [
            'platform' => 'facebook',
            'post_id' => $postId,
            'mentioner' => $event['user_info'],
            'content' => $event['content_info']['text'] ?? '',
        ]);

        Log::info('Facebook comment mention', [
            'post_id' => $postId,
            'webhook_event_id' => $this->webhookEvent->id,
        ]);
    }

    protected function handleMessageReceived(array $event): void
    {
        $messageData = $event['content_info'];
        $senderInfo = $event['user_info'];
        
        // Store message for potential analysis
        $this->storeMessageData($messageData, $senderInfo, 'received');

        // Check for urgent keywords
        $this->checkUrgentKeywords($messageData, $event);

        Log::info('Facebook message received', [
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

        Log::info('Facebook message sent', [
            'recipient_id' => $recipientInfo['user_id'] ?? null,
            'webhook_event_id' => $this->webhookEvent->id,
        ]);
    }

    protected function handleMessageRead(array $event): void
    {
        // Update message read status
        Log::info('Facebook message read', [
            'webhook_event_id' => $this->webhookEvent->id,
        ]);
    }

    protected function handleUserFollowed(array $event): void
    {
        // Facebook doesn't have "follow" but has "page like"
        $this->updateSocialAccountMetadata([
            'page_likes' => $this->webhookEvent->getPlatformData('entry.0.changes.0.value.page_likes'),
            'new_follower_at' => now(),
        ]);

        $this->sendNotification('new_follower', [
            'platform' => 'facebook',
            'follower_info' => $event['user_info'],
        ]);

        Log::info('Facebook page liked', [
            'user_id' => $event['user_info']['user_id'] ?? null,
            'webhook_event_id' => $this->webhookEvent->id,
        ]);
    }

    protected function handleUserUnfollowed(array $event): void
    {
        $this->updateSocialAccountMetadata([
            'page_unlikes' => ($this->webhookEvent->socialAccount->metadata['page_unlikes'] ?? 0) + 1,
            'last_unfollow_at' => now(),
        ]);

        Log::info('Facebook page unliked', [
            'user_id' => $event['user_info']['user_id'] ?? null,
            'webhook_event_id' => $this->webhookEvent->id,
        ]);
    }

    protected function handleUserUpdated(array $event): void
    {
        // Handle user profile updates
        Log::info('Facebook user updated', [
            'user_id' => $event['user_info']['user_id'] ?? null,
            'webhook_event_id' => $this->webhookEvent->id,
        ]);
    }

    protected function handleUserMentioned(array $event): void
    {
        $this->sendNotification('mention', [
            'platform' => 'facebook',
            'mentioner' => $event['user_info'],
            'content' => $event['content_info']['text'] ?? '',
            'post_id' => $event['object_id'],
        ]);

        Log::info('Facebook user mentioned', [
            'post_id' => $event['object_id'],
            'webhook_event_id' => $this->webhookEvent->id,
        ]);
    }

    protected function handleLeadGenerated(array $event): void
    {
        $leadData = $this->webhookEvent->getPlatformData('entry.0.changes.0.value', []);
        
        $this->executeInTransaction(function () use ($leadData) {
            // Create special analytics record for lead
            $this->analyticsUpdater->create([
                'social_account_id' => $this->webhookEvent->social_account_id,
                'platform' => 'facebook',
                'platform_post_id' => $leadData['leadgen_id'] ?? null,
                'metrics' => [
                    'leads' => 1,
                    'lead_data' => $leadData,
                    'lead_source' => 'facebook_lead_form',
                ],
                'recorded_at' => now(),
            ]);
        });

        $this->sendNotification('lead_generated', $leadData);

        Log::info('Facebook lead generated', [
            'lead_id' => $leadData['leadgen_id'] ?? null,
            'webhook_event_id' => $this->webhookEvent->id,
        ]);
    }

    protected function handleLeadUpdated(array $event): void
    {
        Log::info('Facebook lead updated', [
            'webhook_event_id' => $this->webhookEvent->id,
        ]);
    }

    protected function handleStoryCreated(array $event): void
    {
        $storyId = $event['object_id'];
        
        $this->getOrCreatePostAnalytics($storyId);

        Log::info('Facebook story created', [
            'story_id' => $storyId,
            'webhook_event_id' => $this->webhookEvent->id,
        ]);
    }

    protected function handleStoryUpdated(array $event): void
    {
        Log::info('Facebook story updated', [
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

        Log::info('Facebook story deleted', [
            'story_id' => $storyId,
            'webhook_event_id' => $this->webhookEvent->id,
        ]);
    }

    protected function handleStoryInsightsUpdated(array $event): void
    {
        $storyId = $event['object_id'];
        $metrics = $event['engagement_metrics'];
        
        $storyMetrics = array_merge($metrics, [
            'story_exits' => $this->webhookEvent->getPlatformData('entry.0.changes.0.value.story_exits'),
            'story_taps_forward' => $this->webhookEvent->getPlatformData('entry.0.changes.0.value.story_taps_forward'),
            'story_taps_back' => $this->webhookEvent->getPlatformData('entry.0.changes.0.value.story_taps_back'),
            'story_replies' => $this->webhookEvent->getPlatformData('entry.0.changes.0.value.story_replies'),
        ]);

        $this->updateAnalytics($storyId, $this->webhookEvent->platform, $storyMetrics);

        Log::info('Facebook story insights updated', [
            'story_id' => $storyId,
            'metrics' => $storyMetrics,
            'webhook_event_id' => $this->webhookEvent->id,
        ]);
    }

    protected function handleStoryReply(array $event): void
    {
        $storyId = $event['object_id'];
        
        $this->updateAnalytics($storyId, $this->webhookEvent->platform, [
            'story_replies' => 1,
        ]);

        Log::info('Facebook story reply', [
            'story_id' => $storyId,
            'webhook_event_id' => $this->webhookEvent->id,
        ]);
    }

    protected function handleAccountUpdated(array $event): void
    {
        $accountData = $this->webhookEvent->getPlatformData('entry.0.changes.0.value', []);
        
        $this->updateSocialAccountMetadata([
            'page_name' => $accountData['name'] ?? null,
            'page_category' => $accountData['category'] ?? null,
            'page_about' => $accountData['about'] ?? null,
            'page_phone' => $accountData['phone'] ?? null,
            'page_website' => $accountData['website'] ?? null,
            'page_likes' => $accountData['fan_count'] ?? null,
            'page_talking_about' => $accountData['talking_about_count'] ?? null,
            'last_updated_at' => now(),
        ]);

        Log::info('Facebook account updated', [
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
            'platform' => 'facebook',
            'verification_date' => now(),
        ]);

        Log::info('Facebook account verified', [
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
            'platform' => 'facebook',
            'suspension_date' => now(),
        ]);

        Log::warning('Facebook account suspended', [
            'webhook_event_id' => $this->webhookEvent->id,
        ]);
    }

    protected function handleAccountInsightsUpdated(array $event): void
    {
        $insights = $this->webhookEvent->getPlatformData('entry.0.changes.0.value', []);
        
        $this->updateSocialAccountMetadata([
            'page_impressions' => $insights['page_impressions'] ?? null,
            'page_impressions_unique' => $insights['page_impressions_unique'] ?? null,
            'page_engaged_users' => $insights['page_engaged_users'] ?? null,
            'page_post_engagements' => $insights['page_post_engagements'] ?? null,
            'page_fan_adds' => $insights['page_fan_adds'] ?? null,
            'page_fan_removes' => $insights['page_fan_removes'] ?? null,
            'last_insights_update' => now(),
        ]);

        Log::info('Facebook account insights updated', [
            'insights' => $insights,
            'webhook_event_id' => $this->webhookEvent->id,
        ]);
    }

    /**
     * Check for engagement milestones.
     */
    protected function checkEngagementMilestones(array $metrics, array $event): void
    {
        $totalEngagement = ($metrics['likes'] ?? 0) + ($metrics['comments'] ?? 0) + ($metrics['shares'] ?? 0);
        
        $milestones = [100, 500, 1000, 5000, 10000, 50000, 100000];
        
        foreach ($milestones as $milestone) {
            if ($totalEngagement >= $milestone && $totalEngagement - 1 < $milestone) {
                $this->sendNotification('engagement_milestone', [
                    'milestone' => $milestone,
                    'total_engagement' => $totalEngagement,
                    'post_id' => $event['object_id'],
                    'metrics' => $metrics,
                ]);
                break;
            }
        }
    }

    /**
     * Check for viral content.
     */
    protected function checkViralContent(array $metrics, array $event): void
    {
        $totalEngagement = ($metrics['likes'] ?? 0) + ($metrics['comments'] ?? 0) + ($metrics['shares'] ?? 0);
        $reach = $metrics['reach'] ?? 0;
        
        if ($reach > 0) {
            $engagementRate = ($totalEngagement / $reach) * 100;
            
            // Consider viral if engagement rate > 10% and reach > 1000
            if ($engagementRate > 10 && $reach > 1000) {
                $this->sendNotification('viral_content', [
                    'engagement_rate' => $engagementRate,
                    'reach' => $reach,
                    'total_engagement' => $totalEngagement,
                    'post_id' => $event['object_id'],
                    'metrics' => $metrics,
                ]);
            }
        }
    }

    /**
     * Analyze comment sentiment.
     */
    protected function analyzeCommentSentiment(array $commentData, array $event): void
    {
        $text = $commentData['text'] ?? '';
        
        if (empty($text)) {
            return;
        }

        // Simple sentiment analysis (can be enhanced with AI service)
        $negativeKeywords = ['bad', 'terrible', 'awful', 'hate', 'worst', 'disappointed'];
        $positiveKeywords = ['great', 'awesome', 'amazing', 'love', 'best', 'excellent'];
        
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
                'sentiment_score' => -0.5,
                'content' => $text,
                'post_id' => $event['object_id'],
            ]);
        }
    }

    /**
     * Store message data for analysis.
     */
    protected function storeMessageData(array $messageData, array $userInfo, string $direction): void
    {
        // Store in cache or database for analysis
        $messageKey = "fb_message_{$this->webhookEvent->social_account_id}_{$direction}_" . now()->timestamp;
        
        cache()->put($messageKey, [
            'message' => $messageData,
            'user' => $userInfo,
            'direction' => $direction,
            'timestamp' => now(),
        ], now()->addDays(7));
    }

    /**
     * Check for urgent keywords in messages.
     */
    protected function checkUrgentKeywords(array $messageData, array $event): void
    {
        $text = $messageData['text'] ?? '';
        
        if (empty($text)) {
            return;
        }

        $urgentKeywords = ['urgent', 'emergency', 'asap', 'immediately', 'help', 'problem', 'issue'];
        $lowerText = strtolower($text);
        
        foreach ($urgentKeywords as $keyword) {
            if (str_contains($lowerText, $keyword)) {
                $this->sendNotification('urgent_message', [
                    'message' => $text,
                    'sender' => $event['user_info'],
                    'keyword' => $keyword,
                ]);
                break;
            }
        }
    }
}