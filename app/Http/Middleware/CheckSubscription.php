<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckSubscription
{
    public function handle(Request $request, Closure $next, string $feature = null): Response
    {
        $user = $request->user();
        
        if (!$user) {
            return redirect()->route('login');
        }

        $subscription = $user->subscription;

        // Check if user has any active subscription
        if (!$subscription || !$subscription->isActive()) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Active subscription required.',
                    'redirect' => route('billing.subscription')
                ], 402);
            }
            
            return redirect()->route('billing.subscription')
                ->with('error', 'An active subscription is required to access this feature.');
        }

        // Check specific feature access
        if ($feature) {
            switch ($feature) {
                case 'ai':
                    if (!$subscription->canAccessAI()) {
                        return $this->handleFeatureAccessDenied($request, 'AI features require a Pro or Enterprise subscription.');
                    }
                    break;
                    
                case 'analytics':
                    if (!$subscription->canAccessAnalytics()) {
                        return $this->handleFeatureAccessDenied($request, 'Analytics require a Pro or Enterprise subscription.');
                    }
                    break;
                    
                case 'teams':
                    if (!$subscription->canCreateTeams()) {
                        return $this->handleFeatureAccessDenied($request, 'Team features require an Enterprise subscription.');
                    }
                    break;
                    
                case 'social-accounts':
                    $maxAccounts = $subscription->getMaxSocialAccounts();
                    $currentAccounts = $user->socialAccounts()->where('is_active', true)->count();
                    
                    if ($currentAccounts >= $maxAccounts) {
                        return $this->handleFeatureAccessDenied($request, "You've reached the maximum number of social accounts for your subscription. Upgrade to add more.");
                    }
                    break;
            }
        }

        return $next($request);
    }

    private function handleFeatureAccessDenied(Request $request, string $message)
    {
        if ($request->expectsJson()) {
            return response()->json([
                'message' => $message,
                'redirect' => route('billing.subscription')
            ], 402);
        }
        
        return redirect()->route('billing.subscription')
            ->with('error', $message);
    }
}