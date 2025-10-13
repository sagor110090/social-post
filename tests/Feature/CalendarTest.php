<?php

use App\Models\User;
use App\Models\Post;
use App\Models\ScheduledPost;
use App\Models\SocialAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

test('user can view calendar page', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $response = $this->get('/calendar');

    $response->assertStatus(200);
});

test('user can fetch calendar events', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $socialAccount = SocialAccount::factory()->create(['user_id' => $user->id]);
    $post = Post::factory()->create(['user_id' => $user->id]);
    $scheduledPost = ScheduledPost::factory()->create([
        'user_id' => $user->id,
        'post_id' => $post->id,
        'social_account_id' => $socialAccount->id,
        'scheduled_for' => now()->addDays(1),
    ]);

    $response = $this->get('/calendar/events');

    $response->assertStatus(200)
        ->assertJsonCount(1)
        ->assertJsonFragment([
            'id' => $scheduledPost->id,
            'title' => $post->content,
            'platform' => $socialAccount->platform,
        ]);
});

test('user can create calendar event', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $response = $this->post('/calendar/events', [
        'title' => 'Test Post',
        'start' => now()->addDays(1)->toISOString(),
        'end' => now()->addDays(1)->addHours(1)->toISOString(),
    ]);

    $response->assertStatus(200);
    $this->assertDatabaseHas('posts', [
        'user_id' => $user->id,
        'content' => 'Test Post',
    ]);
    $this->assertDatabaseHas('scheduled_posts', [
        'user_id' => $user->id,
        'scheduled_for' => now()->addDays(1)->toDateTimeString(),
    ]);
});

test('user can update calendar event', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $socialAccount = SocialAccount::factory()->create(['user_id' => $user->id]);
    $post = Post::factory()->create(['user_id' => $user->id]);
    $scheduledPost = ScheduledPost::factory()->create([
        'user_id' => $user->id,
        'post_id' => $post->id,
        'social_account_id' => $socialAccount->id,
    ]);

    $newDate = now()->addDays(2);

    $response = $this->patch("/calendar/events/{$scheduledPost->id}", [
        'start' => $newDate->toISOString(),
    ]);

    $response->assertStatus(200);
    $this->assertDatabaseHas('scheduled_posts', [
        'id' => $scheduledPost->id,
        'scheduled_for' => $newDate->toDateTimeString(),
    ]);
});

test('user can delete calendar event', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $socialAccount = SocialAccount::factory()->create(['user_id' => $user->id]);
    $post = Post::factory()->create(['user_id' => $user->id]);
    $scheduledPost = ScheduledPost::factory()->create([
        'user_id' => $user->id,
        'post_id' => $post->id,
        'social_account_id' => $socialAccount->id,
    ]);

    $response = $this->delete("/calendar/events/{$scheduledPost->id}");

    $response->assertStatus(200);
    $this->assertDatabaseMissing('scheduled_posts', [
        'id' => $scheduledPost->id,
    ]);
});

test('unauthorized user cannot access calendar', function () {
    $response = $this->get('/calendar');

    $response->assertRedirect('/login');
});

test('user cannot access other users calendar events', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    Sanctum::actingAs($user);

    $socialAccount = SocialAccount::factory()->create(['user_id' => $otherUser->id]);
    $post = Post::factory()->create(['user_id' => $otherUser->id]);
    $scheduledPost = ScheduledPost::factory()->create([
        'user_id' => $otherUser->id,
        'post_id' => $post->id,
        'social_account_id' => $socialAccount->id,
    ]);

    $response = $this->get('/calendar/events');

    $response->assertStatus(200)
        ->assertJsonCount(0);
});