<?php

namespace App\Console\Commands;

use App\Jobs\PublishScheduledPostJob;
use App\Models\ScheduledPost;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SchedulePostsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'social:publish-scheduled 
                            {--force : Force publishing of posts that are slightly in the future}
                            {--batch-size=100 : Number of posts to process in one batch}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Publish scheduled social media posts that are due';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Starting to publish scheduled posts...');

        $batchSize = $this->option('batch-size');
        $force = $this->option('force');

        try {
            // Get posts that are due for publishing
            $query = ScheduledPost::where('status', 'pending')
                ->where('scheduled_at', '<=', now())
                ->with(['post', 'user'])
                ->orderBy('scheduled_at', 'asc')
                ->limit($batchSize);

            $scheduledPosts = $query->get();

            if ($scheduledPosts->isEmpty()) {
                $this->info('No scheduled posts to publish.');
                return self::SUCCESS;
            }

            $this->info("Found {$scheduledPosts->count()} scheduled posts to publish.");

            $bar = $this->output->createProgressBar($scheduledPosts->count());
            $bar->start();

            $publishedCount = 0;
            $failedCount = 0;

            foreach ($scheduledPosts as $scheduledPost) {
                try {
                    // Validate that the post exists and user is active
                    if (!$scheduledPost->post) {
                        $this->warn("Skipping scheduled post {$scheduledPost->id}: No associated post found");
                        $scheduledPost->update([
                            'status' => 'failed',
                            'error_message' => 'Associated post not found'
                        ]);
                        $failedCount++;
                        $bar->advance();
                        continue;
                    }

                    if (!$scheduledPost->user) {
                        $this->warn("Skipping scheduled post {$scheduledPost->id}: No associated user found");
                        $scheduledPost->update([
                            'status' => 'failed',
                            'error_message' => 'Associated user not found'
                        ]);
                        $failedCount++;
                        $bar->advance();
                        continue;
                    }

                    // Dispatch the job to publish the post
                    PublishScheduledPostJob::dispatch($scheduledPost);
                    
                    $publishedCount++;
                    $this->line(" Dispatched job for scheduled post {$scheduledPost->id}");

                } catch (\Exception $e) {
                    $this->error("Failed to dispatch job for scheduled post {$scheduledPost->id}: " . $e->getMessage());
                    
                    $scheduledPost->update([
                        'status' => 'failed',
                        'error_message' => $e->getMessage()
                    ]);
                    
                    $failedCount++;
                }

                $bar->advance();
            }

            $bar->finish();
            $this->newLine();

            $this->info("Successfully dispatched {$publishedCount} posts for publishing.");
            
            if ($failedCount > 0) {
                $this->warn("Failed to process {$failedCount} posts.");
            }

            // Log the summary
            Log::info('Social posts publishing completed', [
                'published' => $publishedCount,
                'failed' => $failedCount,
                'total' => $scheduledPosts->count(),
            ]);

            return self::SUCCESS;

        } catch (\Exception $e) {
            $this->error("An error occurred while publishing scheduled posts: " . $e->getMessage());
            Log::error('Social posts publishing command failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return self::FAILURE;
        }
    }
}
