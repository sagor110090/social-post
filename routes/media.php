<?php

use App\Http\Controllers\Media\MediaController;
use Illuminate\Support\Facades\Route;

// Media Routes
Route::prefix('media')->middleware('auth')->group(function () {
    // Upload image
    Route::post('/upload-image', [MediaController::class, 'uploadImage'])->name('media.upload-image');
    
    // Upload video
    Route::post('/upload-video', [MediaController::class, 'uploadVideo'])->name('media.upload-video');
    
    // Get media library
    Route::get('/library', [MediaController::class, 'getMediaLibrary'])->name('media.library');
    
    // Delete media
    Route::delete('/{mediaId}', [MediaController::class, 'deleteMedia'])->name('media.delete');
});