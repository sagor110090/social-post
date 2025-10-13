<?php

namespace App\Http\Controllers\Social;

use App\Http\Controllers\Controller;
use App\Models\ScheduledPost;
use App\Services\Scheduling\ScheduledPostService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ScheduledPostController extends Controller
{
    public function __construct(private ScheduledPostService $scheduledPostService)
    {
    }

    /**
     * Get upcoming scheduled posts.
     */
    public function upcoming(Request $request)
    {
        $user = Auth::user();
        $limit = $request->get('limit', 10);

        $posts = $this->scheduledPostService->getUpcomingPosts($user, $limit);

        return response()->json([
            'posts' => $posts->map(function ($post) {
                return [
                    'id' => $post->id,
                    'post_id' => $post->post_id,
                    'content' => $post->post?->content,
                    'platforms' => $post->platforms,
                    'scheduled_at' => $post->scheduled_at->toISOString(),
                    'scheduled_at_for_humans' => $post->getScheduledAtForHumans(),
                    'time_until' => $post->getTimeUntilPublication(),
                    'status' => $post->status,
                    'can_be_rescheduled' => $post->canBeRescheduled(),
                    'can_be_cancelled' => $post->canBeCancelled(),
                    'can_be_published_now' => $post->canBePublishedNow(),
                ];
            })
        ]);
    }

    /**
     * Get scheduled posts for a specific date range.
     */
    public function calendar(Request $request)
    {
        $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);

        $user = Auth::user();
        $startDate = Carbon::parse($request->start_date);
        $endDate = Carbon::parse($request->end_date);

        $posts = $this->scheduledPostService->getScheduledPostsInRange($user, $startDate, $endDate);

        return response()->json([
            'posts' => $posts->map(function ($post) {
                return [
                    'id' => $post->id,
                    'post_id' => $post->post_id,
                    'title' => $post->post?->getExcerpt(50),
                    'content' => $post->post?->content,
                    'platforms' => $post->platforms,
                    'scheduled_at' => $post->scheduled_at->toISOString(),
                    'status' => $post->status,
                    'color' => $this->getStatusColor($post->status),
                ];
            })
        ]);
    }

    /**
     * Reschedule a post.
     */
    public function reschedule(Request $request, ScheduledPost $scheduledPost)
    {
        $this->authorize('update', $scheduledPost);

        $request->validate([
            'scheduled_at' => 'required|date|after:now',
        ]);

        try {
            $newScheduledAt = Carbon::parse($request->scheduled_at);
            $updatedPost = $this->scheduledPostService->reschedulePost($scheduledPost, $newScheduledAt);

            return response()->json([
                'success' => true,
                'message' => 'Post rescheduled successfully',
                'scheduled_post' => [
                    'id' => $updatedPost->id,
                    'scheduled_at' => $updatedPost->scheduled_at->toISOString(),
                    'scheduled_at_for_humans' => $updatedPost->getScheduledAtForHumans(),
                    'time_until' => $updatedPost->getTimeUntilPublication(),
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Cancel a scheduled post.
     */
    public function cancel(Request $request, ScheduledPost $scheduledPost)
    {
        $this->authorize('delete', $scheduledPost);

        try {
            $this->scheduledPostService->cancelScheduledPost($scheduledPost);

            return response()->json([
                'success' => true,
                'message' => 'Scheduled post cancelled successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Publish a scheduled post immediately.
     */
    public function publishNow(Request $request, ScheduledPost $scheduledPost)
    {
        $this->authorize('update', $scheduledPost);

        try {
            $this->scheduledPostService->publishNow($scheduledPost);

            return response()->json([
                'success' => true,
                'message' => 'Post dispatched for immediate publishing'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Get scheduling statistics.
     */
    public function stats(Request $request)
    {
        $user = Auth::user();
        $stats = $this->scheduledPostService->getSchedulingStats($user);
        $constraints = $this->scheduledPostService->validateSchedulingConstraints($user);

        return response()->json([
            'stats' => $stats,
            'constraints' => $constraints,
        ]);
    }

    /**
     * Get optimal posting times.
     */
    public function optimalTimes(Request $request)
    {
        $request->validate([
            'platform' => 'nullable|string|in:facebook,instagram,linkedin,twitter',
        ]);

        $user = Auth::user();
        $platform = $request->get('platform');
        $optimalTimes = $this->scheduledPostService->getOptimalPostingTimes($user, $platform);

        return response()->json([
            'optimal_times' => $optimalTimes,
            'platform' => $platform,
        ]);
    }

    /**
     * Get scheduled post details.
     */
    public function show(Request $request, ScheduledPost $scheduledPost)
    {
        $this->authorize('view', $scheduledPost);

        return response()->json([
            'id' => $scheduledPost->id,
            'post_id' => $scheduledPost->post_id,
            'post' => $scheduledPost->post ? [
                'id' => $scheduledPost->post->id,
                'content' => $scheduledPost->post->content,
                'link' => $scheduledPost->post->link,
                'image_url' => $scheduledPost->post->image_url,
                'media_urls' => $scheduledPost->post->media_urls,
                'platforms' => $scheduledPost->post->platforms,
            ] : null,
            'platforms' => $scheduledPost->platforms,
            'scheduled_at' => $scheduledPost->scheduled_at->toISOString(),
            'scheduled_at_for_humans' => $scheduledPost->getScheduledAtForHumans(),
            'time_until' => $scheduledPost->getTimeUntilPublication(),
            'status' => $scheduledPost->status,
            'error_message' => $scheduledPost->error_message,
            'results' => $scheduledPost->results,
            'completed_at' => $scheduledPost->completed_at?->toISOString(),
            'failed_at' => $scheduledPost->failed_at?->toISOString(),
            'created_at' => $scheduledPost->created_at->toISOString(),
            'can_be_rescheduled' => $scheduledPost->canBeRescheduled(),
            'can_be_cancelled' => $scheduledPost->canBeCancelled(),
            'can_be_published_now' => $scheduledPost->canBePublishedNow(),
        ]);
    }

    /**
     * Get status color for calendar display.
     */
    private function getStatusColor(string $status): string
    {
        return match ($status) {
            'pending' => '#3B82F6', // blue
            'processing' => '#F59E0B', // amber
            'completed' => '#10B981', // green
            'partially_completed' => '#F59E0B', // amber
            'failed' => '#EF4444', // red
            'cancelled' => '#6B7280', // gray
            default => '#6B7280', // gray
        };
    }
}