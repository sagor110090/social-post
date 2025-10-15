<?php

use App\Jobs\ProcessWebhookEventJob;
use App\Models\SocialAccount;
use App\Models\User;
use App\Models\WebhookConfig;
use App\Models\WebhookDeliveryMetric;
use App\Models\WebhookEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

test('webhook test endpoint works', function () {
    $payload = ['test' => 'data'];

    $response = $this->postJson('/test', $payload);

    $response->assertStatus(200)
        ->assertJson([
            'status' => 'success',
            'message' => 'Test webhook received',
        ]);
});

test('webhook health check endpoint works', function () {
    $response = $this->getJson('/health');

    $response->assertStatus(200)
        ->assertJson([
            'status' => 'healthy',
        ]);
});

test('webhook signature verification middleware works', function () {
    $payload = ['test' => 'data'];

    // Test webhook security middleware is applied
    $response = $this->postJson('/facebook', $payload);

    // Should fail because no signature is provided
    $response->assertStatus(401);
});

test('webhook delivery metrics are recorded', function () {
    $user = User::factory()->create();
    $socialAccount = SocialAccount::factory()->create(['user_id' => $user->id]);
    $webhookConfig = WebhookConfig::factory()->create(['social_account_id' => $socialAccount->id]);

    // Simulate a successful webhook delivery
    WebhookDeliveryMetric::factory()->create([
        'webhook_config_id' => $webhookConfig->id,
        'social_account_id' => $socialAccount->id,
        'platform' => 'facebook',
        'date' => now()->toDateString(),
        'total_received' => 10,
        'successfully_processed' => 8,
        'failed' => 2,
    ]);

    $this->assertDatabaseHas('webhook_delivery_metrics', [
        'webhook_config_id' => $webhookConfig->id,
        'platform' => 'facebook',
        'total_received' => 10,
    ]);
});

test('webhook configuration can be activated and deactivated', function () {
    $user = User::factory()->create();
    $socialAccount = SocialAccount::factory()->create(['user_id' => $user->id]);
    $webhookConfig = WebhookConfig::factory()->create([
        'social_account_id' => $socialAccount->id,
        'is_active' => true
    ]);

    expect($webhookConfig->is_active)->toBeTrue();

    $webhookConfig->update(['is_active' => false]);

    expect($webhookConfig->fresh()->is_active)->toBeFalse();
});

test('webhook event processing status updates correctly', function () {
    $user = User::factory()->create();
    $socialAccount = SocialAccount::factory()->create(['user_id' => $user->id]);
    $webhookConfig = WebhookConfig::factory()->create(['social_account_id' => $socialAccount->id]);
    $webhookEvent = WebhookEvent::factory()->create([
        'webhook_config_id' => $webhookConfig->id,
        'social_account_id' => $socialAccount->id,
        'status' => 'pending'
    ]);

    expect($webhookEvent->status)->toBe('pending');

    $webhookEvent->update(['status' => 'processing']);

    expect($webhookEvent->fresh()->status)->toBe('processing');

    $webhookEvent->update(['status' => 'processed']);

    expect($webhookEvent->fresh()->status)->toBe('processed');
});