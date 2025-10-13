<?php

use App\Http\Controllers\Analytics\AnalyticsController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/analytics', [AnalyticsController::class, 'index'])->name('analytics.dashboard');
    Route::get('/analytics/posts', [AnalyticsController::class, 'posts'])->name('analytics.posts');
    Route::get('/analytics/posts/{post}', [AnalyticsController::class, 'post'])->name('analytics.post');
    Route::get('/analytics/accounts', [AnalyticsController::class, 'accounts'])->name('analytics.accounts');
    Route::get('/analytics/accounts/{account}', [AnalyticsController::class, 'account'])->name('analytics.account');
    Route::get('/analytics/engagement', [AnalyticsController::class, 'engagement'])->name('analytics.engagement');
    Route::get('/analytics/team', [AnalyticsController::class, 'team'])->name('analytics.team');
    Route::post('/analytics/export', [AnalyticsController::class, 'export'])->name('analytics.export');
    Route::get('/analytics/download/{filename}', [AnalyticsController::class, 'download'])->name('analytics.download');
});