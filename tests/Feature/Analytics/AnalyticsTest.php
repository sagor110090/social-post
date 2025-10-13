<?php

use App\Models\User;
use App\Models\Post;
use App\Models\PostAnalytics;
use App\Models\SocialAccount;
use App\Models\Subscription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

test('user with active subscription can view analytics dashboard', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    // Create active subscription
    Subscription::factory()->create([
        'user_id' => $user->id,
        'stripe_status' => 'active',
        'stripe_price' => 'price_pro',
    ]);

    $response = $this->get('/analytics');

    $response->assertStatus(200);
});

test('user without subscription cannot access analytics', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $response = $this->get('/analytics');

    $response->assertRedirect('/subscription');
});

test('user can fetch analytics posts data', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    // Create active subscription
    Subscription::factory()->create([
        'user_id' => $user->id,
        'stripe_status' => 'active',
        'stripe_price' => 'price_pro',
    ]);

    $socialAccount = SocialAccount::factory()->create(['user_id' => $user->id]);
    $post = Post::factory()->create(['user_id' => $user->id]);
    PostAnalytics::factory()->create([
        'post_id' => $post->id,
        'social_account_id' => $socialAccount->id,
        'likes' => 100,
        'comments' => 20,
        'shares' => 10,
        'reach' => 1000,
    ]);

    $response = $this->get('/analytics/posts');

    $response->assertStatus(200)
        ->assertJsonCount(1)
        ->assertJsonFragment([
            'id' => $post->id,
            'engagement' => 130, // 100 + 20 + 10
            'reach' => 1000,
        ]);
});

test('user can fetch analytics accounts data', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    // Create active subscription
    Subscription::factory()->create([
        'user_id' => $user->id,
        'stripe_status' => 'active',
        'stripe_price' => 'price_pro',
    ]);

    $socialAccount = SocialAccount::factory()->create(['user_id' => $user->id]);

    $response = $this->get('/analytics/accounts');

    $response->assertStatus(200)
        ->assertJsonCount(1)
        ->assertJsonFragment([
            'id' => $socialAccount->id,
            'platform' => $socialAccount->platform,
        ]);
});

test('user can fetch engagement analytics', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    // Create active subscription
    Subscription::factory()->create([
        'user_id' => $user->id,
        'stripe_status' => 'active',
        'stripe_price' => 'price_pro',
    ]);

    $response = $this->get('/analytics/engagement');

    $response->assertStatus(200)
        ->assertJsonStructure([
            'engagement_over_time',
            'platform_performance',
            'post_types_performance',
        ]);
});

test('user with enterprise subscription can access team analytics', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    // Create enterprise subscription
    Subscription::factory()->create([
        'user_id' => $user->id,
        'stripe_status' => 'active',
        'stripe_price' => 'price_enterprise',
    ]);

    $response = $this->get('/analytics/team');

    $response->assertStatus(200);
});

test('user with pro subscription cannot access team analytics', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    // Create pro subscription
    Subscription::factory()->create([
        'user_id' => $user->id,
        'stripe_status' => 'active',
        'stripe_price' => 'price_pro',
    ]);

    $response = $this->get('/analytics/team');

    $response->assertStatus(403)
        ->assertJson([
            'error' => 'Team analytics require an Enterprise subscription',
        ]);
});

test('user can export analytics data', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    // Create active subscription
    Subscription::factory()->create([
        'user_id' => $user->id,
        'stripe_status' => 'active',
        'stripe_price' => 'price_pro',
    ]);

    $response = $this->post('/analytics/export', [
        'format' => 'csv',
    ]);

    $response->assertStatus(200)
        ->assertJsonStructure([
            'message',
            'filename',
            'download_url',
        ]);
});

test('analytics data respects date range filters', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    // Create active subscription
    Subscription::factory()->create([
        'user_id' => $user->id,
        'stripe_status' => 'active',
        'stripe_price' => 'price_pro',
    ]);

    $socialAccount = SocialAccount::factory()->create(['user_id' => $user->id]);
    
    // Create post outside date range
    $oldPost = Post::factory()->create([
        'user_id' => $user->id,
        'created_at' => now()->subDays(60),
    ]);
    
    // Create post within date range
    $newPost = Post::factory()->create([
        'user_id' => $user->id,
        'created_at' => now()->subDays(10),
    ]);

    $response = $this->get('/analytics/posts?' . http_build_query([
        'start_date' => now()->subDays(30)->format('Y-m-d'),
        'end_date' => now()->format('Y-m-d'),
    ]));

    $response->assertStatus(200)
        ->assertJsonCount(1)
        ->assertJsonFragment(['id' => $newPost->id])
        ->assertJsonMissing(['id' => $oldPost->id]);
});