<?php

namespace App\Services\Webhooks;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class WebhookMonitoringService
{
    private array $config;
    private WebhookLoggingService $logger;

    public function __construct(WebhookLoggingService $logger)
    {
        $this->config = config('monitoring');
        $this->logger = $logger;
    }

    /**
     * Run all health checks.
     */
    public function runHealthChecks(): array
    {
        if (!$this->config['health']['enabled']) {
            return [];
        }

        $results = [];
        $checks = $this->config['health']['checks'];

        foreach ($checks as $checkName => $checkConfig) {
            if (!$checkConfig['enabled']) {
                continue;
            }

            try {
                $results[$checkName] = $this->runHealthCheck($checkName, $checkConfig);
            } catch (\Throwable $e) {
                $results[$checkName] = [
                    'status' => 'error',
                    'message' => $e->getMessage(),
                    'timestamp' => now()->toISOString(),
                ];
                
                $this->logger->logHealthCheck($checkName, false, ['error' => $e->getMessage()]);
            }
        }

        return $results;
    }

    /**
     * Run a specific health check.
     */
    public function runHealthCheck(string $checkName, array $config): array
    {
        return match ($checkName) {
            'webhook_endpoints' => $this->checkWebhookEndpoints($config),
            'queue_health' => $this->checkQueueHealth($config),
            'database_health' => $this->checkDatabaseHealth($config),
            'redis_health' => $this->checkRedisHealth($config),
            'disk_space' => $this->checkDiskSpace($config),
            default => ['status' => 'unknown', 'message' => "Unknown check: {$checkName}"],
        };
    }

    /**
     * Check webhook endpoints health.
     */
    private function checkWebhookEndpoints(array $config): array
    {
        $results = [];
        $baseUrl = config('app.url');
        
        foreach ($config['endpoints'] as $platform => $endpoint) {
            try {
                $startTime = microtime(true);
                $response = Http::timeout($this->config['health']['timeout'])
                    ->get($baseUrl . $endpoint);
                
                $responseTime = (microtime(true) - $startTime) * 1000;
                
                $results[$platform] = [
                    'status' => $response->successful() ? 'healthy' : 'unhealthy',
                    'response_code' => $response->status(),
                    'response_time' => round($responseTime, 2),
                    'timestamp' => now()->toISOString(),
                ];
                
                $this->logger->logHealthCheck("webhook_endpoint_{$platform}", $response->successful(), [
                    'response_time' => $responseTime,
                    'response_code' => $response->status(),
                ]);
                
            } catch (\Throwable $e) {
                $results[$platform] = [
                    'status' => 'error',
                    'error' => $e->getMessage(),
                    'timestamp' => now()->toISOString(),
                ];
                
                $this->logger->logHealthCheck("webhook_endpoint_{$platform}", false, ['error' => $e->getMessage()]);
            }
        }
        
        $overallStatus = collect($results)->every(fn($result) => $result['status'] === 'healthy');
        
        return [
            'status' => $overallStatus ? 'healthy' : 'unhealthy',
            'endpoints' => $results,
            'timestamp' => now()->toISOString(),
        ];
    }

    /**
     * Check queue health.
     */
    private function checkQueueHealth(array $config): array
    {
        $queueSizes = [];
        $queueWaitTimes = [];
        $overallHealthy = true;

        // Check webhook queue specifically
        try {
            $webhookQueueSize = Queue::size('webhooks');
            $queueSizes['webhooks'] = $webhookQueueSize;
            
            if ($webhookQueueSize > $config['max_size']) {
                $overallHealthy = false;
            }

            // Check queue wait time by sampling recent jobs
            $waitTime = $this->getQueueWaitTime('webhooks');
            $queueWaitTimes['webhooks'] = $waitTime;
            
            if ($waitTime > $config['max_wait_time']) {
                $overallHealthy = false;
            }
            
        } catch (\Throwable $e) {
            $overallHealthy = false;
            $queueSizes['webhooks'] = 'error';
            $queueWaitTimes['webhooks'] = 'error';
        }

        // Check default queue
        try {
            $defaultQueueSize = Queue::size();
            $queueSizes['default'] = $defaultQueueSize;
            
            if ($defaultQueueSize > $config['max_size']) {
                $overallHealthy = false;
            }

            $waitTime = $this->getQueueWaitTime('default');
            $queueWaitTimes['default'] = $waitTime;
            
            if ($waitTime > $config['max_wait_time']) {
                $overallHealthy = false;
            }
            
        } catch (\Throwable $e) {
            $overallHealthy = false;
            $queueSizes['default'] = 'error';
            $queueWaitTimes['default'] = 'error';
        }

        $metrics = [
            'queue_sizes' => $queueSizes,
            'queue_wait_times' => $queueWaitTimes,
        ];

        $this->logger->logHealthCheck('queue_health', $overallHealthy, $metrics);

        return [
            'status' => $overallHealthy ? 'healthy' : 'unhealthy',
            'metrics' => $metrics,
            'timestamp' => now()->toISOString(),
        ];
    }

    /**
     * Check database health.
     */
    private function checkDatabaseHealth(array $config): array
    {
        try {
            $startTime = microtime(true);
            $result = DB::select('SELECT 1 as test');
            $connectionTime = (microtime(true) - $startTime) * 1000;
            
            $healthy = !empty($result) && $connectionTime <= ($config['max_connection_time'] * 1000);
            
            $metrics = [
                'connection_time' => round($connectionTime, 2),
                'test_query_result' => !empty($result),
            ];
            
            $this->logger->logHealthCheck('database_health', $healthy, $metrics);
            
            return [
                'status' => $healthy ? 'healthy' : 'unhealthy',
                'metrics' => $metrics,
                'timestamp' => now()->toISOString(),
            ];
            
        } catch (\Throwable $e) {
            $this->logger->logHealthCheck('database_health', false, ['error' => $e->getMessage()]);
            
            return [
                'status' => 'error',
                'error' => $e->getMessage(),
                'timestamp' => now()->toISOString(),
            ];
        }
    }

    /**
     * Check Redis health.
     */
    private function checkRedisHealth(array $config): array
    {
        try {
            $startTime = microtime(true);
            Redis::ping();
            $connectionTime = (microtime(true) - $startTime) * 1000;
            
            $info = Redis::info();
            $memoryUsage = $info['used_memory'] ?? 0;
            $maxMemory = $info['maxmemory'] ?? 0;
            $memoryUsagePercent = $maxMemory > 0 ? ($memoryUsage / $maxMemory) * 100 : 0;
            
            $healthy = $connectionTime <= ($config['max_connection_time'] * 1000);
            
            $metrics = [
                'connection_time' => round($connectionTime, 2),
                'memory_usage' => $memoryUsage,
                'memory_usage_mb' => round($memoryUsage / 1024 / 1024, 2),
                'memory_usage_percent' => round($memoryUsagePercent, 2),
                'connected_clients' => $info['connected_clients'] ?? 0,
            ];
            
            $this->logger->logHealthCheck('redis_health', $healthy, $metrics);
            
            return [
                'status' => $healthy ? 'healthy' : 'unhealthy',
                'metrics' => $metrics,
                'timestamp' => now()->toISOString(),
            ];
            
        } catch (\Throwable $e) {
            $this->logger->logHealthCheck('redis_health', false, ['error' => $e->getMessage()]);
            
            return [
                'status' => 'error',
                'error' => $e->getMessage(),
                'timestamp' => now()->toISOString(),
            ];
        }
    }

    /**
     * Check disk space.
     */
    private function checkDiskSpace(array $config): array
    {
        try {
            $logPath = storage_path('logs');
            $totalSpace = disk_total_space($logPath);
            $freeSpace = disk_free_space($logPath);
            $usedSpace = $totalSpace - $freeSpace;
            $usagePercent = ($usedSpace / $totalSpace) * 100;
            
            $status = 'healthy';
            if ($usagePercent >= $config['critical_threshold']) {
                $status = 'critical';
            } elseif ($usagePercent >= $config['warning_threshold']) {
                $status = 'warning';
            }
            
            $metrics = [
                'total_space' => $totalSpace,
                'total_space_gb' => round($totalSpace / 1024 / 1024 / 1024, 2),
                'free_space' => $freeSpace,
                'free_space_gb' => round($freeSpace / 1024 / 1024 / 1024, 2),
                'used_space' => $usedSpace,
                'used_space_gb' => round($usedSpace / 1024 / 1024 / 1024, 2),
                'usage_percent' => round($usagePercent, 2),
            ];
            
            $healthy = $usagePercent < $config['warning_threshold'];
            $this->logger->logHealthCheck('disk_space', $healthy, $metrics);
            
            return [
                'status' => $status,
                'metrics' => $metrics,
                'timestamp' => now()->toISOString(),
            ];
            
        } catch (\Throwable $e) {
            $this->logger->logHealthCheck('disk_space', false, ['error' => $e->getMessage()]);
            
            return [
                'status' => 'error',
                'error' => $e->getMessage(),
                'timestamp' => now()->toISOString(),
            ];
        }
    }

    /**
     * Get queue wait time.
     */
    private function getQueueWaitTime(string $queue): float
    {
        try {
            // This is a simplified implementation
            // In a real scenario, you might want to track job timestamps more accurately
            $cacheKey = "queue_wait_time:{$queue}";
            
            return Cache::remember($cacheKey, 30, function () use ($queue) {
                // Sample recent jobs to estimate wait time
                $recentJobs = DB::table('jobs')
                    ->where('queue', $queue)
                    ->orderBy('created_at', 'desc')
                    ->limit(10)
                    ->get();
                
                if ($recentJobs->isEmpty()) {
                    return 0;
                }
                
                $totalWaitTime = 0;
                $count = 0;
                
                foreach ($recentJobs as $job) {
                    $createdAt = Carbon::parse($job->created_at);
                    $waitTime = $createdAt->diffInSeconds(now());
                    $totalWaitTime += $waitTime;
                    $count++;
                }
                
                return $count > 0 ? $totalWaitTime / $count : 0;
            });
            
        } catch (\Throwable $e) {
            return 0;
        }
    }

    /**
     * Get system metrics.
     */
    public function getSystemMetrics(): array
    {
        return [
            'memory' => $this->getMemoryMetrics(),
            'cpu' => $this->getCpuMetrics(),
            'disk' => $this->getDiskMetrics(),
            'network' => $this->getNetworkMetrics(),
        ];
    }

    /**
     * Get memory metrics.
     */
    private function getMemoryMetrics(): array
    {
        $memoryUsage = memory_get_usage(true);
        $memoryPeak = memory_get_peak_usage(true);
        
        // Get system memory if available
        $systemMemory = $this->getSystemMemoryUsage();
        
        return [
            'php_memory_usage' => $memoryUsage,
            'php_memory_usage_mb' => round($memoryUsage / 1024 / 1024, 2),
            'php_memory_peak' => $memoryPeak,
            'php_memory_peak_mb' => round($memoryPeak / 1024 / 1024, 2),
            'system_memory_usage' => $systemMemory['usage'] ?? null,
            'system_memory_usage_mb' => isset($systemMemory['usage']) ? round($systemMemory['usage'] / 1024 / 1024, 2) : null,
            'system_memory_total' => $systemMemory['total'] ?? null,
            'system_memory_total_mb' => isset($systemMemory['total']) ? round($systemMemory['total'] / 1024 / 1024, 2) : null,
            'system_memory_percent' => $systemMemory['percent'] ?? null,
        ];
    }

    /**
     * Get CPU metrics.
     */
    private function getCpuMetrics(): array
    {
        // This is a simplified implementation
        // In production, you might want to use a proper system monitoring library
        $load = sys_getloadavg();
        
        return [
            'load_1min' => $load[0] ?? null,
            'load_5min' => $load[1] ?? null,
            'load_15min' => $load[2] ?? null,
            'cpu_count' => $this->getCpuCount(),
        ];
    }

    /**
     * Get disk metrics.
     */
    private function getDiskMetrics(): array
    {
        $path = storage_path();
        $totalSpace = disk_total_space($path);
        $freeSpace = disk_free_space($path);
        $usedSpace = $totalSpace - $freeSpace;
        
        return [
            'total_space' => $totalSpace,
            'total_space_gb' => round($totalSpace / 1024 / 1024 / 1024, 2),
            'free_space' => $freeSpace,
            'free_space_gb' => round($freeSpace / 1024 / 1024 / 1024, 2),
            'used_space' => $usedSpace,
            'used_space_gb' => round($usedSpace / 1024 / 1024 / 1024, 2),
            'usage_percent' => round(($usedSpace / $totalSpace) * 100, 2),
        ];
    }

    /**
     * Get network metrics.
     */
    private function getNetworkMetrics(): array
    {
        // This is a placeholder implementation
        // In production, you might want to track actual network metrics
        return [
            'connections' => null,
            'bytes_sent' => null,
            'bytes_received' => null,
        ];
    }

    /**
     * Get system memory usage.
     */
    private function getSystemMemoryUsage(): array
    {
        // This is a simplified implementation for Linux systems
        if (!function_exists('shell_exec')) {
            return [];
        }
        
        try {
            $meminfo = shell_exec('cat /proc/meminfo');
            if (!$meminfo) {
                return [];
            }
            
            $lines = explode("\n", $meminfo);
            $memInfo = [];
            
            foreach ($lines as $line) {
                if (preg_match('/^(\w+):\s+(\d+)\s+kB/', $line, $matches)) {
                    $memInfo[$matches[1]] = (int) $matches[2] * 1024; // Convert to bytes
                }
            }
            
            if (isset($memInfo['MemTotal']) && isset($memInfo['MemAvailable'])) {
                $usage = $memInfo['MemTotal'] - $memInfo['MemAvailable'];
                $percent = ($usage / $memInfo['MemTotal']) * 100;
                
                return [
                    'total' => $memInfo['MemTotal'],
                    'usage' => $usage,
                    'percent' => round($percent, 2),
                ];
            }
            
        } catch (\Throwable $e) {
            // Ignore errors
        }
        
        return [];
    }

    /**
     * Get CPU count.
     */
    private function getCpuCount(): int
    {
        if (!function_exists('shell_exec')) {
            return 1;
        }
        
        try {
            $cpuCount = shell_exec('nproc');
            return is_numeric($cpuCount) ? (int) $cpuCount : 1;
        } catch (\Throwable $e) {
            return 1;
        }
    }
}