<?php

namespace App\Console\Commands;

use App\Services\Webhooks\WebhookEventProcessingService;
use App\Models\WebhookEvent;
use App\Models\SocialAccount;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class WebhookProcessingCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'webhooks:process 
                            {action : The action to perform (retry|cleanup|stats|batch)}
                            {--account-id= : Specific social account ID}
                            {--limit=50 : Limit for batch operations}
                            {--days=30 : Days for cleanup operations}
                            {--force : Force operation without confirmation}';

    /**
     * The console command description.
     */
    protected $description = 'Manage webhook event processing operations';

    /**
     * Execute the console command.
     */
    public function handle(WebhookEventProcessingService $processingService): int
    {
        $action = $this->argument('action');

        return match ($action) {
            'retry' => $this->retryFailedEvents($processingService),
            'cleanup' => $this->cleanupOldEvents($processingService),
            'stats' => $this->showStats($processingService),
            'batch' => $this->processBatch($processingService),
            default => $this->error("Unknown action: {$action}"),
        };
    }

    /**
     * Retry failed webhook events.
     */
    private function retryFailedEvents(WebhookEventProcessingService $processingService): int
    {
        $accountId = $this->option('account-id');
        $limit = $this->option('limit');

        if ($accountId) {
            $retried = $processingService->retryFailedEvents((int) $accountId, $limit);
            $this->info("Retried {$retried} failed events for account {$accountId}");
        } else {
            $totalRetried = 0;
            $accounts = SocialAccount::all();

            foreach ($accounts as $account) {
                $retried = $processingService->retryFailedEvents($account->id, $limit);
                $totalRetried += $retried;
                $this->line("Account {$account->id} ({$account->platform}): {$retried} events retried");
            }

            $this->info("Total retried events: {$totalRetried}");
        }

        return 0;
    }

    /**
     * Clean up old processed events.
     */
    private function cleanupOldEvents(WebhookEventProcessingService $processingService): int
    {
        $days = $this->option('days');

        if (!$this->option('force') && !$this->confirm("Delete all processed events older than {$days} days?")) {
            $this->info('Operation cancelled.');
            return 0;
        }

        $deleted = $processingService->cleanupOldEvents($days);
        $this->info("Deleted {$deleted} old webhook events.");

        return 0;
    }

    /**
     * Show processing statistics.
     */
    private function showStats(WebhookEventProcessingService $processingService): int
    {
        $accountId = $this->option('account-id');

        if ($accountId) {
            $stats = $processingService->getProcessingStats((int) $accountId);
            $this->displayStats($accountId, $stats);
        } else {
            $accounts = SocialAccount::all();
            
            $this->table(
                ['Account ID', 'Platform', 'Total', 'Pending', 'Processed', 'Failed', 'Success Rate'],
                $accounts->map(function ($account) use ($processingService) {
                    $stats = $processingService->getProcessingStats($account->id);
                    return [
                        $account->id,
                        $account->platform,
                        $stats['total_events'],
                        $stats['pending_events'],
                        $stats['processed_events'],
                        $stats['failed_events'],
                        $stats['success_rate'] . '%',
                    ];
                })
            );
        }

        return 0;
    }

    /**
     * Process events in batch.
     */
    private function processBatch(WebhookEventProcessingService $processingService): int
    {
        $accountId = $this->option('account-id');
        $limit = $this->option('limit');

        $query = WebhookEvent::where('status', 'pending')
            ->orderBy('received_at', 'asc')
            ->limit($limit);

        if ($accountId) {
            $query->where('social_account_id', $accountId);
        }

        $events = $query->get();

        if ($events->isEmpty()) {
            $this->info('No pending events to process.');
            return 0;
        }

        $this->info("Processing {$events->count()} events in batch...");

        $bar = $this->output->createProgressBar($events->count());
        $bar->start();

        $processingService->processBatch($events->toArray());

        $bar->finish();
        $this->newLine();

        $this->info('Batch processing completed.');

        return 0;
    }

    /**
     * Display statistics for a specific account.
     */
    private function displayStats(int $accountId, array $stats): void
    {
        $this->info("Webhook Processing Stats for Account {$accountId}");
        $this->line("=====================================");
        $this->line("Total Events: {$stats['total_events']}");
        $this->line("Pending Events: {$stats['pending_events']}");
        $this->line("Processing Events: {$stats['processing_events']}");
        $this->line("Processed Events: {$stats['processed_events']}");
        $this->line("Failed Events: {$stats['failed_events']}");
        $this->line("Ignored Events: {$stats['ignored_events']}");
        $this->line("Average Processing Time: {$stats['average_processing_time']}s");
        $this->line("Success Rate: {$stats['success_rate']}%");
    }
}