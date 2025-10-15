<?php

namespace App\Notifications\Webhook;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Messages\DatabaseMessage;
use Illuminate\Notifications\Messages\SlackMessage;

class CriticalAlertNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public array $data
    ) {}

    /**
     * Get the notification's delivery channels.
     */
    public function via(object $notifiable): array
    {
        $channels = ['database'];
        
        if ($notifiable->notification_preferences['webhooks']['critical_alerts']['email'] ?? false) {
            $channels[] = 'mail';
        }
        
        if ($notifiable->notification_preferences['webhooks']['critical_alerts']['slack'] ?? false) {
            $channels[] = 'slack';
        }
        
        return $channels;
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('🚨 Critical Alert: ' . $this->data['message'])
            ->greeting("Hello {$notifiable->name},")
            ->line("A critical alert has been triggered:")
            ->line("**Message:** {$this->data['message']}")
            ->line("**Platform:** " . ucfirst($this->data['platform']))
            ->line("**Time:** " . now()->toDateTimeString())
            ->line("**Context:** " . $this->formatContext())
            ->action('View Details', $this->getActionUrl())
            ->line('Please investigate this issue immediately.');
    }

    /**
     * Get the database representation of the notification.
     */
    public function toDatabase(object $notifiable): array
    {
        return [
            'type' => 'critical_alert',
            'title' => 'Critical Alert',
            'message' => $this->data['message'],
            'data' => $this->data,
            'platform' => $this->data['platform'],
            'context' => $this->data['context'],
            'requires_action' => true,
        ];
    }

    /**
     * Get the Slack representation of the notification.
     */
    public function toSlack(object $notifiable): SlackMessage
    {
        return (new SlackMessage)
            ->error()
            ->content('🚨 Critical Alert: ' . $this->data['message'])
            ->attachment(function ($attachment) {
                $attachment->title('Alert Details')
                    ->fields([
                        'Platform' => ucfirst($this->data['platform']),
                        'Message' => $this->data['message'],
                        'Time' => now()->toDateTimeString(),
                        'Context' => $this->formatContext(),
                    ])
                    ->action('View Details', $this->getActionUrl());
            });
    }

    /**
     * Format the context for display.
     */
    private function formatContext(): string
    {
        if (empty($this->data['context'])) {
            return 'No additional context';
        }

        $context = [];
        foreach ($this->data['context'] as $key => $value) {
            $context[] = "{$key}: {$value}";
        }

        return implode(', ', $context);
    }

    /**
     * Get the action URL.
     */
    private function getActionUrl(): string
    {
        if (isset($this->data['webhook_event_id'])) {
            return route('admin.webhooks.events.show', $this->data['webhook_event_id']);
        }

        return route('admin.webhooks.index');
    }
}