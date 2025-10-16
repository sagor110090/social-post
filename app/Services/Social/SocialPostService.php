<?php

namespace App\Services\Social;

use App\Models\Post;
use App\Models\SocialAccount;
use App\Models\User;
use App\Services\Social\ContentFormatterService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SocialPostService
{
    public function __construct(
        private ContentFormatterService $contentFormatter
    ) {}

    /**
     * Post content to multiple social media platforms.
     */
    public function postToPlatforms(Post $post, array $platforms): array
    {
        $results = [];
        $user = $post->user;

        foreach ($platforms as $platform) {
            $account = $user->socialAccounts()
                ->where('platform', $platform)
                ->where('is_active', true)
                ->first();

            if (!$account) {
                $results[$platform] = [
                    'success' => false,
                    'error' => "No active {$platform} account found"
                ];
                continue;
            }

            try {
                $result = $this->postToPlatform($post, $account);
                $results[$platform] = $result;
            } catch (\Exception $e) {
                Log::error("Failed to post to {$platform}: " . $e->getMessage(), [
                    'post_id' => $post->id,
                    'account_id' => $account->id,
                    'platform' => $platform,
                    'content_length' => strlen($post->content),
                    'trace' => $e->getTraceAsString()
                ]);
                $results[$platform] = [
                    'success' => false,
                    'error' => $e->getMessage()
                ];
            }
        }

        return $results;
    }

    /**
     * Post to a specific platform.
     */
    private function postToPlatform(Post $post, SocialAccount $account): array
    {
        return match ($account->platform) {
            'facebook' => $this->postToFacebook($post, $account),
            'instagram' => $this->postToInstagram($post, $account),
            'linkedin' => $this->postToLinkedIn($post, $account),
            'twitter' => $this->postToTwitter($post, $account),
            default => ['success' => false, 'error' => 'Unsupported platform'],
        };
    }

    /**
     * Get content with hashtags appended, formatted for specific platform.
     */
    private function getContentWithHashtags(Post $post, string $platform): string
    {
        // Format the main content for the platform
        $content = $this->contentFormatter->formatForPlatform($post->content, $platform);
        
        // Add formatted hashtags
        if (!empty($post->hashtags) && is_array($post->hashtags)) {
            $hashtags = $this->contentFormatter->formatHashtags($post->hashtags, $platform);
            if (!empty($hashtags)) {
                $content .= "\n\n" . $hashtags;
            }
        }
        
        return $content;
    }

    /**
     * Post to Facebook.
     */
    private function postToFacebook(Post $post, SocialAccount $account): array
    {
        $pages = $account->additional_data['pages'] ?? [];
        $userAccessToken = is_array($account->access_token) 
            ? $account->access_token['access_token'] 
            : $account->access_token;

        $message = $this->getContentWithHashtags($post, 'facebook');
        
        // Ensure UTF-8 encoding for Facebook API
        $message = mb_convert_encoding($message, 'UTF-8', 'UTF-8');
        
        // Remove any invalid UTF-8 sequences
        $message = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $message);
        
        $postData = [
            'message' => $message,
            'published' => true,
        ];

        if ($post->link) {
            $postData['link'] = $post->link;
        }

        // Handle media URLs (images/videos)
        $mediaUrls = $post->media_urls ?? [];
        if ($post->image_url) {
            $mediaUrls[] = $post->image_url;
        }
        
        if (!empty($mediaUrls)) {
            // For Facebook, we can use the first image URL
            $postData['url'] = $mediaUrls[0];
            
            // TODO: For multiple images, we'd need to upload them as photos first
            // and then use the attached_media parameter
        }

        // Try to post to a page first, fallback to user timeline
        if (!empty($pages)) {
            $page = $pages[0];
            $pageAccessToken = $page['access_token'];
            
            $response = Http::post("https://graph.facebook.com/v18.0/{$page['id']}/feed", $postData + [
                'access_token' => $pageAccessToken
            ]);
        } else {
            // Post to user's timeline instead
            $response = Http::post("https://graph.facebook.com/v18.0/me/feed", $postData + [
                'access_token' => $userAccessToken
            ]);
        }

        $result = $response->json();

        if ($response->failed() || !isset($result['id'])) {
            $errorMessage = $result['error']['message'] ?? 'Failed to create Facebook post';
            Log::error('Facebook posting error', [
                'error' => $result,
                'post_id' => $post->id,
                'account_id' => $account->id,
                'response_status' => $response->status()
            ]);
            return ['success' => false, 'error' => $errorMessage];
        }

        return [
            'success' => true,
            'post_id' => $result['id'],
            'platform_post_id' => $result['id'],
            'url' => "https://facebook.com/{$result['id']}"
        ];
    }

    /**
     * Post to Instagram.
     */
    private function postToInstagram(Post $post, SocialAccount $account): array
    {
        // Handle media URLs (images/videos)
        $mediaUrls = $post->media_urls ?? [];
        if ($post->image_url) {
            $mediaUrls[] = $post->image_url;
        }
        
        if (empty($mediaUrls)) {
            return ['success' => false, 'error' => 'Instagram posts require an image or video'];
        }

        $accounts = $account->additional_data['business_accounts'] ?? [];
        
        if (empty($accounts)) {
            return ['success' => false, 'error' => 'No Instagram business accounts available'];
        }

        // Use the first account for now
        $igAccount = $accounts[0];
        $accountId = $igAccount['id'];

        // Need Facebook account for Instagram posting
        $facebookAccount = $account->user->socialAccounts()
            ->where('platform', 'facebook')
            ->first();

        if (!$facebookAccount) {
            return ['success' => false, 'error' => 'Facebook account required for Instagram posting'];
        }

        // Create media container
        $imageUrl = $mediaUrls[0]; // Use first image for now
        $mediaType = 'IMAGE'; // TODO: Detect if video based on URL or file type
        
        $containerResponse = Http::post("https://graph.facebook.com/v18.0/{$accountId}/media", [
            'image_url' => $imageUrl,
            'caption' => $this->getContentWithHashtags($post, 'instagram'),
            'media_type' => $mediaType,
            'access_token' => $facebookAccount->access_token
        ]);

        $containerResult = $containerResponse->json();

        if ($containerResponse->failed() || !isset($containerResult['id'])) {
            return ['success' => false, 'error' => 'Failed to create Instagram media container'];
        }

        // Publish media
        $publishResponse = Http::post("https://graph.facebook.com/v18.0/{$accountId}/media_publish", [
            'creation_id' => $containerResult['id'],
            'access_token' => $facebookAccount->access_token
        ]);

        $publishResult = $publishResponse->json();

        if ($publishResponse->failed() || !isset($publishResult['id'])) {
            return ['success' => false, 'error' => 'Failed to publish Instagram media'];
        }

        return [
            'success' => true,
            'post_id' => $publishResult['id'],
            'platform_post_id' => $publishResult['id'],
            'url' => "https://instagram.com/p/{$publishResult['id']}"
        ];
    }

    /**
     * Post to LinkedIn.
     */
    private function postToLinkedIn(Post $post, SocialAccount $account): array
    {
        // Get person URN
        $profileResponse = Http::withToken($account->access_token)
            ->get("https://api.linkedin.com/v2/people/~:id");

        if ($profileResponse->failed()) {
            return ['success' => false, 'error' => 'Failed to get LinkedIn profile ID'];
        }

        $personUrn = "urn:li:person:{$profileResponse->json('id')}";

        // Create share content
        $shareData = [
            'author' => $personUrn,
            'lifecycleState' => 'PUBLISHED',
            'specificContent' => [
                'com.linkedin.ugc.ShareContent' => [
                    'shareCommentary' => [
                        'text' => $this->getContentWithHashtags($post, 'linkedin')
                    ],
                    'shareMediaCategory' => $post->image_url ? 'IMAGE' : 'NONE'
                ]
            ],
            'visibility' => [
                'com.linkedin.ugc.MemberNetworkVisibility' => 'PUBLIC'
            ]
        ];

        // Add image if present
        if ($post->image_url) {
            // For simplicity, we'll skip image upload for now
            // In production, you'd need to implement the full LinkedIn image upload flow
            $shareData['specificContent']['com.linkedin.ugc.ShareContent']['shareMediaCategory'] = 'NONE';
        }

        $response = Http::withToken($account->access_token)
            ->post("https://api.linkedin.com/v2/ugcPosts", $shareData);

        $result = $response->json();

        if ($response->failed() || !isset($result['id'])) {
            return ['success' => false, 'error' => 'Failed to create LinkedIn post'];
        }

        return [
            'success' => true,
            'post_id' => $result['id'],
            'platform_post_id' => $result['id'],
            'url' => "https://linkedin.com/feed/update/{$result['id']}"
        ];
    }

    /**
     * Post to Twitter.
     */
    private function postToTwitter(Post $post, SocialAccount $account): array
    {
        $tweetData = [
            'text' => $this->getContentWithHashtags($post, 'twitter')
        ];

        // Handle media if present
        if ($post->image_url) {
            // For simplicity, we'll skip media upload for now
            // In production, you'd need to implement the full Twitter media upload flow
        }

        $response = Http::withToken($account->access_token)
            ->post("https://api.twitter.com/2/tweets", $tweetData);

        $result = $response->json();

        if ($response->failed() || !isset($result['data'])) {
            return ['success' => false, 'error' => 'Failed to create tweet'];
        }

        return [
            'success' => true,
            'post_id' => $result['data']['id'],
            'platform_post_id' => $result['data']['id'],
            'url' => "https://twitter.com/user/status/{$result['data']['id']}"
        ];
    }

    /**
     * Get platform-specific character limits.
     */
    public function getCharacterLimits(): array
    {
        return [
            'facebook' => 2000,
            'instagram' => 2200,
            'linkedin' => 3000,
            'twitter' => 280,
        ];
    }

    /**
     * Validate content for platform.
     */
    public function validateContent(string $content, string $platform, array $hashtags = []): array
    {
        $limits = $this->getCharacterLimits();
        $limit = $limits[$platform] ?? 280;

        // Build full content with hashtags for validation using platform-specific formatting
        $fullContent = $this->contentFormatter->formatForPlatform($content, $platform);
        if (!empty($hashtags)) {
            $hashtagString = $this->contentFormatter->formatHashtags($hashtags, $platform);
            if (!empty($hashtagString)) {
                $fullContent .= "\n\n" . $hashtagString;
            }
        }

        $errors = [];

        if (strlen($fullContent) > $limit) {
            $errors[] = "Content exceeds {$limit} character limit for {$platform}";
        }

        if (empty(trim($content))) {
            $errors[] = "Content cannot be empty";
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'character_count' => strlen($fullContent),
            'character_limit' => $limit,
        ];
    }

    /**
     * Get available platforms for user.
     */
    public function getAvailablePlatforms(User $user): array
    {
        return $user->socialAccounts()
            ->where('is_active', true)
            ->pluck('platform')
            ->toArray();
    }
}