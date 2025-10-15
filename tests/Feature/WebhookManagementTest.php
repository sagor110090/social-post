<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\SocialAccount;
use App\Models\WebhookConfig;
use App\Models\WebhookEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class WebhookManagementTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->user = User::factory()->create();
        Sanctum::actingAs($this->user);
    }

    /** @test */
    public function it_can_access_webhook_settings_index()
    {
        $response = $this->get('/settings/webhooks');

        $response->assertStatus(200);
    }

    /** @test */
    public function it_can_access_webhook_configs_page()
    {
        $response = $this->get('/settings/webhooks/configs');

        $response->assertStatus(200);
    }

    /** @test */
    public function it_can_access_webhook_events_page()
    {
        $response = $this->get('/settings/webhooks/events');

        $response->assertStatus(200);
    }

    /** @test */
    public function it_can_access_webhook_analytics_page()
    {
        $response = $this->get('/settings/webhooks/analytics');

        $response->assertStatus(200);
    }

    /** @test */
    public function it_can_access_webhook_security_page()
    {
        $response = $this->get('/settings/webhooks/security');

        $response->assertStatus(200);
    }

    /** @test */
    public function it_can_fetch_webhook_stats()
    {
        // Create test data
        $socialAccount = SocialAccount::factory()->create(['user_id' => $this->user->id]);
        WebhookConfig::factory()->create(['social_account_id' => $socialAccount->id]);
        WebhookEvent::factory()->count(5)->create(['social_account_id' => $socialAccount->id]);

        $response = $this->get('/settings/webhooks/api/stats');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'total_configs',
                'active_configs',
                'total_events',
                'pending_events',
                'failed_events',
                'processed_events',
                'events_by_platform',
                'recent_events',
            ]);
    }

    /** @test */
    public function it_can_fetch_social_accounts()
    {
        SocialAccount::factory()->count(3)->create(['user_id' => $this->user->id]);
        SocialAccount::factory()->create(); // This one shouldn't be included

        $response = $this->get('/api/social-accounts');

        $response->assertStatus(200)
            ->assertJsonCount(3);
    }

    /** @test */
    public function it_can_create_webhook_configuration()
    {
        $socialAccount = SocialAccount::factory()->create(['user_id' => $this->user->id]);

        $response = $this->post('/webhooks/manage/configs', [
            'social_account_id' => $socialAccount->id,
            'events' => ['feed', 'messages'],
            'metadata' => [],
        ]);

        $response->assertStatus(201);
        
        $this->assertDatabaseHas('webhook_configs', [
            'social_account_id' => $socialAccount->id,
            'is_active' => true,
        ]);
    }

    /** @test */
    public function it_cannot_create_webhook_for_other_users_account()
    {
        $otherUser = User::factory()->create();
        $socialAccount = SocialAccount::factory()->create(['user_id' => $otherUser->id]);

        $response = $this->post('/webhooks/manage/configs', [
            'social_account_id' => $socialAccount->id,
            'events' => ['feed'],
        ]);

        $response->assertStatus(403);
    }

    /** @test */
    public function it_can_update_webhook_configuration()
    {
        $socialAccount = SocialAccount::factory()->create(['user_id' => $this->user->id]);
        $config = WebhookConfig::factory()->create(['social_account_id' => $socialAccount->id]);

        $response = $this->put("/webhooks/manage/configs/{$config->id}", [
            'events' => ['feed', 'messages', 'comments'],
            'is_active' => false,
        ]);

        $response->assertStatus(200);
        
        $this->assertDatabaseHas('webhook_configs', [
            'id' => $config->id,
            'is_active' => false,
        ]);
    }

    /** @test */
    public function it_can_delete_webhook_configuration()
    {
        $socialAccount = SocialAccount::factory()->create(['user_id' => $this->user->id]);
        $config = WebhookConfig::factory()->create(['social_account_id' => $socialAccount->id]);

        $response = $this->delete("/webhooks/manage/configs/{$config->id}");

        $response->assertStatus(204);
        
        $this->assertDatabaseMissing('webhook_configs', [
            'id' => $config->id,
        ]);
    }

    /** @test */
    public function it_can_regenerate_webhook_secret()
    {
        $socialAccount = SocialAccount::factory()->create(['user_id' => $this->user->id]);
        $config = WebhookConfig::factory()->create(['social_account_id' => $socialAccount->id]);
        $originalSecret = $config->secret;

        $response = $this->post("/webhooks/manage/configs/{$config->id}/regenerate-secret");

        $response->assertStatus(200);
        
        $config->refresh();
        $this->assertNotEquals($originalSecret, $config->secret);
    }

    /** @test */
    public function it_can_fetch_webhook_events()
    {
        $socialAccount = SocialAccount::factory()->create(['user_id' => $this->user->id]);
        WebhookEvent::factory()->count(5)->create(['social_account_id' => $socialAccount->id]);

        $response = $this->get('/webhooks/manage/events');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data',
                'current_page',
                'last_page',
                'per_page',
                'total',
            ]);
    }

    /** @test */
    public function it_can_retry_failed_webhook_event()
    {
        $socialAccount = SocialAccount::factory()->create(['user_id' => $this->user->id]);
        $event = WebhookEvent::factory()->create([
            'social_account_id' => $socialAccount->id,
            'status' => 'failed',
            'retry_count' => 1,
        ]);

        $response = $this->post("/webhooks/manage/events/{$event->id}/retry");

        $response->assertStatus(200);
        
        $event->refresh();
        $this->assertEquals('pending', $event->status);
    }

    /** @test */
    public function it_can_fetch_analytics_data()
    {
        $socialAccount = SocialAccount::factory()->create(['user_id' => $this->user->id]);
        
        // Create some test events
        WebhookEvent::factory()->count(10)->create([
            'social_account_id' => $socialAccount->id,
            'status' => 'processed',
            'received_at' => now()->subDays(5),
        ]);

        $response = $this->get('/settings/webhooks/api/analytics');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'metrics',
                'delivery_metrics',
                'summary' => [
                    'total_deliveries',
                    'success_rate',
                    'average_response_time',
                    'total_errors',
                    'platform_stats',
                ],
            ]);
    }

    /** @test */
    public function it_can_fetch_security_settings()
    {
        $response = $this->get('/settings/webhooks/api/security/settings');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'ip_whitelist_enabled',
                'ip_whitelist',
                'rate_limit_enabled',
                'rate_limit_requests',
                'rate_limit_window',
                'signature_verification_enabled',
                'webhook_timeout',
            ]);
    }

    /** @test */
    public function it_can_update_security_settings()
    {
        $response = $this->put('/settings/webhooks/api/security/settings', [
            'ip_whitelist_enabled' => true,
            'ip_whitelist' => ['192.168.1.1', '10.0.0.1'],
            'rate_limit_enabled' => true,
            'rate_limit_requests' => 200,
            'rate_limit_window' => 120,
            'signature_verification_enabled' => true,
            'webhook_timeout' => 60,
        ]);

        $response->assertStatus(200);
    }

    /** @test */
    public function it_can_create_webhook_subscription()
    {
        $socialAccount = SocialAccount::factory()->create(['user_id' => $this->user->id]);
        $config = WebhookConfig::factory()->create(['social_account_id' => $socialAccount->id]);

        $response = $this->post('/webhooks/manage/subscriptions', [
            'webhook_config_id' => $config->id,
            'entity_id' => 'page_123',
            'fields' => ['feed', 'messages'],
        ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('webhook_subscriptions', [
            'webhook_config_id' => $config->id,
            'entity_id' => 'page_123',
            'status' => 'active',
        ]);
    }

    /** @test */
    public function it_can_update_webhook_subscription()
    {
        $socialAccount = SocialAccount::factory()->create(['user_id' => $this->user->id]);
        $config = WebhookConfig::factory()->create(['social_account_id' => $socialAccount->id]);
        $subscription = \App\Models\WebhookSubscription::factory()->create([
            'webhook_config_id' => $config->id,
            'status' => 'active',
        ]);

        $response = $this->put("/webhooks/manage/subscriptions/{$subscription->id}", [
            'fields' => ['feed', 'messages', 'mentions'],
            'status' => 'paused',
        ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('webhook_subscriptions', [
            'id' => $subscription->id,
            'status' => 'paused',
        ]);
    }

    /** @test */
    public function it_can_delete_webhook_subscription()
    {
        $socialAccount = SocialAccount::factory()->create(['user_id' => $this->user->id]);
        $config = WebhookConfig::factory()->create(['social_account_id' => $socialAccount->id]);
        $subscription = \App\Models\WebhookSubscription::factory()->create([
            'webhook_config_id' => $config->id,
        ]);

        $response = $this->delete("/webhooks/manage/subscriptions/{$subscription->id}");

        $response->assertStatus(204);

        $this->assertDatabaseMissing('webhook_subscriptions', [
            'id' => $subscription->id,
        ]);
    }

    /** @test */
    public function it_can_toggle_webhook_config_status()
    {
        $socialAccount = SocialAccount::factory()->create(['user_id' => $this->user->id]);
        $config = WebhookConfig::factory()->create([
            'social_account_id' => $socialAccount->id,
            'is_active' => true,
        ]);

        $response = $this->post("/webhooks/manage/configs/{$config->id}/toggle");

        $response->assertStatus(200);

        $this->assertDatabaseHas('webhook_configs', [
            'id' => $config->id,
            'is_active' => false,
        ]);
    }

    /** @test */
    public function it_can_test_webhook_endpoint()
    {
        $socialAccount = SocialAccount::factory()->create(['user_id' => $this->user->id]);
        $config = WebhookConfig::factory()->create(['social_account_id' => $socialAccount->id]);

        $response = $this->post("/webhooks/manage/configs/{$config->id}/test");

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'message' => 'Test webhook sent successfully',
            ]);
    }

    /** @test */
    public function it_can_export_webhook_events()
    {
        $socialAccount = SocialAccount::factory()->create(['user_id' => $this->user->id]);
        WebhookEvent::factory()->count(3)->create(['social_account_id' => $socialAccount->id]);

        $response = $this->get('/webhooks/manage/events/export');

        $response->assertStatus(200)
            ->assertHeader('Content-Type', 'text/csv; charset=UTF-8')
            ->assertHeader('Content-Disposition');
    }

    /** @test */
    public function it_validates_webhook_config_creation()
    {
        $response = $this->post('/webhooks/manage/configs', [
            'social_account_id' => null,
            'events' => [],
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['social_account_id', 'events']);
    }

    /** @test */
    public function it_can_bulk_update_webhook_configs()
    {
        $socialAccount = SocialAccount::factory()->create(['user_id' => $this->user->id]);
        $configs = WebhookConfig::factory()->count(3)->create([
            'social_account_id' => $socialAccount->id,
            'is_active' => true,
        ]);

        $response = $this->post('/webhooks/manage/configs/bulk-update', [
            'config_ids' => $configs->pluck('id'),
            'is_active' => false,
        ]);

        $response->assertStatus(200);

        foreach ($configs as $config) {
            $this->assertDatabaseHas('webhook_configs', [
                'id' => $config->id,
                'is_active' => false,
            ]);
        }
    }

    /** @test */
    public function it_can_clone_webhook_config()
    {
        $socialAccount = SocialAccount::factory()->create(['user_id' => $this->user->id]);
        $originalConfig = WebhookConfig::factory()->create([
            'social_account_id' => $socialAccount->id,
            'events' => ['page_posts', 'page_comments'],
        ]);

        $response = $this->post("/webhooks/manage/configs/{$originalConfig->id}/clone");

        $response->assertStatus(201);

        $this->assertEquals(2, WebhookConfig::where('social_account_id', $socialAccount->id)->count());
    }

    /** @test */
    public function it_can_fetch_webhook_delivery_metrics()
    {
        $socialAccount = SocialAccount::factory()->create(['user_id' => $this->user->id]);
        $config = WebhookConfig::factory()->create(['social_account_id' => $socialAccount->id]);
        
        // Create some delivery metrics
        \App\Models\WebhookDeliveryMetric::factory()->count(10)->create([
            'webhook_config_id' => $config->id,
        ]);

        $response = $this->get("/webhooks/manage/configs/{$config->id}/metrics");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data',
                'summary' => [
                    'total_deliveries',
                    'success_rate',
                    'average_response_time',
                ],
            ]);
    }

    /** @test */
    public function it_can_view_webhook_event_details()
    {
        $socialAccount = SocialAccount::factory()->create(['user_id' => $this->user->id]);
        $event = WebhookEvent::factory()->create([
            'social_account_id' => $socialAccount->id,
            'payload' => ['test' => 'data'],
        ]);

        $response = $this->get("/webhooks/manage/events/{$event->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'id',
                'platform',
                'event_type',
                'status',
                'payload',
                'received_at',
                'processed_at',
            ]);
    }

    /** @test */
    public function it_can_ignore_webhook_event()
    {
        $socialAccount = SocialAccount::factory()->create(['user_id' => $this->user->id]);
        $event = WebhookEvent::factory()->create([
            'social_account_id' => $socialAccount->id,
            'status' => 'pending',
        ]);

        $response = $this->post("/webhooks/manage/events/{$event->id}/ignore");

        $response->assertStatus(200);

        $event->refresh();
        $this->assertEquals('ignored', $event->status);
    }

    /** @test */
    public function it_can_delete_webhook_event()
    {
        $socialAccount = SocialAccount::factory()->create(['user_id' => $this->user->id]);
        $event = WebhookEvent::factory()->create(['social_account_id' => $socialAccount->id]);

        $response = $this->delete("/webhooks/manage/events/{$event->id}");

        $response->assertStatus(204);

        $this->assertDatabaseMissing('webhook_events', [
            'id' => $event->id,
        ]);
    }

    /** @test */
    public function it_can_fetch_webhook_logs_with_filters()
    {
        $socialAccount = SocialAccount::factory()->create(['user_id' => $this->user->id]);
        WebhookEvent::factory()->count(5)->create([
            'social_account_id' => $socialAccount->id,
            'platform' => 'facebook',
            'status' => 'processed',
        ]);

        $response = $this->get('/webhooks/manage/events?platform=facebook&status=processed');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data',
                'current_page',
                'last_page',
                'per_page',
                'total',
            ]);
    }
}