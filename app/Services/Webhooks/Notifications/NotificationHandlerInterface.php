<?php

namespace App\Services\Webhooks\Notifications;

use App\Models\WebhookEvent;

interface NotificationHandlerInterface
{
    /**
     * Handle webhook event notification.
     */
    public function handle(string $type, array $data, WebhookEvent $webhookEvent): void;

    /**
     * Send engagement milestone notification.
     */
    public function sendEngagementMilestone(array $metrics, WebhookEvent $webhookEvent): void;

    /**
     * Send critical alert notification.
     */
    public function sendCriticalAlert(string $message, array $context, WebhookEvent $webhookEvent): void;

    /**
     * Send lead generation notification.
     */
    public function sendLeadNotification(array $leadData, WebhookEvent $webhookEvent): void;

    /**
     * Send account status change notification.
     */
    public function sendAccountStatusNotification(string $status, array $accountData, WebhookEvent $webhookEvent): void;
}