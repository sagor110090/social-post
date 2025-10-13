<?php

namespace App\Services\Scheduling;

use App\Jobs\PublishScheduledPostJob;
use App\Models\Post;
use App\Models\ScheduledPost;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class ScheduledPostService
{
    /**
     * Schedule a post for publishing.
     */
    public function schedulePost(Post $post, array $platforms, Carbon $scheduledAt): ScheduledPost
    {
        // Validate that the scheduled time is in the future
        if ($scheduledAt->isPast()) {
            throw new \InvalidArgumentException('Scheduled time must be in the future');
        }

        // Don't schedule more than 1 year in advance
        if ($scheduledAt->gt(now()->addYear())) {
            throw new \InvalidArgumentException('Cannot schedule posts more than 1 year in advance');
        }

        // Check for duplicate schedules
        $existingSchedule = ScheduledPost::where('post_id', $post->id)
            ->where('status', 'pending')
            ->where('scheduled_at', $scheduledAt)
            ->first();

        if ($existingSchedule) {
            throw new \InvalidArgumentException('This post is already scheduled for this time');
        }

        // Create the scheduled post
        $scheduledPost = ScheduledPost::create([
            'post_id' => $post->id,
            'user_id' => $post->user_id,
            'platforms' => $platforms,
            'scheduled_at' => $scheduledAt,
            'status' => 'pending',
        ]);

        // Update the post status
        $post->update(['status' => 'scheduled']);

        Log::info("Post scheduled successfully", [
            'post_id' => $post->id,
            'scheduled_post_id' => $scheduledPost->id,
            'scheduled_at' => $scheduledAt->toISOString(),
            'platforms' => $platforms,
        ]);

        return $scheduledPost;
    }

    /**
     * Reschedule a post.
     */
    public function reschedulePost(ScheduledPost $scheduledPost, Carbon $newScheduledAt): ScheduledPost
    {
        // Can only reschedule pending posts
        if ($scheduledPost->status !== 'pending') {
            throw new \InvalidArgumentException('Can only reschedule posts with pending status');
        }

        // Validate the new time
        if ($newScheduledAt->isPast()) {
            throw new \InvalidArgumentException('Scheduled time must be in the future');
        }

        if ($newScheduledAt->gt(now()->addYear())) {
            throw new \InvalidArgumentException('Cannot schedule posts more than 1 year in advance');
        }

        // Update the scheduled time
        $scheduledPost->update(['scheduled_at' => $newScheduledAt]);

        Log::info("Post rescheduled successfully", [
            'scheduled_post_id' => $scheduledPost->id,
            'old_scheduled_at' => $scheduledPost->getOriginal('scheduled_at'),
            'new_scheduled_at' => $newScheduledAt->toISOString(),
        ]);

        return $scheduledPost->refresh();
    }

    /**
     * Cancel a scheduled post.
     */
    public function cancelScheduledPost(ScheduledPost $scheduledPost): bool
    {
        // Can only cancel pending posts
        if ($scheduledPost->status !== 'pending') {
            throw new \InvalidArgumentException('Can only cancel posts with pending status');
        }

        // Update status to cancelled
        $scheduledPost->update(['status' => 'cancelled']);

        // Update the post status back to draft
        if ($post = $scheduledPost->post) {
            $post->update(['status' => 'draft']);
        }

        Log::info("Scheduled post cancelled", [
            'scheduled_post_id' => $scheduledPost->id,
            'post_id' => $scheduledPost->post_id,
        ]);

        return true;
    }

    /**
     * Get upcoming scheduled posts for a user.
     */
    public function getUpcomingPosts(User $user, int $limit = 10): \Illuminate\Database\Eloquent\Collection
    {
        return ScheduledPost::where('user_id', $user->id)
            ->where('status', 'pending')
            ->where('scheduled_at', '>', now())
            ->with(['post'])
            ->orderBy('scheduled_at', 'asc')
            ->limit($limit)
            ->get();
    }

    /**
     * Get scheduled posts for a date range.
     */
    public function getScheduledPostsInRange(
        User $user, 
        Carbon $startDate, 
        Carbon $endDate
    ): \Illuminate\Database\Eloquent\Collection {
        return ScheduledPost::where('user_id', $user->id)
            ->where('status', 'pending')
            ->whereBetween('scheduled_at', [$startDate, $endDate])
            ->with(['post'])
            ->orderBy('scheduled_at', 'asc')
            ->get();
    }

    /**
     * Get scheduled posts for a specific date.
     */
    public function getScheduledPostsForDate(User $user, Carbon $date): \Illuminate\Database\Eloquent\Collection
    {
        return $this->getScheduledPostsInRange(
            $user,
            $date->copy()->startOfDay(),
            $date->copy()->endOfDay()
        );
    }

    /**
     * Get scheduling statistics for a user.
     */
    public function getSchedulingStats(User $user): array
    {
        $now = now();
        
        $total = ScheduledPost::where('user_id', $user->id)->count();
        $pending = ScheduledPost::where('user_id', $user->id)
            ->where('status', 'pending')
            ->count();
        $completed = ScheduledPost::where('user_id', $user->id)
            ->where('status', 'completed')
            ->count();
        $failed = ScheduledPost::where('user_id', $user->id)
            ->where('status', 'failed')
            ->count();
        $cancelled = ScheduledPost::where('user_id', $user->id)
            ->where('status', 'cancelled')
            ->count();

        // Posts scheduled for today
        $today = ScheduledPost::where('user_id', $user->id)
            ->where('status', 'pending')
            ->whereDate('scheduled_at', $now->toDateString())
            ->count();

        // Posts scheduled for this week
        $thisWeek = ScheduledPost::where('user_id', $user->id)
            ->where('status', 'pending')
            ->whereBetween('scheduled_at', [
                $now->copy()->startOfWeek(),
                $now->copy()->endOfWeek()
            ])
            ->count();

        // Posts scheduled for this month
        $thisMonth = ScheduledPost::where('user_id', $user->id)
            ->where('status', 'pending')
            ->whereBetween('scheduled_at', [
                $now->copy()->startOfMonth(),
                $now->copy()->endOfMonth()
            ])
            ->count();

        return [
            'total' => $total,
            'pending' => $pending,
            'completed' => $completed,
            'failed' => $failed,
            'cancelled' => $cancelled,
            'today' => $today,
            'this_week' => $thisWeek,
            'this_month' => $thisMonth,
            'success_rate' => $total > 0 ? round(($completed / $total) * 100, 2) : 0,
        ];
    }

    /**
     * Get overdue scheduled posts that should have been published.
     */
    public function getOverduePosts(): \Illuminate\Database\Eloquent\Collection
    {
        return ScheduledPost::where('status', 'pending')
            ->where('scheduled_at', '<', now()->subMinutes(5)) // 5 minute grace period
            ->with(['post', 'user'])
            ->orderBy('scheduled_at', 'asc')
            ->get();
    }

    /**
     * Publish a scheduled post immediately.
     */
    public function publishNow(ScheduledPost $scheduledPost): bool
    {
        // Can only publish pending posts
        if ($scheduledPost->status !== 'pending') {
            throw new \InvalidArgumentException('Can only publish posts with pending status');
        }

        // Dispatch the job immediately
        PublishScheduledPostJob::dispatch($scheduledPost);

        Log::info("Scheduled post dispatched for immediate publishing", [
            'scheduled_post_id' => $scheduledPost->id,
            'post_id' => $scheduledPost->post_id,
        ]);

        return true;
    }

    /**
     * Get optimal posting times based on historical data.
     */
    public function getOptimalPostingTimes(User $user, string $platform = null): array
    {
        // This is a placeholder for a more sophisticated algorithm
        // In a real implementation, you would analyze historical engagement data
        
        $defaultTimes = [
            'morning' => ['09:00', '10:00', '11:00'],
            'afternoon' => ['14:00', '15:00', '16:00'],
            'evening' => ['18:00', '19:00', '20:00'],
        ];

        // Platform-specific optimal times (simplified)
        $platformTimes = [
            'facebook' => ['09:00', '15:00', '19:00'],
            'instagram' => ['11:00', '14:00', '17:00'],
            'linkedin' => ['08:00', '12:00', '17:00'],
            'twitter' => ['09:00', '13:00', '17:00'],
        ];

        return $platform ? ($platformTimes[$platform] ?? $defaultTimes['morning']) : $defaultTimes;
    }

    /**
     * Validate scheduling constraints for a user's subscription.
     */
    public function validateSchedulingConstraints(User $user): array
    {
        $constraints = [];
        $subscription = $user->subscription;

        if (!$subscription || !$subscription->isActive()) {
            $constraints['max_scheduled_posts'] = 3;
            $constraints['max_schedule_ahead_days'] = 7;
        } else {
            $constraints['max_scheduled_posts'] = match ($subscription->type) {
                'pro' => 50,
                'agency' => 500,
                default => 3,
            };
            $constraints['max_schedule_ahead_days'] = match ($subscription->type) {
                'pro' => 90,
                'agency' => 365,
                default => 7,
            };
        }

        // Check current usage
        $currentScheduled = ScheduledPost::where('user_id', $user->id)
            ->where('status', 'pending')
            ->count();

        $constraints['current_scheduled'] = $currentScheduled;
        $constraints['can_schedule_more'] = $currentScheduled < $constraints['max_scheduled_posts'];
        $constraints['remaining_slots'] = max(0, $constraints['max_scheduled_posts'] - $currentScheduled);

        return $constraints;
    }
}