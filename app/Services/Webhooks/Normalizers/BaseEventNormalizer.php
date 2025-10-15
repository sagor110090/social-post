<?php

namespace App\Services\Webhooks\Normalizers;

use App\Models\WebhookEvent;
use Illuminate\Support\Arr;

abstract class BaseEventNormalizer implements EventNormalizerInterface
{
    /**
     * Normalize webhook event data into a standard format.
     */
    public function normalize(WebhookEvent $webhookEvent): array
    {
        return [
            'webhook_event_id' => $webhookEvent->id,
            'platform' => $webhookEvent->platform,
            'event_type' => $this->extractEventType($webhookEvent),
            'object_type' => $this->extractObjectType($webhookEvent),
            'object_id' => $this->extractObjectId($webhookEvent),
            'platform_event_id' => $webhookEvent->event_id,
            'user_info' => $this->extractUserInfo($webhookEvent),
            'content_info' => $this->extractContentInfo($webhookEvent),
            'engagement_metrics' => $this->extractEngagementMetrics($webhookEvent),
            'raw_payload' => $webhookEvent->payload,
            'received_at' => $webhookEvent->received_at,
            'social_account_id' => $webhookEvent->social_account_id,
        ];
    }

    /**
     * Extract engagement metrics from the payload.
     */
    public function extractEngagementMetrics(WebhookEvent $webhookEvent): array
    {
        $payload = $webhookEvent->payload;
        $metrics = [];

        // Common metrics across platforms
        $metrics['likes'] = $this->extractMetric($payload, [
            'like_count', 'likes', 'numLikes', 'favorite_count'
        ]);

        $metrics['comments'] = $this->extractMetric($payload, [
            'comment_count', 'comments', 'numComments'
        ]);

        $metrics['shares'] = $this->extractMetric($payload, [
            'share_count', 'shares', 'numShares', 'retweet_count'
        ]);

        $metrics['reach'] = $this->extractMetric($payload, [
            'reach', 'impressions_unique'
        ]);

        $metrics['impressions'] = $this->extractMetric($payload, [
            'impressions', 'views', 'view_count'
        ]);

        // Platform-specific metrics
        $metrics = array_merge($metrics, $this->extractPlatformSpecificMetrics($payload));

        return array_filter($metrics, fn($value) => $value !== null);
    }

    /**
     * Extract user/account information from the payload.
     */
    public function extractUserInfo(WebhookEvent $webhookEvent): array
    {
        $payload = $webhookEvent->payload;
        $userInfo = [];

        // Common user fields
        $userInfo['user_id'] = $this->extractValue($payload, [
            'user.id', 'sender.id', 'actor.id', 'from.id', 'user_id'
        ]);

        $userInfo['username'] = $this->extractValue($payload, [
            'user.username', 'sender.username', 'actor.username', 'from.username'
        ]);

        $userInfo['name'] = $this->extractValue($payload, [
            'user.name', 'sender.name', 'actor.name', 'from.name'
        ]);

        $userInfo['profile_picture'] = $this->extractValue($payload, [
            'user.profile_pic', 'sender.profile_pic', 'actor.profile_pic_url'
        ]);

        // Platform-specific user info
        $userInfo = array_merge($userInfo, $this->extractPlatformSpecificUserInfo($payload));

        return array_filter($userInfo, fn($value) => $value !== null);
    }

    /**
     * Extract content information from the payload.
     */
    public function extractContentInfo(WebhookEvent $webhookEvent): array
    {
        $payload = $webhookEvent->payload;
        $contentInfo = [];

        // Common content fields
        $contentInfo['text'] = $this->extractValue($payload, [
            'message', 'text', 'content', 'caption', 'description'
        ]);

        $contentInfo['media_type'] = $this->extractValue($payload, [
            'media_type', 'type', 'attachment.type'
        ]);

        $contentInfo['media_url'] = $this->extractValue($payload, [
            'media_url', 'url', 'link', 'permalink_url'
        ]);

        $contentInfo['thumbnail_url'] = $this->extractValue($payload, [
            'thumbnail_url', 'picture', 'full_picture'
        ]);

        // Platform-specific content info
        $contentInfo = array_merge($contentInfo, $this->extractPlatformSpecificContentInfo($payload));

        return array_filter($contentInfo, fn($value) => $value !== null);
    }

    /**
     * Extract a metric value from multiple possible paths.
     */
    protected function extractMetric(array $payload, array $paths): ?int
    {
        foreach ($paths as $path) {
            $value = Arr::get($payload, $path);
            if ($value !== null && is_numeric($value)) {
                return (int) $value;
            }
        }
        return null;
    }

    /**
     * Extract a string value from multiple possible paths.
     */
    protected function extractValue(array $payload, array $paths): ?string
    {
        foreach ($paths as $path) {
            $value = Arr::get($payload, $path);
            if ($value !== null && is_string($value)) {
                return $value;
            }
        }
        return null;
    }

    /**
     * Extract platform-specific metrics.
     */
    abstract protected function extractPlatformSpecificMetrics(array $payload): array;

    /**
     * Extract platform-specific user information.
     */
    abstract protected function extractPlatformSpecificUserInfo(array $payload): array;

    /**
     * Extract platform-specific content information.
     */
    abstract protected function extractPlatformSpecificContentInfo(array $payload): array;
}