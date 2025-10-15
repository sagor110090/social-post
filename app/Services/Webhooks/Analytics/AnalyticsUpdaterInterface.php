<?php

namespace App\Services\Webhooks\Analytics;

interface AnalyticsUpdaterInterface
{
    /**
     * Update analytics for a specific post.
     */
    public function update(string $platformPostId, string $platform, array $metrics): void;

    /**
     * Create new analytics record.
     */
    public function create(array $data): void;

    /**
     * Calculate engagement rate.
     */
    public function calculateEngagementRate(array $metrics): float;

    /**
     * Aggregate metrics over time period.
     */
    public function aggregateMetrics(string $platform, string $period = '24h'): array;

    /**
     * Update trend analysis.
     */
    public function updateTrends(string $platform, array $newMetrics): void;
}