<?php

namespace App\Notifications\Webhook;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Messages\DatabaseMessage;

class AccountStatusNotification extends Notification implements ShouldQueue
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
        
        if ($notifiable->notification_preferences['webhooks']['account_status']['email'] ?? false) {
            $channels[] = 'mail';
        }
        
        return $channels;
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $platform = ucfirst($this->data['platform']);
        $status = $this->data['status'];
        $statusText = match ($status) {
            'verified' => '✅ Verified',
            'suspended' => '⚠️ Suspended',
            'updated' => '📝 Updated',
            default => '📢 Status Changed',
        };

        return (new MailMessage)
            ->subject("{$statusText} - {$platform} Account Status")
            ->greeting("Hello {$notifiable->name},")
            ->line("Your {$platform} account status has changed:")
            ->line("**Status:** {$statusText}")
            ->line("**Platform:** {$platform}")
            ->line("**Time:** " . now()->toDateTimeString())
            ->action('View Account', $this->getAccountUrl())
            ->line($this->getStatusMessage());
    }

    /**
     * Get the database representation of the notification.
     */
    public function toDatabase(object $notifiable): array
    {
        return [
            'type' => 'account_status_changed',
            'title' => 'Account Status Changed',
            'message' => "Your {$this->data['platform']} account status: {$this->data['status']}",
            'data' => $this->data,
            'platform' => $this->data['platform'],
            'status' => $this->data['status'],
            'requires_action' => $this->data['status'] === 'suspended',
        ];
    }

    /**
     * Get the status-specific message.
     */
    private function getStatusMessage(): string
    {
        return match ($this->data['status']) {
            'verified' => 'Congratulations! Your account has been verified.',
            'suspended' => 'Your account has been suspended. Please take immediate action.',
            'updated' => 'Your account information has been updated.',
            default => 'Your account status has changed.',
        };
    }

    /**
     * Get the account URL.
     */
    private function getAccountUrl(): string
    {
        return route('admin.social-accounts.show', $this->data['social_account']->id);
    }
}