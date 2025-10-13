<?php

namespace App\Http\Controllers\Analytics;

use App\Http\Controllers\Controller;
use App\Http\Middleware\CheckSubscription;
use App\Models\Post;
use App\Models\SocialAccount;
use App\Services\Analytics\AnalyticsService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;

class AnalyticsController extends Controller
{
    public function __construct(private AnalyticsService $analyticsService)
    {
        $this->middleware(CheckSubscription::class . ':analytics');
    }

    public function index(Request $request)
    {
        $user = $request->user();
        $startDate = $request->get('start_date') ? Carbon::parse($request->get('start_date')) : now()->subDays(30);
        $endDate = $request->get('end_date') ? Carbon::parse($request->get('end_date')) : now();

        $analytics = $this->analyticsService->getDashboardAnalytics($user, $startDate, $endDate);

        return Inertia::render('Analytics/Dashboard', [
            'analytics' => $analytics,
            'date_range' => [
                'start_date' => $startDate->format('Y-m-d'),
                'end_date' => $endDate->format('Y-m-d'),
            ],
        ]);
    }

    public function posts(Request $request): JsonResponse
    {
        $user = $request->user();
        $startDate = $request->get('start_date') ? Carbon::parse($request->get('start_date')) : now()->subDays(30);
        $endDate = $request->get('end_date') ? Carbon::parse($request->get('end_date')) : now();

        $posts = Post::where('user_id', $user->id)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->with(['analytics', 'socialAccount'])
            ->get()
            ->map(function ($post) {
                return [
                    'id' => $post->id,
                    'content' => $post->content,
                    'type' => $post->type,
                    'status' => $post->status,
                    'platform' => $post->socialAccount->platform,
                    'created_at' => $post->created_at->toISOString(),
                    'engagement' => $post->analytics->sum(function ($analytic) {
                        return $analytic->likes + $analytic->comments + $analytic->shares;
                    }),
                    'reach' => $post->analytics->sum('reach'),
                    'likes' => $post->analytics->sum('likes'),
                    'comments' => $post->analytics->sum('comments'),
                    'shares' => $post->analytics->sum('shares'),
                ];
            });

        return response()->json($posts);
    }

    public function post(Post $post): JsonResponse
    {
        $this->authorize('view', $post);

        $analytics = $this->analyticsService->getPostAnalytics($post);

        return response()->json($analytics);
    }

    public function accounts(Request $request): JsonResponse
    {
        $user = $request->user();
        $startDate = $request->get('start_date') ? Carbon::parse($request->get('start_date')) : now()->subDays(30);
        $endDate = $request->get('end_date') ? Carbon::parse($request->get('end_date')) : now();

        $accounts = SocialAccount::where('user_id', $user->id)
            ->where('is_active', true)
            ->get()
            ->map(function ($account) use ($startDate, $endDate) {
                $analytics = $this->analyticsService->getAccountAnalytics($account, $startDate, $endDate);
                
                return [
                    'id' => $account->id,
                    'platform' => $account->platform,
                    'username' => $account->username,
                    'analytics' => $analytics,
                ];
            });

        return response()->json($accounts);
    }

    public function account(SocialAccount $account, Request $request): JsonResponse
    {
        $this->authorize('view', $account);

        $startDate = $request->get('start_date') ? Carbon::parse($request->get('start_date')) : now()->subDays(30);
        $endDate = $request->get('end_date') ? Carbon::parse($request->get('end_date')) : now();

        $analytics = $this->analyticsService->getAccountAnalytics($account, $startDate, $endDate);

        return response()->json($analytics);
    }

    public function engagement(Request $request): JsonResponse
    {
        $user = $request->user();
        $startDate = $request->get('start_date') ? Carbon::parse($request->get('start_date')) : now()->subDays(30);
        $endDate = $request->get('end_date') ? Carbon::parse($request->get('end_date')) : now();

        $analytics = $this->analyticsService->getDashboardAnalytics($user, $startDate, $endDate);

        return response()->json([
            'engagement_over_time' => $analytics['engagement_over_time'],
            'platform_performance' => $analytics['platform_performance'],
            'post_types_performance' => $analytics['post_types_performance'],
        ]);
    }

    public function team(Request $request): JsonResponse
    {
        $user = $request->user();
        $startDate = $request->get('start_date') ? Carbon::parse($request->get('start_date')) : now()->subDays(30);
        $endDate = $request->get('end_date') ? Carbon::parse($request->get('end_date')) : now();

        if (!$user->canCreateTeams()) {
            return response()->json([
                'error' => 'Team analytics require an Enterprise subscription',
            ], 403);
        }

        $analytics = $this->analyticsService->getTeamAnalytics($user, $startDate, $endDate);

        return response()->json($analytics);
    }

    public function export(Request $request): JsonResponse
    {
        $user = $request->user();
        $startDate = $request->get('start_date') ? Carbon::parse($request->get('start_date')) : now()->subDays(30);
        $endDate = $request->get('end_date') ? Carbon::parse($request->get('end_date')) : now();
        $format = $request->get('format', 'csv');

        $analytics = $this->analyticsService->getDashboardAnalytics($user, $startDate, $endDate);

        // Generate export file (this is a simplified version)
        $filename = "analytics-{$startDate->format('Y-m-d')}-to-{$endDate->format('Y-m-d')}.{$format}";
        
        // In a real implementation, you would generate and store the file
        // then return the download URL

        return response()->json([
            'message' => 'Export generated successfully',
            'filename' => $filename,
            'download_url' => route('analytics.download', ['filename' => $filename]),
        ]);
    }

    public function download(string $filename)
    {
        // In a real implementation, you would serve the generated file
        // For now, return a placeholder response
        return response()->json([
            'message' => 'File download not implemented yet',
            'filename' => $filename,
        ]);
    }
}