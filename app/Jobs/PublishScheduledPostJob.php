<?php

namespace App\Jobs;

use App\Models\Post;
use App\Models\ScheduledPost;
use App\Services\Social\SocialPostService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class PublishScheduledPostJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The number of seconds to wait before retrying the job.
     */
    public int $retryAfter = 60;

    /**
     * The maximum number of unhandled exceptions to allow before failing.
     */
    public int $maxExceptions = 3;

    /**
     * The number of seconds the job can run before timing out.
     */
    public int $timeout = 120;

    /**
     * Create a new job instance.
     */
    public function __construct(
        private ScheduledPost $scheduledPost
    ) {
        $this->onQueue('social-publishing');
    }

    /**
     * Execute the job.
     */
    public function handle(SocialPostService $socialPostService): void
    {
        try {
            // Refresh the scheduled post to get the latest data
            $this->scheduledPost->refresh();

            // Check if the scheduled post is still pending
            if ($this->scheduledPost->status !== 'pending') {
                Log::info("Scheduled post {$this->scheduledPost->id} is not pending, skipping");
                return;
            }

            // Check if the scheduled time has passed (with a 5-minute grace period)
            if ($this->scheduledPost->scheduled_at->isFuture()) {
                Log::info("Scheduled post {$this->scheduledPost->id} is still in the future, rescheduling");
                $this->release(300); // Release for 5 minutes
                return;
            }

            // Get the associated post
            $post = $this->scheduledPost->post;
            if (!$post) {
                Log::error("Post not found for scheduled post {$this->scheduledPost->id}");
                $this->markAsFailed('Post not found');
                return;
            }

            // Update status to processing
            $this->scheduledPost->update(['status' => 'processing']);

            // Publish to all platforms
            $results = $socialPostService->postToPlatforms($post, $this->scheduledPost->platforms);

            // Process results
            $allSuccessful = collect($results)->every(fn($result) => $result['success']);
            $anySuccessful = collect($results)->contains('success', true);

            // Store platform-specific results
            $platformResults = [];
            foreach ($results as $platform => $result) {
                if ($result['success']) {
                    $platformResults[$platform] = [
                        'platform_post_id' => $result['platform_post_id'],
                        'url' => $result['url'] ?? null,
                        'published_at' => now(),
                    ];
                }
            }

            // Update post with results
            $post->update(['platform_results' => $platformResults]);

            // Update post status
            if ($allSuccessful) {
                $post->update(['status' => 'published']);
                $this->scheduledPost->update([
                    'status' => 'completed',
                    'completed_at' => now(),
                    'results' => $results
                ]);
                
                Log::info("Successfully published scheduled post {$this->scheduledPost->id} to all platforms");
            } elseif ($anySuccessful) {
                $post->update(['status' => 'partially_published']);
                $this->scheduledPost->update([
                    'status' => 'partially_completed',
                    'completed_at' => now(),
                    'results' => $results
                ]);
                
                Log::warning("Partially published scheduled post {$this->scheduledPost->id}", $results);
            } else {
                $post->update(['status' => 'failed']);
                $this->markAsFailed('All platforms failed', $results);
            }

        } catch (\Exception $e) {
            Log::error("Failed to publish scheduled post {$this->scheduledPost->id}: " . $e->getMessage(), [
                'exception' => $e,
                'scheduled_post_id' => $this->scheduledPost->id,
                'post_id' => $this->scheduledPost->post_id,
            ]);

            $this->markAsFailed($e->getMessage());
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error("PublishScheduledPostJob failed for scheduled post {$this->scheduledPost->id}: " . $exception->getMessage());

        $this->markAsFailed($exception->getMessage());
    }

    /**
     * Mark the scheduled post as failed.
     */
    private function markAsFailed(string $error, array $results = []): void
    {
        $this->scheduledPost->update([
            'status' => 'failed',
            'failed_at' => now(),
            'error_message' => $error,
            'results' => $results,
        ]);

        // Also update the post status
        if ($post = $this->scheduledPost->post) {
            $post->update(['status' => 'failed']);
        }
    }

    /**
     * Get the tags that should be assigned to the job.
     */
    public function tags(): array
    {
        return [
            'social-publishing',
            'scheduled-post:' . $this->scheduledPost->id,
            'user:' . $this->scheduledPost->user_id,
            'platforms:' . implode(',', $this->scheduledPost->platforms),
        ];
    }
}
