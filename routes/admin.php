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
    
 
});
