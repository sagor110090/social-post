<?php

namespace App\Http\Controllers;

use App\Services\Webhooks\WebhookLoggingService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\File;
use Carbon\Carbon;

class WebhookLogsController extends Controller
{
    private WebhookLoggingService $logger;

    public function __construct(WebhookLoggingService $logger)
    {
        $this->logger = $logger;
    }

    /**
     * Get logs with filtering and pagination.
     */
    public function index(Request $request): JsonResponse
    {
        $channel = $request->get('channel', 'webhook-events');
        $lines = $request->get('lines', 100);
        $search = $request->get('search');
        $since = $request->get('since');
        $level = $request->get('level');
        
        try {
            $logPath = $this->getLogPath($channel);
            
            if (!file_exists($logPath)) {
                return response()->json([
                    'status' => 'success',
                    'data' => [
                        'logs' => [],
                        'total' => 0,
                    ],
                    'message' => 'Log file not found',
                ]);
            }
            
            $logs = $this->parseLogFile($logPath, $lines, $search, $since, $level);
            
            return response()->json([
                'status' => 'success',
                'data' => [
                    'logs' => $logs,
                    'total' => count($logs),
                    'channel' => $channel,
                    'filters' => [
                        'search' => $search,
                        'since' => $since,
                        'level' => $level,
                    ],
                ],
                'timestamp' => now()->toISOString(),
            ]);
            
        } catch (\Throwable $e) {
            $this->logger->logSecurityEvent('logs_api_error', [
                'error' => $e->getMessage(),
                'channel' => $channel,
            ], 'error');
            
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to load logs',
            ], 500);
        }
    }

    /**
     * Search logs.
     */
    public function search(Request $request): JsonResponse
    {
        $query = $request->get('query');
        $channel = $request->get('channel', 'webhook-events');
        $limit = $request->get('limit', 50);
        
        if (!$query) {
            return response()->json([
                'status' => 'error',
                'message' => 'Search query is required',
            ], 400);
        }
        
        try {
            $logPath = $this->getLogPath($channel);
            
            if (!file_exists($logPath)) {
                return response()->json([
                    'status' => 'success',
                    'data' => [
                        'results' => [],
                        'total' => 0,
                    ],
                ]);
            }
            
            $results = $this->searchInLogFile($logPath, $query, $limit);
            
            return response()->json([
                'status' => 'success',
                'data' => [
                    'results' => $results,
                    'total' => count($results),
                    'query' => $query,
                    'channel' => $channel,
                ],
                'timestamp' => now()->toISOString(),
            ]);
            
        } catch (\Throwable $e) {
            $this->logger->logSecurityEvent('log_search_error', [
                'error' => $e->getMessage(),
                'query' => $query,
                'channel' => $channel,
            ], 'error');
            
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to search logs',
            ], 500);
        }
    }

    /**
     * Get log statistics.
     */
    public function stats(Request $request): JsonResponse
    {
        $channel = $request->get('channel', 'webhook-events');
        $since = $request->get('since', '24h');
        
        try {
            $logPath = $this->getLogPath($channel);
            
            if (!file_exists($logPath)) {
                return response()->json([
                    'status' => 'success',
                    'data' => [
                        'total_lines' => 0,
                        'file_size' => 0,
                        'levels' => [],
                        'platforms' => [],
                        'time_distribution' => [],
                    ],
                ]);
            }
            
            $stats = $this->getLogStatistics($logPath, $since);
            
            return response()->json([
                'status' => 'success',
                'data' => $stats,
                'channel' => $channel,
                'since' => $since,
                'timestamp' => now()->toISOString(),
            ]);
            
        } catch (\Throwable $e) {
            $this->logger->logSecurityEvent('log_stats_error', [
                'error' => $e->getMessage(),
                'channel' => $channel,
            ], 'error');
            
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to get log statistics',
            ], 500);
        }
    }

    /**
     * Download log file.
     */
    public function download(Request $request): JsonResponse
    {
        $channel = $request->get('channel', 'webhook-events');
        
        try {
            $logPath = $this->getLogPath($channel);
            
            if (!file_exists($logPath)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Log file not found',
                ], 404);
            }
            
            $content = file_get_contents($logPath);
            $filename = "{$channel}-" . date('Y-m-d-H-i-s') . ".log";
            
            return response($content)
                ->header('Content-Type', 'text/plain')
                ->header('Content-Disposition', "attachment; filename=\"{$filename}\"");
                
        } catch (\Throwable $e) {
            $this->logger->logSecurityEvent('log_download_error', [
                'error' => $e->getMessage(),
                'channel' => $channel,
            ], 'error');
            
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to download log file',
            ], 500);
        }
    }

    /**
     * Clear logs.
     */
    public function clear(Request $request): JsonResponse
    {
        $channel = $request->get('channel');
        $confirm = $request->get('confirm');
        
        if ($confirm !== 'yes') {
            return response()->json([
                'status' => 'error',
                'message' => 'Confirmation required. Add ?confirm=yes to clear logs',
            ], 400);
        }
        
        try {
            if ($channel) {
                $logPath = $this->getLogPath($channel);
                if (file_exists($logPath)) {
                    file_put_contents($logPath, '');
                }
            } else {
                // Clear all webhook logs
                $channels = ['webhook-events', 'webhook-processing', 'webhook-security', 'webhook-performance', 'webhook-errors', 'webhook-metrics'];
                foreach ($channels as $ch) {
                    $logPath = $this->getLogPath($ch);
                    if (file_exists($logPath)) {
                        file_put_contents($logPath, '');
                    }
                }
            }
            
            $this->logger->logSecurityEvent('logs_cleared', [
                'channel' => $channel ?? 'all',
            ], 'info');
            
            return response()->json([
                'status' => 'success',
                'message' => 'Logs cleared successfully',
                'timestamp' => now()->toISOString(),
            ]);
            
        } catch (\Throwable $e) {
            $this->logger->logSecurityEvent('log_clear_error', [
                'error' => $e->getMessage(),
                'channel' => $channel,
            ], 'error');
            
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to clear logs',
            ], 500);
        }
    }

    /**
     * Get available log channels.
     */
    public function channels(): JsonResponse
    {
        $channels = [
            'webhook-events' => [
                'name' => 'Webhook Events',
                'description' => 'Incoming webhook events and processing',
                'path' => $this->getLogPath('webhook-events'),
            ],
            'webhook-processing' => [
                'name' => 'Webhook Processing',
                'description' => 'Event processing activities and results',
                'path' => $this->getLogPath('webhook-processing'),
            ],
            'webhook-security' => [
                'name' => 'Webhook Security',
                'description' => 'Security-related events and violations',
                'path' => $this->getLogPath('webhook-security'),
            ],
            'webhook-performance' => [
                'name' => 'Webhook Performance',
                'description' => 'Performance metrics and monitoring',
                'path' => $this->getLogPath('webhook-performance'),
            ],
            'webhook-errors' => [
                'name' => 'Webhook Errors',
                'description' => 'Error tracking and debugging',
                'path' => $this->getLogPath('webhook-errors'),
            ],
            'webhook-metrics' => [
                'name' => 'Webhook Metrics',
                'description' => 'Metrics collection and aggregation',
                'path' => $this->getLogPath('webhook-metrics'),
            ],
        ];
        
        // Add file existence and size information
        foreach ($channels as $key => &$channel) {
            if (file_exists($channel['path'])) {
                $channel['exists'] = true;
                $channel['size'] = filesize($channel['path']);
                $channel['size_mb'] = round($channel['size'] / 1024 / 1024, 2);
                $channel['modified'] = date('Y-m-d H:i:s', filemtime($channel['path']));
            } else {
                $channel['exists'] = false;
                $channel['size'] = 0;
                $channel['size_mb'] = 0;
                $channel['modified'] = null;
            }
        }
        
        return response()->json([
            'status' => 'success',
            'data' => $channels,
            'timestamp' => now()->toISOString(),
        ]);
    }

    /**
     * Parse log file and extract entries.
     */
    private function parseLogFile(string $logPath, int $lines, ?string $search, ?string $since, ?string $level): array
    {
        $content = file_get_contents($logPath);
        $logLines = explode("\n", $content);
        $logs = [];
        $cutoffTime = $since ? Carbon::now()->sub($since) : null;
        
        // Get last N lines
        $logLines = array_slice($logLines, -$lines);
        
        foreach (array_reverse($logLines) as $line) {
            if (empty(trim($line))) {
                continue;
            }
            
            try {
                $entry = json_decode($line, true);
                if (!$entry) {
                    continue;
                }
                
                // Apply filters
                if ($search && !$this->matchesSearch($entry, $search)) {
                    continue;
                }
                
                if ($level && ($entry['level'] ?? '') !== $level) {
                    continue;
                }
                
                if ($cutoffTime && isset($entry['timestamp'])) {
                    $entryTime = Carbon::parse($entry['timestamp']);
                    if ($entryTime->lt($cutoffTime)) {
                        continue;
                    }
                }
                
                $logs[] = $entry;
                
            } catch (\Throwable $e) {
                // Skip invalid JSON lines
                continue;
            }
        }
        
        return array_reverse($logs);
    }

    /**
     * Search in log file.
     */
    private function searchInLogFile(string $logPath, string $query, int $limit): array
    {
        $content = file_get_contents($logPath);
        $logLines = explode("\n", $content);
        $results = [];
        
        foreach ($logLines as $line) {
            if (empty(trim($line))) {
                continue;
            }
            
            if (str_contains(strtolower($line), strtolower($query))) {
                try {
                    $entry = json_decode($line, true);
                    if ($entry) {
                        $results[] = $entry;
                        
                        if (count($results) >= $limit) {
                            break;
                        }
                    }
                } catch (\Throwable $e) {
                    // Skip invalid JSON lines
                    continue;
                }
            }
        }
        
        return $results;
    }

    /**
     * Get log statistics.
     */
    private function getLogStatistics(string $logPath, string $since): array
    {
        $content = file_get_contents($logPath);
        $logLines = explode("\n", $content);
        $cutoffTime = Carbon::now()->sub($since);
        
        $stats = [
            'total_lines' => 0,
            'file_size' => filesize($logPath),
            'file_size_mb' => round(filesize($logPath) / 1024 / 1024, 2),
            'levels' => [],
            'platforms' => [],
            'time_distribution' => [],
        ];
        
        foreach ($logLines as $line) {
            if (empty(trim($line))) {
                continue;
            }
            
            try {
                $entry = json_decode($line, true);
                if (!$entry) {
                    continue;
                }
                
                // Check time filter
                if (isset($entry['timestamp'])) {
                    $entryTime = Carbon::parse($entry['timestamp']);
                    if ($entryTime->lt($cutoffTime)) {
                        continue;
                    }
                }
                
                $stats['total_lines']++;
                
                // Count levels
                $level = $entry['level'] ?? 'unknown';
                $stats['levels'][$level] = ($stats['levels'][$level] ?? 0) + 1;
                
                // Count platforms
                if (isset($entry['webhook']['platform'])) {
                    $platform = $entry['webhook']['platform'];
                    $stats['platforms'][$platform] = ($stats['platforms'][$platform] ?? 0) + 1;
                }
                
                // Time distribution (hourly)
                if (isset($entry['timestamp'])) {
                    $hour = Carbon::parse($entry['timestamp'])->format('H:00');
                    $stats['time_distribution'][$hour] = ($stats['time_distribution'][$hour] ?? 0) + 1;
                }
                
            } catch (\Throwable $e) {
                continue;
            }
        }
        
        return $stats;
    }

    /**
     * Check if log entry matches search query.
     */
    private function matchesSearch(array $entry, string $search): bool
    {
        $searchLower = strtolower($search);
        $content = json_encode($entry);
        
        return str_contains(strtolower($content), $searchLower);
    }

    /**
     * Get log file path for channel.
     */
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