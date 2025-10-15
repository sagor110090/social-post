<?php

namespace App\Services\Webhooks\Processors;

use App\Services\Webhooks\Normalizers\EventNormalizerInterface;
use App\Services\Webhooks\Analytics\AnalyticsUpdaterInterface;
use App\Services\Webhooks\Notifications\NotificationHandlerInterface;
use Illuminate\Support\Facades\Log;

class LinkedInWebhookEventProcessor extends BaseWebhookEventProcessor
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
        $shareId = $event['object_id'];
        $contentInfo = $event['content_info'];
        $metrics = $event['engagement_metrics'];
        
        $this->executeInTransaction(function () use ($shareId, $contentInfo, $metrics) {
            $analytics = $this->getOrCreatePostAnalytics($shareId);
            
            if ($analytics) {
                $this->updateAnalytics($shareId, $this->webhookEvent->platform, [
                    'text' => $contentInfo['text'] ?? '',
                    'share_type' => $this->getShareType($contentInfo),
                    'media_type' => $contentInfo['media_type'] ?? 'text',
                    'author_id' => $this->webhookEvent->getPlatformData('shareUpdate.author'),
                    'likes' => $metrics['likes'] ?? 0,
                    'comments' => $metrics['comments'] ?? 0,
                    'shares' => $metrics['shares'] ?? 0,
                    'share_created_at' => $event['received_at'],
                ]);
            }
        });

        Log::info('LinkedIn share created', [
            'share_id' => $shareId,
            'share_type' => $this->getShareType($contentInfo),
            'webhook_event_id' => $this->webhookEvent->id,
        ]);
    }

    protected function handlePostUpdated(array $event): void
    {
        $shareId = $event['object_id'];
        
        $this->updateAnalytics($shareId, $this->webhookEvent->platform, [
            'updated_at' => now(),
            'last_update_type' => 'content_modified',
        ]);

        Log::info('LinkedIn share updated', [
            'share_id' => $shareId,
            'webhook_event_id' => $this->webhookEvent->id,
        ]);
    }

    protected function handlePostDeleted(array $event): void
    {
        $shareId = $event['object_id'];
        
        $this->executeInTransaction(function () use ($shareId) {
            $analytics = $this->getOrCreatePostAnalytics($shareId);
            
            if ($analytics) {
                $analytics->update([
                    'metrics->deleted' => true,
                    'metrics->deleted_at' => now()->toISOString(),
                    'metrics->deletion_reason' => 'webhook_notification',
                ]);
            }
        });

        Log::info('LinkedIn share deleted', [
            'share_id' => $shareId,
            'webhook_event_id' => $this->webhookEvent->id,
        ]);
    }

    protected function handlePostEngagement(array $event): void
    {
        $shareId = $event['object_id'];
        $metrics = $event['engagement_metrics'];
        
        $this->updateAnalytics($shareId, $this->webhookEvent->platform, $metrics);

        // Check for engagement milestones
        $this->checkEngagementMilestones($metrics, $event);

        // Check for viral content
        $this->checkViralContent($metrics, $event);

        Log::info('LinkedIn share engagement updated', [
            'share_id' => $shareId,
            'metrics' => $metrics,
            'webhook_event_id' => $this->webhookEvent->id,
        ]);
    }

    protected function handlePostMetricsUpdated(array $event): void
    {
        $shareId = $event['object_id'];
        $metrics = $event['engagement_metrics'];
        
        // Add LinkedIn-specific metrics
        $linkedinMetrics = array_merge($metrics, [
            'clicks' => $this->webhookEvent->getPlatformData('shareUpdate.numClicks'),
            'impressions' => $this->webhookEvent->getPlatformData('shareUpdate.numImpressions'),
            'shares' => $this->webhookEvent->getPlatformData('shareUpdate.numShares'),
            'engagement' => $this->webhookEvent->getPlatformData('shareUpdate.engagement'),
            'reach' => $this->webhookEvent->getPlatformData('shareUpdate.reach'),
            'unique_impressions' => $this->webhookEvent->getPlatformData('shareUpdate.numUniqueImpressions'),
        ]);

        $this->updateAnalytics($shareId, $this->webhookEvent->platform, $linkedinMetrics);

        Log::info('LinkedIn share metrics updated', [
            'share_id' => $shareId,
            'metrics' => $linkedinMetrics,
            'webhook_event_id' => $this->webhookEvent->id,
        ]);
    }

    protected function handleCommentCreated(array $event): void
    {
        $shareId = $event['object_id'];
        $commentData = $event['content_info'];
        
        $this->updateAnalytics($shareId, $this->webhookEvent->platform, [
            'comments' => 1,
            'last_comment_at' => now(),
        ]);

        // Analyze comment sentiment
        $this->analyzeCommentSentiment($commentData, $event);

        Log::info('LinkedIn comment created', [
            'share_id' => $shareId,
            'comment_id' => $this->webhookEvent->getPlatformData('commentUpdate.commentId'),
            'webhook_event_id' => $this->webhookEvent->id,
        ]);
    }

    protected function handleCommentUpdated(array $event): void
    {
        Log::info('LinkedIn comment updated', [
            'webhook_event_id' => $this->webhookEvent->id,
        ]);
    }

    protected function handleCommentDeleted(array $event): void
    {
        $shareId = $event['object_id'];
        
        $this->updateAnalytics($shareId, $this->webhookEvent->platform, [
            'comments' => -1,
        ]);

        Log::info('LinkedIn comment deleted', [
            'share_id' => $shareId,
            'webhook_event_id' => $this->webhookEvent->id,
        ]);
    }

    protected function handleCommentMention(array $event): void
    {
        $shareId = $event['object_id'];
        
        $this->updateAnalytics($shareId, $this->webhookEvent->platform, [
            'mentions' => 1,
        ]);

        $this->sendNotification('mention', [
            'platform' => 'linkedin',
            'share_id' => $shareId,
            'mentioner' => $event['user_info'],
            'content' => $event['content_info']['text'] ?? '',
        ]);

        Log::info('LinkedIn comment mention', [
            'share_id' => $shareId,
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

        Log::info('LinkedIn message received', [
            'sender_id' => $senderInfo['user_id'] ?? null,
            'message_id' => $this->webhookEvent->getPlatformData('messageEvent.messageId'),
            'webhook_event_id' => $this->webhookEvent->id,
        ]);
    }

    protected function handleMessageSent(array $event): void
    {
        $messageData = $event['content_info'];
        $recipientInfo = $event['user_info'];
        
        $this->storeMessageData($messageData, $recipientInfo, 'sent');

        Log::info('LinkedIn message sent', [
            'recipient_id' => $recipientInfo['user_id'] ?? null,
            'webhook_event_id' => $this->webhookEvent->id,
        ]);
    }

    protected function handleMessageRead(array $event): void
    {
        Log::info('LinkedIn message read', [
            'webhook_event_id' => $this->webhookEvent->id,
        ]);
    }

    protected function handleUserFollowed(array $event): void
    {
        // LinkedIn uses "connections" instead of "followers"
        $this->updateSocialAccountMetadata([
            'connections_count' => $this->webhookEvent->getPlatformData('connectionUpdate.connectionsCount'),
            'new_connection_at' => now(),
        ]);

        $this->sendNotification('new_connection', [
            'platform' => 'linkedin',
            'connection_info' => $event['user_info'],
        ]);

        Log::info('LinkedIn connection made', [
            'user_id' => $event['user_info']['user_id'] ?? null,
            'webhook_event_id' => $this->webhookEvent->id,
        ]);
    }

    protected function handleUserUnfollowed(array $event): void
    {
        $this->updateSocialAccountMetadata([
            'connections_count' => $this->webhookEvent->getPlatformData('connectionUpdate.connectionsCount'),
            'last_disconnection_at' => now(),
        ]);

        Log::info('LinkedIn connection removed', [
            'user_id' => $event['user_info']['user_id'] ?? null,
            'webhook_event_id' => $this->webhookEvent->id,
        ]);
    }

    protected function handleUserUpdated(array $event): void
    {
        $userData = $this->webhookEvent->getPlatformData('personUpdate', []);
        
        $this->updateSocialAccountMetadata([
            'first_name' => $userData['firstName'] ?? null,
            'last_name' => $userData['lastName'] ?? null,
            'headline' => $userData['headline'] ?? null,
            'summary' => $userData['summary'] ?? null,
            'location' => $userData['location'] ?? null,
            'industry' => $userData['industry'] ?? null,
            'profile_url' => $userData['profilePictureUrl'] ?? null,
            'connections_count' => $userData['connectionsCount'] ?? null,
        ]);

        Log::info('LinkedIn person updated', [
            'user_data' => $userData,
            'webhook_event_id' => $this->webhookEvent->id,
        ]);
    }

    protected function handleUserMentioned(array $event): void
    {
        $shareId = $event['object_id'];
        
        $this->sendNotification('mention', [
            'platform' => 'linkedin',
            'mentioner' => $event['user_info'],
            'content' => $event['content_info']['text'] ?? '',
            'share_id' => $shareId,
        ]);

        Log::info('LinkedIn user mentioned', [
            'share_id' => $shareId,
            'webhook_event_id' => $this->webhookEvent->id,
        ]);
    }

    protected function handleLeadGenerated(array $event): void
    {
        // LinkedIn leads typically come from Lead Gen Forms or message inquiries
        $leadData = $this->webhookEvent->getPlatformData('leadGenFormUpdate', []);
        
        $this->executeInTransaction(function () use ($leadData) {
            $this->analyticsUpdater->create([
                'social_account_id' => $this->webhookEvent->social_account_id,
                'platform' => 'linkedin',
                'platform_post_id' => $event['object_id'],
                'metrics' => [
                    'leads' => 1,
                    'lead_data' => $leadData,
                    'lead_source' => 'linkedin_lead_gen_form',
                ],
                'recorded_at' => now(),
            ]);
        });

        $this->sendNotification('lead_generated', $leadData);

        Log::info('LinkedIn lead generated', [
            'lead_id' => $leadData['leadId'] ?? null,
            'webhook_event_id' => $this->webhookEvent->id,
        ]);
    }

    protected function handleLeadUpdated(array $event): void
    {
        Log::info('LinkedIn lead updated', [
            'webhook_event_id' => $this->webhookEvent->id,
        ]);
    }

    protected function handleStoryCreated(array $event): void
    {
        Log::info('LinkedIn story created', [
            'webhook_event_id' => $this->webhookEvent->id,
        ]);
    }

    protected function handleStoryUpdated(array $event): void
    {
        Log::info('LinkedIn story updated', [
            'webhook_event_id' => $this->webhookEvent->id,
        ]);
    }

    protected function handleStoryDeleted(array $event): void
    {
        Log::info('LinkedIn story deleted', [
            'webhook_event_id' => $this->webhookEvent->id,
        ]);
    }

    protected function handleStoryInsightsUpdated(array $event): void
    {
        Log::info('LinkedIn story insights updated', [
            'webhook_event_id' => $this->webhookEvent->id,
        ]);
    }

    protected function handleStoryReply(array $event): void
    {
        Log::info('LinkedIn story reply', [
            'webhook_event_id' => $this->webhookEvent->id,
        ]);
    }

    protected function handleAccountUpdated(array $event): void
    {
        $accountData = $this->webhookEvent->getPlatformData('organizationUpdate', []);
        
        $this->updateSocialAccountMetadata([
            'name' => $accountData['name'] ?? null,
            'description' => $accountData['description'] ?? null,
            'website' => $accountData['websiteUrl'] ?? null,
            'industry' => $accountData['industry'] ?? null,
            'company_size' => $accountData['companySize'] ?? null,
            'headquarters' => $accountData['headquarters'] ?? null,
            'founded' => $accountData['founded'] ?? null,
            'specialties' => $accountData['specialties'] ?? null,
            'logo_url' => $accountData['logoUrl'] ?? null,
            'universal_name' => $accountData['universalName'] ?? null,
            'last_updated_at' => now(),
        ]);

        Log::info('LinkedIn organization updated', [
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
            'platform' => 'linkedin',
            'verification_date' => now(),
        ]);

        Log::info('LinkedIn account verified', [
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
            'platform' => 'linkedin',
            'suspension_date' => now(),
        ]);

        Log::warning('LinkedIn account suspended', [
            'webhook_event_id' => $this->webhookEvent->id,
        ]);
    }

    protected function handleAccountInsightsUpdated(array $event): void
    {
        $insights = $this->webhookEvent->getPlatformData('organizationInsights', []);
        
        $this->updateSocialAccountMetadata([
            'page_views' => $insights['pageViews'] ?? null,
            'unique_visitors' => $insights['uniqueVisitors'] ?? null,
            'clicks' => $insights['clicks'] ?? null,
            'followers' => $insights['followers'] ?? null,
            'new_followers' => $insights['newFollowers'] ?? null,
            'employee_count' => $insights['employeeCount'] ?? null,
            'reach' => $insights['reach'] ?? null,
            'impressions' => $insights['impressions'] ?? null,
            'engagement_rate' => $insights['engagementRate'] ?? null,
            'last_insights_update' => now(),
        ]);

        Log::info('LinkedIn account insights updated', [
            'insights' => $insights,
            'webhook_event_id' => $this->webhookEvent->id,
        ]);
    }

    /**
     * Get share type from content info.
     */
    protected function getShareType(array $contentInfo): string
    {
        $mediaType = $contentInfo['media_type'] ?? '';
        
        return match ($mediaType) {
            'article' => 'article',
            'image' => 'image',
            'video' => 'video',
            'document' => 'document',
            'poll' => 'poll',
            default => 'text',
        };
    }

    /**
     * Check for engagement milestones specific to LinkedIn.
     */
    protected function checkEngagementMilestones(array $metrics, array $event): void
    {
        $totalEngagement = ($metrics['likes'] ?? 0) + ($metrics['comments'] ?? 0) + ($metrics['shares'] ?? 0);
        
        $milestones = [50, 100, 500, 1000, 5000, 10000, 25000, 50000];
        
        foreach ($milestones as $milestone) {
            if ($totalEngagement >= $milestone && $totalEngagement - 1 < $milestone) {
                $this->sendNotification('engagement_milestone', [
                    'milestone' => $milestone,
                    'total_engagement' => $totalEngagement,
                    'share_id' => $event['object_id'],
                    'metrics' => $metrics,
                ]);
                break;
            }
        }
    }

    /**
     * Check for viral content specific to LinkedIn.
     */
    protected function checkViralContent(array $metrics, array $event): void
    {
        $totalEngagement = ($metrics['likes'] ?? 0) + ($metrics['comments'] ?? 0) + ($metrics['shares'] ?? 0);
        $impressions = $metrics['impressions'] ?? 0;
        
        if ($impressions > 0) {
            $engagementRate = ($totalEngagement / $impressions) * 100;
            
            // LinkedIn viral threshold: engagement rate > 2% and impressions > 5000
            if ($engagementRate > 2 && $impressions > 5000) {
                $this->sendNotification('viral_content', [
                    'engagement_rate' => $engagementRate,
                    'impressions' => $impressions,
                    'total_engagement' => $totalEngagement,
                    'share_id' => $event['object_id'],
                    'metrics' => $metrics,
                ]);
            }
        }
    }

    /**
     * Analyze comment sentiment for LinkedIn.
     */
    protected function analyzeCommentSentiment(array $commentData, array $event): void
    {
        $text = $commentData['text'] ?? '';
        
        if (empty($text)) {
            return;
        }

        // LinkedIn-specific sentiment analysis (professional context)
        $negativeKeywords = ['disappointed', 'unprofessional', 'inappropriate', 'misleading', 'inaccurate', 'poor', 'terrible'];
        $positiveKeywords = ['excellent', 'professional', 'insightful', 'valuable', 'helpful', 'great', 'amazing', 'brilliant'];
        
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
                'sentiment_score' => -0.4,
                'content' => $text,
                'share_id' => $event['object_id'],
            ]);
        }
    }

    /**
     * Store message data for LinkedIn.
     */
    protected function storeMessageData(array $messageData, array $userInfo, string $direction): void
    {
        $messageKey = "li_message_{$this->webhookEvent->social_account_id}_{$direction}_" . now()->timestamp;
        
        cache()->put($messageKey, [
            'message' => $messageData,
            'user' => $userInfo,
            'direction' => $direction,
            'timestamp' => now(),
        ], now()->addDays(7));
    }

    /**
     * Check for urgent keywords in LinkedIn messages.
     */
    protected function checkUrgentKeywords(array $messageData, array $event): void
    {
        $text = $messageData['text'] ?? '';
        
        if (empty($text)) {
            return;
        }

        $urgentKeywords = ['urgent', 'emergency', 'asap', 'immediately', 'help', 'problem', 'issue', 'opportunity', 'inquiry'];
        $lowerText = strtolower($text);
        
        foreach ($urgentKeywords as $keyword) {
            if (str_contains($lowerText, $keyword)) {
                $this->sendNotification('urgent_message', [
                    'message' => $text,
                    'sender' => $event['user_info'],
                    'keyword' => $keyword,
                    'platform' => 'linkedin',
                ]);
                break;
            }
        }
    }
}