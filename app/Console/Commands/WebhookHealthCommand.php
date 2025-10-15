<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\Webhooks\WebhookMonitoringService;
use App\Services\Webhooks\WebhookLoggingService;

class WebhookHealthCommand extends Command
{
    protected $signature = 'webhook:health 
                            {--check= : Specific health check to run}
                            {--json : Output in JSON format}
                            {--notify : Send alerts if issues found}';

    protected $description = 'Check webhook system health';

    public function handle(WebhookMonitoringService $monitoring, WebhookLoggingService $logger): int
    {
        $specificCheck = $this->option('check');
        $asJson = $this->option('json');
        $notify = $this->option('notify');

        $this->info('Running webhook health checks...');

        try {
            if ($specificCheck) {
                $results = $this->runSpecificCheck($monitoring, $specificCheck);
            } else {
                $results = $monitoring->runHealthChecks();
            }

            if ($asJson) {
                $this->line(json_encode($results, JSON_PRETTY_PRINT));
            } else {
                $this->displayResults($results);
            }

            // Send notifications if requested and issues found
            if ($notify && $this->hasIssues($results)) {
                $this->sendNotifications($results, $logger);
            }

            // Return appropriate exit code
            return $this->hasIssues($results) ? 1 : 0;

        } catch (\Throwable $e) {
            $this->error("Health check failed: {$e->getMessage()}");
            
            if ($asJson) {
                $this->line(json_encode([
                    'error' => $e->getMessage(),
                    'timestamp' => now()->toISOString(),
                ], JSON_PRETTY_PRINT));
            }
            
            return 1;
        }
    }

    private function runSpecificCheck(WebhookMonitoringService $monitoring, string $check): array
    {
        $config = config('monitoring.health.checks');
        
        if (!isset($config[$check])) {
            throw new \InvalidArgumentException("Unknown health check: {$check}");
        }
        
        $this->info("Running specific check: {$check}");
        
        return [$check => $monitoring->runHealthCheck($check, $config[$check])];
    }

    private function displayResults(array $results): void
    {
        $this->newLine();
        $this->info('Health Check Results:');
        $this->newLine();

        foreach ($results as $check => $result) {
            $status = $result['status'] ?? 'unknown';
            $statusIcon = $this->getStatusIcon($status);
            
            $this->line("{$statusIcon} {$check}: {$status}");
            
            if ($status !== 'healthy' && isset($result['error'])) {
                $this->line("    Error: {$result['error']}");
            }
            
            // Show additional details for specific checks
            $this->showCheckDetails($check, $result);
            
            $this->newLine();
        }

        // Summary
        $total = count($results);
        $healthy = collect($results)->where('status', 'healthy')->count();
        $unhealthy = $total - $healthy;

        $this->info("Summary: {$healthy}/{$total} checks healthy");
        
        if ($unhealthy > 0) {
            $this->error("{$unhealthy} check(s) failed");
        }
    }

    private function showCheckDetails(string $check, array $result): void
    {
        switch ($check) {
            case 'webhook_endpoints':
                if (isset($result['endpoints'])) {
                    foreach ($result['endpoints'] as $platform => $endpoint) {
                        $status = $endpoint['status'] ?? 'unknown';
                        $icon = $this->getStatusIcon($status);
                        $responseTime = $endpoint['response_time'] ?? 'N/A';
                        $this->line("    {$icon} {$platform}: {$status} ({$responseTime}ms)");
                    }
                }
                break;
                
            case 'queue_health':
                if (isset($result['metrics']['queue_sizes'])) {
                    foreach ($result['metrics']['queue_sizes'] as $queue => $size) {
                        $this->line("    Queue {$queue}: {$size} jobs");
                    }
                }
                break;
                
            case 'disk_space':
                if (isset($result['metrics'])) {
                    $usage = $result['metrics']['usage_percent'] ?? 0;
                    $free = $result['metrics']['free_space_gb'] ?? 0;
                    $this->line("    Usage: {$usage}% ({$free}GB free)");
                }
                break;
                
            case 'database_health':
                if (isset($result['metrics']['connection_time'])) {
                    $time = $result['metrics']['connection_time'];
                    $this->line("    Connection time: {$time}ms");
                }
                break;
                
            case 'redis_health':
                if (isset($result['metrics'])) {
                    $memory = $result['metrics']['memory_usage_mb'] ?? 0;
                    $clients = $result['metrics']['connected_clients'] ?? 0;
                    $this->line("    Memory: {$memory}MB, Clients: {$clients}");
                }
                break;
        }
    }

    private function getStatusIcon(string $status): string
    {
        return match ($status) {
            'healthy' => '✅',
            'warning' => '⚠️',
            'critical' => '🚨',
            'unhealthy' => '❌',
            'error' => '💥',
            default => '❓',
        };
    }

    private function hasIssues(array $results): bool
    {
        return collect($results)->contains(function ($result) {
            $status = $result['status'] ?? 'unknown';
            return !in_array($status, ['healthy', 'warning']);
        });
    }

    private function sendNotifications(array $results, WebhookLoggingService $logger): void
    {
        $unhealthyChecks = collect($results)->filter(function ($result) {
            $status = $result['status'] ?? 'unknown';
            return !in_array($status, ['healthy', 'warning']);
        });

        if ($unhealthyChecks->isEmpty()) {
            return;
        }

        $message = "Webhook health check failures detected:\n";
        foreach ($unhealthyChecks as $check => $result) {
            $message .= "- {$check}: {$result['status']}\n";
        }

        $logger->logSecurityEvent('health_check_failures', [
            'failed_checks' => $unhealthyChecks->keys()->toArray(),
            'results' => $results,
        ], 'warning');

        $this->info('Notifications sent for health check failures.');
    }
}