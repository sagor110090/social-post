<?php

namespace App\Services\Webhooks;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;
use Carbon\Carbon;

class WebhookSecurityService
{
    /**
     * Security violation types.
     */
    const VIOLATION_SIGNATURE = 'signature_failure';
    const VIOLATION_RATE_LIMIT = 'rate_limit_violation';
    const VIOLATION_IP_WHITELIST = 'ip_violation';
    const VIOLATION_VALIDATION = 'validation_error';
    const VIOLATION_SUSPICIOUS = 'suspicious_activity';

    /**
     * Check if security monitoring is enabled.
     */
    public function isEnabled(): bool
    {
        return config('webhooks.security.logging.enabled', true);
    }

    /**
     * Record a security violation.
     */
    public function recordViolation(string $type, array $context): array
    {
        $window = $this->getViolationWindow($type);
        $cacheKey = $this->getViolationCacheKey($type, $context);
        
        // Increment violation counter
        $count = Cache::increment($cacheKey, 1, $window);
        
        // Log the violation
        $this->logViolation($type, $context, $count);
        
        // Check if alert should be triggered
        $alertTriggered = $this->checkAlertThreshold($type, $count, $context);
        
        return [
            'type' => $type,
            'count' => $count,
            'window' => $window,
            'alert_triggered' => $alertTriggered,
            'cache_key' => $cacheKey,
        ];
    }

    /**
     * Get violation window for type.
     */
    protected function getViolationWindow(string $type): int
    {
        return match ($type) {
            self::VIOLATION_SIGNATURE => 60, // 1 minute
            self::VIOLATION_RATE_LIMIT => 60, // 1 minute
            self::VIOLATION_IP_WHITELIST => 60, // 1 minute
            self::VIOLATION_VALIDATION => 3600, // 1 hour
            self::VIOLATION_SUSPICIOUS => 300, // 5 minutes
            default => 60,
        };
    }

    /**
     * Get violation cache key.
     */
    protected function getViolationCacheKey(string $type, array $context): string
    {
        $identifier = $context['ip'] ?? 'unknown';
        
        if (isset($context['platform'])) {
            $identifier .= ':' . $context['platform'];
        }
        
        if (isset($context['config_id'])) {
            $identifier .= ':' . $context['config_id'];
        }
        
        return "security_violation:{$type}:" . md5($identifier);
    }

    /**
     * Log security violation.
     */
    protected function logViolation(string $type, array $context, int $count): void
    {
        $logLevel = $this->getViolationLogLevel($type, $count);
        
        Log::log($logLevel, "Security violation recorded: {$type}", array_merge($context, [
            'violation_count' => $count,
            'timestamp' => now()->toISOString(),
        ]));
    }

    /**
     * Get log level for violation.
     */
    protected function getViolationLogLevel(string $type, int $count): string
    {
        if ($count >= 10) {
            return 'critical';
        }
        
        if ($count >= 5) {
            return 'warning';
        }
        
        return 'info';
    }

    /**
     * Check if alert threshold is exceeded.
     */
    protected function checkAlertThreshold(string $type, int $count, array $context): bool
    {
        if (!config('webhooks.security.alerting.enabled', false)) {
            return false;
        }

        $thresholds = config('webhooks.security.alerting.thresholds', []);
        $thresholdKey = $this->getAlertThresholdKey($type);
        
        if (!isset($thresholds[$thresholdKey])) {
            return false;
        }

        if ($count >= $thresholds[$thresholdKey]) {
            $this->triggerSecurityAlert($type, $context, $count);
            return true;
        }

        return false;
    }

    /**
     * Get alert threshold key for violation type.
     */
    protected function getAlertThresholdKey(string $type): string
    {
        return match ($type) {
            self::VIOLATION_SIGNATURE => 'signature_failures_per_minute',
            self::VIOLATION_RATE_LIMIT => 'rate_limit_violations_per_minute',
            self::VIOLATION_IP_WHITELIST => 'ip_violations_per_minute',
            self::VIOLATION_VALIDATION => 'payload_size_violations_per_hour',
            self::VIOLATION_SUSPICIOUS => 'suspicious_activity_per_minute',
            default => 'violations_per_minute',
        };
    }

    /**
     * Trigger security alert.
     */
    protected function triggerSecurityAlert(string $type, array $context, int $count): void
    {
        $alertData = array_merge($context, [
            'alert_type' => $type,
            'count' => $count,
            'timestamp' => now()->toISOString(),
            'severity' => $this->getAlertSeverity($type, $count),
        ]);

        Log::critical("Security alert triggered: {$type}", $alertData);

        // Send to external alerting systems
        $this->sendAlertToChannels($alertData);
    }

    /**
     * Get alert severity.
     */
    protected function getAlertSeverity(string $type, int $count): string
    {
        if ($count >= 20) {
            return 'critical';
        }
        
        if ($count >= 10) {
            return 'high';
        }
        
        if ($count >= 5) {
            return 'medium';
        }
        
        return 'low';
    }

    /**
     * Send alert to configured channels.
     */
    protected function sendAlertToChannels(array $alertData): void
    {
        $channels = config('webhooks.security.alerting.channels', []);
        
        foreach ($channels as $channel) {
            try {
                match ($channel) {
                    'slack' => $this->sendSlackAlert($alertData),
                    'email' => $this->sendEmailAlert($alertData),
                    'webhook' => $this->sendWebhookAlert($alertData),
                    default => Log::warning("Unknown alert channel: {$channel}"),
                };
            } catch (\Exception $e) {
                Log::error("Failed to send alert to {$channel}", [
                    'error' => $e->getMessage(),
                    'alert_data' => $alertData,
                ]);
            }
        }
    }

    /**
     * Send Slack alert.
     */
    protected function sendSlackAlert(array $alertData): void
    {
        $webhookUrl = config('services.slack.webhook_url');
        
        if (!$webhookUrl) {
            return;
        }

        $message = $this->formatSlackMessage($alertData);
        
        Http::post($webhookUrl, $message);
    }

    /**
     * Format Slack message.
     */
    protected function formatSlackMessage(array $alertData): array
    {
        $color = match ($alertData['severity']) {
            'critical' => 'danger',
            'high' => 'warning',
            'medium' => 'warning',
            default => 'good',
        };

        return [
            'attachments' => [
                [
                    'color' => $color,
                    'title' => 'Webhook Security Alert',
                    'text' => "Security violation detected: {$alertData['alert_type']}",
                    'fields' => [
                        [
                            'title' => 'Type',
                            'value' => $alertData['alert_type'],
                            'short' => true,
                        ],
                        [
                            'title' => 'Count',
                            'value' => $alertData['count'],
                            'short' => true,
                        ],
                        [
                            'title' => 'IP Address',
                            'value' => $alertData['ip'] ?? 'Unknown',
                            'short' => true,
                        ],
                        [
                            'title' => 'Platform',
                            'value' => $alertData['platform'] ?? 'Unknown',
                            'short' => true,
                        ],
                        [
                            'title' => 'Timestamp',
                            'value' => $alertData['timestamp'],
                            'short' => false,
                        ],
                    ],
                    'footer' => 'Webhook Security System',
                    'ts' => now()->timestamp,
                ],
            ],
        ];
    }

    /**
     * Send email alert.
     */
    protected function sendEmailAlert(array $alertData): void
    {
        $recipients = config('webhooks.security.alerting.email_recipients', []);
        
        if (empty($recipients)) {
            return;
        }

        // This would typically use Laravel's Mail facade
        // Implementation depends on your mail configuration
        Log::info('Email alert would be sent', [
            'recipients' => $recipients,
            'alert_data' => $alertData,
        ]);
    }

    /**
     * Send webhook alert.
     */
    protected function sendWebhookAlert(array $alertData): void
    {
        $webhookUrl = config('webhooks.security.alerting.webhook_url');
        
        if (!$webhookUrl) {
            return;
        }

        Http::post($webhookUrl, $alertData);
    }

    /**
     * Get security statistics.
     */
    public function getSecurityStats(array $filters = []): array
    {
        $stats = [
            'total_violations' => 0,
            'violations_by_type' => [],
            'violations_by_platform' => [],
            'recent_violations' => [],
            'alert_summary' => [],
        ];

        // Get violation counts by type
        $violationTypes = [
            self::VIOLATION_SIGNATURE,
            self::VIOLATION_RATE_LIMIT,
            self::VIOLATION_IP_WHITELIST,
            self::VIOLATION_VALIDATION,
            self::VIOLATION_SUSPICIOUS,
        ];

        foreach ($violationTypes as $type) {
            $pattern = "security_violation:{$type}:*";
            $keys = Redis::connection()->keys($pattern);
            
            $count = 0;
            foreach ($keys as $key) {
                $count += (int) Redis::connection()->get($key);
            }
            
            $stats['violations_by_type'][$type] = $count;
            $stats['total_violations'] += $count;
        }

        // Get recent violations (last hour)
        $stats['recent_violations'] = $this->getRecentViolations($filters);

        // Get alert summary
        $stats['alert_summary'] = $this->getAlertSummary();

        return $stats;
    }

    /**
     * Get recent violations.
     */
    protected function getRecentViolations(array $filters): array
    {
        // This would typically query a database or logs
        // For now, return a placeholder
        return [
            'last_hour' => 0,
            'last_24_hours' => 0,
            'last_week' => 0,
        ];
    }

    /**
     * Get alert summary.
     */
    protected function getAlertSummary(): array
    {
        return [
            'alerts_sent_today' => 0,
            'alerts_sent_this_week' => 0,
            'most_common_violation' => null,
            'most_active_ip' => null,
        ];
    }

    /**
     * Clear security violations.
     */
    public function clearViolations(string $type = null, string $ip = null): int
    {
        $pattern = $type 
            ? "security_violation:{$type}:*"
            : "security_violation:*";
        
        $keys = Redis::connection()->keys($pattern);
        $cleared = 0;
        
        foreach ($keys as $key) {
            if ($ip && !str_contains($key, md5($ip))) {
                continue;
            }
            
            Redis::connection()->del($key);
            $cleared++;
        }
        
        Log::info('Security violations cleared', [
            'type' => $type,
            'ip' => $ip,
            'keys_cleared' => $cleared,
        ]);
        
        return $cleared;
    }

    /**
     * Block IP address temporarily.
     */
    public function blockIp(string $ip, int $duration = 3600): bool
    {
        $cacheKey = "blocked_ip:{$ip}";
        
        Cache::put($cacheKey, true, $duration);
        
        Log::warning('IP address blocked', [
            'ip' => $ip,
            'duration' => $duration,
            'timestamp' => now()->toISOString(),
        ]);
        
        return true;
    }

    /**
     * Check if IP is blocked.
     */
    public function isIpBlocked(string $ip): bool
    {
        $cacheKey = "blocked_ip:{$ip}";
        
        return Cache::has($cacheKey);
    }

    /**
     * Unblock IP address.
     */
    public function unblockIp(string $ip): bool
    {
        $cacheKey = "blocked_ip:{$ip}";
        
        $result = Cache::forget($cacheKey);
        
        if ($result) {
            Log::info('IP address unblocked', [
                'ip' => $ip,
                'timestamp' => now()->toISOString(),
            ]);
        }
        
        return $result;
    }

    /**
     * Get blocked IPs.
     */
    public function getBlockedIps(): array
    {
        $pattern = "blocked_ip:*";
        $keys = Redis::connection()->keys($pattern);
        
        $blockedIps = [];
        foreach ($keys as $key) {
            $ip = str_replace('blocked_ip:', '', $key);
            $ttl = Redis::connection()->ttl($key);
            
            $blockedIps[] = [
                'ip' => $ip,
                'blocked_at' => now()->subSeconds($ttl)->toISOString(),
                'expires_at' => now()->addSeconds($ttl)->toISOString(),
                'remaining_seconds' => $ttl,
            ];
        }
        
        return $blockedIps;
    }

    /**
     * Update security configuration.
     */
    public function updateConfig(array $config): bool
    {
        try {
            // Validate configuration
            $this->validateSecurityConfig($config);
            
            // Update configuration in cache
            Cache::put('webhook_security_config', $config, 86400); // 24 hours
            
            Log::info('Security configuration updated', [
                'config_keys' => array_keys($config),
                'timestamp' => now()->toISOString(),
            ]);
            
            return true;
        } catch (\Exception $e) {
            Log::error('Failed to update security configuration', [
                'error' => $e->getMessage(),
                'config' => $config,
            ]);
            
            return false;
        }
    }

    /**
     * Validate security configuration.
     */
    protected function validateSecurityConfig(array $config): void
    {
        $requiredFields = [
            'rate_limits',
            'ip_whitelist',
            'validation',
        ];

        foreach ($requiredFields as $field) {
            if (!isset($config[$field])) {
                throw new \InvalidArgumentException("Missing required security config field: {$field}");
            }
        }
    }

    /**
     * Get security configuration.
     */
    public function getConfig(): array
    {
        return Cache::remember('webhook_security_config', 3600, function () {
            return config('webhooks.security', []);
        });
    }

    /**
     * Run security health check.
     */
    public function healthCheck(): array
    {
        $health = [
            'status' => 'healthy',
            'checks' => [],
            'timestamp' => now()->toISOString(),
        ];

        // Check Redis connection
        try {
            Redis::connection()->ping();
            $health['checks']['redis'] = 'ok';
        } catch (\Exception $e) {
            $health['checks']['redis'] = 'error: ' . $e->getMessage();
            $health['status'] = 'degraded';
        }

        // Check cache functionality
        try {
            $testKey = 'health_check_test';
            Cache::put($testKey, 'test', 60);
            $value = Cache::get($testKey);
            Cache::forget($testKey);
            
            if ($value === 'test') {
                $health['checks']['cache'] = 'ok';
            } else {
                $health['checks']['cache'] = 'error: cache not working';
                $health['status'] = 'degraded';
            }
        } catch (\Exception $e) {
            $health['checks']['cache'] = 'error: ' . $e->getMessage();
            $health['status'] = 'degraded';
        }

        // Check configuration
        try {
            $config = $this->getConfig();
            if (empty($config)) {
                $health['checks']['config'] = 'error: no security config found';
                $health['status'] = 'degraded';
            } else {
                $health['checks']['config'] = 'ok';
            }
        } catch (\Exception $e) {
            $health['checks']['config'] = 'error: ' . $e->getMessage();
            $health['status'] = 'degraded';
        }

        return $health;
    }
}