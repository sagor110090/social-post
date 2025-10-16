<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class HasSocialAccount
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next, string $provider = null): Response
    {
        $user = Auth::user();

        if (!$user) {
            return redirect()->route('login');
        }

        $query = $user->socialAccounts();

        if ($provider) {
            $query->where('platform', $provider);
        }

        if ($query->count() === 0) {
            $message = $provider 
                ? "You need to connect your {$this->getProviderName($provider)} account first."
                : "You need to connect at least one social media account first.";

            return redirect()->route('dashboard')
                ->with('error', $message);
        }

        return $next($request);
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