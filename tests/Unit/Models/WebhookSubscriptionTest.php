<?php

namespace Tests\Unit\Models;

use App\Models\SocialAccount;
use App\Models\User;
use App\Models\WebhookConfig;
use App\Models\WebhookSubscription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WebhookSubscriptionTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected SocialAccount $socialAccount;
    protected WebhookConfig $webhookConfig;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->user = User::factory()->create();
        $this->socialAccount = SocialAccount::factory()->create([
            'user_id' => $this->user->id,
            'platform' => 'facebook',
        ]);
        $this->webhookConfig = WebhookConfig::factory()->create([
            'social_account_id' => $this->socialAccount->id,
        ]);
    }

    /** @test */
    public function it_can_create_a_webhook_subscription()
    {
        $subscription = WebhookSubscription::factory()->create([
            'webhook_config_id' => $this->webhookConfig->id,
        ]);

        $this->assertInstanceOf(WebhookSubscription::class, $subscription);
        $this->assertEquals($this->webhookConfig->id, $subscription->webhook_config_id);
        $this->assertNotNull($subscription->subscription_id);
        $this->assertNotNull($subscription->entity_id);
        $this->assertNotNull($subscription->fields);
        $this->assertEquals('active', $subscription->status);
    }

    /** @test */
    public function it_casts_attributes_correctly()
    {
        $subscription = WebhookSubscription::factory()->create([
            'webhook_config_id' => $this->webhookConfig->id,
            'fields' => ['feed', 'messages'],
            'status' => 'active',
            'subscribed_at' => now(),
            'unsubscribed_at' => null,
        ]);

        $this->assertIsArray($subscription->fields);
        $this->assertIsString($subscription->status);
        $this->assertInstanceOf(\Carbon\Carbon::class, $subscription->subscribed_at);
        $this->assertNull($subscription->unsubscribed_at);
    }

    /** @test */
    public function it_belongs_to_webhook_config()
    {
        $subscription = WebhookSubscription::factory()->create([
            'webhook_config_id' => $this->webhookConfig->id,
        ]);

        $this->assertInstanceOf(WebhookConfig::class, $subscription->webhookConfig);
        $this->assertEquals($this->webhookConfig->id, $subscription->webhookConfig->id);
    }

    /** @test */
    public function it_can_scope_to_active_subscriptions()
    {
        $activeSubscription = WebhookSubscription::factory()->create([
            'webhook_config_id' => $this->webhookConfig->id,
            'status' => 'active',
        ]);

        $inactiveSubscription = WebhookSubscription::factory()->create([
            'webhook_config_id' => $this->webhookConfig->id,
            'status' => 'inactive',
        ]);

        $pausedSubscription = WebhookSubscription::factory()->create([
            'webhook_config_id' => $this->webhookConfig->id,
            'status' => 'paused',
        ]);

        $activeSubscriptions = WebhookSubscription::active()->get();
        
        $this->assertCount(1, $activeSubscriptions);
        $this->assertEquals($activeSubscription->id, $activeSubscriptions->first()->id);
    }

    /** @test */
    public function it_can_scope_to_inactive_subscriptions()
    {
        $activeSubscription = WebhookSubscription::factory()->create([
            'webhook_config_id' => $this->webhookConfig->id,
            'status' => 'active',
        ]);

        $inactiveSubscription = WebhookSubscription::factory()->create([
            'webhook_config_id' => $this->webhookConfig->id,
            'status' => 'inactive',
        ]);

        $pausedSubscription = WebhookSubscription::factory()->create([
            'webhook_config_id' => $this->webhookConfig->id,
            'status' => 'paused',
        ]);

        $inactiveSubscriptions = WebhookSubscription::inactive()->get();
        
        $this->assertCount(1, $inactiveSubscriptions);
        $this->assertEquals($inactiveSubscription->id, $inactiveSubscriptions->first()->id);
    }

    /** @test */
    public function it_can_scope_to_paused_subscriptions()
    {
        $activeSubscription = WebhookSubscription::factory()->create([
            'webhook_config_id' => $this->webhookConfig->id,
            'status' => 'active',
        ]);

        $inactiveSubscription = WebhookSubscription::factory()->create([
            'webhook_config_id' => $this->webhookConfig->id,
            'status' => 'inactive',
        ]);

        $pausedSubscription = WebhookSubscription::factory()->create([
            'webhook_config_id' => $this->webhookConfig->id,
            'status' => 'paused',
        ]);

        $pausedSubscriptions = WebhookSubscription::paused()->get();
        
        $this->assertCount(1, $pausedSubscriptions);
        $this->assertEquals($pausedSubscription->id, $pausedSubscriptions->first()->id);
    }

    /** @test */
    public function it_can_activate_subscription()
    {
        $subscription = WebhookSubscription::factory()->create([
            'webhook_config_id' => $this->webhookConfig->id,
            'status' => 'inactive',
            'subscribed_at' => null,
        ]);

        $subscription->activate();

        $freshSubscription = $subscription->fresh();
        $this->assertEquals('active', $freshSubscription->status);
        $this->assertNotNull($freshSubscription->subscribed_at);
        $this->assertNull($freshSubscription->unsubscribed_at);
    }

    /** @test */
    public function it_can_deactivate_subscription()
    {
        $subscription = WebhookSubscription::factory()->create([
            'webhook_config_id' => $this->webhookConfig->id,
            'status' => 'active',
            'subscribed_at' => now(),
            'unsubscribed_at' => null,
        ]);

        $subscription->deactivate();

        $freshSubscription = $subscription->fresh();
        $this->assertEquals('inactive', $freshSubscription->status);
        $this->assertNotNull($freshSubscription->unsubscribed_at);
    }

    /** @test */
    public function it_can_pause_subscription()
    {
        $subscription = WebhookSubscription::factory()->create([
            'webhook_config_id' => $this->webhookConfig->id,
            'status' => 'active',
        ]);

        $subscription->pause();

        $freshSubscription = $subscription->fresh();
        $this->assertEquals('paused', $freshSubscription->status);
    }

    /** @test */
    public function it_can_resume_subscription()
    {
        $subscription = WebhookSubscription::factory()->create([
            'webhook_config_id' => $this->webhookConfig->id,
            'status' => 'paused',
        ]);

        $subscription->resume();

        $freshSubscription = $subscription->fresh();
        $this->assertEquals('active', $freshSubscription->status);
    }

    /** @test */
    public function it_can_check_if_subscription_is_active()
    {
        $activeSubscription = WebhookSubscription::factory()->create([
            'webhook_config_id' => $this->webhookConfig->id,
            'status' => 'active',
        ]);

        $inactiveSubscription = WebhookSubscription::factory()->create([
            'webhook_config_id' => $this->webhookConfig->id,
            'status' => 'inactive',
        ]);

        $pausedSubscription = WebhookSubscription::factory()->create([
            'webhook_config_id' => $this->webhookConfig->id,
            'status' => 'paused',
        ]);

        $this->assertTrue($activeSubscription->isActive());
        $this->assertFalse($inactiveSubscription->isActive());
        $this->assertFalse($pausedSubscription->isActive());
    }

    /** @test */
    public function it_can_check_if_subscription_is_subscribed_to_field()
    {
        $subscription = WebhookSubscription::factory()->create([
            'webhook_config_id' => $this->webhookConfig->id,
            'fields' => ['feed', 'messages', 'mentions'],
        ]);

        $this->assertTrue($subscription->isSubscribedTo('feed'));
        $this->assertTrue($subscription->isSubscribedTo('messages'));
        $this->assertTrue($subscription->isSubscribedTo('mentions'));
        $this->assertFalse($subscription->isSubscribedTo('likes'));
        $this->assertFalse($subscription->isSubscribedTo(''));
    }

    /** @test */
    public function it_handles_empty_fields_array()
    {
        $subscription = WebhookSubscription::factory()->create([
            'webhook_config_id' => $this->webhookConfig->id,
            'fields' => [],
        ]);

        $this->assertFalse($subscription->isSubscribedTo('any_field'));
    }

    /** @test */
    public function it_handles_null_fields()
    {
        $subscription = WebhookSubscription::factory()->create([
            'webhook_config_id' => $this->webhookConfig->id,
            'fields' => null,
        ]);

        $this->assertFalse($subscription->isSubscribedTo('any_field'));
    }

    /** @test */
    public function it_can_get_subscription_duration()
    {
        $subscribedAt = now()->subDays(10);
        $subscription = WebhookSubscription::factory()->create([
            'webhook_config_id' => $this->webhookConfig->id,
            'status' => 'active',
            'subscribed_at' => $subscribedAt,
        ]);

        $duration = $subscription->getSubscriptionDuration();
        
        $this->assertEquals(10, $duration->days);
        $this->assertInstanceOf(\Carbon\CarbonInterval::class, $duration);
    }

    /** @test */
    public function it_returns_null_duration_for_inactive_subscription()
    {
        $subscription = WebhookSubscription::factory()->create([
            'webhook_config_id' => $this->webhookConfig->id,
            'status' => 'inactive',
            'subscribed_at' => null,
        ]);

        $this->assertNull($subscription->getSubscriptionDuration());
    }

    /** @test */
    public function it_can_get_platform_specific_data()
    {
        $metadata = [
            'facebook' => [
                'page_id' => '123456789',
                'page_name' => 'Test Page',
            ],
        ];

        $subscription = WebhookSubscription::factory()->create([
            'webhook_config_id' => $this->webhookConfig->id,
            'metadata' => $metadata,
        ]);

        $this->assertEquals('123456789', $subscription->getPlatformData('facebook.page_id'));
        $this->assertEquals('Test Page', $subscription->getPlatformData('facebook.page_name'));
        $this->assertNull($subscription->getPlatformData('facebook.nonexistent'));
        $this->assertEquals('default', $subscription->getPlatformData('facebook.nonexistent', 'default'));
    }

    /** @test */
    public function it_handles_empty_metadata()
    {
        $subscription = WebhookSubscription::factory()->create([
            'webhook_config_id' => $this->webhookConfig->id,
            'metadata' => [],
        ]);

        $this->assertNull($subscription->getPlatformData('any.key'));
        $this->assertEquals('default', $subscription->getPlatformData('any.key', 'default'));
    }

    /** @test */
    public function it_handles_null_metadata()
    {
        $subscription = WebhookSubscription::factory()->create([
            'webhook_config_id' => $this->webhookConfig->id,
            'metadata' => null,
        ]);

        $this->assertNull($subscription->getPlatformData('any.key'));
        $this->assertEquals('default', $subscription->getPlatformData('any.key', 'default'));
    }
}