<?php

use App\Models\User;
use App\Models\Post;
use App\Models\SocialAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

test('admin can view admin dashboard', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    Sanctum::actingAs($admin);

    $response = $this->get('/admin/dashboard');

    $response->assertStatus(200);
});

test('non-admin cannot access admin dashboard', function () {
    $user = User::factory()->create(['is_admin' => false]);
    Sanctum::actingAs($user);

    $response = $this->get('/admin/dashboard');

    $response->assertRedirect('/dashboard');
});

test('admin can view users list', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    Sanctum::actingAs($admin);

    User::factory()->count(5)->create();

    $response = $this->get('/admin/users');

    $response->assertStatus(200);
});

test('admin can view specific user', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    Sanctum::actingAs($admin);

    $user = User::factory()->create();
    SocialAccount::factory()->create(['user_id' => $user->id]);
    Post::factory()->create(['user_id' => $user->id]);

    $response = $this->get("/admin/users/{$user->id}");

    $response->assertStatus(200)
        ->assertJsonFragment([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
        ]);
});

test('admin can update user', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    Sanctum::actingAs($admin);

    $user = User::factory()->create();

    $response = $this->patch("/admin/users/{$user->id}", [
        'name' => 'Updated Name',
        'email' => 'updated@example.com',
        'role' => 'admin',
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('users', [
        'id' => $user->id,
        'name' => 'Updated Name',
        'email' => 'updated@example.com',
        'is_admin' => true,
    ]);
});

test('admin can delete user', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    Sanctum::actingAs($admin);

    $user = User::factory()->create();

    $response = $this->delete("/admin/users/{$user->id}");

    $response->assertRedirect('/admin/users');
    $this->assertDatabaseMissing('users', [
        'id' => $user->id,
    ]);
});

test('admin cannot delete themselves', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    Sanctum::actingAs($admin);

    $response = $this->delete("/admin/users/{$admin->id}");

    $response->assertRedirect()
        ->assertSessionHasErrors('error');
    $this->assertDatabaseHas('users', [
        'id' => $admin->id,
    ]);
});

test('admin can view posts list', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    Sanctum::actingAs($admin);

    Post::factory()->count(10)->create();

    $response = $this->get('/admin/posts');

    $response->assertStatus(200);
});

test('admin can delete post', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    Sanctum::actingAs($admin);

    $post = Post::factory()->create();

    $response = $this->delete("/admin/posts/{$post->id}");

    $response->assertRedirect();
    $this->assertDatabaseMissing('posts', [
        'id' => $post->id,
    ]);
});



test('admin can impersonate user', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    Sanctum::actingAs($admin);

    $user = User::factory()->create();

    $response = $this->post("/admin/users/{$user->id}/impersonate");

    $response->assertRedirect('/dashboard');
    $this->assertEquals($user->id, session('impersonate'));
});

test('admin can stop impersonating', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    Sanctum::actingAs($admin);

    session(['impersonate' => 123]);

    $response = $this->post('/admin/stop-impersonate');

    $response->assertRedirect('/admin/dashboard');
    $this->assertNull(session('impersonate'));
});

test('admin can fetch analytics data', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    Sanctum::actingAs($admin);

    User::factory()->count(10)->create();
    Post::factory()->count(20)->create();
    Subscription::factory()->count(5)->create(['stripe_status' => 'active']);

    $response = $this->get('/admin/analytics');

    $response->assertStatus(200)
        ->assertJsonStructure([
            'user_growth',
            'post_growth',
            'revenue',
            'platform_stats',
            'subscription_stats',
        ]);
});

test('admin users list can be filtered', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    Sanctum::actingAs($admin);

    User::factory()->create(['name' => 'John Doe', 'email' => 'john@example.com']);
    User::factory()->create(['name' => 'Jane Smith', 'email' => 'jane@example.com']);
    
    $userWithSubscription = User::factory()->create();
    Subscription::factory()->create([
        'user_id' => $userWithSubscription->id,
        'stripe_status' => 'active',
    ]);

    // Test search filter
    $response = $this->get('/admin/users?search=John');
    $response->assertStatus(200);

    // Test status filter
    $response = $this->get('/admin/users?status=active');
    $response->assertStatus(200);
});

test('admin posts list can be filtered', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    Sanctum::actingAs($admin);

    $socialAccount = SocialAccount::factory()->create(['platform' => 'facebook']);
    Post::factory()->create([
        'content' => 'Test post',
        'status' => 'published',
        'social_account_id' => $socialAccount->id,
    ]);

    // Test search filter
    $response = $this->get('/admin/posts?search=Test');
    $response->assertStatus(200);

    // Test status filter
    $response = $this->get('/admin/posts?status=published');
    $response->assertStatus(200);

    // Test platform filter
    $response = $this->get('/admin/posts?platform=facebook');
    $response->assertStatus(200);
});