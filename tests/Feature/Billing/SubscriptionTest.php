<?php

use App\Models\User;
use App\Models\Subscription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

test('user can view subscription page', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $response = $this->get('/subscription');

    $response->assertStatus(200);
});

test('user can subscribe to a plan', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    // Mock Stripe setup intent
    $user->createOrGetStripeCustomer();
    $intent = $user->createSetupIntent();

    $response = $this->post('/subscription', [
        'price_id' => 'price_pro',
        'payment_method_id' => 'pm_card_visa',
    ]);

    $response->assertRedirect('/subscription');
    $this->assertDatabaseHas('subscriptions', [
        'user_id' => $user->id,
        'stripe_price' => 'price_pro',
        'stripe_status' => 'active',
    ]);
});

test('user can swap subscription plans', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $subscription = Subscription::factory()->create([
        'user_id' => $user->id,
        'stripe_price' => 'price_basic',
        'stripe_status' => 'active',
    ]);

    $response = $this->patch('/subscription', [
        'price_id' => 'price_pro',
    ]);

    $response->assertRedirect('/subscription');
    $this->assertDatabaseHas('subscriptions', [
        'id' => $subscription->id,
        'stripe_price' => 'price_pro',
    ]);
});

test('user can cancel subscription', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $subscription = Subscription::factory()->create([
        'user_id' => $user->id,
        'stripe_status' => 'active',
    ]);

    $response = $this->delete('/subscription');

    $response->assertRedirect('/subscription');
    $subscription->refresh();
    $this->assertNotNull($subscription->ends_at);
});

test('user can resume cancelled subscription', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $subscription = Subscription::factory()->create([
        'user_id' => $user->id,
        'stripe_status' => 'active',
        'ends_at' => now()->addMonth(),
    ]);

    $response = $this->post('/subscription/resume');

    $response->assertRedirect('/subscription');
    $subscription->refresh();
    $this->assertNull($subscription->ends_at);
});

test('user can view invoices', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $response = $this->get('/invoices');

    $response->assertStatus(200);
});

test('unauthorized user cannot access subscription pages', function () {
    $response = $this->get('/subscription');

    $response->assertRedirect('/login');
});

test('user cannot subscribe without payment method', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $response = $this->post('/subscription', [
        'price_id' => 'price_pro',
    ]);

    $response->assertSessionHasErrors('payment_method_id');
});

test('user cannot swap plans without active subscription', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $response = $this->patch('/subscription', [
        'price_id' => 'price_pro',
    ]);

    $response->assertRedirect('/subscription')
        ->assertSessionHas('error');
});