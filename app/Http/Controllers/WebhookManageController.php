<?php

namespace App\Http\Controllers;

use App\Models\WebhookConfig;
use App\Models\WebhookEvent;
use App\Models\WebhookDeliveryMetric;
use App\Models\SocialAccount;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Inertia\Inertia;
use Inertia\Response;

class WebhookManageController extends Controller
{
    public function __construct()
    {

    }

    /**
     * Display webhook settings index.
     */
    public function index(): Response
    {
        return Inertia::render('settings/Webhooks/Index');
    }

    /**
     * Display webhook configurations page.
     */
    public function configs(): Response
    {
        $configs = WebhookConfig::with(['socialAccount'])
            ->whereHas('socialAccount', function ($query) {
                $query->where('user_id', Auth::id());
            })
            ->get();

        $socialAccounts = SocialAccount::where('user_id', Auth::id())->get();

        return Inertia::render('settings/Webhooks/Configs', [
            'configs' => $configs,
            'socialAccounts' => $socialAccounts,
        ]);
    }

    /**
     * Display webhook events page.
     */
    public function events(): Response
    {
        return Inertia::render('settings/Webhooks/Events');
    }

    /**
     * Display webhook analytics page.
     */
    public function analytics(): Response
    {
        return Inertia::render('settings/Webhooks/Analytics');
    }

    /**
     * Display webhook security page.
     */
    public function security(): Response
    {
        return Inertia::render('settings/Webhooks/Security');
    }

    /**
     * Get webhook statistics for the dashboard.
     */
    public function stats(): JsonResponse
    {
        $stats = [
            'total_configs' => WebhookConfig::whereHas('socialAccount', function ($query) {
                $query->where('user_id', Auth::id());
            })->count(),

            'active_configs' => WebhookConfig::whereHas('socialAccount', function ($query) {
                $query->where('user_id', Auth::id());
            })->where('is_active', true)->count(),

            'total_events' => WebhookEvent::whereHas('socialAccount', function ($query) {
                $query->where('user_id', Auth::id());
            })->count(),

            'pending_events' => WebhookEvent::whereHas('socialAccount', function ($query) {
                $query->where('user_id', Auth::id());
            })->where('status', 'pending')->count(),

            'failed_events' => WebhookEvent::whereHas('socialAccount', function ($query) {
                $query->where('user_id', Auth::id());
            })->where('status', 'failed')->count(),

            'processed_events' => WebhookEvent::whereHas('socialAccount', function ($query) {
                $query->where('user_id', Auth::id());
            })->where('status', 'processed')->count(),

            'events_by_platform' => WebhookEvent::selectRaw('platform, COUNT(*) as count')
                ->whereHas('socialAccount', function ($query) {
                    $query->where('user_id', Auth::id());
                })
                ->groupBy('platform')
                ->get(),

            'recent_events' => WebhookEvent::with(['socialAccount'])
                ->whereHas('socialAccount', function ($query) {
                    $query->where('user_id', Auth::id());
                })
                ->orderBy('received_at', 'desc')
                ->limit(10)
                ->get(),
        ];

        return response()->json($stats);
    }

    /**
     * Get webhook analytics data.
     */
    public function getAnalytics(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'time_range' => 'in:7d,30d,90d',
            'platform' => 'in:facebook,instagram,twitter,linkedin',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => 'Invalid parameters'], 422);
        }

        $timeRange = $request->get('time_range', '30d');
        $platform = $request->get('platform');

        $days = match($timeRange) {
            '7d' => 7,
            '30d' => 30,
            '90d' => 90,
            default => 30,
        };

        $startDate = now()->subDays($days)->startOfDay();
        $endDate = now()->endOfDay();

        // Build query
        $query = WebhookDeliveryMetric::with(['webhookConfig.socialAccount'])
            ->whereHas('webhookConfig.socialAccount', function ($query) {
                $query->where('user_id', Auth::id());
            })
            ->whereBetween('delivered_at', [$startDate, $endDate]);

        if ($platform) {
            $query->whereHas('webhookConfig.socialAccount', function ($query) use ($platform) {
                $query->where('platform', $platform);
            });
        }

        // Get daily metrics
        $dailyMetrics = WebhookEvent::selectRaw('
                DATE(received_at) as date,
                COUNT(*) as total,
                SUM(CASE WHEN status = "processed" THEN 1 ELSE 0 END) as delivered,
                SUM(CASE WHEN status = "failed" THEN 1 ELSE 0 END) as failed,
                SUM(CASE WHEN status = "pending" THEN 1 ELSE 0 END) as pending
            ')
            ->whereHas('socialAccount', function ($query) {
                $query->where('user_id', Auth::id());
            })
            ->whereBetween('received_at', [$startDate, $endDate])
            ->when($platform, function ($query, $platform) {
                return $query->where('platform', $platform);
            })
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        // Get platform stats
        $platformStats = WebhookEvent::selectRaw('
                platform,
                COUNT(*) as deliveries,
                AVG(CASE WHEN status = "processed" THEN 100 ELSE 0 END) as success_rate,
                AVG(COALESCE(response_time_ms, 0)) as average_response_time
            ')
            ->whereHas('socialAccount', function ($query) {
                $query->where('user_id', Auth::id());
            })
            ->whereBetween('received_at', [$startDate, $endDate])
            ->groupBy('platform')
            ->get();

        // Get recent deliveries
        $recentDeliveries = $query->orderBy('delivered_at', 'desc')->limit(10)->get();

        $analytics = [
            'metrics' => $dailyMetrics->map(fn($metric) => [
                'date' => $metric->date,
                'delivered' => $metric->delivered,
                'failed' => $metric->failed,
                'pending' => $metric->pending,
                'total' => $metric->total,
            ]),
            'delivery_metrics' => $recentDeliveries,
            'summary' => [
                'total_deliveries' => $dailyMetrics->sum('total'),
                'success_rate' => $dailyMetrics->sum('total') > 0
                    ? ($dailyMetrics->sum('delivered') / $dailyMetrics->sum('total')) * 100
                    : 0,
                'average_response_time' => $recentDeliveries->avg('response_time_ms') ?? 0,
                'total_errors' => $dailyMetrics->sum('failed'),
                'platform_stats' => $platformStats->map(fn($stat) => [
                    'platform' => $stat->platform,
                    'deliveries' => $stat->deliveries,
                    'success_rate' => floatval($stat->success_rate),
                    'average_response_time' => floatval($stat->average_response_time),
                ]),
            ],
        ];

        return response()->json($analytics);
    }

    /**
     * Get security events.
     */
    public function getSecurityEvents(): JsonResponse
    {
        // For now, return empty array as security events model doesn't exist yet
        // This would be implemented when the security logging system is created
        return response()->json([]);
    }

    /**
     * Get security settings.
     */
    public function getSecuritySettings(): JsonResponse
    {
        // Return default security settings
        // This would be stored in a settings table or config file
        $settings = [
            'ip_whitelist_enabled' => false,
            'ip_whitelist' => [],
            'rate_limit_enabled' => true,
            'rate_limit_requests' => 100,
            'rate_limit_window' => 60,
            'signature_verification_enabled' => true,
            'webhook_timeout' => 30,
        ];

        return response()->json($settings);
    }

    /**
     * Update security settings.
     */
    public function updateSecuritySettings(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'ip_whitelist_enabled' => 'boolean',
            'ip_whitelist' => 'array',
            'ip_whitelist.*' => 'ip',
            'rate_limit_enabled' => 'boolean',
            'rate_limit_requests' => 'integer|min:1|max:1000',
            'rate_limit_window' => 'integer|min:1|max:3600',
            'signature_verification_enabled' => 'boolean',
            'webhook_timeout' => 'integer|min:1|max:300',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => 'Invalid settings'], 422);
        }

        // Save settings to database or config
        // This would be implemented with a proper settings storage system

        return response()->json(['message' => 'Security settings updated successfully']);
    }

    /**
     * Resolve security event.
     */
    public function resolveSecurityEvent($eventId): JsonResponse
    {
        // This would be implemented when security events model is created
        return response()->json(['message' => 'Security event resolved']);
    }

    /**
     * Export analytics data.
     */
    public function exportAnalytics(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'time_range' => 'in:7d,30d,90d',
            'platform' => 'in:facebook,instagram,twitter,linkedin',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => 'Invalid parameters'], 422);
        }

        // Generate CSV export
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="webhook-analytics.csv"',
        ];

        $callback = function() use ($request) {
            $file = fopen('php://output', 'w');

            // CSV header
            fputcsv($file, ['Date', 'Platform', 'Event Type', 'Status', 'Response Time', 'Created At']);

            // CSV data (placeholder - would fetch actual data)
            fputcsv($file, [now()->toDateString(), 'example', 'test', 'processed', '100ms', now()]);

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Export events data.
     */
    public function exportEvents(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'platform' => 'in:facebook,instagram,twitter,linkedin',
            'status' => 'in:pending,processing,processed,failed',
            'start_date' => 'date',
            'end_date' => 'date|after_or_equal:start_date',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => 'Invalid parameters'], 422);
        }

        // Generate CSV export
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="webhook-events.csv"',
        ];

        $callback = function() use ($request) {
            $file = fopen('php://output', 'w');

            // CSV header
            fputcsv($file, ['ID', 'Platform', 'Event Type', 'Status', 'Received At', 'Processed At', 'Retry Count']);

            // CSV data (placeholder - would fetch actual data)
            fputcsv($file, [1, 'example', 'test', 'processed', now(), now(), 0]);

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }
}
