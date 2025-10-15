<?php

namespace App\Services\Webhooks\Normalizers;

use App\Models\WebhookEvent;
use Illuminate\Support\Arr;

class LinkedInEventNormalizer extends BaseEventNormalizer
{
    public function extractEventType(WebhookEvent $webhookEvent): string
    {
        $payload = $webhookEvent->payload;

        // Check for different event types in LinkedIn webhooks
        if (Arr::has($payload, 'shareUpdate')) {
            $update = Arr::get($payload, 'shareUpdate');
            
            return match (true) {
                isset($update['shareId']) && isset($update['updateType']) && $update['updateType'] === 'CREATED' => 'created',
                isset($update['shareId']) && isset($update['updateType']) && $update['updateType'] === 'UPDATED' => 'updated',
                isset($update['shareId']) && isset($update['updateType']) && $update['updateType'] === 'DELETED' => 'deleted',
                default => 'engagement',
            };
        }

        if (Arr::has($payload, 'commentUpdate')) {
            return 'created';
        }

        if (Arr::has($payload, 'reactionUpdate')) {
            return 'engagement';
        }

        if (Arr::has($payload, 'personUpdate')) {
            return 'updated';
        }

        if (Arr::has($payload, 'organizationUpdate')) {
            return 'updated';
        }

        if (Arr::has($payload, 'connectionUpdate')) {
            return 'followed';
        }

        if (Arr::has($payload, 'messageEvent')) {
            return 'message_received';
        }

        if (Arr::has($payload, 'leadGenFormUpdate')) {
            return 'lead_generated';
        }

        if (Arr::has($payload, 'organizationInsights')) {
            return 'insights_updated';
        }

        return $webhookEvent->event_type;
    }

    public function extractObjectType(WebhookEvent $webhookEvent): string
    {
        $payload = $webhookEvent->payload;

        if (Arr::has($payload, 'shareUpdate')) {
            return 'post';
        }

        if (Arr::has($payload, 'commentUpdate')) {
            return 'comment';
        }

        if (Arr::has($payload, 'reactionUpdate')) {
            return 'reaction';
        }

        if (Arr::has($payload, 'personUpdate')) {
            return 'user';
        }

        if (Arr::has($payload, 'organizationUpdate')) {
            return 'account';
        }

        if (Arr::has($payload, 'connectionUpdate')) {
            return 'user';
        }

        if (Arr::has($payload, 'messageEvent')) {
            return 'message';
        }

        if (Arr::has($payload, 'leadGenFormUpdate')) {
            return 'lead';
        }

        if (Arr::has($payload, 'organizationInsights')) {
            return 'account';
        }

        return 'unknown';
    }

    public function extractObjectId(WebhookEvent $webhookEvent): ?string
    {
        $payload = $webhookEvent->payload;
        
        // Try multiple paths for object ID
        $paths = [
            'shareUpdate.shareId',
            'commentUpdate.commentId',
            'commentUpdate.shareId',
            'reactionUpdate.shareId',
            'personUpdate.personId',
            'organizationUpdate.organizationId',
            'connectionUpdate.personId',
            'messageEvent.messageId',
            'messageEvent.conversationId',
            'leadGenFormUpdate.leadId',
            'leadGenFormUpdate.formId',
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

        // Share metrics
        if ($share = Arr::get($payload, 'shareUpdate')) {
            $metrics['numLikes'] = Arr::get($share, 'numLikes');
            $metrics['numComments'] = Arr::get($share, 'numComments');
            $metrics['numShares'] = Arr::get($share, 'numShares');
            $metrics['numImpressions'] = Arr::get($share, 'numImpressions');
            $metrics['numClicks'] = Arr::get($share, 'numClicks');
            $metrics['engagement'] = Arr::get($share, 'engagement');
            $metrics['reach'] = Arr::get($share, 'reach');
            $metrics['numUniqueImpressions'] = Arr::get($share, 'numUniqueImpressions');
            $metrics['shareType'] = Arr::get($share, 'shareType');
            $metrics['shareMediaType'] = Arr::get($share, 'shareMediaType');
            $metrics['updateType'] = Arr::get($share, 'updateType');
        }

        // Comment metrics
        if ($comment = Arr::get($payload, 'commentUpdate')) {
            $metrics['numComments'] = Arr::get($comment, 'numComments');
            $metrics['commentType'] = Arr::get($comment, 'commentType');
            $metrics['commentUpdateType'] = Arr::get($comment, 'updateType');
        }

        // Reaction metrics
        if ($reaction = Arr::get($payload, 'reactionUpdate')) {
            $metrics['numLikes'] = Arr::get($reaction, 'numLikes');
            $metrics['reactionType'] = Arr::get($reaction, 'reactionType');
            $metrics['reactionUpdateType'] = Arr::get($reaction, 'updateType');
        }

        // Person metrics
        if ($person = Arr::get($payload, 'personUpdate')) {
            $metrics['connectionsCount'] = Arr::get($person, 'connectionsCount');
            $metrics['followersCount'] = Arr::get($person, 'followersCount');
            $metrics['updateType'] = Arr::get($person, 'updateType');
        }

        // Organization metrics
        if ($org = Arr::get($payload, 'organizationUpdate')) {
            $metrics['employeeCount'] = Arr::get($org, 'employeeCount');
            $metrics['followerCount'] = Arr::get($org, 'followerCount');
            $metrics['updateType'] = Arr::get($org, 'updateType');
        }

        // Connection metrics
        if ($connection = Arr::get($payload, 'connectionUpdate')) {
            $metrics['connectionsCount'] = Arr::get($connection, 'connectionsCount');
            $metrics['connectionType'] = Arr::get($connection, 'connectionType');
            $metrics['connectionState'] = Arr::get($connection, 'connectionState');
        }

        // Organization insights
        if ($insights = Arr::get($payload, 'organizationInsights')) {
            $metrics['pageViews'] = Arr::get($insights, 'pageViews');
            $metrics['uniqueVisitors'] = Arr::get($insights, 'uniqueVisitors');
            $metrics['clicks'] = Arr::get($insights, 'clicks');
            $metrics['followers'] = Arr::get($insights, 'followers');
            $metrics['newFollowers'] = Arr::get($insights, 'newFollowers');
            $metrics['employeeCount'] = Arr::get($insights, 'employeeCount');
            $metrics['reach'] = Arr::get($insights, 'reach');
            $metrics['impressions'] = Arr::get($insights, 'impressions');
            $metrics['engagementRate'] = Arr::get($insights, 'engagementRate');
            $metrics['updateFrequency'] = Arr::get($insights, 'updateFrequency');
        }

        return array_filter($metrics, fn($value) => $value !== null);
    }

    protected function extractPlatformSpecificUserInfo(array $payload): array
    {
        $userInfo = [];

        // From person updates
        if ($person = Arr::get($payload, 'personUpdate')) {
            $userInfo['person_id'] = Arr::get($person, 'personId');
            $userInfo['first_name'] = Arr::get($person, 'firstName');
            $userInfo['last_name'] = Arr::get($person, 'lastName');
            $userInfo['headline'] = Arr::get($person, 'headline');
            $userInfo['summary'] = Arr::get($person, 'summary');
            $userInfo['location'] = Arr::get($person, 'location');
            $userInfo['industry'] = Arr::get($person, 'industry');
            $userInfo['profile_picture_url'] = Arr::get($person, 'profilePictureUrl');
            $userInfo['connections_count'] = Arr::get($person, 'connectionsCount');
            $userInfo['followers_count'] = Arr::get($person, 'followersCount');
        }

        // From connection updates
        if ($connection = Arr::get($payload, 'connectionUpdate')) {
            $userInfo['person_id'] = Arr::get($connection, 'personId');
            $userInfo['connected_person_id'] = Arr::get($connection, 'connectedPersonId');
            $userInfo['connection_type'] = Arr::get($connection, 'connectionType');
            $userInfo['connection_state'] = Arr::get($connection, 'connectionState');
        }

        // From message events
        if ($message = Arr::get($payload, 'messageEvent')) {
            $userInfo['sender_id'] = Arr::get($message, 'senderId');
            $userInfo['recipient_id'] = Arr::get($message, 'recipientId');
            $userInfo['conversation_id'] = Arr::get($message, 'conversationId');
        }

        // From share updates (author info)
        if ($share = Arr::get($payload, 'shareUpdate')) {
            $userInfo['author_id'] = Arr::get($share, 'author');
            $userInfo['author_name'] = Arr::get($share, 'authorName');
            $userInfo['author_profile_url'] = Arr::get($share, 'authorProfileUrl');
        }

        // From comment updates
        if ($comment = Arr::get($payload, 'commentUpdate')) {
            $userInfo['commenter_id'] = Arr::get($comment, 'commenterId');
            $userInfo['commenter_name'] = Arr::get($comment, 'commenterName');
            $userInfo['commenter_profile_url'] = Arr::get($comment, 'commenterProfileUrl');
        }

        return array_filter($userInfo, fn($value) => $value !== null);
    }

    protected function extractPlatformSpecificContentInfo(array $payload): array
    {
        $contentInfo = [];

        // Share content
        if ($share = Arr::get($payload, 'shareUpdate')) {
            $contentInfo['share_text'] = Arr::get($share, 'shareText');
            $contentInfo['share_commentary'] = Arr::get($share, 'shareCommentary');
            $contentInfo['share_media_url'] = Arr::get($share, 'shareMediaUrl');
            $contentInfo['share_thumbnail_url'] = Arr::get($share, 'shareThumbnailUrl');
            $contentInfo['share_title'] = Arr::get($share, 'shareTitle');
            $contentInfo['share_description'] = Arr::get($share, 'shareDescription');
            $contentInfo['share_url'] = Arr::get($share, 'shareUrl');
            $contentInfo['share_type'] = Arr::get($share, 'shareType');
            $contentInfo['share_media_type'] = Arr::get($share, 'shareMediaType');
            $contentInfo['update_type'] = Arr::get($share, 'updateType');
            $contentInfo['published_at'] = Arr::get($share, 'publishedAt');
            $contentInfo['last_modified_at'] = Arr::get($share, 'lastModifiedAt');
        }

        // Comment content
        if ($comment = Arr::get($payload, 'commentUpdate')) {
            $contentInfo['comment_text'] = Arr::get($comment, 'commentText');
            $contentInfo['comment_type'] = Arr::get($comment, 'commentType');
            $contentInfo['update_type'] = Arr::get($comment, 'updateType');
            $contentInfo['created_at'] = Arr::get($comment, 'createdAt');
            $contentInfo['last_modified_at'] = Arr::get($comment, 'lastModifiedAt');
        }

        // Reaction content
        if ($reaction = Arr::get($payload, 'reactionUpdate')) {
            $contentInfo['reaction_type'] = Arr::get($reaction, 'reactionType');
            $contentInfo['update_type'] = Arr::get($reaction, 'updateType');
            $contentInfo['created_at'] = Arr::get($reaction, 'createdAt');
        }

        // Message content
        if ($message = Arr::get($payload, 'messageEvent')) {
            $contentInfo['message_text'] = Arr::get($message, 'messageText');
            $contentInfo['message_type'] = Arr::get($message, 'messageType');
            $contentInfo['message_attachments'] = Arr::get($message, 'attachments');
            $contentInfo['message_created_at'] = Arr::get($message, 'createdAt');
            $contentInfo['message_read_at'] = Arr::get($message, 'readAt');
        }

        // Lead form content
        if ($lead = Arr::get($payload, 'leadGenFormUpdate')) {
            $contentInfo['lead_id'] = Arr::get($lead, 'leadId');
            $contentInfo['form_id'] = Arr::get($lead, 'formId');
            $contentInfo['form_name'] = Arr::get($lead, 'formName');
            $contentInfo['lead_data'] = Arr::get($lead, 'leadData');
            $contentInfo['created_at'] = Arr::get($lead, 'createdAt');
            $contentInfo['campaign_id'] = Arr::get($lead, 'campaignId');
            $contentInfo['ad_id'] = Arr::get($lead, 'adId');
        }

        // Organization content
        if ($org = Arr::get($payload, 'organizationUpdate')) {
            $contentInfo['name'] = Arr::get($org, 'name');
            $contentInfo['description'] = Arr::get($org, 'description');
            $contentInfo['website_url'] = Arr::get($org, 'websiteUrl');
            $contentInfo['industry'] = Arr::get($org, 'industry');
            $contentInfo['company_size'] = Arr::get($org, 'companySize');
            $contentInfo['headquarters'] = Arr::get($org, 'headquarters');
            $contentInfo['founded'] = Arr::get($org, 'founded');
            $contentInfo['specialties'] = Arr::get($org, 'specialties');
            $contentInfo['logo_url'] = Arr::get($org, 'logoUrl');
            $contentInfo['universal_name'] = Arr::get($org, 'universalName');
            $contentInfo['update_type'] = Arr::get($org, 'updateType');
        }

        return array_filter($contentInfo, fn($value) => $value !== null);
    }
}