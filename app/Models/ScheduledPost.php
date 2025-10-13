<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ScheduledPost extends Model
{
    protected $fillable = [
        'post_id',
        'user_id',
        'platforms',
        'scheduled_at',
        'status',
        'error_message',
        'results',
        'completed_at',
        'failed_at',
    ];

    protected $casts = [
        'platforms' => 'array',
        'results' => 'array',
        'scheduled_at' => 'datetime',
        'completed_at' => 'datetime',
        'failed_at' => 'datetime',
    ];

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function analytics(): HasMany
    {
        return $this->hasMany(PostAnalytics::class);
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isProcessing(): bool
    {
        return $this->status === 'processing';
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function hasFailed(): bool
    {
        return $this->status === 'failed';
    }

    public function isOverdue(): bool
    {
        return $this->isPending() && $this->scheduled_at->isPast();
    }

    public function isPartiallyCompleted(): bool
    {
        return $this->status === 'partially_completed';
    }

    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }

    public function getTimeUntilPublication(): string
    {
        if (!$this->isPending()) {
            return 'Not scheduled';
        }

        return $this->scheduled_at->diffForHumans(now(), true);
    }

    public function getScheduledAtForHumans(): string
    {
        return $this->scheduled_at->format('M j, Y \a\t g:i A');
    }

    public function canBeRescheduled(): bool
    {
        return $this->isPending() && $this->scheduled_at->gt(now()->addMinutes(30));
    }

    public function canBeCancelled(): bool
    {
        return $this->isPending();
    }

    public function canBePublishedNow(): bool
    {
        return $this->isPending();
    }

    /**
     * Scope to get only pending scheduled posts.
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope to get only completed scheduled posts.
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    /**
     * Scope to get only failed scheduled posts.
     */
    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    /**
     * Scope to get posts scheduled for a specific date range.
     */
    public function scopeScheduledBetween($query, $startDate, $endDate)
    {
        return $query->whereBetween('scheduled_at', [$startDate, $endDate]);
    }

    /**
     * Scope to get posts scheduled for today.
     */
    public function scopeScheduledForToday($query)
    {
        return $query->whereDate('scheduled_at', now()->toDateString());
    }

    /**
     * Scope to get overdue posts.
     */
    public function scopeOverdue($query)
    {
        return $query->where('status', 'pending')
            ->where('scheduled_at', '<', now()->subMinutes(5));
    }
}
