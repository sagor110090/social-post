<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Post;
use App\Models\SocialAccount;
use App\Models\PostAnalytics;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        // Get basic stats
        $stats = [
            'total_posts' => $user->posts()->count(),
            'published_posts' => $user->posts()->where('status', 'published')->count(),
            'scheduled_posts' => $user->posts()->where('status', 'scheduled')->count(),
            'draft_posts' => $user->posts()->where('status', 'draft')->count(),
            'connected_accounts' => $user->socialAccounts()->where('is_active', true)->count(),
            'total_engagement' => $user->posts()
                ->whereHas('analytics')
                ->with('analytics')
                ->get()
                ->sum(function ($post) {
                    return $post->analytics->sum('engagement');
                }),
        ];

        // Get recent posts with analytics
        $recentPosts = $user->posts()
            ->with(['analytics', 'scheduledPosts.socialAccount'])
            ->latest()
            ->take(5)
            ->get()
            ->map(function ($post) {
                return [
                    'id' => $post->id,
                    'content' => $post->getExcerpt(100),
                    'status' => $post->status,
                    'platforms' => $post->platforms,
                    'created_at' => $post->created_at->toISOString(),
                    'scheduled_for' => $post->scheduledPosts->first()?->scheduled_for?->toISOString(),
                    'engagement' => $post->analytics->sum('engagement'),
                    'reach' => $post->analytics->sum('reach'),
                ];
            });

        // Get connected accounts
        $connectedAccounts = $user->socialAccounts()
            ->where('is_active', true)
            ->get()
            ->map(function ($account) {
                return [
                    'id' => $account->id,
                    'platform' => $account->platform,
                    'username' => $account->username,
                    'platform_display_name' => $account->getPlatformDisplayName(),
                    'is_token_expired' => $account->isTokenExpired(),
                    'token_expires_at' => $account->token_expires_at?->toISOString(),
                ];
            });

        // Get upcoming scheduled posts
        $upcomingPosts = $user->posts()
            ->where('status', 'scheduled')
            ->whereHas('scheduledPosts', function ($query) {
                $query->where('scheduled_for', '>', now())
                       ->where('status', 'pending')
                       ->orderBy('scheduled_for', 'asc')
                       ->take(3);
            })
            ->with(['scheduledPosts.socialAccount'])
            ->get()
            ->map(function ($post) {
                $nextScheduled = $post->scheduledPosts
                    ->where('status', 'pending')
                    ->sortBy('scheduled_for')
                    ->first();

                return [
                    'id' => $post->id,
                    'content' => $post->getExcerpt(80),
                    'platform' => $nextScheduled?->socialAccount?->platform,
                    'scheduled_for' => $nextScheduled?->scheduled_for?->toISOString(),
                    'account_username' => $nextScheduled?->socialAccount?->username,
                ];
            });

        // Get analytics summary (last 30 days)
        $analyticsSummary = $this->getAnalyticsSummary($user);

        // Quick actions based on user's state
        $quickActions = $this->getQuickActions($user);

        return Inertia::render('Dashboard', [
            'stats' => $stats,
            'recent_posts' => $recentPosts,
            'connected_accounts' => $connectedAccounts,
            'upcoming_posts' => $upcomingPosts,
            'analytics_summary' => $analyticsSummary,

            'quick_actions' => $quickActions,
        ]);
    }

    private function getAnalyticsSummary($user): array
    {
        $thirtyDaysAgo = now()->subDays(30);

        $analytics = $user->posts()
            ->whereHas('analytics', function ($query) use ($thirtyDaysAgo) {
                $query->where('recorded_at', '>=', $thirtyDaysAgo);
            })
            ->with('analytics')
            ->get();

        return [
            'total_engagement' => $analytics->sum(function ($post) {
                return $post->analytics->sum('engagement');
            }),
            'total_reach' => $analytics->sum(function ($post) {
                return $post->analytics->sum('reach');
            }),
            'total_likes' => $analytics->sum(function ($post) {
                return $post->analytics->sum('likes');
            }),
            'total_comments' => $analytics->sum(function ($post) {
                return $post->analytics->sum('comments');
            }),
            'total_shares' => $analytics->sum(function ($post) {
                return $post->analytics->sum('shares');
            }),
            'posts_with_analytics' => $analytics->count(),
        ];
    }

    private function getQuickActions($user): array
    {
        $actions = [];

        // Always available actions
        $actions[] = [
            'title' => 'Create Post',
            'description' => 'Compose a new social media post',
            'href' => route('social.posts.create'),
            'icon' => 'FileText',
            'color' => 'blue',
        ];

        // Check if user can connect more accounts
        if (true) { // Allow all users to connect accounts
            $actions[] = [
                'title' => 'Connect Account',
                'description' => 'Add a new social media account',
                'href' => route('social.accounts'),
                'icon' => 'Users',
                'color' => 'green',
            ];
        }

        // AI Generator (available to all users)
        if (true) {
            $actions[] = [
                'title' => 'AI Generator',
                'description' => 'Generate content with AI',
                'href' => route('ai.generator'),
                'icon' => 'Wand2',
                'color' => 'purple',
            ];
        }

        // Calendar
        $actions[] = [
            'title' => 'View Calendar',
            'description' => 'Manage your posting schedule',
            'href' => route('social.scheduled-posts.calendar'),
            'icon' => 'Calendar',
            'color' => 'orange',
        ];

        // Analytics (available to all users)
        if (true) {
            $actions[] = [
                'title' => 'Analytics',
                'description' => 'View performance metrics',
                'href' => route('analytics.dashboard'),
                'icon' => 'BarChart3',
                'color' => 'indigo',
            ];
        }



        return $actions;
    }
}
