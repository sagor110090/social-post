<?php

namespace App\Http\Controllers;

use App\Services\Webhooks\WebhookMonitoringService;
use App\Services\Webhooks\WebhookMetricsService;
use App\Services\Webhooks\WebhookAlertingService;
use App\Services\Webhooks\WebhookLoggingService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class WebhookMonitoringController extends Controller
{
    private WebhookMonitoringService $monitoring;
    private WebhookMetricsService $metrics;
    private WebhookAlertingService $alerting;
    private WebhookLoggingService $logger;

    public function __construct(
        WebhookMonitoringService $monitoring,
        WebhookMetricsService $metrics,
        WebhookAlertingService $alerting,
        WebhookLoggingService $logger
    ) {
        $this->monitoring = $monitoring;
        $this->metrics = $metrics;
        $this->alerting = $alerting;
        $this->logger = $logger;
    }

    /**
     * Get monitoring dashboard data.
     */
    public function dashboard(Request $request): JsonResponse
    {
        $timeRange = $request->get('time_range', '24h');
        
        try {
            $data = $this->metrics->getDashboardData($timeRange);
            
            return response()->json([
                'status' => 'success',
                'data' => $data,
                'time_range' => $timeRange,
                'timestamp' => now()->toISOString(),
            ]);
            
        } catch (\Throwable $e) {
            $this->logger->logSecurityEvent('dashboard_error', [
                'error' => $e->getMessage(),
                'time_range' => $timeRange,
            ], 'error');
            
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to load dashboard data',
            ], 500);
        }
    }

    /**
     * Get health check results.
     */
    public function health(Request $request): JsonResponse
    {
        $check = $request->get('check');
        
        try {
            if ($check) {
                $config = config('monitoring.health.checks');
                if (!isset($config[$check])) {
                    return response()->json([
                        'status' => 'error',
                        'message' => "Unknown health check: {$check}",
                    ], 400);
                }
                
                $results = [$check => $this->monitoring->runHealthCheck($check, $config[$check])];
            } else {
                $results = $this->monitoring->runHealthChecks();
            }
            
            $overallStatus = collect($results)->every(fn($result) => in_array($result['status'] ?? 'unknown', ['healthy', 'warning']));
            
            return response()->json([
                'status' => $overallStatus ? 'healthy' : 'unhealthy',
                'checks' => $results,
                'timestamp' => now()->toISOString(),
            ], $overallStatus ? 200 : 503);
            
        } catch (\Throwable $e) {
            $this->logger->logSecurityEvent('health_check_error', [
                'error' => $e->getMessage(),
                'check' => $check,
            ], 'error');
            
            return response()->json([
                'status' => 'error',
                'message' => 'Health check failed',
            ], 500);
        }
    }

    /**
     * Get metrics data.
     */
    public function metrics(Request $request): JsonResponse
    {
        $type = $request->get('type');
        $interval = $request->get('interval', '1h');
        $filters = $request->only(['platform', 'event_type', 'error_type']);
        
        if (!$type) {
            return response()->json([
                'status' => 'error',
                'message' => 'Metric type is required',
            ], 400);
        }
        
        try {
            $data = match ($type) {
                'request_volume' => $this->metrics->getRequestVolumeMetrics($interval, $filters),
                'response_times' => $this->metrics->getResponseTimeMetrics($interval, $filters),
                'error_rates' => $this->metrics->getErrorRateMetrics($interval, $filters),
                'queue_metrics' => $this->metrics->getQueueMetrics($interval, $filters),
                'security_events' => $this->metrics->getSecurityEventMetrics($interval, $filters),
                default => throw new \InvalidArgumentException("Unknown metric type: {$type}"),
            };
            
            return response()->json([
                'status' => 'success',
                'data' => $data,
                'type' => $type,
                'interval' => $interval,
                'filters' => $filters,
                'timestamp' => now()->toISOString(),
            ]);
            
        } catch (\Throwable $e) {
            $this->logger->logSecurityEvent('metrics_error', [
                'error' => $e->getMessage(),
                'type' => $type,
                'interval' => $interval,
                'filters' => $filters,
            ], 'error');
            
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to load metrics',
            ], 500);
        }
    }

    /**
     * Get system metrics.
     */
    public function systemMetrics(): JsonResponse
    {
        try {
            $metrics = $this->monitoring->getSystemMetrics();
            
            return response()->json([
                'status' => 'success',
                'data' => $metrics,
                'timestamp' => now()->toISOString(),
            ]);
            
        } catch (\Throwable $e) {
            $this->logger->logSecurityEvent('system_metrics_error', [
                'error' => $e->getMessage(),
            ], 'error');
            
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to load system metrics',
            ], 500);
        }
    }

    /**
     * Get alerts information.
     */
    public function alerts(Request $request): JsonResponse
    {
        $action = $request->get('action', 'list');
        
        try {
            return match ($action) {
                'list' => $this->listAlerts(),
                'evaluate' => $this->evaluateAlerts(),
                'trigger' => $this->triggerAlert($request),
                'suppress' => $this->suppressAlert($request),
                'clear' => $this->clearSuppression($request),
                default => response()->json([
                    'status' => 'error',
                    'message' => "Unknown action: {$action}",
                ], 400),
            };
            
        } catch (\Throwable $e) {
            $this->logger->logSecurityEvent('alerts_error', [
                'error' => $e->getMessage(),
                'action' => $action,
            ], 'error');
            
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to process alert request',
            ], 500);
        }
    }

    /**
     * List alert information.
     */
    private function listAlerts(): JsonResponse
    {
        $rules = config('monitoring.alerting.rules');
        $channels = config('monitoring.alerting.channels');
        $suppressed = $this->alerting->getSuppressedAlerts();
        
        return response()->json([
            'status' => 'success',
            'data' => [
                'rules' => $rules,
                'channels' => $channels,
                'suppressed' => $suppressed,
            ],
            'timestamp' => now()->toISOString(),
        ]);
    }

    /**
     * Evaluate alert rules.
     */
    private function evaluateAlerts(): JsonResponse
    {
        $alerts = $this->alerting->evaluateAlerts();
        
        return response()->json([
            'status' => 'success',
            'data' => [
                'alerts' => $alerts,
                'count' => count($alerts),
            ],
            'timestamp' => now()->toISOString(),
        ]);
    }

    /**
     * Trigger manual alert.
     */
    private function triggerAlert(Request $request): JsonResponse
    {
        $rule = $request->get('rule');
        $message = $request->get('message');
        $severity = $request->get('severity', 'warning');
        
        if (!$rule || !$message) {
            return response()->json([
                'status' => 'error',
                'message' => 'Rule and message are required',
            ], 400);
        }
        
        $this->alerting->triggerAlert($rule, $message, [], $severity);
        
        return response()->json([
            'status' => 'success',
            'message' => 'Alert triggered successfully',
            'timestamp' => now()->toISOString(),
        ]);
    }

    /**
     * Suppress alert.
     */
    private function suppressAlert(Request $request): JsonResponse
    {
        $rule = $request->get('rule');
        $duration = $request->get('duration', 3600);
        
        if (!$rule) {
            return response()->json([
                'status' => 'error',
                'message' => 'Rule name is required',
            ], 400);
        }
        
        // This is a simplified implementation
        $suppressionKey = "alert_suppression:{$rule}";
        cache()->put($suppressionKey, true, $duration);
        
        return response()->json([
            'status' => 'success',
            'message' => 'Alert suppressed successfully',
            'duration' => $duration,
            'timestamp' => now()->toISOString(),
        ]);
    }

    /**
     * Clear alert suppression.
     */
    private function clearSuppression(Request $request): JsonResponse
    {
        $rule = $request->get('rule');
        
        if ($rule) {
            $this->alerting->clearSuppression($rule);
        } else {
            $suppressed = $this->alerting->getSuppressedAlerts();
            foreach ($suppressed as $suppressedRule) {
                $this->alerting->clearSuppression($suppressedRule);
            }
        }
        
        return response()->json([
            'status' => 'success',
            'message' => 'Suppression cleared successfully',
            'timestamp' => now()->toISOString(),
        ]);
    }

    /**
     * Get monitoring configuration.
     */
    public function config(): JsonResponse
    {
        $config = [
            'monitoring' => config('monitoring'),
            'webhooks' => config('webhooks'),
        ];
        
        // Remove sensitive information
        unset($config['monitoring']['alerting']['channels']['email']['to']);
        unset($config['monitoring']['alerting']['channels']['slack']['webhook_url']);
        unset($config['monitoring']['alerting']['channels']['webhook']['url']);
        
        return response()->json([
            'status' => 'success',
            'data' => $config,
            'timestamp' => now()->toISOString(),
        ]);
    }

    /**
     * Test monitoring endpoint.
     */
    public function test(): JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'message' => 'Monitoring endpoint is working',
            'timestamp' => now()->toISOString(),
            'version' => config('app.version', '1.0.0'),
        ]);
    }
}