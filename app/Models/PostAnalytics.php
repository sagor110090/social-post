<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PostAnalytics extends Model
{
    protected $fillable = [
        'post_id',
        'scheduled_post_id',
        'platform',
        'platform_post_id',
        'likes',
        'comments',
        'shares',
        'reach',
        'impressions',
        'engagement',
        'metrics',
        'recorded_at',
    ];

    protected $casts = [
        'metrics' => 'array',
        'recorded_at' => 'datetime',
    ];

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }

    public function scheduledPost(): BelongsTo
    {
        return $this->belongsTo(ScheduledPost::class);
    }

    public function webhookEventProcessing(): HasMany
    {
        return $this->hasMany(WebhookEventProcessing::class);
    }

    public function calculateEngagementRate(): float
    {
        $totalEngagement = $this->likes + $this->comments + $this->shares;
        $reach = $this->reach > 0 ? $this->reach : $this->impressions;

        if ($reach === 0) {
            return 0;
        }

        return round(($totalEngagement / $reach) * 100, 2);
    }

    public function getTotalEngagement(): int
    {
        return $this->likes + $this->comments + $this->shares;
    }
}
