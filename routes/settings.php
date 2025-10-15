<?php

use App\Http\Controllers\Settings\PasswordController;
use App\Http\Controllers\Settings\ProfileController;
use App\Http\Controllers\Settings\TwoFactorAuthenticationController;
use App\Http\Controllers\WebhookManageController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::middleware('auth')->group(function () {
    Route::redirect('settings', '/settings/profile');

    Route::get('settings/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('settings/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('settings/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('settings/password', [PasswordController::class, 'edit'])->name('password.edit');

    Route::put('settings/password', [PasswordController::class, 'update'])
        ->middleware('throttle:6,1')
        ->name('password.update');

    Route::get('settings/appearance', function () {
        return Inertia::render('settings/Appearance');
    })->name('appearance.edit');

    Route::get('settings/two-factor', [TwoFactorAuthenticationController::class, 'show'])
        ->name('two-factor.show');

    // Webhook Management Routes
    Route::prefix('settings/webhooks')->group(function () {
        Route::get('/', [WebhookManageController::class, 'index'])->name('webhooks.index');
        Route::get('/configs', [WebhookManageController::class, 'configs'])->name('webhooks.configs');
        Route::get('/events', [WebhookManageController::class, 'events'])->name('webhooks.events');
        Route::get('/analytics', [WebhookManageController::class, 'analytics'])->name('webhooks.analytics');
        Route::get('/security', [WebhookManageController::class, 'security'])->name('webhooks.security');
        
        // API endpoints for webhook management
        Route::prefix('api')->group(function () {
            Route::get('/stats', [WebhookManageController::class, 'stats']);
            Route::get('/analytics', [WebhookManageController::class, 'getAnalytics']);
            Route::get('/analytics/export', [WebhookManageController::class, 'exportAnalytics']);
            Route::get('/events/export', [WebhookManageController::class, 'exportEvents']);
            Route::get('/security/events', [WebhookManageController::class, 'getSecurityEvents']);
            Route::get('/security/settings', [WebhookManageController::class, 'getSecuritySettings']);
            Route::put('/security/settings', [WebhookManageController::class, 'updateSecuritySettings']);
            Route::post('/security/events/{event}/resolve', [WebhookManageController::class, 'resolveSecurityEvent']);
        });
    });
});
