<?php

namespace App\Services\Webhooks\Normalizers;

use App\Models\WebhookEvent;

interface EventNormalizerInterface
{
    /**
     * Normalize webhook event data into a standard format.
     */
    public function normalize(WebhookEvent $webhookEvent): array;

    /**
     * Extract the event type from the payload.
     */
    public function extractEventType(WebhookEvent $webhookEvent): string;

    /**
     * Extract the object type from the payload.
     */
    public function extractObjectType(WebhookEvent $webhookEvent): string;

    /**
     * Extract the platform-specific object ID.
     */
    public function extractObjectId(WebhookEvent $webhookEvent): ?string;

    /**
     * Extract engagement metrics from the payload.
     */
    public function extractEngagementMetrics(WebhookEvent $webhookEvent): array;

    /**
     * Extract user/account information from the payload.
     */
    public function extractUserInfo(WebhookEvent $webhookEvent): array;

    /**
     * Extract content information from the payload.
     */
    public function extractContentInfo(WebhookEvent $webhookEvent): array;
}