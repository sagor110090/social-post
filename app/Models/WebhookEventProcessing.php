<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WebhookEventProcessing extends Model
{
    use HasFactory;

    protected $fillable = [
        'webhook_event_id',
        'post_id',
        'scheduled_post_id',
        'post_analytics_id',
        'processor_type',
        'status',
        'processing_data',
        'result',
        'error_message',
        'attempt',
        'next_attempt_at',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'processing_data' => 'array',
        'result' => 'array',
        'next_attempt_at' => 'datetime',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    /**
     * Get the webhook event that owns the processing record.
     */
    public function webhookEvent(): BelongsTo
    {
        return $this->belongsTo(WebhookEvent::class);
    }

    /**
     * Get the post associated with this processing.
     */
    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }

    /**
     * Get the scheduled post associated with this processing.
     */
    public function scheduledPost(): BelongsTo
    {
        return $this->belongsTo(ScheduledPost::class);
    }

    /**
     * Get the post analytics associated with this processing.
     */
    public function postAnalytics(): BelongsTo
    {
        return $this->belongsTo(PostAnalytics::class);
    }

    /**
     * Scope a query to only include pending processing.
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending')
            ->where(function ($q) {
                $q->whereNull('next_attempt_at')
                  ->orWhere('next_attempt_at', '<=', now());
            });
    }

    /**
     * Scope a query to only include failed processing.
     */
    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    /**
     * Scope a query to only include processing that needs retry.
     */
    public function scopeNeedsRetry($query)
    {
        return $query->failed()
            ->where('attempt', '<', 3)
            ->where('next_attempt_at', '<=', now());
    }

    /**
     * Mark processing as started.
     */
    public function markAsStarted(): void
    {
        $this->update([
            'status' => 'processing',
            'started_at' => now(),
        ]);
    }

    /**
     * Mark processing as completed.
     */
    public function markAsCompleted(array $result = []): void
    {
        $this->update([
            'status' => 'completed',
            'result' => $result,
            'completed_at' => now(),
        ]);
    }

    /**
     * Mark processing as failed.
     */
    public function markAsFailed(string $errorMessage): void
    {
        $this->update([
            'status' => 'failed',
            'error_message' => $errorMessage,
            'attempt' => $this->attempt + 1,
            'next_attempt_at' => $this->attempt < 3 ? now()->addMinutes(5 * $this->attempt) : null,
        ]);
    }

    /**
     * Get processing duration in seconds.
     */
    public function getProcessingDuration(): float
    {
        if (!$this->started_at) {
            return 0;
        }

        $endTime = $this->completed_at ?? now();
        
        return $endTime->diffInSeconds($this->started_at, true);
    }

    /**
     * Check if processing can be retried.
     */
    public function canRetry(): bool
    {
        return $this->status === 'failed' && $this->attempt < 3;
    }
}