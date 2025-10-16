<?php

namespace App\Http\Controllers\Social;

use App\Http\Controllers\Controller;
use App\Models\SocialAccount;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;

class OAuthController extends Controller
{
    /**
     * Redirect to the provider's authentication page.
     */
    public function redirect(string $provider)
    {
        $this->validateProvider($provider);

        if ($provider === 'instagram') {
            // For Instagram, build the Facebook OAuth URL manually with Instagram callback
            $clientId = config('services.facebook.client_id');
            $redirectUri = config('services.instagram.redirect');
            $scopes = implode(',', $this->getScopes('instagram'));
            $state = Str::random(40);
            
            // Store state in session
            session(['instagram_oauth_state' => $state]);
            
            $url = "https://www.facebook.com/v18.0/dialog/oauth?" . http_build_query([
                'client_id' => $clientId,
                'redirect_uri' => $redirectUri,
                'scope' => $scopes,
                'response_type' => 'code',
                'state' => $state,
            ]);
            
            return redirect($url);
        }

        return Socialite::driver($provider)
            ->scopes($this->getScopes($provider))
            ->with($this->getAdditionalParameters($provider))
            ->redirect();
    }

    /**
     * Handle the callback from the provider.
     */
    public function callback(Request $request, string $provider)
    {
        $this->validateProvider($provider);

        try {
            // Debug logging
            \Illuminate\Support\Facades\Log::info("OAuth callback for provider: {$provider}");
            
            // For Instagram, validate state and use Facebook driver
            if ($provider === 'instagram') {
                // Validate state
                if ($request->state !== session('instagram_oauth_state')) {
                    return redirect()->route('dashboard')
                        ->with('error', 'Invalid OAuth state. Please try again.');
                }
                
                // Clear state from session
                session()->forget('instagram_oauth_state');
                
                // Use Facebook driver with Instagram redirect URI
                $socialUser = Socialite::driver('facebook')
                    ->redirectUrl(config('services.instagram.redirect'))
                    ->user();
                
                \Illuminate\Support\Facades\Log::info("Got Instagram user via Facebook driver");
            } else {
                $socialUser = Socialite::driver($provider)->user();
            }

            $user = Auth::user();

            // Debug logging
            \Illuminate\Support\Facades\Log::info("Processing OAuth callback for provider: {$provider}, User ID: {$user->id}");

            // For Facebook, we need to handle page selection
            if ($provider === 'facebook') {
                \Illuminate\Support\Facades\Log::info("Handling Facebook page connection");
                return $this->handleFacebookPageConnection($user, $socialUser);
            }

            // For Instagram, handle the connection directly
            if ($provider === 'instagram') {
                \Illuminate\Support\Facades\Log::info("Handling Instagram connection");
                return $this->handleInstagramConnection($user, $socialUser);
            }

            // Check if account already exists
            $existingAccount = SocialAccount::where('platform', $provider)
                ->where('platform_id', $socialUser->getId())
                ->first();

            if ($existingAccount) {
                // Update existing account
                $this->updateSocialAccount($existingAccount, $socialUser, $provider);
            } else {
                // Create new social account
                $this->createSocialAccount($user, $socialUser, $provider);
            }

            return redirect()->route('dashboard')
                ->with('success', "{$this->getProviderName($provider)} account connected successfully!");

        } catch (\Exception $e) {
            return redirect()->route('dashboard')
                ->with('error', "Failed to connect {$this->getProviderName($provider)} account: " . $e->getMessage());
        }
    }

    /**
     * Disconnect a social account.
     */
    public function disconnect(Request $request, string $provider)
    {
        $this->validateProvider($provider);

        $user = Auth::user();

        $socialAccount = SocialAccount::where('user_id', $user->id)
            ->where('platform', $provider)
            ->first();

        if (!$socialAccount) {
            return redirect()->back()
                ->with('error', 'No connected account found.');
        }

        $socialAccount->delete();

        return redirect()->back()
            ->with('success', "{$this->getProviderName($provider)} account disconnected successfully.");
    }

    /**
     * Get list of connected accounts for the user.
     */
    public function accounts(Request $request)
    {
        $user = Auth::user();

        $accounts = SocialAccount::where('user_id', $user->id)
            ->with('user')
            ->get()
            ->map(function ($account) {
                return [
                    'id' => $account->id,
                    'platform' => $account->platform,
                    'provider_name' => $this->getProviderName($account->platform),
                    'username' => $account->username,
                    'display_name' => $account->display_name,
                    'avatar' => $account->avatar,
                    'is_active' => $account->is_active,
                    'last_synced_at' => $account->last_synced_at?->diffForHumans(),
                    'connected_at' => $account->created_at->diffForHumans(),
                ];
            });

        return response()->json($accounts);
    }

    /**
     * Validate if the provider is supported.
     */
    private function validateProvider(string $provider): void
    {
        $supportedProviders = ['facebook', 'instagram', 'linkedin', 'twitter'];

        if (!in_array($provider, $supportedProviders)) {
            abort(404, 'Provider not supported.');
        }
    }

    /**
     * Get the required scopes for each provider.
     */
    private function getScopes(string $provider): array
    {
        return match ($provider) {
            'facebook' => [
                'pages_manage_posts',
                'pages_read_engagement',
                'instagram_basic',
                'instagram_content_publish',
                'public_profile',
                'email'
            ],
            'instagram' => [
                'instagram_basic',
                'instagram_content_publish',
                'public_profile'
            ],
            'linkedin' => [
                'r_liteprofile',
                'r_emailaddress',
                'w_member_social',
                'r_organization_social',
                'rw_organization_admin'
            ],
            'twitter' => [
                'tweet.read',
                'tweet.write',
                'users.read',
                'offline.access'
            ],
            default => [],
        };
    }

    /**
     * Get additional parameters for OAuth flow.
     */
    private function getAdditionalParameters(string $provider): array
    {
        return match ($provider) {
            'facebook' => [
                'response_type' => 'code',
                'display' => 'popup'
            ],
            'instagram' => [
                'response_type' => 'code',
                'display' => 'popup'
            ],
            'linkedin' => [
                'response_type' => 'code'
            ],
            'twitter' => [
                'response_type' => 'code',
                'code_challenge' => '',
                'code_challenge_method' => 'plain'
            ],
            default => [],
        };
    }

    /**
     * Get the display name for a provider.
     */
    private function getProviderName(string $provider): string
    {
        return match ($provider) {
            'facebook' => 'Facebook',
            'instagram' => 'Instagram',
            'linkedin' => 'LinkedIn',
            'twitter' => 'X (Twitter)',
            default => ucfirst($provider),
        };
    }

    /**
     * Create a new social account.
     */
    private function createSocialAccount(User $user, $socialUser, string $provider, ?string $userToken = null): void
    {
        // For Facebook pages, we already have the page access token
        if ($provider === 'facebook' && isset($socialUser->token) && method_exists($socialUser, 'getId') === false) {
            $tokens = [
                'access_token' => $socialUser->token,
                'refresh_token' => null,
                'expires_at' => null,
                'additional_data' => [
                    'token_type' => $socialUser->tokenType ?? 'Bearer',
                    'is_page' => true,
                ],
            ];
        } else {
            $tokens = $this->extractTokens($socialUser, $provider);
        }

        SocialAccount::create([
            'user_id' => $user->id,
            'platform' => $provider,
            'platform_id' => $socialUser->getId(),
            'username' => $socialUser->getNickname() ?? $socialUser->getEmail() ?? $socialUser->id,
            'display_name' => $socialUser->getName() ?? $socialUser->name,
            'email' => $socialUser->getEmail(),
            'avatar' => $socialUser->getAvatar(),
            'access_token' => $tokens['access_token'],
            'user_token' => $userToken,
            'refresh_token' => $tokens['refresh_token'] ?? null,
            'token_expires_at' => $tokens['expires_at'] ?? null,
            'additional_data' => $tokens['additional_data'] ?? null,
            'is_active' => true,
        ]);
    }

    /**
     * Update an existing social account.
     */
    private function updateSocialAccount(SocialAccount $account, $socialUser, string $provider, ?string $userToken = null): void
    {
        // For Facebook pages, we already have the page access token
        if ($provider === 'facebook' && isset($socialUser->token) && method_exists($socialUser, 'getId') === false) {
            $tokens = [
                'access_token' => $socialUser->token,
                'refresh_token' => null,
                'expires_at' => null,
                'additional_data' => [
                    'token_type' => $socialUser->tokenType ?? 'Bearer',
                    'is_page' => true,
                ],
            ];
        } else {
            $tokens = $this->extractTokens($socialUser, $provider);
        }

        $account->update([
            'username' => $socialUser->getNickname() ?? $socialUser->getEmail() ?? $socialUser->id,
            'display_name' => $socialUser->getName() ?? $socialUser->name,
            'email' => $socialUser->getEmail(),
            'avatar' => $socialUser->getAvatar(),
            'access_token' => $tokens['access_token'],
            'user_token' => $userToken ?? $account->user_token,
            'refresh_token' => $tokens['refresh_token'] ?? $account->refresh_token,
            'token_expires_at' => $tokens['expires_at'] ?? $account->token_expires_at,
            'additional_data' => $tokens['additional_data'] ?? $account->additional_data,
            'is_active' => true,
            'last_synced_at' => now(),
        ]);
    }

    /**
     * Extract tokens from social user response.
     */
    private function extractTokens($socialUser, string $provider): array
    {
        $tokens = [
            'access_token' => $socialUser->token,
            'refresh_token' => $socialUser->refreshToken,
            'expires_at' => null,
            'additional_data' => null,
        ];

        // Handle different token formats for different providers
        switch ($provider) {
            case 'facebook':
                if ($socialUser->expiresIn) {
                    $tokens['expires_at'] = now()->addSeconds($socialUser->expiresIn);
                }
                $tokens['additional_data'] = [
                    'token_type' => $socialUser->tokenType ?? 'Bearer',
                ];

                // Fetch Facebook pages
                try {
                    $pagesResponse = \Illuminate\Support\Facades\Http::get("https://graph.facebook.com/v18.0/me/accounts", [
                        'access_token' => $socialUser->token
                    ]);

                    if ($pagesResponse->successful()) {
                        $pagesData = $pagesResponse->json();
                        $tokens['additional_data']['pages'] = $pagesData['data'] ?? [];
                    }
                } catch (\Exception $e) {
                    \Illuminate\Support\Facades\Log::error('Failed to fetch Facebook pages: ' . $e->getMessage());
                }
                break;

            case 'instagram':
                if ($socialUser->expiresIn) {
                    $tokens['expires_at'] = now()->addSeconds($socialUser->expiresIn);
                }
                $tokens['additional_data'] = [
                    'token_type' => $socialUser->tokenType ?? 'Bearer',
                    'api_version' => 'graph.instagram.com',
                ];

                // Try to exchange short-lived token for long-lived token
                try {
                    $response = \Illuminate\Support\Facades\Http::get("https://graph.instagram.com/access_token", [
                        'grant_type' => 'ig_exchange_token',
                        'client_secret' => config('services.instagram.client_secret'),
                        'access_token' => $socialUser->token
                    ]);

                    if ($response->successful()) {
                        $data = $response->json();
                        if (isset($data['access_token'])) {
                            $tokens['access_token'] = $data['access_token'];
                            if (isset($data['expires_in'])) {
                                $tokens['expires_at'] = now()->addSeconds($data['expires_in']);
                            }
                            $tokens['additional_data']['long_lived'] = true;
                        }
                    }
                } catch (\Exception $e) {
                    \Illuminate\Support\Facades\Log::error('Failed to exchange Instagram token: ' . $e->getMessage());
                }
                break;

            case 'linkedin':
                if ($socialUser->expiresIn) {
                    $tokens['expires_at'] = now()->addSeconds($socialUser->expiresIn);
                }
                break;

            case 'twitter':
                // Twitter OAuth 2.0 tokens
                $tokens['additional_data'] = [
                    'scope' => $socialUser->approvedScopes ?? [],
                ];
                break;
        }

        return $tokens;
    }

    /**
     * Handle Facebook Page connection by showing page selection
     */
    private function handleFacebookPageConnection(User $user, $socialUser)
    {
        try {
            // Fetch Facebook pages
            $pagesResponse = \Illuminate\Support\Facades\Http::get("https://graph.facebook.com/v18.0/me/accounts", [
                'access_token' => $socialUser->token
            ]);

            if (!$pagesResponse->successful()) {
                throw new \Exception('Failed to fetch Facebook pages');
            }

            $pagesData = $pagesResponse->json();
            $pages = $pagesData['data'] ?? [];

            if (empty($pages)) {
                return redirect()->route('dashboard')
                    ->with('error', 'No Facebook pages found. Make sure you have admin access to at least one Facebook page.');
            }

            // Store the user token and pages in session for page selection
            session([
                'facebook_user_token' => $socialUser->token,
                'facebook_pages' => $pages,
                'facebook_user_info' => [
                    'id' => $socialUser->getId(),
                    'name' => $socialUser->getName(),
                    'email' => $socialUser->getEmail(),
                    'avatar' => $socialUser->getAvatar(),
                ]
            ]);

            // Redirect to page selection view
            return redirect()->route('facebook.page.selection');

        } catch (\Exception $e) {
            return redirect()->route('dashboard')
                ->with('error', "Failed to connect Facebook: " . $e->getMessage());
        }
    }

    /**
     * Save selected Facebook page as social account
     */
    public function saveFacebookPage(Request $request)
    {
        $request->validate([
            'page_id' => 'required|string',
            'page_name' => 'required|string',
            'page_access_token' => 'required|string',
        ]);

        $user = Auth::user();

        // Check if account already exists
        $existingAccount = SocialAccount::where('platform', 'facebook')
            ->where('platform_id', $request->page_id)
            ->first();

        // Get user info from session
        $userInfo = session('facebook_user_info', []);
        $userToken = session('facebook_user_token');

        // Create social user object for the page
        $pageSocialUser = new class {
            public function getId() { return $this->id; }
            public function getName() { return $this->name; }
            public function getNickname() { return $this->nickname; }
            public function getEmail() { return $this->email; }
            public function getAvatar() { return $this->avatar; }

            public $id;
            public $name;
            public $nickname;
            public $email;
            public $avatar;
            public $token;
            public $refreshToken;
            public $expiresIn;
            public $tokenType;
        };

        $pageSocialUser->id = $request->page_id;
        $pageSocialUser->name = $request->page_name;
        $pageSocialUser->nickname = null;
        $pageSocialUser->email = $userInfo['email'] ?? null;
        $pageSocialUser->avatar = null; // Pages don't have avatars like users
        $pageSocialUser->token = $request->page_access_token;
        $pageSocialUser->refreshToken = null;
        $pageSocialUser->expiresIn = null;
        $pageSocialUser->tokenType = 'Bearer';

        if ($existingAccount) {
            // Update existing account
            $this->updateSocialAccount($existingAccount, $pageSocialUser, 'facebook', $userToken);
        } else {
            // Create new social account
            $this->createSocialAccount($user, $pageSocialUser, 'facebook', $userToken);
        }

        // Clear session data
        session()->forget(['facebook_user_token', 'facebook_pages', 'facebook_user_info']);

        return redirect()->route('dashboard')
            ->with('success', 'Facebook page connected successfully!');
    }

    /**
     * Show Facebook page selection view
     */
    public function showPageSelection()
    {
        $pages = session('facebook_pages', []);
        $userInfo = session('facebook_user_info', []);

        if (empty($pages)) {
            return redirect()->route('dashboard')
                ->with('error', 'No Facebook pages found. Please try connecting again.');
        }

        // Get existing connected Facebook pages for this user
        $existingPageIds = SocialAccount::where('user_id', Auth::id())
            ->where('platform', 'facebook')
            ->pluck('platform_id')
            ->toArray();

        return inertia('Social/FacebookPageSelection', [
            'pages' => $pages,
            'userInfo' => $userInfo,
            'existingPageIds' => $existingPageIds
        ]);
    }

    /**
     * Handle Instagram connection
     */
    private function handleInstagramConnection(User $user, $socialUser)
    {
        try {
            \Illuminate\Support\Facades\Log::info("Starting Instagram connection for user: {$user->id}");
            // Since Instagram uses Facebook OAuth, we need to get Instagram accounts from Facebook pages
            // First, let's try to get Instagram accounts associated with this user's Facebook pages
            $response = \Illuminate\Support\Facades\Http::get("https://graph.facebook.com/v18.0/me/accounts", [
                'access_token' => $socialUser->token,
                'fields' => 'id,name,instagram_business_account'
            ]);

            if ($response->successful()) {
                $pagesData = $response->json();
                $pages = $pagesData['data'] ?? [];
                
                $instagramAccounts = [];
                foreach ($pages as $page) {
                    if (isset($page['instagram_business_account'])) {
                        // Get Instagram account details
                        $igResponse = \Illuminate\Support\Facades\Http::get("https://graph.facebook.com/v18.0/{$page['instagram_business_account']}", [
                            'access_token' => $socialUser->token,
                            'fields' => 'id,username,account_type,media_count,followers_count'
                        ]);
                        
                        if ($igResponse->successful()) {
                            $instagramAccounts[] = $igResponse->json();
                        }
                    }
                }

                if (!empty($instagramAccounts)) {
                    // Store the Instagram accounts and create a basic connection
                    $instagramData = $instagramAccounts[0]; // Use first account for now
                    
                    // Create social user object with Instagram data
                    $instagramSocialUser = new class {
                        public function getId() { return $this->id; }
                        public function getName() { return $this->name; }
                        public function getNickname() { return $this->nickname; }
                        public function getEmail() { return $this->email; }
                        public function getAvatar() { return $this->avatar; }

                        public $id;
                        public $name;
                        public $nickname;
                        public $email;
                        public $avatar;
                        public $token;
                        public $refreshToken;
                        public $expiresIn;
                        public $tokenType;
                    };

                    $instagramSocialUser->id = $instagramData['id'];
                    $instagramSocialUser->name = $instagramData['username'] ?? 'Instagram User';
                    $instagramSocialUser->nickname = $instagramData['username'] ?? null;
                    $instagramSocialUser->email = $socialUser->getEmail();
                    $instagramSocialUser->avatar = $socialUser->getAvatar();
                    $instagramSocialUser->token = $socialUser->token;
                    $instagramSocialUser->refreshToken = $socialUser->refreshToken;
                    $instagramSocialUser->expiresIn = $socialUser->expiresIn;
                    $instagramSocialUser->tokenType = $socialUser->tokenType ?? 'Bearer';

                    // Check if account already exists
                    $existingAccount = SocialAccount::where('platform', 'instagram')
                        ->where('platform_id', $instagramData['id'])
                        ->first();

                    if ($existingAccount) {
                        // Update existing account
                        $this->updateSocialAccount($existingAccount, $instagramSocialUser, 'instagram');
                    } else {
                        // Create new social account
                        $this->createSocialAccount($user, $instagramSocialUser, 'instagram');
                    }

                    return redirect()->route('dashboard')
                        ->with('success', 'Instagram account connected successfully! Found ' . count($instagramAccounts) . ' Instagram business account(s).');
                }
            }

            // If no Instagram business accounts found, create a basic connection anyway
            $instagramSocialUser = new class {
                public function getId() { return $this->id; }
                public function getName() { return $this->name; }
                public function getNickname() { return $this->nickname; }
                public function getEmail() { return $this->email; }
                public function getAvatar() { return $this->avatar; }

                public $id;
                public $name;
                public $nickname;
                public $email;
                public $avatar;
                public $token;
                public $refreshToken;
                public $expiresIn;
                public $tokenType;
            };

            $instagramSocialUser->id = $socialUser->getId();
            $instagramSocialUser->name = $socialUser->getName() ?? 'Instagram User';
            $instagramSocialUser->nickname = $socialUser->getNickname();
            $instagramSocialUser->email = $socialUser->getEmail();
            $instagramSocialUser->avatar = $socialUser->getAvatar();
            $instagramSocialUser->token = $socialUser->token;
            $instagramSocialUser->refreshToken = $socialUser->refreshToken;
            $instagramSocialUser->expiresIn = $socialUser->expiresIn;
            $instagramSocialUser->tokenType = $socialUser->tokenType ?? 'Bearer';

            // Check if account already exists
            $existingAccount = SocialAccount::where('platform', 'instagram')
                ->where('platform_id', $instagramSocialUser->id)
                ->first();

            if ($existingAccount) {
                // Update existing account
                $this->updateSocialAccount($existingAccount, $instagramSocialUser, 'instagram');
            } else {
                // Create new social account
                $this->createSocialAccount($user, $instagramSocialUser, 'instagram');
            }

            return redirect()->route('dashboard')
                ->with('success', 'Instagram account connected successfully! For full posting features, ensure your Instagram is a Business/Creator account linked to a Facebook page.');

        } catch (\Exception $e) {
            return redirect()->route('dashboard')
                ->with('error', "Failed to connect Instagram: " . $e->getMessage());
        }
    }
}
