<?php

use App\Http\Controllers\Social\FacebookController;
use App\Http\Controllers\Social\InstagramController;
use App\Http\Controllers\Social\LinkedInController;
use App\Http\Controllers\Social\OAuthController;
use App\Http\Controllers\Social\PostController;
use App\Http\Controllers\Social\ScheduledPostController;
use App\Http\Controllers\Social\SocialAccountController;
use App\Http\Controllers\Social\SocialPostController;
use App\Http\Controllers\Social\TwitterController;
use Illuminate\Support\Facades\Route;

// OAuth Routes
Route::prefix('oauth')->group(function () {
    // Redirect to provider
    Route::get('/{provider}', [OAuthController::class, 'redirect'])->name('oauth.redirect');
    
    // Callback from provider
    Route::get('/{provider}/callback', [OAuthController::class, 'callback'])->name('oauth.callback');
    
    // Disconnect account
    Route::delete('/{provider}/disconnect', [OAuthController::class, 'disconnect'])->name('oauth.disconnect');
    
    // Get connected accounts
    Route::get('/accounts', [OAuthController::class, 'accounts'])->name('oauth.accounts');
});

// Facebook Routes
Route::prefix('facebook')->middleware('auth')->group(function () {
    // Get Facebook pages
    Route::get('/pages', [FacebookController::class, 'getPages'])->name('facebook.pages');
    
    // Post to Facebook page
    Route::post('/pages/{pageId}/post', [FacebookController::class, 'postToPage'])->name('facebook.post');
    
    // Get page insights
    Route::get('/pages/{pageId}/insights', [FacebookController::class, 'getPageInsights'])->name('facebook.insights');
    
    // Refresh page tokens
    Route::post('/refresh-tokens', [FacebookController::class, 'refreshPageTokens'])->name('facebook.refresh-tokens');
});

// Instagram Routes
Route::prefix('instagram')->middleware('auth')->group(function () {
    // Get Instagram business accounts
    Route::get('/accounts', [InstagramController::class, 'getBusinessAccounts'])->name('instagram.accounts');
    
    // Create media container
    Route::post('/accounts/{accountId}/media', [InstagramController::class, 'createMediaContainer'])->name('instagram.create-media');
    
    // Publish media
    Route::post('/accounts/{accountId}/publish', [InstagramController::class, 'publishMedia'])->name('instagram.publish');
    
    // Get media insights
    Route::get('/media/{mediaId}/insights', [InstagramController::class, 'getMediaInsights'])->name('instagram.media-insights');
    
    // Get user insights
    Route::get('/accounts/{accountId}/insights', [InstagramController::class, 'getUserInsights'])->name('instagram.user-insights');
    
    // Upload image
    Route::post('/upload-image', [InstagramController::class, 'uploadImage'])->name('instagram.upload-image');
});

// LinkedIn Routes
Route::prefix('linkedin')->middleware('auth')->group(function () {
    // Get LinkedIn profile
    Route::get('/profile', [LinkedInController::class, 'getProfile'])->name('linkedin.profile');
    
    // Post to LinkedIn profile
    Route::post('/post', [LinkedInController::class, 'postToProfile'])->name('linkedin.post');
    
    // Post with image to LinkedIn
    Route::post('/post-with-image', [LinkedInController::class, 'postWithImage'])->name('linkedin.post-image');
    
    // Get post statistics
    Route::get('/posts/{postId}/stats', [LinkedInController::class, 'getPostStats'])->name('linkedin.post-stats');
    
    // Get network statistics
    Route::get('/network-stats', [LinkedInController::class, 'getNetworkStats'])->name('linkedin.network-stats');
});

// Twitter Routes
Route::prefix('twitter')->middleware('auth')->group(function () {
    // Get Twitter profile
    Route::get('/profile', [TwitterController::class, 'getProfile'])->name('twitter.profile');
    
    // Post tweet
    Route::post('/tweet', [TwitterController::class, 'postTweet'])->name('twitter.tweet');
    
    // Post tweet with media
    Route::post('/tweet-with-media', [TwitterController::class, 'postTweetWithMedia'])->name('twitter.tweet-media');
    
    // Upload media
    Route::post('/upload-media', [TwitterController::class, 'uploadMedia'])->name('twitter.upload-media');
    
    // Get tweet metrics
    Route::get('/tweets/{tweetId}/metrics', [TwitterController::class, 'getTweetMetrics'])->name('twitter.tweet-metrics');
    
    // Get user timeline
    Route::get('/timeline', [TwitterController::class, 'getUserTimeline'])->name('twitter.timeline');
});

// Social Account Management Routes
Route::prefix('social')->middleware('auth')->group(function () {
    // Accounts management
    Route::get('/accounts', [SocialAccountController::class, 'index'])->name('social.accounts');
    
    // Post creation
    Route::get('/posts/create', [PostController::class, 'create'])->name('social.posts.create');
    
    // Post history
    Route::get('/posts/history', [PostController::class, 'history'])->name('social.posts.history');
    
    // Post details
    Route::get('/posts/{post}', [PostController::class, 'show'])->name('social.posts.show');

    // Scheduled Posts Management
    Route::prefix('scheduled-posts')->group(function () {
        // Get upcoming posts
        Route::get('/upcoming', [ScheduledPostController::class, 'upcoming'])->name('social.scheduled-posts.upcoming');
        
        // Get calendar data
        Route::get('/calendar', [ScheduledPostController::class, 'calendar'])->name('social.scheduled-posts.calendar');
        
        // Get scheduling stats
        Route::get('/stats', [ScheduledPostController::class, 'stats'])->name('social.scheduled-posts.stats');
        
        // Get optimal posting times
        Route::get('/optimal-times', [ScheduledPostController::class, 'optimalTimes'])->name('social.scheduled-posts.optimal-times');
        
        // Get scheduled post details
        Route::get('/{scheduledPost}', [ScheduledPostController::class, 'show'])->name('social.scheduled-posts.show');
        
        // Reschedule post
        Route::patch('/{scheduledPost}/reschedule', [ScheduledPostController::class, 'reschedule'])->name('social.scheduled-posts.reschedule');
        
        // Cancel scheduled post
        Route::delete('/{scheduledPost}/cancel', [ScheduledPostController::class, 'cancel'])->name('social.scheduled-posts.cancel');
        
        // Publish immediately
        Route::post('/{scheduledPost}/publish-now', [ScheduledPostController::class, 'publishNow'])->name('social.scheduled-posts.publish-now');
    });
});

// Social Post API Routes
Route::prefix('posts')->middleware('auth')->group(function () {
    // Publish post
    Route::post('/publish', [SocialPostController::class, 'publish'])->name('social.posts.publish');
    
    // Get character limits
    Route::get('/character-limits', [SocialPostController::class, 'getCharacterLimits'])->name('social.posts.character-limits');
    
    // Validate content
    Route::post('/validate', [SocialPostController::class, 'validateContent'])->name('social.posts.validate');
    
    // Get available platforms
    Route::get('/platforms', [SocialPostController::class, 'getAvailablePlatforms'])->name('social.posts.platforms');
    
    // Get post history (API)
    Route::get('/history', [SocialPostController::class, 'history'])->name('social.posts.history.api');
    
    // Delete post
    Route::delete('/{post}', [SocialPostController::class, 'delete'])->name('social.posts.delete');
});