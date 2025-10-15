<?php

namespace App\Notifications\Webhook;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Messages\DatabaseMessage;
use Illuminate\Notifications\Messages\SlackMessage;

class LeadGeneratedNotification extends Notification implements ShouldQueue
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
        
        if ($notifiable->notification_preferences['webhooks']['lead_generation']['email'] ?? false) {
            $channels[] = 'mail';
        }
        
        if ($notifiable->notification_preferences['webhooks']['lead_generation']['slack'] ?? false) {
            $channels[] = 'slack';
        }
        
        return $channels;
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $platform = ucfirst($this->data['platform']);
        $leadId = $this->data['lead_id'] ?? 'Unknown';

        return (new MailMessage)
            ->subject("🎯 New Lead Generated on {$platform}!")
            ->greeting("Great news {$notifiable->name}!")
            ->line("A new lead has been generated through your {$platform} integration.")
            ->line("**Lead ID:** {$leadId}")
            ->line("**Platform:** {$platform}")
            ->line("**Generated:** " . now()->toDateTimeString())
            ->action('View Lead Details', $this->getLeadUrl())
            ->line('Follow up with this lead as soon as possible!');
    }

    /**
     * Get the database representation of the notification.
     */
    public function toDatabase(object $notifiable): array
    {
        return [
            'type' => 'lead_generated',
            'title' => 'New Lead Generated',
            'message' => "New lead from {$this->data['platform']}",
            'data' => $this->data,
            'platform' => $this->data['platform'],
            'lead_id' => $this->data['lead_id'] ?? null,
            'requires_action' => true,
        ];
    }

    /**
     * Get the Slack representation of the notification.
     */
    public function toSlack(object $notifiable): SlackMessage
    {
        return (new SlackMessage)
            ->success()
            ->content('🎯 New Lead Generated!')
            ->attachment(function ($attachment) {
                $attachment->title('Lead Details')
                    ->fields([
                        'Platform' => ucfirst($this->data['platform']),
                        'Lead ID' => $this->data['lead_id'] ?? 'Unknown',
                        'Generated' => now()->toDateTimeString(),
                    ])
                    ->action('View Lead', $this->getLeadUrl());
            });
    }

    /**
     * Get the lead URL.
     */
    private function getLeadUrl(): string
    {
        if (isset($this->data['lead_id'])) {
            return route('admin.leads.show', $this->data['lead_id']);
        }

        return route('admin.leads.index');
    }
}