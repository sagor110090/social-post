<?php

use App\Models\SocialAccount;
use App\Models\User;
use App\Models\WebhookConfig;
use App\Models\WebhookDeliveryMetric;
use App\Models\WebhookEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('webhook config model works correctly', function () {
    $user = User::factory()->create();
    $socialAccount = SocialAccount::factory()->create(['user_id' => $user->id]);
    $webhookConfig = WebhookConfig::factory()->create(['social_account_id' => $socialAccount->id]);

    expect($webhookConfig)->toBeInstanceOf(WebhookConfig::class);
    expect($webhookConfig->socialAccount)->toBeInstanceOf(SocialAccount::class);
    expect($webhookConfig->secret)->toBeString();
    expect($webhookConfig->is_active)->toBeBool();
});

test('webhook event model works correctly', function () {
    $user = User::factory()->create();
    $socialAccount = SocialAccount::factory()->create(['user_id' => $user->id]);
    $webhookConfig = WebhookConfig::factory()->create(['social_account_id' => $socialAccount->id]);
    $webhookEvent = WebhookEvent::factory()->create([
        'webhook_config_id' => $webhookConfig->id,
        'social_account_id' => $socialAccount->id,
        'platform' => 'facebook',
        'status' => 'pending'
    ]);

    expect($webhookEvent)->toBeInstanceOf(WebhookEvent::class);
    expect($webhookEvent->socialAccount)->toBeInstanceOf(SocialAccount::class);
    expect($webhookEvent->webhookConfig)->toBeInstanceOf(WebhookConfig::class);
    expect($webhookEvent->status)->toBe('pending');
});

test('webhook delivery metrics model works correctly', function () {
    $user = User::factory()->create();
    $socialAccount = SocialAccount::factory()->create(['user_id' => $user->id]);
    $webhookConfig = WebhookConfig::factory()->create(['social_account_id' => $socialAccount->id]);
    $metric = WebhookDeliveryMetric::factory()->create([
        'webhook_config_id' => $webhookConfig->id,
        'social_account_id' => $socialAccount->id,
        'platform' => 'facebook'
    ]);

    expect($metric)->toBeInstanceOf(WebhookDeliveryMetric::class);
    expect($metric->webhookConfig)->toBeInstanceOf(WebhookConfig::class);
    expect($metric->socialAccount)->toBeInstanceOf(SocialAccount::class);
    expect($metric->platform)->toBe('facebook');
});

test('webhook signature generation works', function () {
    $payload = json_encode(['test' => 'data']);
    $secret = 'test_secret_key';
    
    $signature = 'sha256=' . hash_hmac('sha256', $payload, $secret);
    
    expect($signature)->toBeString();
    expect($signature)->toStartWith('sha256=');
});

test('webhook configuration can be created with custom events', function () {
    $user = User::factory()->create();
    $socialAccount = SocialAccount::factory()->create(['user_id' => $user->id]);
    
    $events = ['page_post', 'comment', 'like'];
    
    $webhookConfig = WebhookConfig::factory()->create([
        'social_account_id' => $socialAccount->id,
        'events' => $events,
        'is_active' => true
    ]);

    expect($webhookConfig->events)->toBeArray();
    expect($webhookConfig->events)->toEqual($events);
    expect($webhookConfig->is_active)->toBeTrue();
});

test('webhook event status transitions work correctly', function () {
    $user = User::factory()->create();
    $socialAccount = SocialAccount::factory()->create(['user_id' => $user->id]);
    $webhookConfig = WebhookConfig::factory()->create(['social_account_id' => $socialAccount->id]);
    
    $webhookEvent = WebhookEvent::factory()->create([
        'webhook_config_id' => $webhookConfig->id,
        'social_account_id' => $socialAccount->id,
        'status' => 'pending'
    ]);

    // Test valid status transitions
    $webhookEvent->update(['status' => 'processing']);
    expect($webhookEvent->fresh()->status)->toBe('processing');

    $webhookEvent->update(['status' => 'processed']);
    expect($webhookEvent->fresh()->status)->toBe('processed');

    $webhookEvent->update(['status' => 'failed']);
    expect($webhookEvent->fresh()->status)->toBe('failed');

    $webhookEvent->update(['status' => 'ignored']);
    expect($webhookEvent->fresh()->status)->toBe('ignored');
});