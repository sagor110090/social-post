<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Webhooks\FacebookWebhookController;
use App\Http\Controllers\Webhooks\InstagramWebhookController;
use App\Http\Controllers\Webhooks\TwitterWebhookController;
use App\Http\Controllers\Webhooks\LinkedInWebhookController;

/*
|--------------------------------------------------------------------------
| Webhook Routes
|--------------------------------------------------------------------------
|
| These routes handle incoming webhook events from social media platforms.
| All webhook endpoints are protected with signature verification and
| rate limiting to ensure security and reliability.
|
*/

// Apply comprehensive security middleware to webhook endpoints
Route::middleware(['webhook-security'])->group(function () {
    
    // Facebook Webhooks
    Route::prefix('facebook')->group(function () {
        Route::post('/', [FacebookWebhookController::class, 'handle'])
            ->name('webhooks.facebook.handle');
            
        Route::get('/', [FacebookWebhookController::class, 'handle'])
            ->name('webhooks.facebook.verify');
    });

    // Instagram Webhooks
    Route::prefix('instagram')->group(function () {
        Route::post('/', [InstagramWebhookController::class, 'handle'])
            ->name('webhooks.instagram.handle');
            
        Route::get('/', [InstagramWebhookController::class, 'handle'])
            ->name('webhooks.instagram.verify');
    });

    // Twitter/X Webhooks
    Route::prefix('twitter')->group(function () {
        Route::post('/', [TwitterWebhookController::class, 'handle'])
            ->name('webhooks.twitter.handle');
            
        Route::get('/', [TwitterWebhookController::class, 'handle'])
            ->name('webhooks.twitter.verify');
    });

    // LinkedIn Webhooks
    Route::prefix('linkedin')->group(function () {
        Route::post('/', [LinkedInWebhookController::class, 'handle'])
            ->name('webhooks.linkedin.handle');
            
        Route::get('/', [LinkedInWebhookController::class, 'handle'])
            ->name('webhooks.linkedin.verify');
    });

    // Generic webhook endpoint for testing and debugging
    Route::post('test', function (\Illuminate\Http\Request $request) {
        \Illuminate\Support\Facades\Log::info('Test webhook received', [
            'payload' => $request->all(),
            'headers' => $request->headers->all(),
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Test webhook received',
            'timestamp' => now()->toISOString(),
        ]);
    })->name('webhooks.test');

    // Webhook health check endpoint
    Route::get('health', function () {
        return response()->json([
            'status' => 'healthy',
            'timestamp' => now()->toISOString(),
            'version' => config('app.version', '1.0.0'),
        ]);
    })->name('webhooks.health');
});

// Webhook management routes (protected)
Route::middleware(['auth:sanctum', 'webhook-management'])->prefix('manage')->group(function () {
    
    // List webhook configurations
    Route::get('configs', function () {
        $configs = \App\Models\WebhookConfig::with(['socialAccount'])
            ->whereHas('socialAccount', function ($query) {
                $query->where('user_id', auth()->id());
            })
            ->get();

        return response()->json($configs);
    })->name('webhooks.manage.configs');

    // Create webhook configuration
    Route::post('configs', function (\Illuminate\Http\Request $request) {
        $validated = $request->validate([
            'social_account_id' => 'required|exists:social_accounts,id',
            'events' => 'required|array|min:1',
            'metadata' => 'nullable|array',
        ]);

        $socialAccount = \App\Models\SocialAccount::findOrFail($validated['social_account_id']);
        
        // Ensure user owns the social account
        if ($socialAccount->user_id !== auth()->id()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $config = \App\Models\WebhookConfig::create([
            'social_account_id' => $validated['social_account_id'],
            'webhook_url' => route('webhooks.' . $socialAccount->platform . '.handle'),
            'secret' => \Illuminate\Support\Str::random(64),
            'events' => $validated['events'],
            'metadata' => $validated['metadata'] ?? [],
            'is_active' => true,
        ]);

        return response()->json($config, 201);
    })->name('webhooks.manage.configs.store');

    // Update webhook configuration
    Route::put('configs/{config}', function (\App\Models\WebhookConfig $config, \Illuminate\Http\Request $request) {
        // Ensure user owns the config
        if ($config->socialAccount->user_id !== auth()->id()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'events' => 'required|array|min:1',
            'is_active' => 'boolean',
            'metadata' => 'nullable|array',
        ]);

        $config->update($validated);

        return response()->json($config);
    })->name('webhooks.manage.configs.update');

    // Delete webhook configuration
    Route::delete('configs/{config}', function (\App\Models\WebhookConfig $config) {
        // Ensure user owns the config
        if ($config->socialAccount->user_id !== auth()->id()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $config->delete();

        return response()->json(null, 204);
    })->name('webhooks.manage.configs.delete');

    // Regenerate webhook secret
    Route::post('configs/{config}/regenerate-secret', function (\App\Models\WebhookConfig $config) {
        // Ensure user owns the config
        if ($config->socialAccount->user_id !== auth()->id()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $config->update([
            'secret' => \Illuminate\Support\Str::random(64),
        ]);

        return response()->json([
            'secret' => $config->secret,
            'message' => 'Webhook secret regenerated successfully',
        ]);
    })->name('webhooks.manage.configs.regenerate-secret');

    // List webhook events
    Route::get('events', function (\Illuminate\Http\Request $request) {
        $query = \App\Models\WebhookEvent::with(['socialAccount'])
            ->whereHas('socialAccount', function ($query) {
                $query->where('user_id', auth()->id());
            });

        // Filter by platform
        if ($request->has('platform')) {
            $query->where('platform', $request->platform);
        }

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Filter by date range
        if ($request->has('start_date')) {
            $query->whereDate('received_at', '>=', $request->start_date);
        }

        if ($request->has('end_date')) {
            $query->whereDate('received_at', '<=', $request->end_date);
        }

        $events = $query->orderBy('received_at', 'desc')
            ->paginate($request->get('per_page', 50));

        return response()->json($events);
    })->name('webhooks.manage.events');

    // Get webhook event details
    Route::get('events/{event}', function (\App\Models\WebhookEvent $event) {
        // Ensure user owns the event
        if ($event->socialAccount->user_id !== auth()->id()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        return response()->json($event);
    })->name('webhooks.manage.events.show');

    // Retry failed webhook event
    Route::post('events/{event}/retry', function (\App\Models\WebhookEvent $event) {
        // Ensure user owns the event
        if ($event->socialAccount->user_id !== auth()->id()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        if (!$event->canRetry()) {
            return response()->json([
                'error' => 'Event cannot be retried',
                'reason' => 'Event is not in failed state or has exceeded retry limit',
            ], 422);
        }

        // Reset event to pending and dispatch job
        $event->update([
            'status' => 'pending',
            'error_message' => null,
        ]);

        \App\Jobs\ProcessWebhookEventJob::dispatch($event);

        return response()->json([
            'message' => 'Webhook event queued for retry',
            'event_id' => $event->id,
        ]);
    })->name('webhooks.manage.events.retry');

    // Get webhook delivery metrics
    Route::get('metrics', function (\Illuminate\Http\Request $request) {
        $query = \App\Models\WebhookDeliveryMetric::with(['webhookConfig.socialAccount'])
            ->whereHas('webhookConfig.socialAccount', function ($query) {
                $query->where('user_id', auth()->id());
            });

        // Filter by platform
        if ($request->has('platform')) {
            $query->whereHas('webhookConfig.socialAccount', function ($query) use ($request) {
                $query->where('platform', $request->platform);
            });
        }

        // Filter by date range
        if ($request->has('start_date')) {
            $query->whereDate('delivered_at', '>=', $request->start_date);
        }

        if ($request->has('end_date')) {
            $query->whereDate('delivered_at', '<=', $request->end_date);
        }

        $metrics = $query->orderBy('delivered_at', 'desc')
            ->paginate($request->get('per_page', 50));

        return response()->json($metrics);
    })->name('webhooks.manage.metrics');

    // Get webhook statistics
    Route::get('stats', function () {
        $stats = [
            'total_configs' => \App\Models\WebhookConfig::whereHas('socialAccount', function ($query) {
                $query->where('user_id', auth()->id());
            })->count(),
            
            'active_configs' => \App\Models\WebhookConfig::whereHas('socialAccount', function ($query) {
                $query->where('user_id', auth()->id());
            })->where('is_active', true)->count(),
            
            'total_events' => \App\Models\WebhookEvent::whereHas('socialAccount', function ($query) {
                $query->where('user_id', auth()->id());
            })->count(),
            
            'pending_events' => \App\Models\WebhookEvent::whereHas('socialAccount', function ($query) {
                $query->where('user_id', auth()->id());
            })->where('status', 'pending')->count(),
            
            'failed_events' => \App\Models\WebhookEvent::whereHas('socialAccount', function ($query) {
                $query->where('user_id', auth()->id());
            })->where('status', 'failed')->count(),
            
            'processed_events' => \App\Models\WebhookEvent::whereHas('socialAccount', function ($query) {
                $query->where('user_id', auth()->id());
            })->where('status', 'processed')->count(),
            
            'events_by_platform' => \App\Models\WebhookEvent::selectRaw('platform, COUNT(*) as count')
                ->whereHas('socialAccount', function ($query) {
                    $query->where('user_id', auth()->id());
                })
                ->groupBy('platform')
                ->get(),
            
            'recent_events' => \App\Models\WebhookEvent::with(['socialAccount'])
                ->whereHas('socialAccount', function ($query) {
                    $query->where('user_id', auth()->id());
                })
                ->orderBy('received_at', 'desc')
                ->limit(10)
                ->get(),
        ];

        return response()->json($stats);
    })->name('webhooks.manage.stats');
});