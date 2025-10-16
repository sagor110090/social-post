<?php

namespace App\Http\Controllers\Social;

use App\Http\Controllers\Controller;
use App\Models\SocialAccount;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class LinkedInController extends Controller
{
    /**
     * Get LinkedIn user profile.
     */
    public function getProfile(Request $request)
    {
        $user = auth()->user();
        $account = SocialAccount::where('user_id', $user->id)
            ->where('platform', 'linkedin')
            ->first();

        if (!$account) {
            return response()->json(['error' => 'No LinkedIn account connected'], 404);
        }

        try {
            $response = Http::withToken($account->access_token)
                ->get("https://api.linkedin.com/v2/people/~:({$this->getProfileFields()})");

            $profile = $response->json();

            if ($response->failed()) {
                return response()->json(['error' => 'Failed to fetch LinkedIn profile'], 400);
            }

            // Store profile in additional_data
            $account->update([
                'additional_data' => array_merge($account->additional_data ?? [], [
                    'profile' => $profile
                ])
            ]);

            return response()->json($profile);

        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to fetch LinkedIn profile: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Post to LinkedIn profile.
     */
    public function postToProfile(Request $request)
    {
        $request->validate([
            'content' => 'required|string|max:3000',
            'visibility' => 'string|in:PUBLIC,CONNECTIONS'
        ]);

        $user = auth()->user();
        $account = SocialAccount::where('user_id', $user->id)
            ->where('platform', 'linkedin')
            ->first();

        if (!$account) {
            return response()->json(['error' => 'No LinkedIn account connected'], 404);
        }

        try {
            // Get person URN
            $profileResponse = Http::withToken($account->access_token)
                ->get("https://api.linkedin.com/v2/people/~:id");

            if ($profileResponse->failed()) {
                return response()->json(['error' => 'Failed to get LinkedIn profile ID'], 400);
            }

            $personUrn = "urn:li:person:{$profileResponse->json('id')}";

            // Create share content
            $shareData = [
                'author' => $personUrn,
                'lifecycleState' => 'PUBLISHED',
                'specificContent' => [
                    'com.linkedin.ugc.ShareContent' => [
                        'shareCommentary' => [
                            'text' => $request->content
                        ],
                        'shareMediaCategory' => 'NONE'
                    ]
                ],
                'visibility' => [
                    'com.linkedin.ugc.MemberNetworkVisibility' => $request->get('visibility', 'PUBLIC')
                ]
            ];

            $response = Http::withToken($account->access_token)
                ->post("https://api.linkedin.com/v2/ugcPosts", $shareData);

            $result = $response->json();

            if ($response->failed() || !isset($result['id'])) {
                return response()->json(['error' => 'Failed to create LinkedIn post'], 400);
            }

            return response()->json([
                'success' => true,
                'post_id' => $result['id'],
                'post_urn' => $result['id']
            ]);

        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to post to LinkedIn: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Post with image to LinkedIn.
     */
    public function postWithImage(Request $request)
    {
        $request->validate([
            'content' => 'required|string|max:3000',
            'image_url' => 'required|url',
            'title' => 'required|string|max:200',
            'description' => 'nullable|string|max:500',
            'visibility' => 'string|in:PUBLIC,CONNECTIONS'
        ]);

        $user = auth()->user();
        $account = SocialAccount::where('user_id', $user->id)
            ->where('platform', 'linkedin')
            ->first();

        if (!$account) {
            return response()->json(['error' => 'No LinkedIn account connected'], 404);
        }

        try {
            // Get person URN
            $profileResponse = Http::withToken($account->access_token)
                ->get("https://api.linkedin.com/v2/people/~:id");

            if ($profileResponse->failed()) {
                return response()->json(['error' => 'Failed to get LinkedIn profile ID'], 400);
            }

            $personUrn = "urn:li:person:{$profileResponse->json('id')}";

            // Register image upload
            $registerResponse = Http::withToken($account->access_token)
                ->post("https://api.linkedin.com/v2/assets?action=registerUpload", [
                    'registerUploadRequest' => [
                        'recipes' => [
                            'urn:li:digitalmediaRecipe:feedshare-image'
                        ],
                        'owner' => $personUrn,
                        'serviceRelationships' => [
                            [
                                'relationshipType' => 'OWNER',
                                'identifier' => 'urn:li:userGeneratedContent'
                            ]
                        ]
                    ]
                ]);

            $registerResult = $registerResponse->json();

            if ($registerResponse->failed() || !isset($registerResult['value']['uploadMechanism'])) {
                return response()->json(['error' => 'Failed to register image upload'], 400);
            }

            $uploadUrl = $registerResult['value']['uploadMechanism']['com.linkedin.digitalmedia.uploading.MediaUploadHttpRequest']['uploadUrl'];
            $assetUrn = $registerResult['value']['asset'];

            // Upload image
            $imageResponse = Http::withHeaders([
                'Authorization' => "Bearer {$account->access_token}"
            ])->put($uploadUrl, [
                'multipart' => [
                    [
                        'name' => 'file',
                        'contents' => file_get_contents($request->image_url),
                        'filename' => 'image.jpg'
                    ]
                ]
            ]);

            if ($imageResponse->failed()) {
                return response()->json(['error' => 'Failed to upload image'], 400);
            }

            // Create share content with image
            $shareData = [
                'author' => $personUrn,
                'lifecycleState' => 'PUBLISHED',
                'specificContent' => [
                    'com.linkedin.ugc.ShareContent' => [
                        'shareCommentary' => [
                            'text' => $request->content
                        ],
                        'shareMediaCategory' => 'IMAGE',
                        'media' => [
                            [
                                'status' => 'READY',
                                'description' => [
                                    'text' => $request->get('description', '')
                                ],
                                'media' => $assetUrn,
                                'title' => [
                                    'text' => $request->title
                                ]
                            ]
                        ]
                    ]
                ],
                'visibility' => [
                    'com.linkedin.ugc.MemberNetworkVisibility' => $request->get('visibility', 'PUBLIC')
                ]
            ];

            $response = Http::withToken($account->access_token)
                ->post("https://api.linkedin.com/v2/ugcPosts", $shareData);

            $result = $response->json();

            if ($response->failed() || !isset($result['id'])) {
                return response()->json(['error' => 'Failed to create LinkedIn post with image'], 400);
            }

            return response()->json([
                'success' => true,
                'post_id' => $result['id'],
                'post_urn' => $result['id'],
                'asset_urn' => $assetUrn
            ]);

        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to post to LinkedIn with image: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Get LinkedIn post statistics.
     */
    public function getPostStats(Request $request, string $postId)
    {
        $user = auth()->user();
        $account = SocialAccount::where('user_id', $user->id)
            ->where('platform', 'linkedin')
            ->first();

        if (!$account) {
            return response()->json(['error' => 'No LinkedIn account connected'], 404);
        }

        try {
            // Get post social actions (likes, comments, shares)
            $response = Http::withToken($account->access_token)
                ->get("https://api.linkedin.com/v2/socialActions/{$postId}", [
                    'fields' => 'likes,comments,shares'
                ]);

            $stats = $response->json();

            if ($response->failed()) {
                return response()->json(['error' => 'Failed to fetch LinkedIn post stats'], 400);
            }

            return response()->json($stats);

        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to fetch LinkedIn post stats: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Get LinkedIn network statistics.
     */
    public function getNetworkStats(Request $request)
    {
        $user = auth()->user();
        $account = SocialAccount::where('user_id', $user->id)
            ->where('platform', 'linkedin')
            ->first();

        if (!$account) {
            return response()->json(['error' => 'No LinkedIn account connected'], 404);
        }

        try {
            // Get network size
            $response = Http::withToken($account->access_token)
                ->get("https://api.linkedin.com/v2/connections?q=viewer&count=1");

            $network = $response->json();

            if ($response->failed()) {
                return response()->json(['error' => 'Failed to fetch LinkedIn network stats'], 400);
            }

            return response()->json([
                'connections_count' => $network['paging']['total'] ?? 0,
                'profile' => $account->additional_data['profile'] ?? null
            ]);

        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to fetch LinkedIn network stats: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Get profile fields for LinkedIn API.
     */
    private function getProfileFields(): string
    {
        return 'id,firstName,lastName,headline,profilePicture(displayImage~:playableStreams),summary,location,industry';
    }
}