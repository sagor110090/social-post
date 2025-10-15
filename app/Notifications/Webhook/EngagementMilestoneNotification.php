<?php

namespace App\Notifications\Webhook;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Messages\DatabaseMessage;

class EngagementMilestoneNotification extends Notification implements ShouldQueue
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
        
        if ($notifiable->notification_preferences['webhooks']['engagement_milestones']['email'] ?? false) {
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
        $milestone = number_format($this->data['milestone']);
        $totalEngagement = number_format($this->data['total_engagement']);

        return (new MailMessage)
            ->subject("🎉 {$platform} Engagement Milestone Reached!")
            ->greeting("Congratulations {$notifiable->name}!")
            ->line("Your {$platform} post has reached an engagement milestone!")
            ->line("**Milestone:** {$milestone} engagements")
            ->line("**Total Engagement:** {$totalEngagement}")
            ->line("**Platform:** {$platform}")
            ->action('View Post', $this->getPostUrl())
            ->line('Keep up the great work!');
    }

    /**
     * Get the database representation of the notification.
     */
    public function toDatabase(object $notifiable): array
    {
        return [
            'type' => 'engagement_milestone',
            'title' => 'Engagement Milestone Reached!',
            'message' => "Your {$this->data['platform']} post reached {$this->data['milestone']} engagements",
            'data' => $this->data,
            'platform' => $this->data['platform'],
            'milestone' => $this->data['milestone'],
            'total_engagement' => $this->data['total_engagement'],
        ];
    }

    /**
     * Get the post URL.
     */
    private function getPostUrl(): string
    {
        return match ($this->data['platform']) {
            'facebook' => "https://facebook.com/{$this->data['post_id']}",
            'instagram' => "https://instagram.com/p/{$this->data['post_id']}",
            'twitter' => "https://twitter.com/i/web/status/{$this->data['post_id']}",
            'linkedin' => "https://linkedin.com/feed/update/{$this->data['post_id']}",
            default => '#',
        };
    }
}