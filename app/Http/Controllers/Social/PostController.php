<?php

namespace App\Http\Controllers\Social;

use App\Http\Controllers\Controller;
use App\Services\Social\SocialPostService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class PostController extends Controller
{
    public function __construct(private SocialPostService $socialPostService)
    {
    }

    /**
     * Display the post creation page.
     */
    public function create(Request $request)
    {
        try {
            $user = Auth::user();
            
            if (!$user) {
                return redirect()->route('login');
            }
            
            $availablePlatforms = $this->socialPostService->getAvailablePlatforms($user);
            $characterLimits = $this->socialPostService->getCharacterLimits();

            return Inertia::render('Social/Create', [
                'availablePlatforms' => $availablePlatforms,
                'characterLimits' => $characterLimits,
                'flash' => [
                    'success' => $request->session()->get('success'),
                    'error' => $request->session()->get('error'),
                ]
            ]);
        } catch (\Exception $e) {
            // Log the error for debugging
            \Log::error('Error in PostController::create: ' . $e->getMessage(), [
                'exception' => $e,
                'user_id' => Auth::id(),
            ]);
            
            // Return a proper Inertia response with error
            return Inertia::render('Social/Create', [
                'availablePlatforms' => [],
                'characterLimits' => [],
                'flash' => [
                    'error' => 'An error occurred while loading the page. Please try again.',
                ]
            ]);
        }
    }

    /**
     * Display the post history page.
     */
    public function history(Request $request)
    {
        $user = Auth::user();
        
        $query = $user->posts()->with('scheduledPost');

        // Apply filters
        if ($request->get('status')) {
            $query->where('status', $request->get('status'));
        }

        if ($request->get('platform')) {
            $query->whereJsonContains('platforms', $request->get('platform'));
        }

        // Pagination
        $limit = (int) $request->get('limit', 20);
        $offset = (int) $request->get('offset', 0);

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

        return inertia('Social/History', [
            'posts' => [
                'posts' => $posts,
                'pagination' => [
                    'total' => $total,
                    'limit' => $limit,
                    'offset' => $offset,
                    'has_more' => $offset + $limit < $total,
                ]
            ],
            'filters' => [
                'status' => $request->get('status'),
                'platform' => $request->get('platform'),
                'limit' => $limit,
                'offset' => $offset,
            ],
            'flash' => [
                'success' => session('success'),
                'error' => session('error'),
            ]
        ]);
    }

    /**
     * Display a single post.
     */
    public function show(Request $request, $postId)
    {
        $user = Auth::user();
        
        $post = $user->posts()
            ->with('scheduledPost')
            ->findOrFail($postId);

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