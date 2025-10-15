<?php

namespace App\Services\Webhooks\Notifications;

use App\Models\WebhookEvent;
use App\Models\User;
use App\Models\Team;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use App\Notifications\Webhook\EngagementMilestoneNotification;
use App\Notifications\Webhook\CriticalAlertNotification;
use App\Notifications\Webhook\LeadGeneratedNotification;
use App\Notifications\Webhook\AccountStatusNotification;

class NotificationHandler implements NotificationHandlerInterface
{
    /**
     * Handle webhook event notification.
     */
    public function handle(string $type, array $data, WebhookEvent $webhookEvent): void
    {
        try {
            match ($type) {
                'engagement_milestone' => $this->sendEngagementMilestone($data, $webhookEvent),
                'critical_alert' => $this->sendCriticalAlert($data['message'], $data['context'], $webhookEvent),
                'lead_generated' => $this->sendLeadNotification($data, $webhookEvent),
                'account_status_changed' => $this->sendAccountStatusNotification($data['status'], $data['account'], $webhookEvent),
                'webhook_processing_failed' => $this->sendWebhookFailureNotification($data, $webhookEvent),
                'high_engagement' => $this->sendHighEngagementNotification($data, $webhookEvent),
                'negative_sentiment' => $this->sendNegativeSentimentNotification($data, $webhookEvent),
                'viral_content' => $this->sendViralContentNotification($data, $webhookEvent),
                default => Log::info('Unknown notification type', ['type' => $type]),
            };
        } catch (\Exception $e) {
            Log::error('Failed to handle webhook notification', [
                'type' => $type,
                'webhook_event_id' => $webhookEvent->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Send engagement milestone notification.
     */
    public function sendEngagementMilestone(array $metrics, WebhookEvent $webhookEvent): void
    {
        $recipients = $this->getNotificationRecipients($webhookEvent, 'engagement_milestones');
        
        if ($recipients->isEmpty()) {
            return;
        }

        $milestone = $this->determineMilestone($metrics);
        
        Notification::send($recipients, new EngagementMilestoneNotification([
            'platform' => $webhookEvent->platform,
            'post_id' => $webhookEvent->object_id,
            'milestone' => $milestone,
            'metrics' => $metrics,
            'social_account' => $webhookEvent->socialAccount,
        ]));

        Log::info('Engagement milestone notification sent', [
            'milestone' => $milestone,
            'recipients_count' => $recipients->count(),
            'webhook_event_id' => $webhookEvent->id,
        ]);
    }

    /**
     * Send critical alert notification.
     */
    public function sendCriticalAlert(string $message, array $context, WebhookEvent $webhookEvent): void
    {
        $recipients = $this->getNotificationRecipients($webhookEvent, 'critical_alerts');
        
        if ($recipients->isEmpty()) {
            return;
        }

        Notification::send($recipients, new CriticalAlertNotification([
            'message' => $message,
            'context' => $context,
            'platform' => $webhookEvent->platform,
            'social_account' => $webhookEvent->socialAccount,
            'webhook_event_id' => $webhookEvent->id,
        ]));

        Log::critical('Critical alert notification sent', [
            'message' => $message,
            'recipients_count' => $recipients->count(),
            'webhook_event_id' => $webhookEvent->id,
        ]);
    }

    /**
     * Send lead generation notification.
     */
    public function sendLeadNotification(array $leadData, WebhookEvent $webhookEvent): void
    {
        $recipients = $this->getNotificationRecipients($webhookEvent, 'lead_generation');
        
        if ($recipients->isEmpty()) {
            return;
        }

        Notification::send($recipients, new LeadGeneratedNotification([
            'platform' => $webhookEvent->platform,
            'lead_data' => $leadData,
            'social_account' => $webhookEvent->socialAccount,
            'webhook_event_id' => $webhookEvent->id,
        ]));

        Log::info('Lead generation notification sent', [
            'lead_id' => $leadData['lead_id'] ?? null,
            'recipients_count' => $recipients->count(),
            'webhook_event_id' => $webhookEvent->id,
        ]);
    }

    /**
     * Send account status change notification.
     */
    public function sendAccountStatusNotification(string $status, array $accountData, WebhookEvent $webhookEvent): void
    {
        $recipients = $this->getNotificationRecipients($webhookEvent, 'account_status');
        
        if ($recipients->isEmpty()) {
            return;
        }

        Notification::send($recipients, new AccountStatusNotification([
            'platform' => $webhookEvent->platform,
            'status' => $status,
            'account_data' => $accountData,
            'social_account' => $webhookEvent->socialAccount,
            'webhook_event_id' => $webhookEvent->id,
        ]));

        Log::info('Account status notification sent', [
            'status' => $status,
            'recipients_count' => $recipients->count(),
            'webhook_event_id' => $webhookEvent->id,
        ]);
    }

    /**
     * Send webhook processing failure notification.
     */
    protected function sendWebhookFailureNotification(array $data, WebhookEvent $webhookEvent): void
    {
        $recipients = $this->getAdminRecipients();
        
        if ($recipients->isEmpty()) {
            return;
        }

        Notification::send($recipients, new CriticalAlertNotification([
            'message' => 'Webhook event processing failed',
            'context' => [
                'error' => $data['error'],
                'retry_count' => $data['retry_count'],
                'platform' => $webhookEvent->platform,
                'event_type' => $webhookEvent->event_type,
            ],
            'platform' => $webhookEvent->platform,
            'social_account' => $webhookEvent->socialAccount,
            'webhook_event_id' => $webhookEvent->id,
        ]));
    }

    /**
     * Send high engagement notification.
     */
    protected function sendHighEngagementNotification(array $data, WebhookEvent $webhookEvent): void
    {
        $recipients = $this->getNotificationRecipients($webhookEvent, 'high_engagement');
        
        if ($recipients->isEmpty()) {
            return;
        }

        Notification::send($recipients, new EngagementMilestoneNotification([
            'platform' => $webhookEvent->platform,
            'post_id' => $webhookEvent->object_id,
            'milestone' => 'high_engagement',
            'metrics' => $data['metrics'],
            'social_account' => $webhookEvent->socialAccount,
        ]));
    }

    /**
     * Send negative sentiment notification.
     */
    protected function sendNegativeSentimentNotification(array $data, WebhookEvent $webhookEvent): void
    {
        $recipients = $this->getNotificationRecipients($webhookEvent, 'negative_sentiment');
        
        if ($recipients->isEmpty()) {
            return;
        }

        Notification::send($recipients, new CriticalAlertNotification([
            'message' => 'Negative sentiment detected',
            'context' => [
                'sentiment_score' => $data['sentiment_score'],
                'content' => $data['content'],
                'platform' => $webhookEvent->platform,
            ],
            'platform' => $webhookEvent->platform,
            'social_account' => $webhookEvent->socialAccount,
            'webhook_event_id' => $webhookEvent->id,
        ]));
    }

    /**
     * Send viral content notification.
     */
    protected function sendViralContentNotification(array $data, WebhookEvent $webhookEvent): void
    {
        $recipients = $this->getNotificationRecipients($webhookEvent, 'viral_content');
        
        if ($recipients->isEmpty()) {
            return;
        }

        Notification::send($recipients, new EngagementMilestoneNotification([
            'platform' => $webhookEvent->platform,
            'post_id' => $webhookEvent->object_id,
            'milestone' => 'viral_content',
            'metrics' => $data['metrics'],
            'social_account' => $webhookEvent->socialAccount,
        ]));
    }

    /**
     * Get notification recipients for a specific type.
     */
    protected function getNotificationRecipients(WebhookEvent $webhookEvent, string $type): \Illuminate\Database\Eloquent\Collection
    {
        $socialAccount = $webhookEvent->socialAccount;
        if (!$socialAccount) {
            return collect();
        }

        $team = $socialAccount->team;
        if (!$team) {
            return collect();
        }

        // Get team members with notification preferences
        return $team->users()
            ->wherePivot('role', '!=', 'viewer')
            ->whereJsonContains('notification_preferences->webhooks->' . $type, true)
            ->get();
    }

    /**
     * Get admin recipients for system notifications.
     */
    protected function getAdminRecipients(): \Illuminate\Database\Eloquent\Collection
    {
        return User::where('is_admin', true)
            ->orWhereJsonContains('notification_preferences->system->critical_alerts', true)
            ->get();
    }

    /**
     * Determine engagement milestone.
     */
    protected function determineMilestone(array $metrics): string
    {
        $totalEngagement = ($metrics['likes'] ?? 0) + ($metrics['comments'] ?? 0) + ($metrics['shares'] ?? 0);

        return match (true) {
            $totalEngagement >= 10000 => 'viral',
            $totalEngagement >= 5000 => 'high_viral',
            $totalEngagement >= 1000 => 'trending',
            $totalEngagement >= 500 => 'popular',
            $totalEngagement >= 100 => 'engaging',
            $totalEngagement >= 50 => 'growing',
            default => 'initial',
        };
    }

    /**
     * Check if notification should be sent based on rate limiting.
     */
    protected function shouldSendNotification(string $type, WebhookEvent $webhookEvent): bool
    {
        $key = "notification_rate_limit_{$type}_{$webhookEvent->social_account_id}";
        $lastSent = cache()->get($key);
        
        if ($lastSent && $lastSent->gt(now()->subMinutes(30))) {
            return false;
        }

        cache()->put($key, now(), now()->addMinutes(30));
        return true;
    }
}