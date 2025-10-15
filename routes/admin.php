<?php

use App\Http\Controllers\Admin\AdminController;
use App\Http\Controllers\Admin\WebhookSecurityController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/dashboard', [AdminController::class, 'dashboard'])->name('dashboard');
    Route::get('/users', [AdminController::class, 'users'])->name('users');
    Route::get('/users/{user}', [AdminController::class, 'user'])->name('user');
    Route::patch('/users/{user}', [AdminController::class, 'updateUser'])->name('users.update');
    Route::delete('/users/{user}', [AdminController::class, 'deleteUser'])->name('users.delete');
    Route::post('/users/{user}/impersonate', [AdminController::class, 'impersonate'])->name('users.impersonate');
    Route::post('/stop-impersonate', [AdminController::class, 'stopImpersonate'])->name('stop-impersonate');
    
    Route::get('/posts', [AdminController::class, 'posts'])->name('posts');
    Route::delete('/posts/{post}', [AdminController::class, 'deletePost'])->name('posts.delete');
    
    Route::get('/subscriptions', [AdminController::class, 'subscriptions'])->name('subscriptions');
    Route::patch('/subscriptions/{subscription}', [AdminController::class, 'updateSubscription'])->name('subscriptions.update');
    Route::delete('/subscriptions/{subscription}', [AdminController::class, 'cancelSubscription'])->name('subscriptions.cancel');
    
    Route::get('/analytics', [AdminController::class, 'analytics'])->name('analytics');
    
    // Webhook Security Management
    Route::prefix('webhooks/security')->name('webhooks.security.')->group(function () {
        Route::get('/stats', [WebhookSecurityController::class, 'stats'])->name('stats');
        Route::get('/health', [WebhookSecurityController::class, 'healthCheck'])->name('health');
        Route::get('/config', [WebhookSecurityController::class, 'config'])->name('config');
        Route::put('/config', [WebhookSecurityController::class, 'updateConfig'])->name('config.update');
        
        Route::get('/blocked-ips', [WebhookSecurityController::class, 'blockedIps'])->name('blocked-ips');
        Route::post('/block-ip', [WebhookSecurityController::class, 'blockIp'])->name('block-ip');
        Route::post('/unblock-ip', [WebhookSecurityController::class, 'unblockIp'])->name('unblock-ip');
        
        Route::delete('/violations', [WebhookSecurityController::class, 'clearViolations'])->name('violations.clear');
        Route::get('/events', [WebhookSecurityController::class, 'recentEvents'])->name('events');
        Route::get('/export', [WebhookSecurityController::class, 'exportReport'])->name('export');
    });
});