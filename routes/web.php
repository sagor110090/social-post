<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome');
})->name('home');

Route::get('dashboard', [\App\Http\Controllers\Dashboard\DashboardController::class, 'index'])
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
require __DIR__.'/ai.php';
require __DIR__.'/social.php';
require __DIR__.'/media.php';
require __DIR__.'/calendar.php';
require __DIR__.'/analytics.php';
require __DIR__.'/webhooks.php';
require __DIR__.'/admin.php';
require __DIR__.'/webhooks.php';
require __DIR__.'/monitoring.php';

// API routes
Route::middleware(['auth', 'verified'])->prefix('api')->group(function () {
    Route::get('/social-accounts', [App\Http\Controllers\SocialAccountController::class, 'index']);
});
