<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WebhookSubscription extends Model
{
    use HasFactory;

    protected $fillable = [
        'webhook_config_id',
        'platform',
        'event_type',
        'subscription_id',
        'status',
        'subscribed_at',
        'expires_at',
        'subscription_data',
    ];

    protected $casts = [
        'subscription_data' => 'array',
        'subscribed_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    /**
     * Get the webhook config that owns the subscription.
     */
    public function webhookConfig(): BelongsTo
    {
        return $this->belongsTo(WebhookConfig::class);
    }

    /**
     * Scope a query to only include active subscriptions.
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope a query to only include expired subscriptions.
     */
    public function scopeExpired($query)
    {
        return $query->where('expires_at', '<', now());
    }

    /**
     * Check if subscription is expired.
     */
    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    /**
     * Mark subscription as expired.
     */
    public function markAsExpired(): void
    {
        $this->update(['status' => 'expired']);
    }

    /**
     * Get platform-specific event types.
     */
    public static function getPlatformEventTypes(string $platform): array
    {
        return match ($platform) {
            'facebook' => [
                'page_posts',
                'page_comments',
                'page_likes',
                'page_messages',
                'lead_generation',
                'page_updates',
            ],
            'instagram' => [
                'media_comments',
                'media_mentions',
                'story_replies',
                'business_account_updates',
                'media_insights',
            ],
            'twitter' => [
                'tweet_events',
                'tweet_mentions',
                'tweet_replies',
                'tweet_likes',
                'tweet_retweets',
                'direct_messages',
                'account_updates',
            ],
            'linkedin' => [
                'person_updates',
                'organization_updates',
                'share_updates',
                'comment_updates',
                'reaction_updates',
            ],
            default => [],
        };
    }
}