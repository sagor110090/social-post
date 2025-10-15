<?php

use App\Models\SocialAccount;
use App\Models\User;
use App\Models\WebhookConfig;
use App\Models\WebhookDeliveryMetric;
use App\Models\WebhookEvent;
use App\Models\WebhookSubscription;

it('can create a webhook config', function () {
    $user = User::factory()->create();
    $socialAccount = SocialAccount::factory()->create([
        'user_id' => $user->id,
        'platform' => 'facebook',
    ]);
    
    $config = WebhookConfig::factory()->create([
        'social_account_id' => $socialAccount->id,
    ]);

    expect($config)->toBeInstanceOf(WebhookConfig::class);
    expect($config->social_account_id)->toBe($socialAccount->id);
    expect($config->webhook_url)->not->toBeNull();
    expect($config->secret)->not->toBeNull();
    expect($config->events)->toBeArray();
    expect($config->is_active)->toBeTrue();
});

it('casts attributes correctly', function () {
    $user = User::factory()->create();
    $socialAccount = SocialAccount::factory()->create([
        'user_id' => $user->id,
        'platform' => 'facebook',
    ]);
    
    $config = WebhookConfig::factory()->create([
        'social_account_id' => $socialAccount->id,
        'events' => ['page_posts', 'page_comments'],
        'metadata' => ['version' => '1.0'],
        'is_active' => true,
        'last_verified_at' => now(),
    ]);

    expect($config->events)->toBeArray();
    expect($config->metadata)->toBeArray();
    expect($config->is_active)->toBeBool();
    expect($config->last_verified_at)->toBeInstanceOf(\Carbon\Carbon::class);
});

it('belongs to social account', function () {
    $user = User::factory()->create();
    $socialAccount = SocialAccount::factory()->create([
        'user_id' => $user->id,
        'platform' => 'facebook',
    ]);
    
    $config = WebhookConfig::factory()->create([
        'social_account_id' => $socialAccount->id,
    ]);

    expect($config->socialAccount)->toBeInstanceOf(SocialAccount::class);
    expect($config->socialAccount->id)->toBe($socialAccount->id);
});

it('can check if subscribed to event', function () {
    $user = User::factory()->create();
    $socialAccount = SocialAccount::factory()->create([
        'user_id' => $user->id,
        'platform' => 'facebook',
    ]);
    
    $config = WebhookConfig::factory()->create([
        'social_account_id' => $socialAccount->id,
        'events' => ['page_posts', 'page_comments'],
    ]);

    expect($config->isSubscribedTo('page_posts'))->toBeTrue();
    expect($config->isSubscribedTo('page_comments'))->toBeTrue();
    expect($config->isSubscribedTo('media_comments'))->toBeFalse();
    expect($config->isSubscribedTo(''))->toBeFalse();
});

it('can generate secret', function () {
    $user = User::factory()->create();
    $socialAccount = SocialAccount::factory()->create([
        'user_id' => $user->id,
        'platform' => 'facebook',
    ]);
    
    $config = WebhookConfig::factory()->create([
        'social_account_id' => $socialAccount->id,
        'secret' => null,
    ]);

    $secret = $config->generateSecret();

    expect($secret)->toBeString();
    expect($secret)->toHaveLength(64); // 32 bytes = 64 hex chars
    expect($config->fresh()->secret)->toBe($secret);
});

it('can verify signature', function () {
    $user = User::factory()->create();
    $socialAccount = SocialAccount::factory()->create([
        'user_id' => $user->id,
        'platform' => 'facebook',
    ]);
    
    $config = WebhookConfig::factory()->create([
        'social_account_id' => $socialAccount->id,
        'secret' => 'test_secret',
    ]);

    $payload = 'test payload';
    $signature = hash_hmac('sha256', $payload, 'test_secret');

    expect($config->verifySignature($payload, $signature))->toBeTrue();
});

it('fails signature verification with wrong secret', function () {
    $user = User::factory()->create();
    $socialAccount = SocialAccount::factory()->create([
        'user_id' => $user->id,
        'platform' => 'facebook',
    ]);
    
    $config = WebhookConfig::factory()->create([
        'social_account_id' => $socialAccount->id,
        'secret' => 'test_secret',
    ]);

    $payload = 'test payload';
    $signature = hash_hmac('sha256', $payload, 'wrong_secret');

    expect($config->verifySignature($payload, $signature))->toBeFalse();
});