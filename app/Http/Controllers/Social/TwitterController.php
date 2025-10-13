<?php

namespace App\Http\Controllers\Social;

use App\Http\Controllers\Controller;
use App\Models\SocialAccount;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class TwitterController extends Controller
{
    /**
     * Get Twitter user profile.
     */
    public function getProfile(Request $request)
    {
        $user = auth()->user();
        $account = SocialAccount::where('user_id', $user->id)
            ->where('provider', 'twitter')
            ->first();

        if (!$account) {
            return response()->json(['error' => 'No Twitter account connected'], 404);
        }

        try {
            $response = Http::withToken($account->access_token)
                ->get("https://api.twitter.com/2/users/me", [
                    'user.fields' => 'public_metrics,description,location,protected,url,username,created_at,profile_image_url'
                ]);

            $profile = $response->json();

            if ($response->failed() || !isset($profile['data'])) {
                return response()->json(['error' => 'Failed to fetch Twitter profile'], 400);
            }

            // Store profile in additional_data
            $account->update([
                'additional_data' => array_merge($account->additional_data ?? [], [
                    'profile' => $profile['data']
                ])
            ]);

            return response()->json($profile['data']);

        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to fetch Twitter profile: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Post a tweet.
     */
    public function postTweet(Request $request)
    {
        $request->validate([
            'content' => 'required|string|max:280',
            'reply_to_tweet_id' => 'nullable|string',
            'quote_tweet_id' => 'nullable|string',
            'poll_options' => 'nullable|array|min:2|max:4',
            'poll_duration_minutes' => 'nullable|integer|min:5|max:10080'
        ]);

        $user = auth()->user();
        $account = SocialAccount::where('user_id', $user->id)
            ->where('provider', 'twitter')
            ->first();

        if (!$account) {
            return response()->json(['error' => 'No Twitter account connected'], 404);
        }

        try {
            $tweetData = [
                'text' => $request->content
            ];

            // Handle reply
            if ($request->reply_to_tweet_id) {
                $tweetData['reply'] = [
                    'in_reply_to_tweet_id' => $request->reply_to_tweet_id
                ];
            }

            // Handle quote tweet
            if ($request->quote_tweet_id) {
                $tweetData['quote_tweet_id'] = $request->quote_tweet_id;
            }

            // Handle poll
            if ($request->poll_options) {
                $tweetData['poll'] = [
                    'options' => $request->poll_options,
                    'duration_minutes' => $request->get('poll_duration_minutes', 1440) // Default 24 hours
                ];
            }

            $response = Http::withToken($account->access_token)
                ->post("https://api.twitter.com/2/tweets", $tweetData);

            $result = $response->json();

            if ($response->failed() || !isset($result['data'])) {
                return response()->json(['error' => 'Failed to create tweet'], 400);
            }

            return response()->json([
                'success' => true,
                'tweet_id' => $result['data']['id'],
                'text' => $result['data']['text']
            ]);

        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to post tweet: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Post a tweet with media.
     */
    public function postTweetWithMedia(Request $request)
    {
        $request->validate([
            'content' => 'required|string|max:280',
            'media_ids' => 'required|array|min:1|max:4',
            'media_ids.*' => 'string'
        ]);

        $user = auth()->user();
        $account = SocialAccount::where('user_id', $user->id)
            ->where('provider', 'twitter')
            ->first();

        if (!$account) {
            return response()->json(['error' => 'No Twitter account connected'], 404);
        }

        try {
            $tweetData = [
                'text' => $request->content,
                'media' => [
                    'media_ids' => $request->media_ids
                ]
            ];

            $response = Http::withToken($account->access_token)
                ->post("https://api.twitter.com/2/tweets", $tweetData);

            $result = $response->json();

            if ($response->failed() || !isset($result['data'])) {
                return response()->json(['error' => 'Failed to create tweet with media'], 400);
            }

            return response()->json([
                'success' => true,
                'tweet_id' => $result['data']['id'],
                'text' => $result['data']['text']
            ]);

        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to post tweet with media: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Upload media to Twitter.
     */
    public function uploadMedia(Request $request)
    {
        $request->validate([
            'media' => 'required|file|mimes:jpeg,jpg,png,gif,webp,mp4,mov|max:51200' // 50MB max
        ]);

        $user = auth()->user();
        $account = SocialAccount::where('user_id', $user->id)
            ->where('provider', 'twitter')
            ->first();

        if (!$account) {
            return response()->json(['error' => 'No Twitter account connected'], 404);
        }

        try {
            $file = $request->file('media');
            $mimeType = $file->getMimeType();
            $fileSize = $file->getSize();

            // Initialize media upload
            $initResponse = Http::withToken($account->access_token)
                ->post("https://upload.twitter.com/1.1/media/upload.json", [
                    'command' => 'INIT',
                    'total_bytes' => $fileSize,
                    'media_type' => $mimeType,
                    'media_category' => $this->getMediaCategory($mimeType)
                ]);

            $initResult = $initResponse->json();

            if ($initResponse->failed() || !isset($initResult['media_id_string'])) {
                return response()->json(['error' => 'Failed to initialize media upload'], 400);
            }

            $mediaId = $initResult['media_id_string'];

            // Append media data
            $appendResponse = Http::withToken($account->access_token)
                ->attach('media', file_get_contents($file->getPathname()), $file->getClientOriginalName())
                ->post("https://upload.twitter.com/1.1/media/upload.json", [
                    'command' => 'APPEND',
                    'media_id' => $mediaId,
                    'segment_index' => 0
                ]);

            if ($appendResponse->failed()) {
                return response()->json(['error' => 'Failed to upload media data'], 400);
            }

            // Finalize upload
            $finalizeResponse = Http::withToken($account->access_token)
                ->post("https://upload.twitter.com/1.1/media/upload.json", [
                    'command' => 'FINALIZE',
                    'media_id' => $mediaId
                ]);

            $finalizeResult = $finalizeResponse->json();

            if ($finalizeResponse->failed()) {
                return response()->json(['error' => 'Failed to finalize media upload'], 400);
            }

            return response()->json([
                'success' => true,
                'media_id' => $mediaId,
                'size' => $fileSize
            ]);

        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to upload media: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Get tweet metrics.
     */
    public function getTweetMetrics(Request $request, string $tweetId)
    {
        $user = auth()->user();
        $account = SocialAccount::where('user_id', $user->id)
            ->where('provider', 'twitter')
            ->first();

        if (!$account) {
            return response()->json(['error' => 'No Twitter account connected'], 404);
        }

        try {
            $response = Http::withToken($account->access_token)
                ->get("https://api.twitter.com/2/tweets/{$tweetId}", [
                    'tweet.fields' => 'public_metrics,created_at,author_id'
                ]);

            $tweet = $response->json();

            if ($response->failed() || !isset($tweet['data'])) {
                return response()->json(['error' => 'Failed to fetch tweet metrics'], 400);
            }

            return response()->json($tweet['data']);

        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to fetch tweet metrics: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Get user timeline.
     */
    public function getUserTimeline(Request $request)
    {
        $request->validate([
            'max_results' => 'integer|min:5|max:100',
            'exclude' => 'string',
            'tweet_fields' => 'string'
        ]);

        $user = auth()->user();
        $account = SocialAccount::where('user_id', $user->id)
            ->where('provider', 'twitter')
            ->first();

        if (!$account) {
            return response()->json(['error' => 'No Twitter account connected'], 404);
        }

        try {
            // Get user ID from stored profile
            $profile = $account->additional_data['profile'] ?? null;
            if (!$profile || !isset($profile['id'])) {
                return response()->json(['error' => 'User profile not found'], 404);
            }

            $params = [
                'max_results' => $request->get('max_results', 20),
                'tweet.fields' => $request->get('tweet_fields', 'created_at,public_metrics,context_annotations')
            ];

            if ($request->exclude) {
                $params['exclude'] = $request->exclude;
            }

            $response = Http::withToken($account->access_token)
                ->get("https://api.twitter.com/2/users/{$profile['id']}/tweets", $params);

            $timeline = $response->json();

            if ($response->failed()) {
                return response()->json(['error' => 'Failed to fetch user timeline'], 400);
            }

            return response()->json($timeline);

        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to fetch user timeline: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Get media category for Twitter API.
     */
    private function getMediaCategory(string $mimeType): string
    {
        if (str_starts_with($mimeType, 'image/')) {
            return 'tweet_image';
        } elseif (str_starts_with($mimeType, 'video/')) {
            return 'tweet_video';
        } elseif (str_starts_with($mimeType, 'audio/')) {
            return 'tweet_audio';
        }

        return 'tweet_image';
    }
}