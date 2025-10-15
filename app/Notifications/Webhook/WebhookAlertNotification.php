<?php

namespace App\Notifications\Webhook;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;

class WebhookAlertNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public array $alert;

    public function __construct(array $alert)
    {
        $this->alert = $alert;
    }

    /**
     * Get the notification's delivery channels.
     */
    public function via($notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail($notifiable): MailMessage
    {
        $severity = strtoupper($this->alert['severity']);
        $subject = "[{$severity}] Webhook Alert: {$this->alert['rule']}";
        
        $mail = (new MailMessage)
            ->subject($subject)
            ->greeting('Webhook Monitoring Alert')
            ->line($this->alert['message'])
            ->line('**Alert Details:**')
            ->line('- Rule: ' . $this->alert['rule'])
            ->line('- Severity: ' . $severity)
            ->line('- Time: ' . $this->alert['timestamp']);

        // Add context information if available
        if (!empty($this->alert['context'])) {
            $mail->line('**Context:**');
            foreach ($this->alert['context'] as $key => $value) {
                if (is_scalar($value)) {
                    $mail->line("- {$key}: {$value}");
                } elseif (is_array($value)) {
                    $mail->line("- {$key}: " . json_encode($value, JSON_PRETTY_PRINT));
                }
            }
        }

        $mail->action('View Dashboard', route('monitoring.dashboard'))
              ->line('This is an automated message from the webhook monitoring system.');

        return $mail;
    }

    /**
     * Get the array representation of the notification.
     */
    public function toArray($notifiable): array
    {
        return [
            'alert' => $this->alert,
            'timestamp' => now()->toISOString(),
        ];
    }
}