<?php

namespace App\Services\Analytics;

use App\Models\Post;
use App\Models\PostAnalytics;
use App\Models\SocialAccount;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class AnalyticsService
{
    public function getDashboardAnalytics(User $user, Carbon $startDate = null, Carbon $endDate = null): array
    {
        $startDate = $startDate ?: now()->subDays(30);
        $endDate = $endDate ?: now();

        $posts = Post::where('user_id', $user->id)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->with(['analytics', 'socialAccount'])
            ->get();

        return [
            'total_posts' => $posts->count(),
            'published_posts' => $posts->where('status', 'published')->count(),
            'scheduled_posts' => $posts->where('status', 'scheduled')->count(),
            'total_engagement' => $posts->sum(function ($post) {
                return $post->analytics->sum('likes') + 
                       $post->analytics->sum('comments') + 
                       $post->analytics->sum('shares');
            }),
            'total_reach' => $posts->sum(function ($post) {
                return $post->analytics->sum('reach');
            }),
            'best_performing_post' => $this->getBestPerformingPost($posts),
            'platform_performance' => $this->getPlatformPerformance($posts),
            'engagement_over_time' => $this->getEngagementOverTime($posts, $startDate, $endDate),
            'post_types_performance' => $this->getPostTypesPerformance($posts),
        ];
    }

    public function getPostAnalytics(Post $post): array
    {
        $analytics = $post->analytics()->with('socialAccount')->get();

        return [
            'post' => $post->load('socialAccount'),
            'analytics' => $analytics,
            'total_engagement' => $analytics->sum(function ($analytic) {
                return $analytic->likes + $analytic->comments + $analytic->shares;
            }),
            'total_reach' => $analytics->sum('reach'),
            'engagement_rate' => $this->calculateEngagementRate($analytics),
            'daily_performance' => $this->getDailyPerformance($analytics),
            'platform_comparison' => $this->getPostPlatformComparison($analytics),
        ];
    }

    public function getAccountAnalytics(SocialAccount $account, Carbon $startDate = null, Carbon $endDate = null): array
    {
        $startDate = $startDate ?: now()->subDays(30);
        $endDate = $endDate ?: now();

        $posts = Post::where('social_account_id', $account->id)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->with('analytics')
            ->get();

        return [
            'account' => $account,
            'total_posts' => $posts->count(),
            'total_engagement' => $posts->sum(function ($post) {
                return $post->analytics->sum('likes') + 
                       $post->analytics->sum('comments') + 
                       $post->analytics->sum('shares');
            }),
            'total_reach' => $posts->sum(function ($post) {
                return $post->analytics->sum('reach');
            }),
            'average_engagement_rate' => $this->calculateAverageEngagementRate($posts),
            'growth_metrics' => $this->getAccountGrowthMetrics($account, $startDate, $endDate),
            'best_posting_times' => $this->getBestPostingTimes($posts),
            'content_performance' => $this->getContentPerformance($posts),
        ];
    }

    public function getTeamAnalytics(User $user, Carbon $startDate = null, Carbon $endDate = null): array
    {
        $startDate = $startDate ?: now()->subDays(30);
        $endDate = $endDate ?: now();

        $teamMembers = $user->teams()->with('users')->get()->pluck('users')->flatten()->unique('id');
        
        $teamAnalytics = $teamMembers->map(function ($member) use ($startDate, $endDate) {
            return [
                'user' => $member,
                'analytics' => $this->getDashboardAnalytics($member, $startDate, $endDate),
            ];
        });

        return [
            'team_members' => $teamAnalytics,
            'total_team_posts' => $teamAnalytics->sum('analytics.total_posts'),
            'total_team_engagement' => $teamAnalytics->sum('analytics.total_engagement'),
            'top_performer' => $teamAnalytics->sortByDesc('analytics.total_engagement')->first(),
            'collaboration_metrics' => $this->getCollaborationMetrics($user, $startDate, $endDate),
        ];
    }

    private function getBestPerformingPost(Collection $posts): ?array
    {
        $bestPost = $posts->sortByDesc(function ($post) {
            return $post->analytics->sum('likes') + 
                   $post->analytics->sum('comments') + 
                   $post->analytics->sum('shares');
        })->first();

        if (!$bestPost) {
            return null;
        }

        return [
            'id' => $bestPost->id,
            'content' => substr($bestPost->content, 0, 100) . '...',
            'total_engagement' => $bestPost->analytics->sum(function ($analytic) {
                return $analytic->likes + $analytic->comments + $analytic->shares;
            }),
            'platform' => $bestPost->socialAccount->platform,
            'created_at' => $bestPost->created_at,
        ];
    }

    private function getPlatformPerformance(Collection $posts): array
    {
        $platforms = ['facebook', 'twitter', 'instagram', 'linkedin'];
        $performance = [];

        foreach ($platforms as $platform) {
            $platformPosts = $posts->filter(function ($post) use ($platform) {
                return $post->socialAccount->platform === $platform;
            });

            $performance[$platform] = [
                'posts_count' => $platformPosts->count(),
                'total_engagement' => $platformPosts->sum(function ($post) {
                    return $post->analytics->sum('likes') + 
                           $post->analytics->sum('comments') + 
                           $post->analytics->sum('shares');
                }),
                'total_reach' => $platformPosts->sum(function ($post) {
                    return $post->analytics->sum('reach');
                }),
                'average_engagement' => $platformPosts->count() > 0 ? 
                    $platformPosts->sum(function ($post) {
                        return $post->analytics->sum('likes') + 
                               $post->analytics->sum('comments') + 
                               $post->analytics->sum('shares');
                    }) / $platformPosts->count() : 0,
            ];
        }

        return $performance;
    }

    private function getEngagementOverTime(Collection $posts, Carbon $startDate, Carbon $endDate): array
    {
        $period = $startDate->diffInDays($endDate) > 30 ? 'weeks' : 'days';
        $data = [];

        $current = $startDate->copy();
        while ($current <= $endDate) {
            $periodEnd = $period === 'weeks' ? $current->copy()->endOfWeek() : $current->copy()->endOfDay();
            
            $periodPosts = $posts->filter(function ($post) use ($current, $periodEnd) {
                return $post->created_at->between($current, $periodEnd);
            });

            $data[] = [
                'period' => $current->format($period === 'weeks' ? 'Y-m-d' : 'Y-m-d'),
                'engagement' => $periodPosts->sum(function ($post) {
                    return $post->analytics->sum('likes') + 
                           $post->analytics->sum('comments') + 
                           $post->analytics->sum('shares');
                }),
                'posts' => $periodPosts->count(),
            ];

            $current = $period === 'weeks' ? $current->copy()->addWeek() : $current->copy()->addDay();
        }

        return $data;
    }

    private function getPostTypesPerformance(Collection $posts): array
    {
        $types = ['text', 'image', 'video', 'link'];
        $performance = [];

        foreach ($types as $type) {
            $typePosts = $posts->where('type', $type);

            $performance[$type] = [
                'count' => $typePosts->count(),
                'total_engagement' => $typePosts->sum(function ($post) {
                    return $post->analytics->sum('likes') + 
                           $post->analytics->sum('comments') + 
                           $post->analytics->sum('shares');
                }),
                'average_engagement' => $typePosts->count() > 0 ? 
                    $typePosts->sum(function ($post) {
                        return $post->analytics->sum('likes') + 
                               $post->analytics->sum('comments') + 
                               $post->analytics->sum('shares');
                    }) / $typePosts->count() : 0,
            ];
        }

        return $performance;
    }

    private function calculateEngagementRate(Collection $analytics): float
    {
        $totalEngagement = $analytics->sum(function ($analytic) {
            return $analytic->likes + $analytic->comments + $analytic->shares;
        });
        $totalReach = $analytics->sum('reach');

        return $totalReach > 0 ? ($totalEngagement / $totalReach) * 100 : 0;
    }

    private function getDailyPerformance(Collection $analytics): array
    {
        return $analytics->groupBy(function ($analytic) {
            return $analytic->created_at->format('Y-m-d');
        })->map(function ($dayAnalytics) {
            return [
                'date' => $dayAnalytics->first()->created_at->format('Y-m-d'),
                'likes' => $dayAnalytics->sum('likes'),
                'comments' => $dayAnalytics->sum('comments'),
                'shares' => $dayAnalytics->sum('shares'),
                'reach' => $dayAnalytics->sum('reach'),
            ];
        })->values()->toArray();
    }

    private function getPostPlatformComparison(Collection $analytics): array
    {
        return $analytics->groupBy('socialAccount.platform')->map(function ($platformAnalytics) {
            return [
                'platform' => $platformAnalytics->first()->socialAccount->platform,
                'likes' => $platformAnalytics->sum('likes'),
                'comments' => $platformAnalytics->sum('comments'),
                'shares' => $platformAnalytics->sum('shares'),
                'reach' => $platformAnalytics->sum('reach'),
            ];
        })->values()->toArray();
    }

    private function calculateAverageEngagementRate(Collection $posts): float
    {
        $totalEngagement = 0;
        $totalReach = 0;

        foreach ($posts as $post) {
            $postEngagement = $post->analytics->sum(function ($analytic) {
                return $analytic->likes + $analytic->comments + $analytic->shares;
            });
            $postReach = $post->analytics->sum('reach');

            $totalEngagement += $postEngagement;
            $totalReach += $postReach;
        }

        return $totalReach > 0 ? ($totalEngagement / $totalReach) * 100 : 0;
    }

    private function getAccountGrowthMetrics(SocialAccount $account, Carbon $startDate, Carbon $endDate): array
    {
        // This would typically involve API calls to get follower growth
        // For now, return mock data
        return [
            'follower_growth' => rand(100, 1000),
            'engagement_growth' => rand(5, 25),
            'reach_growth' => rand(500, 5000),
        ];
    }

    private function getBestPostingTimes(Collection $posts): array
    {
        $hourlyPerformance = [];

        for ($hour = 0; $hour < 24; $hour++) {
            $hourPosts = $posts->filter(function ($post) use ($hour) {
                return $post->created_at->hour === $hour;
            });

            $hourlyPerformance[$hour] = [
                'hour' => $hour,
                'posts' => $hourPosts->count(),
                'avg_engagement' => $hourPosts->count() > 0 ? 
                    $hourPosts->sum(function ($post) {
                        return $post->analytics->sum('likes') + 
                               $post->analytics->sum('comments') + 
                               $post->analytics->sum('shares');
                    }) / $hourPosts->count() : 0,
            ];
        }

        return collect($hourlyPerformance)
            ->sortByDesc('avg_engagement')
            ->take(5)
            ->values()
            ->toArray();
    }

    private function getContentPerformance(Collection $posts): array
    {
        return [
            'content_types' => $this->getPostTypesPerformance($posts),
            'content_length_analysis' => $this->getContentLengthAnalysis($posts),
            'hashtag_performance' => $this->getHashtagPerformance($posts),
        ];
    }

    private function getContentLengthAnalysis(Collection $posts): array
    {
        $lengths = ['short' => 0, 'medium' => 0, 'long' => 0];
        $engagement = ['short' => 0, 'medium' => 0, 'long' => 0];

        foreach ($posts as $post) {
            $length = strlen($post->content);
            $postEngagement = $post->analytics->sum(function ($analytic) {
                return $analytic->likes + $analytic->comments + $analytic->shares;
            });

            if ($length < 100) {
                $lengths['short']++;
                $engagement['short'] += $postEngagement;
            } elseif ($length < 300) {
                $lengths['medium']++;
                $engagement['medium'] += $postEngagement;
            } else {
                $lengths['long']++;
                $engagement['long'] += $postEngagement;
            }
        }

        return [
            'short' => [
                'count' => $lengths['short'],
                'avg_engagement' => $lengths['short'] > 0 ? $engagement['short'] / $lengths['short'] : 0,
            ],
            'medium' => [
                'count' => $lengths['medium'],
                'avg_engagement' => $lengths['medium'] > 0 ? $engagement['medium'] / $lengths['medium'] : 0,
            ],
            'long' => [
                'count' => $lengths['long'],
                'avg_engagement' => $lengths['long'] > 0 ? $engagement['long'] / $lengths['long'] : 0,
            ],
        ];
    }

    private function getHashtagPerformance(Collection $posts): array
    {
        // This would involve parsing hashtags from content and tracking their performance
        // For now, return mock data
        return [
            'top_hashtags' => [
                ['hashtag' => '#marketing', 'engagement' => 1500],
                ['hashtag' => '#socialmedia', 'engagement' => 1200],
                ['hashtag' => '#digital', 'engagement' => 800],
            ],
        ];
    }

    private function getCollaborationMetrics(User $user, Carbon $startDate, Carbon $endDate): array
    {
        // This would track team collaboration metrics
        return [
            'collaborative_posts' => rand(10, 50),
            'team_engagement' => rand(500, 2000),
            'cross_platform_posts' => rand(20, 100),
        ];
    }
}