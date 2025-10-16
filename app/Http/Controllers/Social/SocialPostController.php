<?php

namespace App\Http\Controllers\Social;

use App\Http\Controllers\Controller;
use App\Models\Post;
use App\Models\ScheduledPost;
use App\Services\Scheduling\ScheduledPostService;
use App\Services\Social\SocialPostService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class SocialPostController extends Controller
{
    public function __construct(
        private SocialPostService $socialPostService,
        private ScheduledPostService $scheduledPostService
    ) {
    }

    /**
     * Create and publish a post to social media.
     */
    public function publish(Request $request)
    {
        $request->validate([
            'content' => 'required|string',
            'platforms' => 'required|array|min:1',
            'platforms.*' => 'string|in:facebook,instagram,linkedin,twitter',
            'hashtags' => 'nullable|array',
            'hashtags.*' => 'string',
            'link' => 'nullable|url',
            'image_url' => 'nullable|url',
            'media_urls' => 'nullable|array',
            'media_urls.*' => 'url',
            'schedule_at' => 'nullable|date|after:now',
        ]);

        $user = Auth::user();

        // Check if user has connected the requested platforms
        $availablePlatforms = $this->socialPostService->getAvailablePlatforms($user);
        $requestedPlatforms = $request->platforms;

        $missingPlatforms = array_diff($requestedPlatforms, $availablePlatforms);

        if (!empty($missingPlatforms)) {
            $errorMessage = 'Missing connected accounts for platforms: ' . implode(', ', $missingPlatforms);

            // Check if this is an Inertia request (not an API request)
            if ($request->header('X-Inertia') && !$request->wantsJson()) {
                return redirect()->back()->with('error', $errorMessage);
            }

            // Return JSON for API requests
            return response()->json(['error' => $errorMessage], 400);
        }

        // Validate content for each platform
        $validationErrors = [];
        foreach ($requestedPlatforms as $platform) {
            $validation = $this->socialPostService->validateContent($request->content, $platform, $request->hashtags ?? []);
            if (!$validation['valid']) {
                $validationErrors[$platform] = $validation['errors'];
            }
        }

        if (!empty($validationErrors)) {
            $errorMessage = 'Content validation failed for some platforms';

            // Check if this is an Inertia request (not an API request)
            if ($request->header('X-Inertia') && !$request->wantsJson()) {
                return redirect()->back()
                    ->with('error', $errorMessage)
                    ->with('validation_errors', $validationErrors);
            }

            // Return JSON for API requests
            return response()->json([
                'error' => $errorMessage,
                'validation_errors' => $validationErrors
            ], 400);
        }

        // Create post record
        $post = Post::create([
            'user_id' => $user->id,
            'content' => $request->content,
            'hashtags' => $request->hashtags,
            'link' => $request->link,
            'image_url' => $request->image_url,
            'media_urls' => $request->media_urls,
            'status' => 'draft',
            'platforms' => $requestedPlatforms,
        ]);

        // Handle scheduling
        if ($request->schedule_at) {
            try {
                $scheduledAt = Carbon::parse($request->schedule_at);
                $scheduledPost = $this->scheduledPostService->schedulePost($post, $requestedPlatforms, $scheduledAt);

                $successMessage = 'Post scheduled successfully for ' . $scheduledAt->format('M j, Y \a\t g:i A');

            // Check if this is an Inertia request (not an API request)
            if ($request->header('X-Inertia') && !$request->wantsJson()) {
                return redirect()->route('social.posts.history')->with('success', $successMessage);
            }

            // Return JSON for API requests
            return response()->json([
                'success' => true,
                'message' => $successMessage,
                'post' => $post->load('scheduledPost'),
                'scheduled_post' => [
                    'id' => $scheduledPost->id,
                    'scheduled_at' => $scheduledPost->scheduled_at->toISOString(),
                    'scheduled_at_for_humans' => $scheduledPost->getScheduledAtForHumans(),
                    'time_until' => $scheduledPost->getTimeUntilPublication(),
                ]
            ]);

            } catch (\Exception $e) {
                $errorMessage = 'Failed to schedule post: ' . $e->getMessage();

                // Check if this is an Inertia request (not an API request)
                if ($request->header('X-Inertia') && !$request->wantsJson()) {
                    return redirect()->back()->with('error', $errorMessage);
                }

                // Return JSON for API requests
                return response()->json(['error' => $errorMessage], 400);
            }
        }

        // Publish immediately
        $results = $this->socialPostService->postToPlatforms($post, $requestedPlatforms);

        // Update post status based on results
        $allSuccessful = collect($results)->every(fn($result) => $result['success']);
        $anySuccessful = collect($results)->contains('success', true);

        if ($allSuccessful) {
            $post->update(['status' => 'published']);
        } elseif ($anySuccessful) {
            $post->update(['status' => 'partially_published']);
        } else {
            $post->update(['status' => 'failed']);
        }

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

        $post->update(['platform_results' => $platformResults]);

        $message = $allSuccessful ? 'Post published successfully!' : 'Post published with some errors';

        // Check if this is an Inertia request (not an API request)
        if ($request->header('X-Inertia') && !$request->wantsJson()) {
            return redirect()->route('social.posts.history')
                ->with('success', $message)
                ->with('results', $results);
        }

        // Return JSON for API requests
        return response()->json([
            'success' => true,
            'message' => $message,
            'post' => $post->fresh(),
            'results' => $results
        ]);
    }

    /**
     * Get character limits for platforms.
     */
    public function getCharacterLimits(Request $request)
    {
        $limits = $this->socialPostService->getCharacterLimits();

        return response()->json($limits);
    }

    /**
     * Validate content for platforms.
     */
    public function validateContent(Request $request)
    {
        $request->validate([
            'content' => 'required|string',
            'platforms' => 'required|array',
            'platforms.*' => 'string|in:facebook,instagram,linkedin,twitter',
        ]);

        $results = [];
        foreach ($request->platforms as $platform) {
            $results[$platform] = $this->socialPostService->validateContent($request->content, $platform);
        }

        return response()->json($results);
    }

    /**
     * Get available platforms for the authenticated user.
     */
    public function getAvailablePlatforms(Request $request)
    {
        $user = Auth::user();
        $platforms = $this->socialPostService->getAvailablePlatforms($user);

        return response()->json($platforms);
    }

    /**
     * Get post history.
     */
    public function history(Request $request)
    {
        $request->validate([
            'status' => 'nullable|string|in:draft,scheduled,published,partially_published,failed',
            'platform' => 'nullable|string|in:facebook,instagram,linkedin,twitter',
            'limit' => 'nullable|integer|min:1|max:100',
            'offset' => 'nullable|integer|min:0',
        ]);

        $user = Auth::user();
        $query = $user->posts()->with('scheduledPost');

        // Filter by status
        if ($request->status) {
            $query->where('status', $request->status);
        }

        // Filter by platform
        if ($request->platform) {
            $query->whereJsonContains('platforms', $request->platform);
        }

        // Pagination
        $limit = $request->get('limit', 20);
        $offset = $request->get('offset', 0);

        $posts = $query->orderBy('created_at', 'desc')
            ->offset($offset)
            ->limit($limit)
            ->get()
            ->map(function ($post) {
                return [
                    'id' => $post->id,
                    'content' => $post->content,
                    'link' => $post->link,
                    'image_url' => $post->image_url,
                    'status' => $post->status,
                    'platforms' => $post->platforms,
                    'platform_results' => $post->platform_results,
                    'scheduled_at' => $post->scheduledPost?->scheduled_at,
                    'created_at' => $post->created_at->toISOString(),
                    'updated_at' => $post->updated_at->toISOString(),
                ];
            });

        $total = $query->count();

        return response()->json([
            'posts' => $posts,
            'pagination' => [
                'total' => $total,
                'limit' => $limit,
                'offset' => $offset,
                'has_more' => $offset + $limit < $total,
            ]
        ]);
    }

    /**
     * Delete a post.
     */
    public function delete(Request $request, Post $post)
    {
        if ($post->user_id !== Auth::id()) {
            if ($request->header('X-Inertia') && !$request->wantsJson()) {
                return redirect()->back()->with('error', 'Unauthorized');
            }
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        // Delete scheduled post if exists
        if ($post->scheduledPost) {
            $post->scheduledPost->delete();
        }

        $post->delete();

        $successMessage = 'Post deleted successfully';

        // Check if this is an Inertia request (not an API request)
        if ($request->header('X-Inertia') && !$request->wantsJson()) {
            return redirect()->route('social.posts.history')->with('success', $successMessage);
        }

        // Return JSON for API requests
        return response()->json([
            'success' => true,
            'message' => $successMessage
        ]);
    }

    /**
     * Get post details.
     */
    public function show(Request $request, Post $post)
    {
        if ($post->user_id !== Auth::id()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        return response()->json([
            'id' => $post->id,
            'content' => $post->content,
            'link' => $post->link,
            'image_url' => $post->image_url,
            'status' => $post->status,
            'platforms' => $post->platforms,
            'platform_results' => $post->platform_results,
            'scheduled_at' => $post->scheduledPost?->scheduled_at,
            'created_at' => $post->created_at->toISOString(),
            'updated_at' => $post->updated_at->toISOString(),
        ]);
    }
}
