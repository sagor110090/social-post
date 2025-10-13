<?php

namespace App\Http\Controllers\Social;

use App\Http\Controllers\Controller;
use App\Models\SocialAccount;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SocialAccountController extends Controller
{
    /**
     * Display the social accounts management page.
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        
        $accounts = $user->socialAccounts()
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

        return inertia('Social/Accounts', [
            'accounts' => $accounts,
            'flash' => [
                'success' => $request->session()->get('success'),
                'error' => $request->session()->get('error'),
            ]
        ]);
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
}