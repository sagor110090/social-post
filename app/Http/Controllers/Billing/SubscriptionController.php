<?php

namespace App\Http\Controllers\Billing;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Laravel\Cashier\Cashier;
use Laravel\Cashier\Subscription;

class SubscriptionController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        $subscription = $user->subscription;
        
        $plans = [
            [
                'name' => 'Basic',
                'price_id' => 'price_basic',
                'amount' => 9.99,
                'interval' => 'month',
                'features' => [
                    '30 posts per month',
                    '3 social accounts',
                    '100 AI generations',
                    'Basic analytics',
                    'Email support'
                ]
            ],
            [
                'name' => 'Pro',
                'price_id' => 'price_pro',
                'amount' => 29.99,
                'interval' => 'month',
                'features' => [
                    '300 posts per month',
                    '10 social accounts',
                    '1,000 AI generations',
                    'Advanced analytics',
                    'Priority support',
                    'Team collaboration (5 members)'
                ],
                'popular' => true
            ],
            [
                'name' => 'Enterprise',
                'price_id' => 'price_enterprise',
                'amount' => 99.99,
                'interval' => 'month',
                'features' => [
                    'Unlimited posts',
                    'Unlimited social accounts',
                    'Unlimited AI generations',
                    'Custom analytics',
                    'Dedicated support',
                    'Unlimited team members',
                    'Custom integrations'
                ]
            ]
        ];

        return Inertia::render('Billing/Subscription', [
            'subscription' => $subscription,
            'plans' => $plans,
            'intent' => $user->createSetupIntent(),
        ]);
    }

    public function subscribe(Request $request)
    {
        $request->validate([
            'price_id' => 'required|string',
            'payment_method_id' => 'required|string',
        ]);

        $user = Auth::user();
        
        try {
            $user->createOrGetStripeCustomer();
            
            $user->addPaymentMethod($request->payment_method_id);
            
            $subscription = $user->newSubscription('default', $request->price_id)
                ->create($request->payment_method_id, [
                    'email' => $user->email,
                ]);

            return redirect()->route('billing.subscription')->with('success', 'Subscription created successfully!');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Unable to create subscription: ' . $e->getMessage()]);
        }
    }

    public function swap(Request $request)
    {
        $request->validate([
            'price_id' => 'required|string',
        ]);

        $user = Auth::user();
        $subscription = $user->subscription;

        if (!$subscription) {
            return back()->withErrors(['error' => 'No active subscription found.']);
        }

        try {
            $subscription->swap($request->price_id);
            
            return redirect()->route('billing.subscription')->with('success', 'Subscription updated successfully!');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Unable to update subscription: ' . $e->getMessage()]);
        }
    }

    public function cancel(Request $request)
    {
        $user = Auth::user();
        $subscription = $user->subscription;

        if (!$subscription) {
            return back()->withErrors(['error' => 'No active subscription found.']);
        }

        try {
            if ($request->has('immediately')) {
                $subscription->cancelNow();
            } else {
                $subscription->cancel();
            }
            
            return redirect()->route('billing.subscription')->with('success', 'Subscription cancelled successfully!');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Unable to cancel subscription: ' . $e->getMessage()]);
        }
    }

    public function resume()
    {
        $user = Auth::user();
        $subscription = $user->subscription;

        if (!$subscription || !$subscription->onGracePeriod()) {
            return back()->withErrors(['error' => 'No subscription to resume.']);
        }

        try {
            $subscription->resume();
            
            return redirect()->route('billing.subscription')->with('success', 'Subscription resumed successfully!');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Unable to resume subscription: ' . $e->getMessage()]);
        }
    }

    public function updatePaymentMethod(Request $request)
    {
        $request->validate([
            'payment_method_id' => 'required|string',
        ]);

        $user = Auth::user();

        try {
            $user->addPaymentMethod($request->payment_method_id);
            
            if ($user->subscription) {
                $user->subscription->updateStripeSubscription([
                    'default_payment_method' => $request->payment_method_id,
                ]);
            }
            
            return redirect()->route('billing.subscription')->with('success', 'Payment method updated successfully!');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Unable to update payment method: ' . $e->getMessage()]);
        }
    }

    public function invoices()
    {
        $user = Auth::user();
        
        try {
            $invoices = $user->invoices();
            
            return Inertia::render('Billing/Invoices', [
                'invoices' => $invoices->map(function ($invoice) {
                    return [
                        'id' => $invoice->id,
                        'date' => $invoice->date()->toFormattedDateString(),
                        'total' => $invoice->total(),
                        'status' => $invoice->status,
                        'url' => $invoice->hosted_invoice_url,
                    ];
                }),
            ]);
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Unable to fetch invoices: ' . $e->getMessage()]);
        }
    }
}