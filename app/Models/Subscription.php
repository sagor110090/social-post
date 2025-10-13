<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Laravel\Cashier\Subscription as CashierSubscription;

class Subscription extends CashierSubscription
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'type',
        'stripe_id',
        'stripe_status',
        'stripe_price',
        'quantity',
        'trial_ends_at',
        'ends_at',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
        'trial_ends_at' => 'datetime',
        'ends_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(SubscriptionItem::class);
    }

    public function isOnTrial()
    {
        return $this->trial_ends_at && $this->trial_ends_at->isFuture();
    }

    public function isCancelled()
    {
        return $this->ends_at && $this->ends_at->isFuture();
    }

    public function isExpired()
    {
        return $this->ends_at && $this->ends_at->isPast();
    }

    public function isActive()
    {
        return $this->stripe_status === 'active' && !$this->isExpired();
    }

    public function getPlanName()
    {
        $plans = [
            'price_basic' => 'Basic',
            'price_pro' => 'Pro',
            'price_enterprise' => 'Enterprise',
        ];

        return $plans[$this->stripe_price] ?? 'Unknown';
    }

    public function getPlanLimits()
    {
        $limits = [
            'price_basic' => [
                'posts_per_month' => 30,
                'social_accounts' => 3,
                'ai_generations' => 100,
                'team_members' => 1,
            ],
            'price_pro' => [
                'posts_per_month' => 300,
                'social_accounts' => 10,
                'ai_generations' => 1000,
                'team_members' => 5,
            ],
            'price_enterprise' => [
                'posts_per_month' => -1, // unlimited
                'social_accounts' => -1,
                'ai_generations' => -1,
                'team_members' => -1,
            ],
        ];

        return $limits[$this->stripe_price] ?? $limits['price_basic'];
    }

    public function getMaxSocialAccounts(): int
    {
        $limits = $this->getPlanLimits();
        return $limits['social_accounts'] === -1 ? 999 : $limits['social_accounts'];
    }

    public function canAccessAI(): bool
    {
        return in_array($this->stripe_price, ['price_pro', 'price_enterprise']) && $this->isActive();
    }

    public function canAccessAnalytics(): bool
    {
        return in_array($this->stripe_price, ['price_pro', 'price_enterprise']) && $this->isActive();
    }

    public function canCreateTeams(): bool
    {
        return $this->stripe_price === 'price_enterprise' && $this->isActive();
    }
}
