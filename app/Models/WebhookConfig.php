<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WebhookConfig extends Model
{
    use HasFactory;

    protected $fillable = [
        'social_account_id',
        'webhook_url',
        'secret',
        'events',
        'is_active',
        'metadata',
        'last_verified_at',
    ];

    protected $casts = [
        'events' => 'array',
        'metadata' => 'array',
        'is_active' => 'boolean',
        'last_verified_at' => 'datetime',
    ];

    /**
     * Get the social account that owns the webhook config.
     */
    public function socialAccount(): BelongsTo
    {
        return $this->belongsTo(SocialAccount::class);
    }

    /**
     * Get the subscriptions for this webhook config.
     */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(WebhookSubscription::class);
    }

    /**
     * Get the webhook events for this config.
     */
    public function webhookEvents(): HasMany
    {
        return $this->hasMany(WebhookEvent::class);
    }

    /**
     * Get the delivery metrics for this config.
     */
    public function deliveryMetrics(): HasMany
    {
        return $this->hasMany(WebhookDeliveryMetric::class);
    }

    /**
     * Scope a query to only include active webhook configs.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Get active subscriptions.
     */
    public function activeSubscriptions(): HasMany
    {
        return $this->subscriptions()->where('status', 'active');
    }

    /**
     * Check if webhook is subscribed to a specific event.
     */
    public function isSubscribedTo(string $eventType): bool
    {
        return in_array($eventType, $this->events ?? []);
    }

    /**
     * Generate a webhook secret if not exists.
     */
    public function generateSecret(): string
    {
        $secret = $this->secret ??= bin2hex(random_bytes(32));
        $this->save();
        
        return $secret;
    }

    /**
     * Verify webhook signature.
     */
    public function verifySignature(string $payload, string $signature): bool
    {
        if (!$this->secret) {
            return false;
        }

        $expectedSignature = hash_hmac('sha256', $payload, $this->secret);
        
        return hash_equals($expectedSignature, $signature);
    }
}