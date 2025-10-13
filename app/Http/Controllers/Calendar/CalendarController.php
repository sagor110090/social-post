<?php

namespace App\Http\Controllers\Calendar;

use App\Http\Controllers\Controller;
use App\Models\Post;
use App\Models\ScheduledPost;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;

class CalendarController extends Controller
{
    public function index()
    {
        return Inertia::render('Calendar');
    }

    public function events(Request $request): JsonResponse
    {
        $user = $request->user();
        
        $posts = ScheduledPost::with(['post', 'socialAccount'])
            ->where('user_id', $user->id)
            ->where('status', '!=', 'draft')
            ->where(function ($query) {
                $query->where('scheduled_for', '>=', now()->subMonths(3))
                      ->orWhere('scheduled_until', '>=', now()->subMonths(3));
            })
            ->get()
            ->map(function ($scheduledPost) {
                return [
                    'id' => $scheduledPost->id,
                    'title' => $scheduledPost->post->content,
                    'content' => $scheduledPost->post->content,
                    'start' => $scheduledPost->scheduled_for->toISOString(),
                    'end' => $scheduledPost->scheduled_until?->toISOString(),
                    'platform' => $scheduledPost->socialAccount->platform,
                    'status' => $scheduledPost->status,
                    'post_id' => $scheduledPost->post->id,
                    'scheduled_post_id' => $scheduledPost->id,
                ];
            });

        return response()->json($posts);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'start' => 'required|date',
            'end' => 'nullable|date|after:start',
            'post_id' => 'nullable|exists:posts,id',
            'social_account_id' => 'nullable|exists:social_accounts,id',
        ]);

        $user = $request->user();

        // If no post_id provided, create a new post
        if (!$request->post_id) {
            $post = Post::create([
                'user_id' => $user->id,
                'content' => $request->title,
                'type' => 'text',
                'status' => 'draft',
            ]);
        } else {
            $post = Post::findOrFail($request->post_id);
        }

        $scheduledPost = ScheduledPost::create([
            'user_id' => $user->id,
            'post_id' => $post->id,
            'social_account_id' => $request->social_account_id,
            'scheduled_for' => $request->start,
            'scheduled_until' => $request->end,
            'status' => 'pending',
        ]);

        return response()->json([
            'id' => $scheduledPost->id,
            'title' => $post->content,
            'content' => $post->content,
            'start' => $scheduledPost->scheduled_for->toISOString(),
            'end' => $scheduledPost->scheduled_until?->toISOString(),
            'platform' => $scheduledPost->socialAccount?->platform,
            'status' => $scheduledPost->status,
            'post_id' => $post->id,
            'scheduled_post_id' => $scheduledPost->id,
        ]);
    }

    public function update(Request $request, ScheduledPost $scheduledPost): JsonResponse
    {
        $this->authorize('update', $scheduledPost);

        $request->validate([
            'start' => 'required|date',
            'end' => 'nullable|date|after:start',
            'title' => 'nullable|string|max:255',
        ]);

        $scheduledPost->update([
            'scheduled_for' => $request->start,
            'scheduled_until' => $request->end,
        ]);

        if ($request->title && $scheduledPost->post) {
            $scheduledPost->post->update([
                'content' => $request->title,
            ]);
        }

        return response()->json([
            'id' => $scheduledPost->id,
            'title' => $scheduledPost->post->content,
            'content' => $scheduledPost->post->content,
            'start' => $scheduledPost->scheduled_for->toISOString(),
            'end' => $scheduledPost->scheduled_until?->toISOString(),
            'platform' => $scheduledPost->socialAccount?->platform,
            'status' => $scheduledPost->status,
            'post_id' => $scheduledPost->post->id,
            'scheduled_post_id' => $scheduledPost->id,
        ]);
    }

    public function destroy(ScheduledPost $scheduledPost): JsonResponse
    {
        $this->authorize('delete', $scheduledPost);

        $scheduledPost->delete();

        return response()->json(['message' => 'Event deleted successfully']);
    }
}