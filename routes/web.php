<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Livewire\Volt\Volt;

Volt::route('/', 'pages.home')->name('home');
Volt::route('/about', 'pages.about')->name('about');
Volt::route('/features', 'pages.features')->name('features');
Volt::route('/contact', 'pages.contact')->name('contact');
Volt::route('/privacy-policy', 'pages.privacy-policy')->name('privacy-policy');
Volt::route('/terms', 'pages.terms')->name('terms');



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
require __DIR__.'/admin.php';

// API routes
Route::middleware(['auth', 'verified'])->prefix('api')->group(function () {
    Route::get('/social-accounts', [App\Http\Controllers\SocialAccountController::class, 'index']);
});
