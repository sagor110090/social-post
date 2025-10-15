<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class WebhookEvent extends Model
{
    use HasFactory;

    protected $fillable = [
        'social_account_id',
        'webhook_config_id',
        'platform',
        'event_type',
        'event_id',
        'object_type',
        'object_id',
        'payload',
        'signature',
        'status',
        'error_message',
        'retry_count',
        'received_at',
        'processed_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'received_at' => 'datetime',
        'processed_at' => 'datetime',
    ];

    /**
     * Get the social account that owns the webhook event.
     */
    public function socialAccount(): BelongsTo
    {
        return $this->belongsTo(SocialAccount::class);
    }

    /**
     * Get the webhook config that owns the webhook event.
     */
    public function webhookConfig(): BelongsTo
    {
        return $this->belongsTo(WebhookConfig::class);
    }

    /**
     * Get the processing records for this webhook event.
     */
    public function processing(): HasMany
    {
        return $this->hasMany(WebhookEventProcessing::class);
    }

    /**
     * Scope a query to only include pending events.
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope a query to only include failed events.
     */
    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    /**
     * Scope a query to only include events that need retry.
     */
    public function scopeNeedsRetry($query)
    {
        return $query->failed()
            ->where('retry_count', '<', 5)
            ->where('updated_at', '<', now()->subMinutes(5));
    }

    /**
     * Mark event as processed.
     */
    public function markAsProcessed(): void
    {
        $this->update([
            'status' => 'processed',
            'processed_at' => now(),
        ]);
    }

    /**
     * Mark event as failed.
     */
    public function markAsFailed(string $errorMessage): void
    {
        $this->update([
            'status' => 'failed',
            'error_message' => $errorMessage,
            'retry_count' => $this->retry_count + 1,
        ]);
    }

    /**
     * Mark event as ignored.
     */
    public function markAsIgnored(): void
    {
        $this->update([
            'status' => 'ignored',
            'processed_at' => now(),
        ]);
    }

    /**
     * Check if event can be retried.
     */
    public function canRetry(): bool
    {
        return $this->status === 'failed' && $this->retry_count < 5;
    }

    /**
     * Get platform-specific data from payload.
     */
    public function getPlatformData(string $key, mixed $default = null): mixed
    {
        return data_get($this->payload, $key, $default);
    }

    /**
     * Check if this event relates to a specific post.
     */
    public function relatesToPost(string $platformPostId): bool
    {
        return $this->object_id === $platformPostId || 
               $this->getPlatformData('post_id') === $platformPostId ||
               $this->getPlatformData('media.id') === $platformPostId;
    }
}