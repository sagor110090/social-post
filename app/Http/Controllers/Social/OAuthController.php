<?php

namespace App\Http\Controllers\Social;

use App\Http\Controllers\Controller;
use App\Models\SocialAccount;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;

class OAuthController extends Controller
{
    /**
     * Redirect to the provider's authentication page.
     */
    public function redirect(string $provider)
    {
        $this->validateProvider($provider);
        
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
            $socialUser = Socialite::driver($provider)->user();
            
            $user = Auth::user();
            
            // Check if account already exists
            $existingAccount = SocialAccount::where('provider', $provider)
                ->where('provider_id', $socialUser->getId())
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
            ->where('provider', $provider)
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
                    'provider' => $account->provider,
                    'provider_name' => $this->getProviderName($account->provider),
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
    private function createSocialAccount(User $user, $socialUser, string $provider): void
    {
        $tokens = $this->extractTokens($socialUser, $provider);
        
        SocialAccount::create([
            'user_id' => $user->id,
            'provider' => $provider,
            'provider_id' => $socialUser->getId(),
            'username' => $socialUser->getNickname() ?? $socialUser->getEmail(),
            'display_name' => $socialUser->getName(),
            'email' => $socialUser->getEmail(),
            'avatar' => $socialUser->getAvatar(),
            'access_token' => $tokens['access_token'],
            'refresh_token' => $tokens['refresh_token'] ?? null,
            'token_expires_at' => $tokens['expires_at'] ?? null,
            'additional_data' => $tokens['additional_data'] ?? null,
            'is_active' => true,
        ]);
    }

    /**
     * Update an existing social account.
     */
    private function updateSocialAccount(SocialAccount $account, $socialUser, string $provider): void
    {
        $tokens = $this->extractTokens($socialUser, $provider);
        
        $account->update([
            'username' => $socialUser->getNickname() ?? $socialUser->getEmail(),
            'display_name' => $socialUser->getName(),
            'email' => $socialUser->getEmail(),
            'avatar' => $socialUser->getAvatar(),
            'access_token' => $tokens['access_token'],
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
            case 'instagram':
                if ($socialUser->expiresIn) {
                    $tokens['expires_at'] = now()->addSeconds($socialUser->expiresIn);
                }
                $tokens['additional_data'] = [
                    'token_type' => $socialUser->tokenType ?? 'Bearer',
                ];
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
}