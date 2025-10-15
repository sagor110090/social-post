<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WebhookDeliveryMetric extends Model
{
    use HasFactory;

    protected $fillable = [
        'webhook_config_id',
        'social_account_id',
        'platform',
        'date',
        'total_received',
        'successfully_processed',
        'failed',
        'ignored',
        'retry_attempts',
        'average_processing_time',
        'event_type_breakdown',
    ];

    protected $casts = [
        'date' => 'date',
        'event_type_breakdown' => 'array',
        'average_processing_time' => 'decimal:3',
    ];

    /**
     * Get the webhook config that owns the metric.
     */
    public function webhookConfig(): BelongsTo
    {
        return $this->belongsTo(WebhookConfig::class);
    }

    /**
     * Get the social account that owns the metric.
     */
    public function socialAccount(): BelongsTo
    {
        return $this->belongsTo(SocialAccount::class);
    }

    /**
     * Calculate success rate.
     */
    public function getSuccessRateAttribute(): float
    {
        if ($this->total_received === 0) {
            return 0;
        }

        return round(($this->successfully_processed / $this->total_received) * 100, 2);
    }

    /**
     * Calculate failure rate.
     */
    public function getFailureRateAttribute(): float
    {
        if ($this->total_received === 0) {
            return 0;
        }

        return round(($this->failed / $this->total_received) * 100, 2);
    }

    /**
     * Get total events processed (successful + failed + ignored).
     */
    public function getTotalProcessedAttribute(): int
    {
        return $this->successfully_processed + $this->failed + $this->ignored;
    }

    /**
     * Scope a query to filter by date range.
     */
    public function scopeDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('date', [$startDate, $endDate]);
    }

    /**
     * Scope a query to filter by platform.
     */
    public function scopePlatform($query, string $platform)
    {
        return $query->where('platform', $platform);
    }

    /**
     * Scope a query for recent metrics.
     */
    public function scopeRecent($query, int $days = 30)
    {
        return $query->where('date', '>=', now()->subDays($days));
    }

    /**
     * Increment metrics for a webhook event.
     */
    public static function incrementMetrics(
        WebhookConfig $config,
        string $eventType,
        string $status,
        float $processingTime = 0
    ): self {
        $metric = static::firstOrCreate(
            [
                'webhook_config_id' => $config->id,
                'social_account_id' => $config->social_account_id,
                'platform' => $config->socialAccount->platform,
                'date' => now()->toDateString(),
            ],
            [
                'total_received' => 0,
                'successfully_processed' => 0,
                'failed' => 0,
                'ignored' => 0,
                'retry_attempts' => 0,
                'average_processing_time' => 0,
                'event_type_breakdown' => [],
            ]
        );

        // Increment counters
        $metric->increment('total_received');
        
        switch ($status) {
            case 'processed':
                $metric->increment('successfully_processed');
                break;
            case 'failed':
                $metric->increment('failed');
                $metric->increment('retry_attempts');
                break;
            case 'ignored':
                $metric->increment('ignored');
                break;
        }

        // Update event type breakdown
        $breakdown = $metric->event_type_breakdown ?? [];
        $breakdown[$eventType] = ($breakdown[$eventType] ?? 0) + 1;
        $metric->event_type_breakdown = $breakdown;

        // Update average processing time
        if ($processingTime > 0) {
            $totalProcessed = $metric->total_processed;
            $currentAvg = $metric->average_processing_time;
            $newAvg = (($currentAvg * ($totalProcessed - 1)) + $processingTime) / $totalProcessed;
            $metric->average_processing_time = round($newAvg, 3);
        }

        $metric->save();

        return $metric;
    }
}