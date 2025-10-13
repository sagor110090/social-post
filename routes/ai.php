<?php

use App\Http\Controllers\AI\AIController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {
    // AI Generator Routes
    Route::get('/ai/generator', [AIController::class, 'index'])->name('ai.generator');
    
    // API Routes for AI functionality
    Route::prefix('api/ai')->group(function () {
        Route::post('/generate', [AIController::class, 'generate'])->name('api.ai.generate');
        Route::post('/improve', [AIController::class, 'improve'])->name('api.ai.improve');
        Route::post('/image-ideas', [AIController::class, 'imageIdeas'])->name('api.ai.image-ideas');
        Route::get('/templates', [AIController::class, 'templates'])->name('api.ai.templates');
        Route::get('/history', [AIController::class, 'history'])->name('api.ai.history');
        Route::post('/templates', [AIController::class, 'saveTemplate'])->name('api.ai.templates.save');
    });
});