<?php

namespace Tests\Unit\Models;

use App\Models\SocialAccount;
use App\Models\User;
use App\Models\WebhookConfig;
use App\Models\WebhookDeliveryMetric;
use App\Models\WebhookEvent;
use App\Models\WebhookSubscription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WebhookConfigTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected SocialAccount $socialAccount;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->user = User::factory()->create();
        $this->socialAccount = SocialAccount::factory()->create([
            'user_id' => $this->user->id,
            'platform' => 'facebook',
        ]);
    }

    /** @test */
    public function it_can_create_a_webhook_config()
    {
        $config = WebhookConfig::factory()->create([
            'social_account_id' => $this->socialAccount->id,
        ]);

        $this->assertInstanceOf(WebhookConfig::class, $config);
        $this->assertEquals($this->socialAccount->id, $config->social_account_id);
        $this->assertNotNull($config->webhook_url);
        $this->assertNotNull($config->secret);
        $this->assertIsArray($config->events);
        $this->assertTrue($config->is_active);
    }

    /** @test */
    public function it_casts_attributes_correctly()
    {
        $config = WebhookConfig::factory()->create([
            'social_account_id' => $this->socialAccount->id,
            'events' => ['page_posts', 'page_comments'],
            'metadata' => ['version' => '1.0'],
            'is_active' => true,
            'last_verified_at' => now(),
        ]);

        $this->assertIsArray($config->events);
        $this->assertIsArray($config->metadata);
        $this->assertIsBool($config->is_active);
        $this->assertInstanceOf(\Carbon\Carbon::class, $config->last_verified_at);
    }

    /** @test */
    public function it_belongs_to_social_account()
    {
        $config = WebhookConfig::factory()->create([
            'social_account_id' => $this->socialAccount->id,
        ]);

        $this->assertInstanceOf(SocialAccount::class, $config->socialAccount);
        $this->assertEquals($this->socialAccount->id, $config->socialAccount->id);
    }

    /** @test */
    public function it_has_many_subscriptions()
    {
        $config = WebhookConfig::factory()->create([
            'social_account_id' => $this->socialAccount->id,
        ]);

        $subscription1 = WebhookSubscription::factory()->create([
            'webhook_config_id' => $config->id,
            'status' => 'active',
        ]);

        $subscription2 = WebhookSubscription::factory()->create([
            'webhook_config_id' => $config->id,
            'status' => 'inactive',
        ]);

        $subscriptions = $config->subscriptions;
        $this->assertCount(2, $subscriptions);
        $this->assertInstanceOf(WebhookSubscription::class, $subscriptions->first());
    }

    /** @test */
    public function it_has_many_webhook_events()
    {
        $config = WebhookConfig::factory()->create([
            'social_account_id' => $this->socialAccount->id,
        ]);

        $event1 = WebhookEvent::factory()->create([
            'webhook_config_id' => $config->id,
        ]);

        $event2 = WebhookEvent::factory()->create([
            'webhook_config_id' => $config->id,
        ]);

        $events = $config->webhookEvents;
        $this->assertCount(2, $events);
        $this->assertInstanceOf(WebhookEvent::class, $events->first());
    }

    /** @test */
    public function it_has_many_delivery_metrics()
    {
        $config = WebhookConfig::factory()->create([
            'social_account_id' => $this->socialAccount->id,
        ]);

        $metric1 = WebhookDeliveryMetric::factory()->create([
            'webhook_config_id' => $config->id,
        ]);

        $metric2 = WebhookDeliveryMetric::factory()->create([
            'webhook_config_id' => $config->id,
        ]);

        $metrics = $config->deliveryMetrics;
        $this->assertCount(2, $metrics);
        $this->assertInstanceOf(WebhookDeliveryMetric::class, $metrics->first());
    }

    /** @test */
    public function it_can_scope_to_active_configs()
    {
        $activeConfig = WebhookConfig::factory()->create([
            'social_account_id' => $this->socialAccount->id,
            'is_active' => true,
        ]);

        $inactiveConfig = WebhookConfig::factory()->create([
            'social_account_id' => $this->socialAccount->id,
            'is_active' => false,
        ]);

        $activeConfigs = WebhookConfig::active()->get();
        
        $this->assertCount(1, $activeConfigs);
        $this->assertEquals($activeConfig->id, $activeConfigs->first()->id);
    }

    /** @test */
    public function it_can_get_active_subscriptions()
    {
        $config = WebhookConfig::factory()->create([
            'social_account_id' => $this->socialAccount->id,
        ]);

        $activeSubscription = WebhookSubscription::factory()->create([
            'webhook_config_id' => $config->id,
            'status' => 'active',
        ]);

        $inactiveSubscription = WebhookSubscription::factory()->create([
            'webhook_config_id' => $config->id,
            'status' => 'inactive',
        ]);

        $activeSubscriptions = $config->activeSubscriptions;
        
        $this->assertCount(1, $activeSubscriptions);
        $this->assertEquals($activeSubscription->id, $activeSubscriptions->first()->id);
    }

    /** @test */
    public function it_can_check_if_subscribed_to_event()
    {
        $config = WebhookConfig::factory()->create([
            'social_account_id' => $this->socialAccount->id,
            'events' => ['page_posts', 'page_comments'],
        ]);

        $this->assertTrue($config->isSubscribedTo('page_posts'));
        $this->assertTrue($config->isSubscribedTo('page_comments'));
        $this->assertFalse($config->isSubscribedTo('media_comments'));
        $this->assertFalse($config->isSubscribedTo(''));
    }

    /** @test */
    public function it_can_generate_secret()
    {
        $config = WebhookConfig::factory()->create([
            'social_account_id' => $this->socialAccount->id,
            'secret' => null,
        ]);

        $secret = $config->generateSecret();

        $this->assertIsString($secret);
        $this->assertEquals(64, strlen($secret)); // 32 bytes = 64 hex chars
        $this->assertEquals($secret, $config->fresh()->secret);
    }

    /** @test */
    public function it_returns_existing_secret_when_generating()
    {
        $existingSecret = 'existing_secret_key';
        $config = WebhookConfig::factory()->create([
            'social_account_id' => $this->socialAccount->id,
            'secret' => $existingSecret,
        ]);

        $secret = $config->generateSecret();

        $this->assertEquals($existingSecret, $secret);
    }

    /** @test */
    public function it_can_verify_signature()
    {
        $config = WebhookConfig::factory()->create([
            'social_account_id' => $this->socialAccount->id,
            'secret' => 'test_secret',
        ]);

        $payload = 'test payload';
        $signature = hash_hmac('sha256', $payload, 'test_secret');

        $this->assertTrue($config->verifySignature($payload, $signature));
    }

    /** @test */
    public function it_fails_signature_verification_with_wrong_secret()
    {
        $config = WebhookConfig::factory()->create([
            'social_account_id' => $this->socialAccount->id,
            'secret' => 'test_secret',
        ]);

        $payload = 'test payload';
        $signature = hash_hmac('sha256', $payload, 'wrong_secret');

        $this->assertFalse($config->verifySignature($payload, $signature));
    }

    /** @test */
    public function it_fails_signature_verification_without_secret()
    {
        $config = WebhookConfig::factory()->create([
            'social_account_id' => $this->socialAccount->id,
            'secret' => null,
        ]);

        $payload = 'test payload';
        $signature = 'some_signature';

        $this->assertFalse($config->verifySignature($payload, $signature));
    }

    /** @test */
    public function it_handles_empty_events_array()
    {
        $config = WebhookConfig::factory()->create([
            'social_account_id' => $this->socialAccount->id,
            'events' => [],
        ]);

        $this->assertFalse($config->isSubscribedTo('any_event'));
    }

    /** @test */
    public function it_handles_null_events()
    {
        $config = WebhookConfig::factory()->create([
            'social_account_id' => $this->socialAccount->id,
            'events' => null,
        ]);

        $this->assertFalse($config->isSubscribedTo('any_event'));
    }

    /** @test */
    public function it_can_be_soft_deleted_if_trait_added()
    {
        $config = WebhookConfig::factory()->create([
            'social_account_id' => $this->socialAccount->id,
        ]);

        // This test would be relevant if SoftDeletes trait is added
        $this->assertNotNull($config->id);
    }
}