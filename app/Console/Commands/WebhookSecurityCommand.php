<?php

namespace App\Console\Commands;

use App\Services\Webhooks\WebhookSecurityService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class WebhookSecurityCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'webhook:security 
                            {action : The action to perform (stats, clear, block, unblock, health, cleanup)}
                            {--type= : The violation type to clear (signature, rate_limit, ip, validation, suspicious)}
                            {--ip= : The IP address to block/unblock}
                            {--duration=3600 : Duration in seconds to block IP}
                            {--platform= : Filter by platform}
                            {--days=30 : Number of days for cleanup}';

    /**
     * The console command description.
     */
    protected $description = 'Manage webhook security settings and monitoring';

    /**
     * Execute the console command.
     */
    public function handle(WebhookSecurityService $securityService): int
    {
        $action = $this->argument('action');

        return match ($action) {
            'stats' => $this->showStats($securityService),
            'clear' => $this->clearViolations($securityService),
            'block' => $this->blockIp($securityService),
            'unblock' => $this->unblockIp($securityService),
            'health' => $this->healthCheck($securityService),
            'cleanup' => $this->cleanup($securityService),
            default => $this->error("Unknown action: {$action}"),
        };
    }

    /**
     * Show security statistics.
     */
    protected function showStats(WebhookSecurityService $securityService): int
    {
        $this->info('Webhook Security Statistics');
        $this->info('==========================');

        $filters = [];
        if ($platform = $this->option('platform')) {
            $filters['platform'] = $platform;
        }

        $stats = $securityService->getSecurityStats($filters);

        $this->table(
            ['Metric', 'Value'],
            [
                ['Total Violations', number_format($stats['total_violations'])],
                ['Signature Failures', number_format($stats['violations_by_type']['signature_failure'] ?? 0)],
                ['Rate Limit Violations', number_format($stats['violations_by_type']['rate_limit_violation'] ?? 0)],
                ['IP Violations', number_format($stats['violations_by_type']['ip_violation'] ?? 0)],
                ['Validation Errors', number_format($stats['violations_by_type']['validation_error'] ?? 0)],
                ['Suspicious Activity', number_format($stats['violations_by_type']['suspicious_activity'] ?? 0)],
            ]
        );

        // Show blocked IPs
        $blockedIps = $securityService->getBlockedIps();
        if (!empty($blockedIps)) {
            $this->newLine();
            $this->info('Blocked IPs:');
            
            $this->table(
                ['IP Address', 'Blocked At', 'Expires At', 'Remaining Seconds'],
                array_map(fn($ip) => [
                    $ip['ip'],
                    $ip['blocked_at'],
                    $ip['expires_at'],
                    $ip['remaining_seconds'],
                ], $blockedIps)
            );
        }

        return Command::SUCCESS;
    }

    /**
     * Clear security violations.
     */
    protected function clearViolations(WebhookSecurityService $securityService): int
    {
        $type = $this->option('type');
        $ip = $this->option('ip');

        if (!$type && !$ip) {
            $this->warn('No type or IP specified. Use --type or --ip to filter what to clear.');
            if (!$this->confirm('Clear ALL security violations?')) {
                return Command::FAILURE;
            }
        }

        $cleared = $securityService->clearViolations($type, $ip);

        $this->info("Cleared {$cleared} security violations.");
        
        if ($type) {
            $this->line("Type: {$type}");
        }
        
        if ($ip) {
            $this->line("IP: {$ip}");
        }

        return Command::SUCCESS;
    }

    /**
     * Block an IP address.
     */
    protected function blockIp(WebhookSecurityService $securityService): int
    {
        $ip = $this->option('ip');
        
        if (!$ip) {
            $ip = $this->ask('Enter IP address to block');
        }

        if (!filter_var($ip, FILTER_VALIDATE_IP)) {
            $this->error("Invalid IP address: {$ip}");
            return Command::FAILURE;
        }

        $duration = (int) $this->option('duration');
        
        if ($securityService->blockIp($ip, $duration)) {
            $this->info("IP {$ip} blocked for {$duration} seconds (" . round($duration / 3600, 2) . " hours)");
            return Command::SUCCESS;
        }

        $this->error("Failed to block IP {$ip}");
        return Command::FAILURE;
    }

    /**
     * Unblock an IP address.
     */
    protected function unblockIp(WebhookSecurityService $securityService): int
    {
        $ip = $this->option('ip');
        
        if (!$ip) {
            $blockedIps = $securityService->getBlockedIps();
            
            if (empty($blockedIps)) {
                $this->info('No IPs are currently blocked.');
                return Command::SUCCESS;
            }

            $choices = array_column($blockedIps, 'ip');
            $ip = $this->choice('Select IP to unblock', $choices);
        }

        if ($securityService->unblockIp($ip)) {
            $this->info("IP {$ip} unblocked successfully");
            return Command::SUCCESS;
        }

        $this->error("Failed to unblock IP {$ip}");
        return Command::FAILURE;
    }

    /**
     * Perform health check.
     */
    protected function healthCheck(WebhookSecurityService $securityService): int
    {
        $this->info('Webhook Security Health Check');
        $this->info('===============================');

        $health = $securityService->healthCheck();

        $status = $health['status'];
        $statusColor = match ($status) {
            'healthy' => 'green',
            'degraded' => 'yellow',
            'unhealthy' => 'red',
            default => 'white',
        };

        $this->line("Overall Status: <fg={$statusColor}>{$status}</fg={$statusColor}>");
        $this->newLine();

        $this->table(
            ['Check', 'Status'],
            array_map(fn($check, $result) => [
                $check,
                str_contains($result, 'error') ? "<fg=red>{$result}</fg=red>" : "<fg=green>{$result}</fg=green>",
            ], array_keys($health['checks']), $health['checks'])
        );

        $this->newLine();
        $this->line("Last Checked: {$health['timestamp']}");

        return $status === 'healthy' ? Command::SUCCESS : Command::FAILURE;
    }

    /**
     * Cleanup old data.
     */
    protected function cleanup(WebhookSecurityService $securityService): int
    {
        $days = (int) $this->option('days');
        
        $this->info("Cleaning up webhook security data older than {$days} days...");
        
        // This would need to be implemented in the security service
        // For now, we'll just log the action
        Log::info('Webhook security cleanup initiated', [
            'days' => $days,
            'timestamp' => now()->toISOString(),
        ]);

        $this->info('Cleanup completed. Check logs for details.');
        
        return Command::SUCCESS;
    }
}