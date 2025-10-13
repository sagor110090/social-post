<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Post;
use App\Models\SocialAccount;
use App\Models\Subscription;
use App\Models\Team;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;

class AdminController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', 'verified', 'admin']);
    }

    public function dashboard()
    {
        $stats = [
            'total_users' => User::count(),
            'active_users' => User::whereHas('subscription', function ($query) {
                $query->where('stripe_status', 'active');
            })->count(),
            'total_posts' => Post::count(),
            'posts_this_month' => Post::whereMonth('created_at', now()->month)
                ->whereYear('created_at', now()->year)
                ->count(),
            'total_social_accounts' => SocialAccount::where('is_active', true)->count(),
            'total_revenue' => Subscription::where('stripe_status', 'active')
                ->sum(function ($subscription) {
                    return $subscription->plan->amount ?? 0;
                }),
            'revenue_this_month' => Subscription::where('stripe_status', 'active')
                ->whereMonth('created_at', now()->month)
                ->whereYear('created_at', now()->year)
                ->sum(function ($subscription) {
                    return $subscription->plan->amount ?? 0;
                }),
        ];

        $recentUsers = User::latest()->take(10)->get();
        $recentPosts = Post::with('user', 'socialAccount')->latest()->take(10)->get();

        return Inertia::render('Admin/Dashboard', [
            'stats' => $stats,
            'recentUsers' => $recentUsers,
            'recentPosts' => $recentPosts,
        ]);
    }

    public function users(Request $request)
    {
        $users = User::with('subscription')
            ->when($request->search, function ($query, $search) {
                $query->where('name', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%");
            })
            ->when($request->status, function ($query, $status) {
                if ($status === 'active') {
                    $query->whereHas('subscription', function ($subQuery) {
                        $subQuery->where('stripe_status', 'active');
                    });
                } elseif ($status === 'trial') {
                    $query->whereHas('subscription', function ($subQuery) {
                        $subQuery->where('stripe_status', 'trialing');
                    });
                } elseif ($status === 'inactive') {
                    $query->whereDoesntHave('subscription')
                          ->orWhereHas('subscription', function ($subQuery) {
                              $subQuery->where('stripe_status', '!=', 'active');
                          });
                }
            })
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return Inertia::render('Admin/Users', [
            'users' => $users,
            'filters' => $request->only(['search', 'status']),
        ]);
    }

    public function user(User $user)
    {
        $user->load(['subscription', 'socialAccounts', 'posts', 'teams']);
        
        $stats = [
            'total_posts' => $user->posts()->count(),
            'published_posts' => $user->posts()->where('status', 'published')->count(),
            'scheduled_posts' => $user->posts()->where('status', 'scheduled')->count(),
            'total_social_accounts' => $user->socialAccounts()->where('is_active', true)->count(),
            'total_teams' => $user->ownedTeams()->count() + $user->teams()->count(),
        ];

        return Inertia::render('Admin/User', [
            'user' => $user,
            'stats' => $stats,
        ]);
    }

    public function posts(Request $request)
    {
        $posts = Post::with(['user', 'socialAccount', 'analytics'])
            ->when($request->search, function ($query, $search) {
                $query->where('content', 'like', "%{$search}%");
            })
            ->when($request->status, function ($query, $status) {
                $query->where('status', $status);
            })
            ->when($request->platform, function ($query, $platform) {
                $query->whereHas('socialAccount', function ($subQuery) use ($platform) {
                    $subQuery->where('platform', $platform);
                });
            })
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return Inertia::render('Admin/Posts', [
            'posts' => $posts,
            'filters' => $request->only(['search', 'status', 'platform']),
        ]);
    }

    public function subscriptions(Request $request)
    {
        $subscriptions = Subscription::with('user')
            ->when($request->status, function ($query, $status) {
                $query->where('stripe_status', $status);
            })
            ->when($request->plan, function ($query, $plan) {
                $query->where('stripe_price', $plan);
            })
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return Inertia::render('Admin/Subscriptions', [
            'subscriptions' => $subscriptions,
            'filters' => $request->only(['status', 'plan']),
        ]);
    }

    public function analytics(Request $request): JsonResponse
    {
        $startDate = $request->get('start_date') ? now()->parse($request->get('start_date')) : now()->subDays(30);
        $endDate = $request->get('end_date') ? now()->parse($request->get('end_date')) : now();

        $userGrowth = User::selectRaw('DATE(created_at) as date, COUNT(*) as count')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        $postGrowth = Post::selectRaw('DATE(created_at) as date, COUNT(*) as count')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        $revenue = Subscription::selectRaw('DATE(created_at) as date, SUM(amount) as total')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->where('stripe_status', 'active')
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        $platformStats = SocialAccount::select('platform', DB::raw('count(*) as count'))
            ->where('is_active', true)
            ->groupBy('platform')
            ->get();

        $subscriptionStats = Subscription::select('stripe_price', DB::raw('count(*) as count'))
            ->where('stripe_status', 'active')
            ->groupBy('stripe_price')
            ->get();

        return response()->json([
            'user_growth' => $userGrowth,
            'post_growth' => $postGrowth,
            'revenue' => $revenue,
            'platform_stats' => $platformStats,
            'subscription_stats' => $subscriptionStats,
        ]);
    }

    public function impersonate(User $user)
    {
        session()->put('impersonate', $user->id);
        
        return redirect()->route('dashboard')
            ->with('success', "You are now impersonating {$user->name}");
    }

    public function stopImpersonate()
    {
        session()->forget('impersonate');
        
        return redirect()->route('admin.dashboard')
            ->with('success', 'You have stopped impersonating');
    }

    public function updateUser(Request $request, User $user)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email,' . $user->id,
            'role' => 'required|in:user,admin',
        ]);

        $user->update($request->only(['name', 'email', 'role']));

        return back()->with('success', 'User updated successfully');
    }

    public function updateSubscription(Request $request, Subscription $subscription)
    {
        $request->validate([
            'stripe_status' => 'required|in:active,canceled,incomplete,incomplete_expired,past_due,trialing,unpaid',
        ]);

        $subscription->update($request->only(['stripe_status']));

        return back()->with('success', 'Subscription updated successfully');
    }

    public function cancelSubscription(Subscription $subscription)
    {
        $subscription->cancelNow();

        return back()->with('success', 'Subscription cancelled successfully');
    }

    public function deletePost(Post $post)
    {
        $post->delete();

        return back()->with('success', 'Post deleted successfully');
    }

    public function deleteUser(User $user)
    {
        if ($user->id === auth()->id()) {
            return back()->withErrors(['error' => 'You cannot delete your own account']);
        }

        $user->delete();

        return redirect()->route('admin.users')
            ->with('success', 'User deleted successfully');
    }
}