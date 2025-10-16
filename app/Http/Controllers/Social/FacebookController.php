<?php

namespace App\Http\Controllers\Social;

use App\Http\Controllers\Controller;
use App\Models\SocialAccount;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class FacebookController extends Controller
{
    /**
     * Get Facebook pages for the authenticated user.
     */
    public function getPages(Request $request)
    {
        $user = auth()->user();

        $account = SocialAccount::where('user_id', $user->id)
            ->where('platform', 'facebook')
            ->first();

        if (!$account) {
            return response()->json(['error' => 'No Facebook account connected'], 404);
        }

        try {
            $response = Http::get("https://graph.facebook.com/v18.0/me/accounts", [
                'access_token' => $account->access_token,
                'fields' => 'id,name,username,category,access_token,tasks,instagram_business_account,fan_count,followers_count'
            ]);

            $pages = $response->json();

            if (!isset($pages['data'])) {
                return response()->json(['error' => 'Failed to fetch pages'], 400);
            }

            // Store pages in additional_data for future use
            $account->update([
                'additional_data' => array_merge($account->additional_data ?? [], [
                    'pages' => $pages['data']
                ])
            ]);

            return response()->json($pages['data']);

        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to fetch Facebook pages: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Post to Facebook page.
     */
    public function postToPage(Request $request, string $pageId)
    {
        $request->validate([
            'content' => 'required|string|max:2000',
            'link' => 'nullable|url',
            'image_url' => 'nullable|url',
            'published' => 'boolean'
        ]);

        $user = auth()->user();
        $account = SocialAccount::where('user_id', $user->id)
            ->where('platform', 'facebook')
            ->first();

        if (!$account) {
            return response()->json(['error' => 'No Facebook account connected'], 404);
        }

        try {
            // Get page access token
            $pages = $account->additional_data['pages'] ?? [];
            $page = collect($pages)->firstWhere('id', $pageId);

            if (!$page) {
                return response()->json(['error' => 'Page not found or not accessible'], 404);
            }

            $pageAccessToken = $page['access_token'];

            // Prepare post data
            $postData = [
                'message' => $request->content,
                'published' => $request->get('published', true),
            ];

            if ($request->link) {
                $postData['link'] = $request->link;
            }

            if ($request->image_url) {
                $postData['url'] = $request->image_url;
            }

            // Create post
            $response = Http::post("https://graph.facebook.com/v18.0/{$pageId}/feed", $postData + [
                'access_token' => $pageAccessToken
            ]);

            $result = $response->json();

            if ($response->failed() || !isset($result['id'])) {
                return response()->json(['error' => 'Failed to create Facebook post'], 400);
            }

            return response()->json([
                'success' => true,
                'post_id' => $result['id'],
                'page_id' => $pageId,
                'page_name' => $page['name']
            ]);

        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to post to Facebook: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Get page insights/analytics.
     */
    public function getPageInsights(Request $request, string $pageId)
    {
        $request->validate([
            'metric' => 'string',
            'period' => 'string|in:day,week,month'
        ]);

        $user = auth()->user();
        $account = SocialAccount::where('user_id', $user->id)
            ->where('platform', 'facebook')
            ->first();

        if (!$account) {
            return response()->json(['error' => 'No Facebook account connected'], 404);
        }

        try {
            $pages = $account->additional_data['pages'] ?? [];
            $page = collect($pages)->firstWhere('id', $pageId);

            if (!$page) {
                return response()->json(['error' => 'Page not found'], 404);
            }

            $metrics = $request->metric ?? 'page_impressions,page_engaged_users,page_fan_adds';
            $period = $request->period ?? 'week';

            $response = Http::get("https://graph.facebook.com/v18.0/{$pageId}/insights", [
                'metric' => $metrics,
                'period' => $period,
                'access_token' => $page['access_token']
            ]);

            $insights = $response->json();

            return response()->json($insights);

        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to fetch Facebook insights: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Refresh page access tokens.
     */
    public function refreshPageTokens(Request $request)
    {
        $user = auth()->user();
        $account = SocialAccount::where('user_id', $user->id)
            ->where('platform', 'facebook')
            ->first();

        dd($account);

        if (!$account) {
            if ($request->inertia()) {
                return back()->with('error', 'No Facebook account connected');
            }
            return response()->json(['error' => 'No Facebook account connected'], 404);
        }

        try {
            // Re-fetch pages with fresh tokens
            $response = Http::get("https://graph.facebook.com/v18.0/me/accounts", [
                'access_token' => $account->access_token,
                'fields' => 'id,name,username,category,access_token,tasks,instagram_business_account,fan_count,followers_count'
            ]);

            $pages = $response->json();

            dd($pages);

            if (!isset($pages['data'])) {
                if ($request->inertia()) {
                    return back()->with('error', 'Failed to refresh page tokens');
                }
                return response()->json(['error' => 'Failed to refresh page tokens'], 400);
            }

            // Update stored pages
            $account->update([
                'additional_data' => array_merge($account->additional_data ?? [], [
                    'pages' => $pages['data']
                ]),
                'last_synced_at' => now()
            ]);

            if ($request->inertia()) {
                return back()->with('success', 'Facebook pages refreshed successfully');
            }

            return response()->json([
                'success' => true,
                'pages_count' => count($pages['data'])
            ]);

        } catch (\Exception $e) {
            if ($request->inertia()) {
                return back()->with('error', 'Failed to refresh Facebook tokens: ' . $e->getMessage());
            }
            return response()->json(['error' => 'Failed to refresh Facebook tokens: ' . $e->getMessage()], 500);
        }
    }
}
