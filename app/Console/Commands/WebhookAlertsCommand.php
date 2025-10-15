<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\Webhooks\WebhookAlertingService;
use App\Services\Webhooks\WebhookLoggingService;

class WebhookAlertsCommand extends Command
{
    protected $signature = 'webhook:alerts 
                            {action : Action to perform (evaluate|trigger|list|suppress|clear)}
                            {--rule= : Specific alert rule}
                            {--message= : Alert message (for trigger)}
                            {--severity=warning : Alert severity (for trigger)}
                            {--duration=3600 : Suppression duration in seconds}';

    protected $description = 'Manage webhook alerts';

    public function handle(WebhookAlertingService $alerting, WebhookLoggingService $logger): int
    {
        $action = $this->argument('action');

        return match ($action) {
            'evaluate' => $this->evaluateAlerts($alerting),
            'trigger' => $this->triggerAlert($alerting),
            'list' => $this->listAlerts($alerting),
            'suppress' => $this->suppressAlert($alerting),
            'clear' => $this->clearSuppression($alerting),
            default => $this->error("Unknown action: {$action}"),
        };
    }

    private function evaluateAlerts(WebhookAlertingService $alerting): int
    {
        $this->info('Evaluating alert rules...');

        try {
            $alerts = $alerting->evaluateAlerts();

            if (empty($alerts)) {
                $this->info('No alerts triggered.');
                return 0;
            }

            $this->info(count($alerts) . ' alert(s) triggered:');
            $this->newLine();

            foreach ($alerts as $alert) {
                $severity = strtoupper($alert['severity']);
                $icon = $this->getSeverityIcon($alert['severity']);
                
                $this->line("{$icon} [{$severity}] {$alert['rule']}");
                $this->line("   {$alert['message']}");
                $this->line("   Time: {$alert['timestamp']}");
                $this->newLine();
            }

            return 0;

        } catch (\Throwable $e) {
            $this->error("Failed to evaluate alerts: {$e->getMessage()}");
            return 1;
        }
    }

    private function triggerAlert(WebhookAlertingService $alerting): int
    {
        $rule = $this->option('rule');
        $message = $this->option('message');
        $severity = $this->option('severity');

        if (!$rule) {
            $this->error('Rule name is required for trigger action.');
            return 1;
        }

        if (!$message) {
            $this->error('Alert message is required for trigger action.');
            return 1;
        }

        $this->info("Triggering manual alert: {$rule}");

        try {
            $alerting->triggerAlert($rule, $message, [], $severity);
            
            $this->info('Alert triggered successfully.');
            return 0;

        } catch (\Throwable $e) {
            $this->error("Failed to trigger alert: {$e->getMessage()}");
            return 1;
        }
    }

    private function listAlerts(WebhookAlertingService $alerting): int
    {
        $this->info('Alert Information:');
        $this->newLine();

        // Show alert rules
        $this->info('📋 Alert Rules:');
        $rules = config('monitoring.alerting.rules');
        
        foreach ($rules as $ruleName => $rule) {
            $status = $rule['enabled'] ? '✅' : '❌';
            $severity = strtoupper($rule['severity']);
            $this->line("  {$status} {$ruleName} [{$severity}]");
            
            if (!$rule['enabled']) {
                $this->line("    (disabled)");
            }
        }
        $this->newLine();

        // Show suppressed alerts
        $this->info('🔇 Suppressed Alerts:');
        $suppressed = $alerting->getSuppressedAlerts();
        
        if (empty($suppressed)) {
            $this->line('  No suppressed alerts');
        } else {
            foreach ($suppressed as $rule) {
                $this->line("  {$rule}");
            }
        }
        $this->newLine();

        // Show alert channels
        $this->info('📢 Alert Channels:');
        $channels = config('monitoring.alerting.channels');
        
        foreach ($channels as $channel => $config) {
            $status = $config['enabled'] ? '✅' : '❌';
            $this->line("  {$status} {$channel}");
            
            if (!$config['enabled']) {
                $this->line("    (disabled)");
            }
        }

        return 0;
    }

    private function suppressAlert(WebhookAlertingService $alerting): int
    {
        $rule = $this->option('rule');
        $duration = $this->option('duration');

        if (!$rule) {
            $this->error('Rule name is required for suppress action.');
            return 1;
        }

        $this->info("Suppressing alerts for rule: {$rule}");
        $this->info("Duration: {$duration} seconds (" . round($duration / 60, 1) . " minutes)");

        try {
            // This is a simplified implementation
            // In practice, you might want to add a method to the alerting service
            $suppressionKey = "alert_suppression:{$rule}";
            cache()->put($suppressionKey, true, $duration);
            
            $this->info('Alert suppression activated successfully.');
            return 0;

        } catch (\Throwable $e) {
            $this->error("Failed to suppress alert: {$e->getMessage()}");
            return 1;
        }
    }

    private function clearSuppression(WebhookAlertingService $alerting): int
    {
        $rule = $this->option('rule');

        if ($rule) {
            $this->info("Clearing suppression for rule: {$rule}");
            
            try {
                $alerting->clearSuppression($rule);
                $this->info('Suppression cleared successfully.');
                return 0;
                
            } catch (\Throwable $e) {
                $this->error("Failed to clear suppression: {$e->getMessage()}");
                return 1;
            }
        } else {
            $this->info('Clearing all alert suppressions...');
            
            try {
                $suppressed = $alerting->getSuppressedAlerts();
                
                foreach ($suppressed as $rule) {
                    $alerting->clearSuppression($rule);
                }
                
                $this->info(count($suppressed) . ' suppression(s) cleared.');
                return 0;
                
            } catch (\Throwable $e) {
                $this->error("Failed to clear suppressions: {$e->getMessage()}");
                return 1;
            }
        }
    }

    private function getSeverityIcon(string $severity): string
    {
        return match ($severity) {
            'critical' => '🚨',
            'warning' => '⚠️',
            'info' => 'ℹ️',
            default => '❓',
        };
    }
}