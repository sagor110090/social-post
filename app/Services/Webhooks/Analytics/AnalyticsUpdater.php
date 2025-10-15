<?php

namespace App\Services\Webhooks\Analytics;

use App\Models\PostAnalytics;
use App\Models\Post;
use App\Models\WebhookEvent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class AnalyticsUpdater implements AnalyticsUpdaterInterface
{
    /**
     * Update analytics for a specific post.
     */
    public function update(string $platformPostId, string $platform, array $metrics): void
    {
        try {
            DB::transaction(function () use ($platformPostId, $platform, $metrics) {
                $analytics = $this->findOrCreateAnalytics($platformPostId, $platform);
                
                if ($analytics) {
                    $this->updateAnalyticsRecord($analytics, $metrics);
                    $this->updateTrends($platform, $metrics);
                }
            });
        } catch (\Exception $e) {
            Log::error('Failed to update analytics', [
                'platform_post_id' => $platformPostId,
                'platform' => $platform,
                'metrics' => $metrics,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Create new analytics record.
     */
    public function create(array $data): void
    {
        try {
            PostAnalytics::create($data);
        } catch (\Exception $e) {
            Log::error('Failed to create analytics record', [
                'data' => $data,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Calculate engagement rate.
     */
    public function calculateEngagementRate(array $metrics): float
    {
        $totalEngagement = ($metrics['likes'] ?? 0) + ($metrics['comments'] ?? 0) + ($metrics['shares'] ?? 0);
        $reach = ($metrics['reach'] ?? 0) ?: ($metrics['impressions'] ?? 0);

        if ($reach === 0) {
            return 0.0;
        }

        return round(($totalEngagement / $reach) * 100, 2);
    }

    /**
     * Aggregate metrics over time period.
     */
    public function aggregateMetrics(string $platform, string $period = '24h'): array
    {
        $cacheKey = "analytics_aggregate_{$platform}_{$period}";
        
        return Cache::remember($cacheKey, now()->addMinutes(15), function () use ($platform, $period) {
            $startDate = match ($period) {
                '1h' => now()->subHour(),
                '24h' => now()->subDay(),
                '7d' => now()->subWeek(),
                '30d' => now()->subMonth(),
                default => now()->subDay(),
            };

            $analytics = PostAnalytics::where('platform', $platform)
                ->where('recorded_at', '>=', $startDate)
                ->get();

            return [
                'total_posts' => $analytics->count(),
                'total_likes' => $analytics->sum('likes'),
                'total_comments' => $analytics->sum('comments'),
                'total_shares' => $analytics->sum('shares'),
                'total_reach' => $analytics->sum('reach'),
                'total_impressions' => $analytics->sum('impressions'),
                'average_engagement_rate' => $analytics->avg(fn($a) => $a->calculateEngagementRate()),
                'top_performing_posts' => $this->getTopPerformingPosts($analytics),
                'growth_metrics' => $this->calculateGrowthMetrics($platform, $startDate),
            ];
        });
    }

    /**
     * Update trend analysis.
     */
    public function updateTrends(string $platform, array $newMetrics): void
    {
        $trendKey = "analytics_trends_{$platform}";
        $trends = Cache::get($trendKey, [
            'hourly' => [],
            'daily' => [],
            'weekly' => [],
        ]);

        $now = now();
        $hourKey = $now->format('Y-m-d H:00');
        $dayKey = $now->format('Y-m-d');
        $weekKey = $now->startOfWeek()->format('Y-m-d');

        // Update hourly trends
        if (!isset($trends['hourly'][$hourKey])) {
            $trends['hourly'][$hourKey] = [
                'likes' => 0,
                'comments' => 0,
                'shares' => 0,
                'reach' => 0,
                'impressions' => 0,
                'posts' => 0,
            ];
        }

        $trends['hourly'][$hourKey]['likes'] += $newMetrics['likes'] ?? 0;
        $trends['hourly'][$hourKey]['comments'] += $newMetrics['comments'] ?? 0;
        $trends['hourly'][$hourKey]['shares'] += $newMetrics['shares'] ?? 0;
        $trends['hourly'][$hourKey]['reach'] += $newMetrics['reach'] ?? 0;
        $trends['hourly'][$hourKey]['impressions'] += $newMetrics['impressions'] ?? 0;
        $trends['hourly'][$hourKey]['posts'] += 1;

        // Clean old hourly data (keep 48 hours)
        $trends['hourly'] = array_slice($trends['hourly'], -48, null, true);

        // Update daily trends
        if (!isset($trends['daily'][$dayKey])) {
            $trends['daily'][$dayKey] = [
                'likes' => 0,
                'comments' => 0,
                'shares' => 0,
                'reach' => 0,
                'impressions' => 0,
                'posts' => 0,
            ];
        }

        $trends['daily'][$dayKey]['likes'] += $newMetrics['likes'] ?? 0;
        $trends['daily'][$dayKey]['comments'] += $newMetrics['comments'] ?? 0;
        $trends['daily'][$dayKey]['shares'] += $newMetrics['shares'] ?? 0;
        $trends['daily'][$dayKey]['reach'] += $newMetrics['reach'] ?? 0;
        $trends['daily'][$dayKey]['impressions'] += $newMetrics['impressions'] ?? 0;
        $trends['daily'][$dayKey]['posts'] += 1;

        // Clean old daily data (keep 30 days)
        $trends['daily'] = array_slice($trends['daily'], -30, null, true);

        Cache::put($trendKey, $trends, now()->addDays(7));
    }

    /**
     * Find or create analytics record.
     */
    protected function findOrCreateAnalytics(string $platformPostId, string $platform): ?PostAnalytics
    {
        // Try to find existing analytics
        $analytics = PostAnalytics::where('platform', $platform)
            ->where('platform_post_id', $platformPostId)
            ->first();

        if ($analytics) {
            return $analytics;
        }

        // Try to find associated post
        $post = Post::where('platform', $platform)
            ->where('platform_post_id', $platformPostId)
            ->first();

        if (!$post) {
            Log::warning('No post found for analytics update', [
                'platform_post_id' => $platformPostId,
                'platform' => $platform,
            ]);
            return null;
        }

        // Create new analytics record
        return PostAnalytics::create([
            'post_id' => $post->id,
            'social_account_id' => $post->social_account_id,
            'platform' => $platform,
            'platform_post_id' => $platformPostId,
            'metrics' => [],
            'recorded_at' => now(),
        ]);
    }

    /**
     * Update analytics record with new metrics.
     */
    protected function updateAnalyticsRecord(PostAnalytics $analytics, array $metrics): void
    {
        $currentMetrics = $analytics->metrics ?? [];
        $updatedMetrics = array_merge($currentMetrics, $metrics);

        // Update standard fields
        $analytics->likes = $metrics['likes'] ?? $analytics->likes;
        $analytics->comments = $metrics['comments'] ?? $analytics->comments;
        $analytics->shares = $metrics['shares'] ?? $analytics->shares;
        $analytics->reach = $metrics['reach'] ?? $analytics->reach;
        $analytics->impressions = $metrics['impressions'] ?? $analytics->impressions;

        // Calculate engagement
        $analytics->engagement = $this->calculateEngagementRate($updatedMetrics);

        // Update metrics JSON
        $analytics->metrics = $updatedMetrics;
        $analytics->recorded_at = now();

        $analytics->save();
    }

    /**
     * Get top performing posts.
     */
    protected function getTopPerformingPosts($analytics): array
    {
        return $analytics
            ->sortByDesc(fn($a) => $a->getTotalEngagement())
            ->take(5)
            ->map(fn($a) => [
                'post_id' => $a->post_id,
                'platform_post_id' => $a->platform_post_id,
                'total_engagement' => $a->getTotalEngagement(),
                'engagement_rate' => $a->calculateEngagementRate(),
                'recorded_at' => $a->recorded_at,
            ])
            ->values()
            ->toArray();
    }

    /**
     * Calculate growth metrics.
     */
    protected function calculateGrowthMetrics(string $platform, Carbon $startDate): array
    {
        $previousPeriod = $startDate->copy()->subDays($startDate->diffInDays(now()));

        $currentMetrics = PostAnalytics::where('platform', $platform)
            ->where('recorded_at', '>=', $startDate)
            ->get();

        $previousMetrics = PostAnalytics::where('platform', $platform)
            ->where('recorded_at', '>=', $previousPeriod)
            ->where('recorded_at', '<', $startDate)
            ->get();

        return [
            'followers_growth' => $this->calculateGrowth(
                $previousMetrics->sum('reach'),
                $currentMetrics->sum('reach')
            ),
            'engagement_growth' => $this->calculateGrowth(
                $previousMetrics->sum('engagement'),
                $currentMetrics->sum('engagement')
            ),
            'posts_growth' => $this->calculateGrowth(
                $previousMetrics->count(),
                $currentMetrics->count()
            ),
        ];
    }

    /**
     * Calculate growth percentage.
     */
    protected function calculateGrowth(int $previous, int $current): float
    {
        if ($previous === 0) {
            return $current > 0 ? 100.0 : 0.0;
        }

        return round((($current - $previous) / $previous) * 100, 2);
    }
}