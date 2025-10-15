<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;
use App\Services\Webhooks\WebhookLoggingService;

class CleanupWebhookLogsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 300; // 5 minutes

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        $this->onQueue('maintenance');
    }

    /**
     * Execute the job.
     */
    public function handle(WebhookLoggingService $logger): void
    {
        $config = config('monitoring.logs.cleanup');
        
        if (!$config['enabled']) {
            return;
        }

        try {
            $this->cleanupLogFiles($config['retention_days'], $logger);
            $this->archiveOldLogs($logger);
            
            $logger->logPerformanceMetrics([
                'operation' => 'log_cleanup',
                'status' => 'completed',
                'timestamp' => now()->toISOString(),
            ]);
            
        } catch (\Throwable $e) {
            $logger->logSecurityEvent('log_cleanup_error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ], 'error');
            
            throw $e;
        }
    }

    /**
     * Clean up old log files.
     */
    private function cleanupLogFiles(array $retentionDays, WebhookLoggingService $logger): void
    {
        $logPath = storage_path('logs/webhooks');
        
        if (!is_dir($logPath)) {
            return;
        }

        $files = File::allFiles($logPath);
        $deletedCount = 0;
        $totalSize = 0;

        foreach ($files as $file) {
            $fileName = $file->getFilename();
            $filePath = $file->getPathname();
            
            // Determine log type from filename
            $logType = $this->getLogTypeFromFilename($fileName);
            $retentionPeriod = $retentionDays[$logType] ?? 30;
            
            // Check if file is older than retention period
            $fileTime = $file->getMTime();
            $cutoffTime = Carbon::now()->subDays($retentionPeriod)->timestamp;
            
            if ($fileTime < $cutoffTime) {
                $fileSize = $file->getSize();
                $totalSize += $fileSize;
                
                if (File::delete($filePath)) {
                    $deletedCount++;
                }
            }
        }

        $logger->logPerformanceMetrics([
            'operation' => 'log_file_cleanup',
            'files_deleted' => $deletedCount,
            'space_freed_bytes' => $totalSize,
            'space_freed_mb' => round($totalSize / 1024 / 1024, 2),
        ]);
    }

    /**
     * Archive old logs if archival is enabled.
     */
    private function archiveOldLogs(WebhookLoggingService $logger): void
    {
        $config = config('monitoring.logs.archival');
        
        if (!$config['enabled']) {
            return;
        }

        $logPath = storage_path('logs/webhooks');
        $archivePath = storage_path('logs/webhooks/archive');
        
        // Create archive directory if it doesn't exist
        if (!is_dir($archivePath)) {
            File::makeDirectory($archivePath, 0755, true);
        }

        $files = File::allFiles($logPath);
        $archivedCount = 0;

        foreach ($files as $file) {
            $fileName = $file->getFilename();
            
            // Skip already archived files
            if (str_contains($fileName, '.archived.')) {
                continue;
            }
            
            // Archive files older than 7 days but younger than retention period
            $fileTime = $file->getMTime();
            $minAge = Carbon::now()->subDays(7)->timestamp;
            $maxAge = Carbon::now()->subDays(1)->timestamp;
            
            if ($fileTime < $maxAge && $fileTime > $minAge) {
                $this->archiveLogFile($file, $archivePath, $config);
                $archivedCount++;
            }
        }

        $logger->logPerformanceMetrics([
            'operation' => 'log_archival',
            'files_archived' => $archivedCount,
        ]);
    }

    /**
     * Archive a single log file.
     */
    private function archiveLogFile($file, string $archivePath, array $config): void
    {
        $originalPath = $file->getPathname();
        $fileName = $file->getFilename();
        $timestamp = Carbon::now()->format('Y-m-d_H-i-s');
        
        // Create archive filename
        $archiveFileName = str_replace('.log', ".archived.{$timestamp}.log", $fileName);
        $archivePath = $archivePath . '/' . $archiveFileName;
        
        // Copy file to archive location
        $content = File::get($originalPath);
        
        // Apply compression if enabled
        if ($config['compression']) {
            $content = gzencode($content, 9);
            $archiveFileName .= '.gz';
        }
        
        // Apply encryption if enabled
        if ($config['encryption']) {
            // This is a placeholder - implement actual encryption
            // $content = $this->encryptContent($content);
        }
        
        File::put($archivePath . '/' . $archiveFileName, $content);
        
        // Store to external storage if configured
        if ($config['storage'] && $config['storage'] !== 'local') {
            Storage::disk($config['storage'])->put(
                "webhook-logs/{$archiveFileName}",
                $content
            );
        }
    }

    /**
     * Get log type from filename.
     */
    private function getLogTypeFromFilename(string $fileName): string
    {
        if (str_contains($fileName, 'events')) {
            return 'webhook-events';
        } elseif (str_contains($fileName, 'processing')) {
            return 'webhook-processing';
        } elseif (str_contains($fileName, 'security')) {
            return 'webhook-security';
        } elseif (str_contains($fileName, 'performance')) {
            return 'webhook-performance';
        } elseif (str_contains($fileName, 'errors')) {
            return 'webhook-errors';
        } elseif (str_contains($fileName, 'metrics')) {
            return 'webhook-metrics';
        }
        
        return 'webhook-events'; // Default
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        $logger = app(WebhookLoggingService::class);
        $logger->logSecurityEvent('log_cleanup_job_failed', [
            'error' => $exception->getMessage(),
        ], 'error');
    }
}