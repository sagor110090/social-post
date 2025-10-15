<?php

namespace App\Services\Webhooks;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class WebhookMetricsService
{
    private array $config;
    private WebhookLoggingService $logger;

    public function __construct(WebhookLoggingService $logger)
    {
        $this->config = config('monitoring.metrics');
        $this->logger = $logger;
    }

    /**
     * Record a metric.
     */
    public function recordMetric(string $type, array $dimensions, float $value, array $tags = []): void
    {
        if (!$this->config['enabled']) {
            return;
        }

        $timestamp = now()->timestamp;
        $key = $this->buildMetricKey($type, $dimensions, $tags);
        
        $metric = [
            'value' => $value,
            'timestamp' => $timestamp,
            'tags' => $tags,
        ];

        // Store in Redis for real-time access
        $this->storeMetricInRedis($key, $metric);
        
        // Store aggregated metrics
        $this->aggregateMetric($type, $dimensions, $value, $timestamp);
        
        // Log metric for archival
        $this->logger->logPerformanceMetrics([
            'metric_type' => $type,
            'dimensions' => $dimensions,
            'value' => $value,
            'tags' => $tags,
        ]);
    }

    /**
     * Record request volume metric.
     */
    public function recordRequestVolume(string $platform, string $eventType, string $status): void
    {
        $this->recordMetric('request_volume', [
            'platform' => $platform,
            'event_type' => $eventType,
            'status' => $status,
        ], 1, [
            'metric_type' => 'counter',
        ]);
    }

    /**
     * Record response time metric.
     */
    public function recordResponseTime(string $platform, float $responseTime): void
    {
        $this->recordMetric('response_times', [
            'platform' => $platform,
        ], $responseTime, [
            'metric_type' => 'histogram',
        ]);
    }

    /**
     * Record error metric.
     */
    public function recordError(string $platform, string $errorType): void
    {
        $this->recordMetric('error_rates', [
            'platform' => $platform,
            'error_type' => $errorType,
        ], 1, [
            'metric_type' => 'counter',
        ]);
    }

    /**
     * Record queue metric.
     */
    public function recordQueueMetric(string $queue, string $metric, float $value): void
    {
        $this->recordMetric('queue_metrics', [
            'queue' => $queue,
            'metric' => $metric,
        ], $value, [
            'metric_type' => 'gauge',
        ]);
    }

    /**
     * Record security event metric.
     */
    public function recordSecurityEvent(string $platform, string $violationType): void
    {
        $this->recordMetric('security_events', [
            'platform' => $platform,
            'violation_type' => $violationType,
        ], 1, [
            'metric_type' => 'counter',
        ]);
    }

    /**
     * Get metrics for a time range.
     */
    public function getMetrics(string $type, array $dimensions = [], string $interval = '1h', int $limit = 100): Collection
    {
        $key = $this->buildAggregatedKey($type, $dimensions, $interval);
        
        return $this->getMetricsFromRedis($key, $limit);
    }

    /**
     * Get request volume metrics.
     */
    public function getRequestVolumeMetrics(string $interval = '1h', array $filters = []): array
    {
        $dimensions = [];
        if (!empty($filters['platform'])) {
            $dimensions['platform'] = $filters['platform'];
        }
        if (!empty($filters['event_type'])) {
            $dimensions['event_type'] = $filters['event_type'];
        }

        return $this->getAggregatedMetrics('request_volume', $dimensions, $interval);
    }

    /**
     * Get response time metrics with percentiles.
     */
    public function getResponseTimeMetrics(string $interval = '1h', array $filters = []): array
    {
        $dimensions = [];
        if (!empty($filters['platform'])) {
            $dimensions['platform'] = $filters['platform'];
        }

        $metrics = $this->getAggregatedMetrics('response_times', $dimensions, $interval);
        $percentiles = $this->config['types']['response_times']['percentiles'];

        // Calculate percentiles
        foreach ($metrics as $platform => $data) {
            $values = $data['values'] ?? [];
            if (!empty($values)) {
                sort($values);
                $count = count($values);
                
                foreach ($percentiles as $percentile) {
                    $index = ceil(($percentile / 100) * $count) - 1;
                    $metrics[$platform]['percentiles'][$percentile] = $values[$index] ?? 0;
                }
            }
        }

        return $metrics;
    }

    /**
     * Get error rate metrics.
     */
    public function getErrorRateMetrics(string $interval = '1h', array $filters = []): array
    {
        $dimensions = [];
        if (!empty($filters['platform'])) {
            $dimensions['platform'] = $filters['platform'];
        }
        if (!empty($filters['error_type'])) {
            $dimensions['error_type'] = $filters['error_type'];
        }

        return $this->getAggregatedMetrics('error_rates', $dimensions, $interval);
    }

    /**
     * Get queue metrics.
     */
    public function getQueueMetrics(string $interval = '1h', array $filters = []): array
    {
        $dimensions = [];
        if (!empty($filters['queue'])) {
            $dimensions['queue'] = $filters['queue'];
        }
        if (!empty($filters['metric'])) {
            $dimensions['metric'] = $filters['metric'];
        }

        return $this->getAggregatedMetrics('queue_metrics', $dimensions, $interval);
    }

    /**
     * Get security event metrics.
     */
    public function getSecurityEventMetrics(string $interval = '1h', array $filters = []): array
    {
        $dimensions = [];
        if (!empty($filters['platform'])) {
            $dimensions['platform'] = $filters['platform'];
        }
        if (!empty($filters['violation_type'])) {
            $dimensions['violation_type'] = $filters['violation_type'];
        }

        return $this->getAggregatedMetrics('security_events', $dimensions, $interval);
    }

    /**
     * Get dashboard data.
     */
    public function getDashboardData(string $timeRange = '24h'): array
    {
        $data = [];
        
        // Request volume
        $data['request_volume'] = $this->getRequestVolumeMetrics($timeRange);
        
        // Response times
        $data['response_times'] = $this->getResponseTimeMetrics($timeRange);
        
        // Error rates
        $data['error_rates'] = $this->getErrorRateMetrics($timeRange);
        
        // Queue metrics
        $data['queue_metrics'] = $this->getQueueMetrics($timeRange);
        
        // Security events
        $data['security_events'] = $this->getSecurityEventMetrics($timeRange);
        
        // Platform breakdown
        $data['platform_breakdown'] = $this->getPlatformBreakdown($timeRange);
        
        // Trend data
        $data['trends'] = $this->getTrendData($timeRange);
        
        return $data;
    }

    /**
     * Get platform breakdown.
     */
    private function getPlatformBreakdown(string $interval): array
    {
        $platforms = ['facebook', 'instagram', 'twitter', 'linkedin'];
        $breakdown = [];
        
        foreach ($platforms as $platform) {
            $breakdown[$platform] = [
                'request_volume' => $this->getRequestVolumeMetrics($interval, ['platform' => $platform]),
                'error_rate' => $this->getErrorRateMetrics($interval, ['platform' => $platform]),
                'avg_response_time' => $this->getResponseTimeMetrics($interval, ['platform' => $platform]),
            ];
        }
        
        return $breakdown;
    }

    /**
     * Get trend data.
     */
    private function getTrendData(string $interval): array
    {
        $trends = [];
        $intervals = $this->getIntervalPoints($interval);
        
        foreach ($intervals as $point) {
            $timestamp = $point['timestamp'];
            $trends[$timestamp] = [
                'timestamp' => $timestamp,
                'request_volume' => $this->getMetricAtTimestamp('request_volume', [], $timestamp),
                'error_rate' => $this->getMetricAtTimestamp('error_rates', [], $timestamp),
                'avg_response_time' => $this->getMetricAtTimestamp('response_times', [], $timestamp),
            ];
        }
        
        return array_values($trends);
    }

    /**
     * Build metric key.
     */
    private function buildMetricKey(string $type, array $dimensions, array $tags): string
    {
        $key = "metric:{$type}";
        
        foreach ($dimensions as $key => $value) {
            $key .= ":{$key}:{$value}";
        }
        
        foreach ($tags as $key => $value) {
            $key .= ":tag:{$key}:{$value}";
        }
        
        return $key;
    }

    /**
     * Build aggregated metric key.
     */
    private function buildAggregatedKey(string $type, array $dimensions, string $interval): string
    {
        $key = "aggregated:{$type}:{$interval}";
        
        foreach ($dimensions as $key => $value) {
            $key .= ":{$key}:{$value}";
        }
        
        return $key;
    }

    /**
     * Store metric in Redis.
     */
    private function storeMetricInRedis(string $key, array $metric): void
    {
        $redisKey = $this->config['storage']['prefix'] . $key;
        $ttl = $this->config['storage']['ttl'];
        
        Redis::lpush($redisKey, json_encode($metric));
        Redis::expire($redisKey, $ttl);
        
        // Trim the list to prevent unlimited growth
        Redis::ltrim($redisKey, 0, 1000);
    }

    /**
     * Aggregate metric.
     */
    private function aggregateMetric(string $type, array $dimensions, float $value, int $timestamp): void
    {
        foreach ($this->config['aggregation']['intervals'] as $name => $seconds) {
            $bucket = floor($timestamp / $seconds) * $seconds;
            $key = $this->buildAggregatedKey($type, $dimensions, $name);
            $redisKey = $this->config['storage']['prefix'] . $key;
            
            // Store aggregated data
            $aggregated = [
                'bucket' => $bucket,
                'count' => 1,
                'sum' => $value,
                'min' => $value,
                'max' => $value,
                'values' => [$value],
            ];
            
            // Use Redis atomic operations for aggregation
            $script = "
                local key = KEYS[1]
                local value = tonumber(ARGV[1])
                local bucket = tonumber(ARGV[2])
                local ttl = tonumber(ARGV[3])
                
                local existing = redis.call('HGET', key, bucket)
                if existing then
                    local data = cjson.decode(existing)
                    data.count = data.count + 1
                    data.sum = data.sum + value
                    data.min = math.min(data.min, value)
                    data.max = math.max(data.max, value)
                    table.insert(data.values, value)
                    redis.call('HSET', key, bucket, cjson.encode(data))
                else
                    local data = {
                        bucket = bucket,
                        count = 1,
                        sum = value,
                        min = value,
                        max = value,
                        values = {value}
                    }
                    redis.call('HSET', key, bucket, cjson.encode(data))
                end
                
                redis.call('EXPIRE', key, ttl)
                return 'OK'
            ";
            
            Redis::eval($script, 1, $redisKey, $value, $bucket, $this->config['storage']['ttl']);
        }
    }

    /**
     * Get metrics from Redis.
     */
    private function getMetricsFromRedis(string $key, int $limit): Collection
    {
        $redisKey = $this->config['storage']['prefix'] . $key;
        $data = Redis::lrange($redisKey, 0, $limit - 1);
        
        return collect($data)->map(function ($item) {
            return json_decode($item, true);
        });
    }

    /**
     * Get aggregated metrics.
     */
    private function getAggregatedMetrics(string $type, array $dimensions, string $interval): array
    {
        $key = $this->buildAggregatedKey($type, $dimensions, $interval);
        $redisKey = $this->config['storage']['prefix'] . $key;
        
        $data = Redis::hgetall($redisKey);
        $metrics = [];
        
        foreach ($data as $bucket => $json) {
            $metric = json_decode($json, true);
            $metrics[] = $metric;
        }
        
        // Group by dimensions
        $grouped = [];
        foreach ($metrics as $metric) {
            $groupKey = $this->getGroupKey($metric, $dimensions);
            
            if (!isset($grouped[$groupKey])) {
                $grouped[$groupKey] = [
                    'count' => 0,
                    'sum' => 0,
                    'min' => PHP_FLOAT_MAX,
                    'max' => 0,
                    'values' => [],
                ];
            }
            
            $grouped[$groupKey]['count'] += $metric['count'];
            $grouped[$groupKey]['sum'] += $metric['sum'];
            $grouped[$groupKey]['min'] = min($grouped[$groupKey]['min'], $metric['min']);
            $grouped[$groupKey]['max'] = max($grouped[$groupKey]['max'], $metric['max']);
            $grouped[$groupKey]['values'] = array_merge($grouped[$groupKey]['values'], $metric['values']);
        }
        
        // Calculate averages
        foreach ($grouped as $key => &$data) {
            $data['average'] = $data['count'] > 0 ? $data['sum'] / $data['count'] : 0;
        }
        
        return $grouped;
    }

    /**
     * Get group key for metrics.
     */
    private function getGroupKey(array $metric, array $dimensions): string
    {
        // This is a simplified implementation
        // In practice, you'd want to extract the dimension values from the metric key
        return 'default';
    }

    /**
     * Get metric at specific timestamp.
     */
    private function getMetricAtTimestamp(string $type, array $dimensions, int $timestamp): float
    {
        // Find the closest bucket
        foreach ($this->config['aggregation']['intervals'] as $name => $seconds) {
            $bucket = floor($timestamp / $seconds) * $seconds;
            $key = $this->buildAggregatedKey($type, $dimensions, $name);
            $redisKey = $this->config['storage']['prefix'] . $key;
            
            $data = Redis::hget($redisKey, $bucket);
            if ($data) {
                $metric = json_decode($data, true);
                return $metric['average'] ?? $metric['sum'] ?? 0;
            }
        }
        
        return 0;
    }

    /**
     * Get interval points for trend data.
     */
    private function getIntervalPoints(string $interval): array
    {
        $points = [];
        $now = now();
        
        $intervals = [
            '1h' => ['count' => 60, 'step' => 60],      // 60 points, 1 minute each
            '6h' => ['count' => 72, 'step' => 300],     // 72 points, 5 minutes each
            '24h' => ['count' => 96, 'step' => 900],    // 96 points, 15 minutes each
            '7d' => ['count' => 168, 'step' => 3600],   // 168 points, 1 hour each
            '30d' => ['count' => 180, 'step' => 14400], // 180 points, 4 hours each
        ];
        
        $config = $intervals[$interval] ?? $intervals['24h'];
        
        for ($i = $config['count'] - 1; $i >= 0; $i--) {
            $timestamp = $now->copy()->subMinutes($i * $config['step'] / 60)->timestamp;
            $points[] = [
                'timestamp' => $timestamp,
                'datetime' => date('Y-m-d H:i:s', $timestamp),
            ];
        }
        
        return $points;
    }

    /**
     * Clean up old metrics.
     */
    public function cleanup(): void
    {
        $cutoffTime = now()->subDays($this->config['retention_days'])->timestamp;
        $prefix = $this->config['storage']['prefix'];
        
        // Get all metric keys
        $keys = Redis::keys($prefix . '*');
        
        foreach ($keys as $key) {
            // Check if it's an aggregated metric
            if (str_contains($key, 'aggregated:')) {
                $this->cleanupAggregatedMetrics($key, $cutoffTime);
            } else {
                // For regular metrics, just check TTL
                $ttl = Redis::ttl($key);
                if ($ttl === -1) { // No expiration set
                    Redis::expire($key, $this->config['storage']['ttl']);
                }
            }
        }
    }

    /**
     * Clean up aggregated metrics.
     */
    private function cleanupAggregatedMetrics(string $key, int $cutoffTime): void
    {
        $data = Redis::hgetall($key);
        
        foreach ($data as $bucket => $json) {
            $bucket = (int) $bucket;
            if ($bucket < $cutoffTime) {
                Redis::hdel($key, $bucket);
            }
        }
    }
}