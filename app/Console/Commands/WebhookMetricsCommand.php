<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\Webhooks\WebhookMetricsService;
use App\Services\Webhooks\WebhookLoggingService;

class WebhookMetricsCommand extends Command
{
    protected $signature = 'webhook:metrics 
                            {action : Action to perform (show|collect|cleanup|dashboard)}
                            {--type= : Metric type to show}
                            {--interval=1h : Time interval}
                            {--platform= : Filter by platform}
                            {--json : Output in JSON format}';

    protected $description = 'Manage webhook metrics';

    public function handle(WebhookMetricsService $metrics, WebhookLoggingService $logger): int
    {
        $action = $this->argument('action');

        return match ($action) {
            'show' => $this->showMetrics($metrics),
            'collect' => $this->collectMetrics($logger),
            'cleanup' => $this->cleanupMetrics($metrics, $logger),
            'dashboard' => $this->showDashboard($metrics),
            default => $this->error("Unknown action: {$action}"),
        };
    }

    private function showMetrics(WebhookMetricsService $metrics): int
    {
        $type = $this->option('type');
        $interval = $this->option('interval');
        $platform = $this->option('platform');
        $asJson = $this->option('json');

        if (!$type) {
            $this->error('Metric type is required. Available types: request_volume, response_times, error_rates, queue_metrics, security_events');
            return 1;
        }

        $filters = [];
        if ($platform) {
            $filters['platform'] = $platform;
        }

        $this->info("Showing {$type} metrics for interval: {$interval}");

        try {
            $data = match ($type) {
                'request_volume' => $metrics->getRequestVolumeMetrics($interval, $filters),
                'response_times' => $metrics->getResponseTimeMetrics($interval, $filters),
                'error_rates' => $metrics->getErrorRateMetrics($interval, $filters),
                'queue_metrics' => $metrics->getQueueMetrics($interval, $filters),
                'security_events' => $metrics->getSecurityEventMetrics($interval, $filters),
                default => throw new \InvalidArgumentException("Unknown metric type: {$type}"),
            };

            if ($asJson) {
                $this->line(json_encode($data, JSON_PRETTY_PRINT));
            } else {
                $this->displayMetrics($type, $data, $interval);
            }

            return 0;

        } catch (\Throwable $e) {
            $this->error("Failed to get metrics: {$e->getMessage()}");
            return 1;
        }
    }

    private function collectMetrics(WebhookLoggingService $logger): int
    {
        $this->info('Collecting system metrics...');

        try {
            // Record current system metrics
            $memoryUsage = memory_get_usage(true);
            $memoryPeak = memory_get_peak_usage(true);
            
            $logger->logPerformanceMetrics([
                'operation' => 'manual_metrics_collection',
                'memory_usage' => $memoryUsage,
                'memory_usage_mb' => round($memoryUsage / 1024 / 1024, 2),
                'memory_peak' => $memoryPeak,
                'memory_peak_mb' => round($memoryPeak / 1024 / 1024, 2),
                'timestamp' => now()->toISOString(),
            ]);

            $this->info('Metrics collected successfully.');
            return 0;

        } catch (\Throwable $e) {
            $this->error("Failed to collect metrics: {$e->getMessage()}");
            return 1;
        }
    }

    private function cleanupMetrics(WebhookMetricsService $metrics, WebhookLoggingService $logger): int
    {
        $this->info('Cleaning up old metrics...');

        try {
            $metrics->cleanup();
            
            $logger->logPerformanceMetrics([
                'operation' => 'metrics_cleanup',
                'status' => 'completed',
                'timestamp' => now()->toISOString(),
            ]);

            $this->info('Metrics cleanup completed successfully.');
            return 0;

        } catch (\Throwable $e) {
            $this->error("Failed to cleanup metrics: {$e->getMessage()}");
            return 1;
        }
    }

    private function showDashboard(WebhookMetricsService $metrics): int
    {
        $timeRange = $this->option('interval');
        $asJson = $this->option('json');

        $this->info("Generating dashboard data for: {$timeRange}");

        try {
            $data = $metrics->getDashboardData($timeRange);

            if ($asJson) {
                $this->line(json_encode($data, JSON_PRETTY_PRINT));
            } else {
                $this->displayDashboard($data, $timeRange);
            }

            return 0;

        } catch (\Throwable $e) {
            $this->error("Failed to generate dashboard: {$e->getMessage()}");
            return 1;
        }
    }

    private function displayMetrics(string $type, array $data, string $interval): void
    {
        $this->newLine();
        $this->info("{$type} Metrics ({$interval}):");
        $this->newLine();

        switch ($type) {
            case 'request_volume':
                $this->displayRequestVolume($data);
                break;
            case 'response_times':
                $this->displayResponseTimes($data);
                break;
            case 'error_rates':
                $this->displayErrorRates($data);
                break;
            case 'queue_metrics':
                $this->displayQueueMetrics($data);
                break;
            case 'security_events':
                $this->displaySecurityEvents($data);
                break;
        }
    }

    private function displayRequestVolume(array $data): void
    {
        foreach ($data as $platform => $metrics) {
            $count = $metrics['count'] ?? 0;
            $average = $metrics['average'] ?? 0;
            $this->line("📊 {$platform}: {$count} requests (avg: {$average})");
        }
    }

    private function displayResponseTimes(array $data): void
    {
        foreach ($data as $platform => $metrics) {
            $average = $metrics['average'] ?? 0;
            $percentiles = $metrics['percentiles'] ?? [];
            
            $this->line("⏱️  {$platform}:");
            $this->line("    Average: {$average}ms");
            
            foreach ($percentiles as $p => $value) {
                $this->line("    {$p}th percentile: {$value}ms");
            }
        }
    }

    private function displayErrorRates(array $data): void
    {
        foreach ($data as $key => $metrics) {
            $count = $metrics['count'] ?? 0;
            $this->line("❌ {$key}: {$count} errors");
        }
    }

    private function displayQueueMetrics(array $data): void
    {
        foreach ($data as $key => $metrics) {
            $value = $metrics['average'] ?? $metrics['count'] ?? 0;
            $this->line("📋 {$key}: {$value}");
        }
    }

    private function displaySecurityEvents(array $data): void
    {
        foreach ($data as $key => $metrics) {
            $count = $metrics['count'] ?? 0;
            $this->line("🔒 {$key}: {$count} events");
        }
    }

    private function displayDashboard(array $data, string $timeRange): void
    {
        $this->newLine();
        $this->info("📊 Webhook Dashboard ({$timeRange}):");
        $this->newLine();

        // Request Volume Summary
        $this->info('📈 Request Volume:');
        $totalRequests = 0;
        foreach ($data['request_volume'] as $platform => $metrics) {
            $count = $metrics['count'] ?? 0;
            $totalRequests += $count;
            $this->line("  {$platform}: {$count}");
        }
        $this->line("  Total: {$totalRequests}");
        $this->newLine();

        // Response Time Summary
        $this->info('⏱️  Response Times:');
        foreach ($data['response_times'] as $platform => $metrics) {
            $avg = $metrics['average'] ?? 0;
            $p95 = $metrics['percentiles'][95] ?? 0;
            $this->line("  {$platform}: Avg {$avg}ms, 95th {$p95}ms");
        }
        $this->newLine();

        // Error Rate Summary
        $this->info('❌ Error Rates:');
        foreach ($data['error_rates'] as $key => $metrics) {
            $count = $metrics['count'] ?? 0;
            if ($count > 0) {
                $this->line("  {$key}: {$count}");
            }
        }
        $this->newLine();

        // Queue Summary
        $this->info('📋 Queue Status:');
        foreach ($data['queue_metrics'] as $key => $metrics) {
            $value = $metrics['average'] ?? $metrics['count'] ?? 0;
            $this->line("  {$key}: {$value}");
        }
        $this->newLine();

        // Security Events
        $this->info('🔒 Security Events:');
        foreach ($data['security_events'] as $key => $metrics) {
            $count = $metrics['count'] ?? 0;
            if ($count > 0) {
                $this->line("  {$key}: {$count}");
            }
        }
    }
}