<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\Webhooks\WebhookLoggingService;
use Illuminate\Support\Facades\File;

class WebhookLogsCommand extends Command
{
    protected $signature = 'webhook:logs 
                            {action : Action to perform (view|cleanup|archive|search)}
                            {--channel= : Log channel to view}
                            {--lines=50 : Number of lines to show}
                            {--pattern= : Search pattern}
                            {--since= : Show logs since (e.g., "1 hour", "1 day")}';

    protected $description = 'Manage webhook logs';

    public function handle(WebhookLoggingService $logger): int
    {
        $action = $this->argument('action');

        return match ($action) {
            'view' => $this->viewLogs(),
            'cleanup' => $this->cleanupLogs($logger),
            'archive' => $this->archiveLogs($logger),
            'search' => $this->searchLogs(),
            default => $this->error("Unknown action: {$action}"),
        };
    }

    private function viewLogs(): int
    {
        $channel = $this->option('channel') ?? 'webhook-events';
        $lines = $this->option('lines');
        $since = $this->option('since');

        $logPath = $this->getLogPath($channel);
        
        if (!file_exists($logPath)) {
            $this->error("Log file not found: {$logPath}");
            return 1;
        }

        $this->info("Viewing logs from: {$logPath}");
        
        if ($since) {
            $this->info("Showing logs since: {$since}");
        }

        // Use tail command if available, otherwise read file
        if (function_exists('shell_exec')) {
            $command = "tail -n {$lines} {$logPath}";
            
            if ($since) {
                // This is a simplified implementation
                // In practice, you might want to use more sophisticated log parsing
                $this->warn("Time-based filtering not available with tail command");
            }
            
            $output = shell_exec($command);
            $this->line($output);
        } else {
            $content = file_get_contents($logPath);
            $linesArray = explode("\n", $content);
            $lastLines = array_slice($linesArray, -$lines);
            
            foreach ($lastLines as $line) {
                if (!empty(trim($line))) {
                    $this->line($line);
                }
            }
        }

        return 0;
    }

    private function cleanupLogs(WebhookLoggingService $logger): int
    {
        $this->info('Starting webhook log cleanup...');
        
        dispatch(new \App\Jobs\CleanupWebhookLogsJob());
        
        $this->info('Log cleanup job dispatched successfully.');
        
        return 0;
    }

    private function archiveLogs(WebhookLoggingService $logger): int
    {
        $this->info('Starting webhook log archival...');
        
        // This would trigger archival logic
        $config = config('monitoring.logs.archival');
        
        if (!$config['enabled']) {
            $this->warn('Log archival is disabled in configuration.');
            return 1;
        }
        
        dispatch(new \App\Jobs\CleanupWebhookLogsJob());
        
        $this->info('Log archival job dispatched successfully.');
        
        return 0;
    }

    private function searchLogs(): int
    {
        $pattern = $this->option('pattern');
        $channel = $this->option('channel') ?? 'webhook-events';

        if (!$pattern) {
            $this->error('Search pattern is required for search action.');
            return 1;
        }

        $logPath = $this->getLogPath($channel);
        
        if (!file_exists($logPath)) {
            $this->error("Log file not found: {$logPath}");
            return 1;
        }

        $this->info("Searching in: {$logPath}");
        $this->info("Pattern: {$pattern}");

        // Use grep if available, otherwise search in PHP
        if (function_exists('shell_exec')) {
            $command = "grep -n \"{$pattern}\" {$logPath} | head -50";
            $output = shell_exec($command);
            
            if (empty($output)) {
                $this->info('No matches found.');
            } else {
                $this->line($output);
            }
        } else {
            $content = file_get_contents($logPath);
            $lines = explode("\n", $content);
            $matches = 0;
            
            foreach ($lines as $index => $line) {
                if (str_contains($line, $pattern)) {
                    $this->line(($index + 1) . ': ' . $line);
                    $matches++;
                    
                    if ($matches >= 50) {
                        $this->info('... (showing first 50 matches)');
                        break;
                    }
                }
            }
            
            if ($matches === 0) {
                $this->info('No matches found.');
            }
        }

        return 0;
    }

    private function getLogPath(string $channel): string
    {
        $logPaths = [
            'webhook-events' => storage_path('logs/webhooks/events.log'),
            'webhook-processing' => storage_path('logs/webhooks/processing.log'),
            'webhook-security' => storage_path('logs/webhooks/security.log'),
            'webhook-performance' => storage_path('logs/webhooks/performance.log'),
            'webhook-errors' => storage_path('logs/webhooks/errors.log'),
            'webhook-metrics' => storage_path('logs/webhooks/metrics.log'),
        ];

        return $logPaths[$channel] ?? storage_path('logs/webhooks/events.log');
    }
}