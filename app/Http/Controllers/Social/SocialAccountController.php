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
                $data = [
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

                // Add Facebook pages if available
                if ($account->platform === 'facebook' && isset($account->additional_data['pages'])) {
                    $data['pages'] = collect($account->additional_data['pages'])->map(function ($page) {
                        return [
                            'id' => $page['id'],
                            'name' => $page['name'],
                            'username' => $page['username'] ?? null,
                            'category' => $page['category'] ?? null,
                            'fan_count' => $page['fan_count'] ?? 0,
                            'followers_count' => $page['followers_count'] ?? 0,
                            'access_token' => isset($page['access_token']),
                            'tasks' => $page['tasks'] ?? [],
                        ];
                    })->toArray();
                }

                return $data;
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
