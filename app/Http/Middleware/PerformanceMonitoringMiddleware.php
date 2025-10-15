<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Services\Webhooks\WebhookLoggingService;
use Symfony\Component\HttpFoundation\Response;

class PerformanceMonitoringMiddleware
{
    private WebhookLoggingService $logger;

    public function __construct(WebhookLoggingService $logger)
    {
        $this->logger = $logger;
    }

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Only monitor webhook and admin routes
        if (!$this->shouldMonitor($request)) {
            return $next($request);
        }

        $startTime = microtime(true);
        $startMemory = memory_get_usage(true);
        
        // Get initial database query count
        $initialQueries = $this->getQueryCount();
        
        // Process the request
        $response = $next($request);
        
        // Calculate metrics
        $endTime = microtime(true);
        $endMemory = memory_get_usage(true);
        $peakMemory = memory_get_peak_usage(true);
        
        $executionTime = ($endTime - $startTime) * 1000; // Convert to milliseconds
        $memoryUsed = $endMemory - $startMemory;
        $queryCount = $this->getQueryCount() - $initialQueries;
        
        // Log performance metrics
        $this->logPerformanceMetrics($request, $response, [
            'execution_time' => $executionTime,
            'memory_used' => $memoryUsed,
            'memory_used_mb' => round($memoryUsed / 1024 / 1024, 2),
            'peak_memory' => $peakMemory,
            'peak_memory_mb' => round($peakMemory / 1024 / 1024, 2),
            'query_count' => $queryCount,
        ]);
        
        // Check for performance issues
        $this->checkPerformanceIssues($request, $executionTime, $memoryUsed, $queryCount);
        
        // Add performance headers
        $this->addPerformanceHeaders($response, $executionTime, $memoryUsed, $queryCount);
        
        return $response;
    }

    /**
     * Determine if the request should be monitored.
     */
    private function shouldMonitor(Request $request): bool
    {
        $path = $request->path();
        
        // Monitor webhook routes
        if (str_starts_with($path, 'webhooks/') || str_starts_with($path, 'api/webhooks/')) {
            return true;
        }
        
        // Monitor admin routes
        if (str_starts_with($path, 'admin/')) {
            return true;
        }
        
        // Monitor monitoring routes
        if (str_starts_with($path, 'monitoring/')) {
            return true;
        }
        
        // Monitor API routes
        if (str_starts_with($path, 'api/')) {
            return true;
        }
        
        return false;
    }

    /**
     * Log performance metrics.
     */
    private function logPerformanceMetrics(Request $request, Response $response, array $metrics): void
    {
        $context = array_merge($metrics, [
            'method' => $request->method(),
            'url' => $request->fullUrl(),
            'status_code' => $response->getStatusCode(),
            'route' => $request->route()?->getName(),
            'path' => $request->path(),
        ]);

        // Log to performance channel
        \Log::channel('webhook-performance')->info('Request performance metrics', array_merge($context, $this->logger->getPerformanceContext()));
        
        // Record metrics for analysis
        $this->recordPerformanceMetrics($request, $metrics);
    }

    /**
     * Check for performance issues.
     */
    private function checkPerformanceIssues(Request $request, float $executionTime, int $memoryUsed, int $queryCount): void
    {
        $config = config('monitoring.performance');
        $issues = [];
        
        // Check execution time
        if ($config['profiling']['slow_query_threshold'] && $executionTime > $config['profiling']['slow_query_threshold']) {
            $issues[] = "Slow execution time: {$executionTime}ms";
        }
        
        // Check memory usage
        if ($config['memory']['warning_threshold'] && $memoryUsed > $config['memory']['warning_threshold'] * 1024 * 1024) {
            $issues[] = "High memory usage: " . round($memoryUsed / 1024 / 1024, 2) . "MB";
        }
        
        // Check query count (N+1 detection)
        if ($queryCount > 20) { // Configurable threshold
            $issues[] = "High query count: {$queryCount} queries";
        }
        
        // Log issues if any
        if (!empty($issues)) {
            $this->logger->logSecurityEvent('performance_issue', [
                'url' => $request->fullUrl(),
                'method' => $request->method(),
                'issues' => $issues,
                'execution_time' => $executionTime,
                'memory_used' => $memoryUsed,
                'query_count' => $queryCount,
            ], 'warning');
        }
    }

    /**
     * Add performance headers to response.
     */
    private function addPerformanceHeaders(Response $response, float $executionTime, int $memoryUsed, int $queryCount): void
    {
        $response->headers->set('X-Execution-Time', round($executionTime, 2) . 'ms');
        $response->headers->set('X-Memory-Used', round($memoryUsed / 1024 / 1024, 2) . 'MB');
        $response->headers->set('X-Query-Count', (string) $queryCount);
        $response->headers->set('X-Peak-Memory', round(memory_get_peak_usage(true) / 1024 / 1024, 2) . 'MB');
    }

    /**
     * Get current database query count.
     */
    private function getQueryCount(): int
    {
        try {
            // This works with Laravel's default database connection
            if (app()->bound('db')) {
                $connection = app('db')->connection();
                if (method_exists($connection, 'getQueryLog')) {
                    return count($connection->getQueryLog());
                }
            }
        } catch (\Throwable $e) {
            // Ignore errors
        }
        
        return 0;
    }

    /**
     * Record performance metrics for analysis.
     */
    private function recordPerformanceMetrics(Request $request, array $metrics): void
    {
        $route = $request->route()?->getName() ?? $request->path();
        
        // Record execution time
        \App\Jobs\ProcessWebhookMetricsJob::dispatch(
            'performance_execution_time',
            ['route' => $route],
            $metrics['execution_time'],
            ['metric_type' => 'histogram']
        );
        
        // Record memory usage
        \App\Jobs\ProcessWebhookMetricsJob::dispatch(
            'performance_memory_usage',
            ['route' => $route],
            $metrics['memory_used'],
            ['metric_type' => 'gauge']
        );
        
        // Record query count
        \App\Jobs\ProcessWebhookMetricsJob::dispatch(
            'performance_query_count',
            ['route' => $route],
            $metrics['query_count'],
            ['metric_type' => 'counter']
        );
    }
}