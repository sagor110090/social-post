<?php

namespace App\Services\Webhooks;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use App\Notifications\Webhook\WebhookAlertNotification;
use Carbon\Carbon;

class WebhookAlertingService
{
    private array $config;
    private WebhookLoggingService $logger;
    private WebhookMetricsService $metrics;

    public function __construct(WebhookLoggingService $logger, WebhookMetricsService $metrics)
    {
        $this->config = config('monitoring.alerting');
        $this->logger = $logger;
        $this->metrics = $metrics;
    }

    /**
     * Evaluate all alert rules.
     */
    public function evaluateAlerts(): array
    {
        if (!$this->config['enabled']) {
            return [];
        }

        $alerts = [];
        $rules = $this->config['rules'];

        foreach ($rules as $ruleName => $rule) {
            if (!$rule['enabled']) {
                continue;
            }

            try {
                $alert = $this->evaluateAlertRule($ruleName, $rule);
                if ($alert) {
                    $alerts[] = $alert;
                }
            } catch (\Throwable $e) {
                $this->logger->logSecurityEvent('alert_evaluation_error', [
                    'rule' => $ruleName,
                    'error' => $e->getMessage(),
                ], 'error');
            }
        }

        return $alerts;
    }

    /**
     * Evaluate a specific alert rule.
     */
    public function evaluateAlertRule(string $ruleName, array $rule): ?array
    {
        // Check if alert is suppressed
        if ($this->isAlertSuppressed($ruleName)) {
            return null;
        }

        $triggered = false;
        $context = [];

        switch ($ruleName) {
            case 'high_error_rate':
                $triggered = $this->checkHighErrorRate($rule, $context);
                break;
            case 'queue_backlog':
                $triggered = $this->checkQueueBacklog($rule, $context);
                break;
            case 'endpoint_down':
                $triggered = $this->checkEndpointDown($rule, $context);
                break;
            case 'slow_response_time':
                $triggered = $this->checkSlowResponseTime($rule, $context);
                break;
            case 'security_violations':
                $triggered = $this->checkSecurityViolations($rule, $context);
                break;
            case 'disk_space_critical':
                $triggered = $this->checkDiskSpaceCritical($rule, $context);
                break;
        }

        if ($triggered) {
            $alert = [
                'rule' => $ruleName,
                'severity' => $rule['severity'],
                'message' => $this->buildAlertMessage($ruleName, $context),
                'context' => $context,
                'timestamp' => now()->toISOString(),
            ];

            $this->sendAlert($alert);
            $this->suppressAlert($ruleName, $rule['cooldown'] ?? $this->config['suppression']['default_duration']);

            return $alert;
        }

        return null;
    }

    /**
     * Check for high error rate.
     */
    private function checkHighErrorRate(array $rule, array &$context): bool
    {
        $window = $rule['window'] ?? 300; // 5 minutes default
        $threshold = $rule['threshold'] ?? 10; // 10% default

        // Get error metrics for the window
        $errorMetrics = $this->metrics->getErrorRateMetrics('5m');
        $requestMetrics = $this->metrics->getRequestVolumeMetrics('5m');

        $totalErrors = 0;
        $totalRequests = 0;

        foreach ($errorMetrics as $platform => $data) {
            $totalErrors += $data['count'] ?? 0;
        }

        foreach ($requestMetrics as $platform => $data) {
            $totalRequests += $data['count'] ?? 0;
        }

        if ($totalRequests === 0) {
            return false;
        }

        $errorRate = ($totalErrors / $totalRequests) * 100;

        $context = [
            'error_rate' => round($errorRate, 2),
            'total_errors' => $totalErrors,
            'total_requests' => $totalRequests,
            'threshold' => $threshold,
            'window' => $window,
        ];

        return $errorRate > $threshold;
    }

    /**
     * Check for queue backlog.
     */
    private function checkQueueBacklog(array $rule, array &$context): bool
    {
        $threshold = $rule['threshold'] ?? 500;

        $queueMetrics = $this->metrics->getQueueMetrics('1h');
        $maxQueueSize = 0;

        foreach ($queueMetrics as $queue => $data) {
            $size = $data['size'] ?? 0;
            $maxQueueSize = max($maxQueueSize, $size);
        }

        $context = [
            'max_queue_size' => $maxQueueSize,
            'threshold' => $threshold,
            'queue_metrics' => $queueMetrics,
        ];

        return $maxQueueSize > $threshold;
    }

    /**
     * Check for endpoint downtime.
     */
    private function checkEndpointDown(array $rule, array &$context): bool
    {
        $threshold = $rule['threshold'] ?? 1;

        // Check recent health check results
        $healthResults = Cache::get('webhook_health_results', []);
        $failedEndpoints = [];

        foreach ($healthResults as $endpoint => $result) {
            if (!isset($result['timestamp'])) {
                continue;
            }

            $timestamp = Carbon::parse($result['timestamp']);
            if ($timestamp->diffInMinutes(now()) > 5) {
                continue; // Skip old results
            }

            if ($result['status'] !== 'healthy') {
                $failedEndpoints[] = $endpoint;
            }
        }

        $context = [
            'failed_endpoints' => $failedEndpoints,
            'failed_count' => count($failedEndpoints),
            'threshold' => $threshold,
            'health_results' => $healthResults,
        ];

        return count($failedEndpoints) >= $threshold;
    }

    /**
     * Check for slow response times.
     */
    private function checkSlowResponseTime(array $rule, array &$context): bool
    {
        $threshold = $rule['threshold'] ?? 5000; // 5 seconds default
        $percentile = $rule['percentile'] ?? 95;
        $window = $rule['window'] ?? 300; // 5 minutes default

        $responseTimeMetrics = $this->metrics->getResponseTimeMetrics('5m');
        $slowPlatforms = [];

        foreach ($responseTimeMetrics as $platform => $data) {
            $percentileValue = $data['percentiles'][$percentile] ?? 0;
            if ($percentileValue > $threshold) {
                $slowPlatforms[] = [
                    'platform' => $platform,
                    'response_time' => $percentileValue,
                ];
            }
        }

        $context = [
            'slow_platforms' => $slowPlatforms,
            'threshold' => $threshold,
            'percentile' => $percentile,
            'window' => $window,
        ];

        return !empty($slowPlatforms);
    }

    /**
     * Check for security violations.
     */
    private function checkSecurityViolations(array $rule, array &$context): bool
    {
        $threshold = $rule['threshold'] ?? 5;
        $window = $rule['window'] ?? 60; // 1 minute default

        $securityMetrics = $this->metrics->getSecurityEventMetrics('1m');
        $totalViolations = 0;

        foreach ($securityMetrics as $platform => $data) {
            $totalViolations += $data['count'] ?? 0;
        }

        $context = [
            'total_violations' => $totalViolations,
            'threshold' => $threshold,
            'window' => $window,
            'security_metrics' => $securityMetrics,
        ];

        return $totalViolations > $threshold;
    }

    /**
     * Check for critical disk space usage.
     */
    private function checkDiskSpaceCritical(array $rule, array &$context): bool
    {
        $threshold = $rule['threshold'] ?? 90; // 90% default

        // Get recent health check results for disk space
        $healthResults = Cache::get('webhook_health_results', []);
        $diskUsage = null;

        if (isset($healthResults['disk_space']['metrics']['usage_percent'])) {
            $diskUsage = $healthResults['disk_space']['metrics']['usage_percent'];
        }

        if ($diskUsage === null) {
            return false;
        }

        $context = [
            'disk_usage_percent' => $diskUsage,
            'threshold' => $threshold,
        ];

        return $diskUsage > $threshold;
    }

    /**
     * Send alert through configured channels.
     */
    private function sendAlert(array $alert): void
    {
        $channels = $this->getEnabledChannels();

        foreach ($channels as $channel => $config) {
            if (!$config['enabled']) {
                continue;
            }

            try {
                $this->sendAlertThroughChannel($alert, $channel, $config);
            } catch (\Throwable $e) {
                $this->logger->logSecurityEvent('alert_send_error', [
                    'alert_rule' => $alert['rule'],
                    'channel' => $channel,
                    'error' => $e->getMessage(),
                ], 'error');
            }
        }

        // Log the alert
        $this->logger->logSecurityEvent('alert_triggered', [
            'rule' => $alert['rule'],
            'severity' => $alert['severity'],
            'message' => $alert['message'],
            'context' => $alert['context'],
        ], 'warning');
    }

    /**
     * Send alert through specific channel.
     */
    private function sendAlertThroughChannel(array $alert, string $channel, array $config): void
    {
        switch ($channel) {
            case 'email':
                $this->sendEmailAlert($alert, $config);
                break;
            case 'slack':
                $this->sendSlackAlert($alert, $config);
                break;
            case 'webhook':
                $this->sendWebhookAlert($alert, $config);
                break;
        }
    }

    /**
     * Send email alert.
     */
    private function sendEmailAlert(array $alert, array $config): void
    {
        if (empty($config['to'])) {
            return;
        }

        foreach ($config['to'] as $recipient) {
            try {
                Mail::to($recipient)->send(new WebhookAlertNotification($alert));
            } catch (\Throwable $e) {
                Log::error("Failed to send alert email to {$recipient}: " . $e->getMessage());
            }
        }
    }

    /**
     * Send Slack alert.
     */
    private function sendSlackAlert(array $alert, array $config): void
    {
        if (empty($config['webhook_url'])) {
            return;
        }

        $payload = [
            'channel' => $config['channel'] ?? '#alerts',
            'username' => $config['username'] ?? 'Webhook Monitor',
            'text' => $this->formatSlackMessage($alert),
            'attachments' => [
                [
                    'color' => $this->getSeverityColor($alert['severity']),
                    'fields' => [
                        [
                            'title' => 'Rule',
                            'value' => $alert['rule'],
                            'short' => true,
                        ],
                        [
                            'title' => 'Severity',
                            'value' => strtoupper($alert['severity']),
                            'short' => true,
                        ],
                        [
                            'title' => 'Time',
                            'value' => $alert['timestamp'],
                            'short' => true,
                        ],
                    ],
                ],
            ],
        ];

        Http::timeout(10)->post($config['webhook_url'], $payload);
    }

    /**
     * Send webhook alert.
     */
    private function sendWebhookAlert(array $alert, array $config): void
    {
        if (empty($config['url'])) {
            return;
        }

        $payload = [
            'alert' => $alert,
            'timestamp' => now()->toISOString(),
            'service' => 'webhook-monitor',
        ];

        Http::timeout($config['timeout'] ?? 10)
            ->retry($config['retry_attempts'] ?? 3)
            ->post($config['url'], $payload);
    }

    /**
     * Get enabled alert channels.
     */
    private function getEnabledChannels(): array
    {
        return array_filter($this->config['channels'], fn($config) => $config['enabled'] ?? false);
    }

    /**
     * Build alert message.
     */
    private function buildAlertMessage(string $ruleName, array $context): string
    {
        $templates = [
            'high_error_rate' => "High error rate detected: {error_rate}% ({total_errors}/{total_requests} requests) in the last {window} seconds (threshold: {threshold}%)",
            'queue_backlog' => "Queue backlog detected: {max_queue_size} jobs in queue (threshold: {threshold})",
            'endpoint_down' => "Endpoint downtime detected: {failed_count} endpoints failed health check",
            'slow_response_time' => "Slow response times detected: {count} platforms with {percentile}th percentile > {threshold}ms",
            'security_violations' => "Security violations detected: {total_violations} violations in the last {window} seconds (threshold: {threshold})",
            'disk_space_critical' => "Critical disk space usage: {disk_usage_percent}% used (threshold: {threshold}%)",
        ];

        $template = $templates[$ruleName] ?? "Alert triggered for rule: {$ruleName}";

        // Replace placeholders
        foreach ($context as $key => $value) {
            if (is_scalar($value)) {
                $template = str_replace('{' . $key . '}', $value, $template);
            }
        }

        return $template;
    }

    /**
     * Format Slack message.
     */
    private function formatSlackMessage(array $alert): string
    {
        $emoji = $this->getSeverityEmoji($alert['severity']);
        return "{$emoji} *Webhook Alert: {$alert['rule']}*\n{$alert['message']}";
    }

    /**
     * Get severity color for Slack.
     */
    private function getSeverityColor(string $severity): string
    {
        return match ($severity) {
            'critical' => 'danger',
            'warning' => 'warning',
            'info' => 'good',
            default => 'warning',
        };
    }

    /**
     * Get severity emoji for Slack.
     */
    private function getSeverityEmoji(string $severity): string
    {
        return match ($severity) {
            'critical' => ':rotating_light:',
            'warning' => ':warning:',
            'info' => ':information_source:',
            default => ':warning:',
        };
    }

    /**
     * Check if alert is suppressed.
     */
    private function isAlertSuppressed(string $ruleName): bool
    {
        if (!$this->config['suppression']['enabled']) {
            return false;
        }

        $suppressionKey = "alert_suppression:{$ruleName}";
        return Cache::has($suppressionKey);
    }

    /**
     * Suppress alert for cooldown period.
     */
    private function suppressAlert(string $ruleName, int $duration): void
    {
        if (!$this->config['suppression']['enabled']) {
            return;
        }

        $duration = min($duration, $this->config['suppression']['max_duration']);
        $suppressionKey = "alert_suppression:{$ruleName}";
        
        Cache::put($suppressionKey, true, $duration);
    }

    /**
     * Manually trigger an alert.
     */
    public function triggerAlert(string $ruleName, string $message, array $context = [], string $severity = 'warning'): void
    {
        $alert = [
            'rule' => $ruleName,
            'severity' => $severity,
            'message' => $message,
            'context' => $context,
            'timestamp' => now()->toISOString(),
            'manual' => true,
        ];

        $this->sendAlert($alert);
    }

    /**
     * Get alert history.
     */
    public function getAlertHistory(int $limit = 100): array
    {
        return Cache::get('alert_history', []);
    }

    /**
     * Clear alert suppression.
     */
    public function clearSuppression(string $ruleName): void
    {
        $suppressionKey = "alert_suppression:{$ruleName}";
        Cache::forget($suppressionKey);
    }

    /**
     * Get all suppressed alerts.
     */
    public function getSuppressedAlerts(): array
    {
        $suppressed = [];
        $prefix = "alert_suppression:";
        
        // This is a simplified implementation
        // In practice, you might want to use Redis keys command
        $knownRules = array_keys($this->config['rules']);
        
        foreach ($knownRules as $rule) {
            if ($this->isAlertSuppressed($rule)) {
                $suppressed[] = $rule;
            }
        }
        
        return $suppressed;
    }
}