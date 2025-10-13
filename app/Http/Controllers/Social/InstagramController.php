<?php

namespace App\Http\Controllers\Social;

use App\Http\Controllers\Controller;
use App\Models\SocialAccount;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class InstagramController extends Controller
{
    /**
     * Get Instagram business accounts for the authenticated user.
     */
    public function getBusinessAccounts(Request $request)
    {
        $user = auth()->user();
        $account = SocialAccount::where('user_id', $user->id)
            ->where('provider', 'instagram')
            ->first();

        if (!$account) {
            return response()->json(['error' => 'No Instagram account connected'], 404);
        }

        try {
            // Get Instagram business accounts from Facebook pages
            $facebookAccount = SocialAccount::where('user_id', $user->id)
                ->where('provider', 'facebook')
                ->first();

            if (!$facebookAccount) {
                return response()->json(['error' => 'Facebook account required for Instagram business accounts'], 400);
            }

            $response = Http::get("https://graph.facebook.com/v18.0/me/accounts", [
                'access_token' => $facebookAccount->access_token,
                'fields' => 'id,name,instagram_business_account'
            ]);

            $pages = $response->json();

            if (!isset($pages['data'])) {
                return response()->json(['error' => 'Failed to fetch Facebook pages'], 400);
            }

            $instagramAccounts = [];

            foreach ($pages['data'] as $page) {
                if (isset($page['instagram_business_account'])) {
                    $igResponse = Http::get("https://graph.facebook.com/v18.0/{$page['instagram_business_account']}", [
                        'access_token' => $facebookAccount->access_token,
                        'fields' => 'id,username,account_type,media_count,followers_count,follows_count,website,biography'
                    ]);

                    $igAccount = $igResponse->json();
                    
                    if (!isset($igAccount['error'])) {
                        $instagramAccounts[] = array_merge($igAccount, [
                            'page_id' => $page['id'],
                            'page_name' => $page['name']
                        ]);
                    }
                }
            }

            // Store Instagram accounts in additional_data
            $account->update([
                'additional_data' => array_merge($account->additional_data ?? [], [
                    'business_accounts' => $instagramAccounts
                ])
            ]);

            return response()->json($instagramAccounts);

        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to fetch Instagram accounts: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Create Instagram media container.
     */
    public function createMediaContainer(Request $request, string $accountId)
    {
        $request->validate([
            'image_url' => 'required|url',
            'caption' => 'nullable|string|max:2200',
            'media_type' => 'string|in:IMAGE,REEL,STORY'
        ]);

        $user = auth()->user();
        $account = SocialAccount::where('user_id', $user->id)
            ->where('provider', 'instagram')
            ->first();

        if (!$account) {
            return response()->json(['error' => 'No Instagram account connected'], 404);
        }

        try {
            // Get Facebook account for access token
            $facebookAccount = SocialAccount::where('user_id', $user->id)
                ->where('provider', 'facebook')
                ->first();

            if (!$facebookAccount) {
                return response()->json(['error' => 'Facebook account required for Instagram posting'], 400);
            }

            $mediaType = $request->media_type ?? 'IMAGE';
            $containerData = [
                'image_url' => $request->image_url,
                'media_type' => $mediaType,
                'access_token' => $facebookAccount->access_token
            ];

            if ($request->caption) {
                $containerData['caption'] = $request->caption;
            }

            // Create media container
            $response = Http::post("https://graph.facebook.com/v18.0/{$accountId}/media", $containerData);

            $result = $response->json();

            if ($response->failed() || !isset($result['id'])) {
                return response()->json(['error' => 'Failed to create Instagram media container'], 400);
            }

            return response()->json([
                'success' => true,
                'container_id' => $result['id']
            ]);

        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to create Instagram media: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Publish Instagram media.
     */
    public function publishMedia(Request $request, string $accountId)
    {
        $request->validate([
            'container_id' => 'required|string'
        ]);

        $user = auth()->user();
        $account = SocialAccount::where('user_id', $user->id)
            ->where('provider', 'instagram')
            ->first();

        if (!$account) {
            return response()->json(['error' => 'No Instagram account connected'], 404);
        }

        try {
            // Get Facebook account for access token
            $facebookAccount = SocialAccount::where('user_id', $user->id)
                ->where('provider', 'facebook')
                ->first();

            if (!$facebookAccount) {
                return response()->json(['error' => 'Facebook account required for Instagram posting'], 400);
            }

            // Publish media
            $response = Http::post("https://graph.facebook.com/v18.0/{$accountId}/media_publish", [
                'creation_id' => $request->container_id,
                'access_token' => $facebookAccount->access_token
            ]);

            $result = $response->json();

            if ($response->failed() || !isset($result['id'])) {
                return response()->json(['error' => 'Failed to publish Instagram media'], 400);
            }

            return response()->json([
                'success' => true,
                'media_id' => $result['id']
            ]);

        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to publish Instagram media: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Get Instagram media insights.
     */
    public function getMediaInsights(Request $request, string $mediaId)
    {
        $request->validate([
            'metrics' => 'string'
        ]);

        $user = auth()->user();
        $account = SocialAccount::where('user_id', $user->id)
            ->where('provider', 'instagram')
            ->first();

        if (!$account) {
            return response()->json(['error' => 'No Instagram account connected'], 404);
        }

        try {
            // Get Facebook account for access token
            $facebookAccount = SocialAccount::where('user_id', $user->id)
                ->where('provider', 'facebook')
                ->first();

            if (!$facebookAccount) {
                return response()->json(['error' => 'Facebook account required for Instagram insights'], 400);
            }

            $metrics = $request->metrics ?? 'impressions,reach,likes,comments,shares,saves';

            $response = Http::get("https://graph.facebook.com/v18.0/{$mediaId}/insights", [
                'metric' => $metrics,
                'access_token' => $facebookAccount->access_token
            ]);

            $insights = $response->json();

            return response()->json($insights);

        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to fetch Instagram insights: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Get Instagram user insights.
     */
    public function getUserInsights(Request $request, string $accountId)
    {
        $request->validate([
            'metric' => 'string',
            'period' => 'string|in:day,week,month'
        ]);

        $user = auth()->user();
        $account = SocialAccount::where('user_id', $user->id)
            ->where('provider', 'instagram')
            ->first();

        if (!$account) {
            return response()->json(['error' => 'No Instagram account connected'], 404);
        }

        try {
            // Get Facebook account for access token
            $facebookAccount = SocialAccount::where('user_id', $user->id)
                ->where('provider', 'facebook')
                ->first();

            if (!$facebookAccount) {
                return response()->json(['error' => 'Facebook account required for Instagram insights'], 400);
            }

            $metric = $request->metric ?? 'impressions,reach,profile_views,website_clicks';
            $period = $request->period ?? 'week';

            $response = Http::get("https://graph.facebook.com/v18.0/{$accountId}/insights", [
                'metric' => $metric,
                'period' => $period,
                'access_token' => $facebookAccount->access_token
            ]);

            $insights = $response->json();

            return response()->json($insights);

        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to fetch Instagram user insights: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Upload image to Instagram (via temporary storage).
     */
    public function uploadImage(Request $request)
    {
        $request->validate([
            'image' => 'required|image|mimes:jpeg,jpg,png|max:10240' // 10MB max
        ]);

        try {
            $path = $request->file('image')->store('instagram-uploads', 'public');
            $url = Storage::url($path);

            return response()->json([
                'success' => true,
                'image_url' => url($url),
                'path' => $path
            ]);

        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to upload image: ' . $e->getMessage()], 500);
        }
    }
}