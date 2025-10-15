<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Webhooks\WebhookSecurityService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class WebhookSecurityController extends Controller
{
    public function __construct(
        private WebhookSecurityService $securityService
    ) {
        $this->middleware(['auth:sanctum', 'admin']);
    }

    /**
     * Get security statistics.
     */
    public function stats(Request $request): JsonResponse
    {
        $filters = [];
        
        if ($request->has('platform')) {
            $filters['platform'] = $request->get('platform');
        }

        $stats = $this->securityService->getSecurityStats($filters);

        return response()->json([
            'status' => 'success',
            'data' => $stats,
        ]);
    }

    /**
     * Get blocked IPs.
     */
    public function blockedIps(): JsonResponse
    {
        $blockedIps = $this->securityService->getBlockedIps();

        return response()->json([
            'status' => 'success',
            'data' => $blockedIps,
        ]);
    }

    /**
     * Block an IP address.
     */
    public function blockIp(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'ip' => 'required|ip',
            'duration' => 'integer|min:60|max:86400', // 1 minute to 24 hours
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $ip = $request->get('ip');
        $duration = $request->get('duration', 3600);

        if ($this->securityService->blockIp($ip, $duration)) {
            return response()->json([
                'status' => 'success',
                'message' => "IP {$ip} blocked for {$duration} seconds",
            ]);
        }

        return response()->json([
            'status' => 'error',
            'message' => "Failed to block IP {$ip}",
        ], 500);
    }

    /**
     * Unblock an IP address.
     */
    public function unblockIp(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'ip' => 'required|ip',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $ip = $request->get('ip');

        if ($this->securityService->unblockIp($ip)) {
            return response()->json([
                'status' => 'success',
                'message' => "IP {$ip} unblocked successfully",
            ]);
        }

        return response()->json([
            'status' => 'error',
            'message' => "Failed to unblock IP {$ip}",
        ], 500);
    }

    /**
     * Clear security violations.
     */
    public function clearViolations(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'type' => 'nullable|in:signature_failure,rate_limit_violation,ip_violation,validation_error,suspicious_activity',
            'ip' => 'nullable|ip',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $type = $request->get('type');
        $ip = $request->get('ip');

        $cleared = $this->securityService->clearViolations($type, $ip);

        return response()->json([
            'status' => 'success',
            'message' => "Cleared {$cleared} security violations",
            'data' => [
                'type' => $type,
                'ip' => $ip,
                'cleared_count' => $cleared,
            ],
        ]);
    }

    /**
     * Get security health check.
     */
    public function healthCheck(): JsonResponse
    {
        $health = $this->securityService->healthCheck();

        return response()->json([
            'status' => 'success',
            'data' => $health,
        ]);
    }

    /**
     * Get security configuration.
     */
    public function config(): JsonResponse
    {
        $config = $this->securityService->getConfig();

        // Remove sensitive information
        $safeConfig = $this->sanitizeConfig($config);

        return response()->json([
            'status' => 'success',
            'data' => $safeConfig,
        ]);
    }

    /**
     * Update security configuration.
     */
    public function updateConfig(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'rate_limits' => 'required|array',
            'ip_whitelist' => 'required|array',
            'validation' => 'required|array',
            'alerting' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $config = $request->all();

        if ($this->securityService->updateConfig($config)) {
            return response()->json([
                'status' => 'success',
                'message' => 'Security configuration updated successfully',
            ]);
        }

        return response()->json([
            'status' => 'error',
            'message' => 'Failed to update security configuration',
        ], 500);
    }

    /**
     * Get recent security events.
     */
    public function recentEvents(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'hours' => 'integer|min:1|max:168', // 1 hour to 1 week
            'type' => 'nullable|in:signature_failure,rate_limit_violation,ip_violation,validation_error,suspicious_activity',
            'platform' => 'nullable|in:facebook,instagram,twitter,linkedin',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $hours = $request->get('hours', 24);
        $type = $request->get('type');
        $platform = $request->get('platform');

        // This would typically query a database or log storage
        // For now, return a placeholder response
        $events = [
            'total' => 0,
            'by_type' => [],
            'by_platform' => [],
            'timeline' => [],
        ];

        return response()->json([
            'status' => 'success',
            'data' => $events,
        ]);
    }

    /**
     * Export security report.
     */
    public function exportReport(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'format' => 'in:json,csv',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'type' => 'nullable|in:signature_failure,rate_limit_violation,ip_violation,validation_error,suspicious_activity',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        // Generate report based on parameters
        $report = [
            'generated_at' => now()->toISOString(),
            'generated_by' => Auth::user()->email,
            'parameters' => $request->all(),
            'data' => [],
        ];

        return response()->json([
            'status' => 'success',
            'data' => $report,
        ]);
    }

    /**
     * Sanitize configuration for API response.
     */
    protected function sanitizeConfig(array $config): array
    {
        // Remove sensitive information
        unset($config['alerting']['webhook_url']);
        unset($config['alerting']['email_recipients']);
        unset($config['services']['slack']['webhook_url']);

        return $config;
    }
}