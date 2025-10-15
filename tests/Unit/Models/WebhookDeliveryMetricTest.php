<?php

namespace Tests\Unit\Models;

use App\Models\SocialAccount;
use App\Models\User;
use App\Models\WebhookConfig;
use App\Models\WebhookDeliveryMetric;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WebhookDeliveryMetricTest extends TestCase
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
    public function it_can_create_a_webhook_delivery_metric()
    {
        $metric = WebhookDeliveryMetric::factory()->create([
            'webhook_config_id' => $this->webhookConfig->id,
        ]);

        $this->assertInstanceOf(WebhookDeliveryMetric::class, $metric);
        $this->assertEquals($this->webhookConfig->id, $metric->webhook_config_id);
        $this->assertNotNull($metric->status);
        $this->assertIsInt($metric->http_status_code);
        $this->assertNotNull($metric->delivered_at);
    }

    /** @test */
    public function it_casts_attributes_correctly()
    {
        $metric = WebhookDeliveryMetric::factory()->create([
            'webhook_config_id' => $this->webhookConfig->id,
            'delivered_at' => now(),
            'response_time' => 150.5,
            'payload_size' => 1024,
        ]);

        $this->assertInstanceOf(Carbon::class, $metric->delivered_at);
        $this->assertIsFloat($metric->response_time);
        $this->assertIsInt($metric->payload_size);
    }

    /** @test */
    public function it_belongs_to_webhook_config()
    {
        $metric = WebhookDeliveryMetric::factory()->create([
            'webhook_config_id' => $this->webhookConfig->id,
        ]);

        $this->assertInstanceOf(WebhookConfig::class, $metric->webhookConfig);
        $this->assertEquals($this->webhookConfig->id, $metric->webhookConfig->id);
    }

    /** @test */
    public function it_can_scope_to_successful_deliveries()
    {
        $successMetric = WebhookDeliveryMetric::factory()->create([
            'webhook_config_id' => $this->webhookConfig->id,
            'status' => 'success',
            'http_status_code' => 200,
        ]);

        $errorMetric = WebhookDeliveryMetric::factory()->create([
            'webhook_config_id' => $this->webhookConfig->id,
            'status' => 'error',
            'http_status_code' => 500,
        ]);

        $failedMetric = WebhookDeliveryMetric::factory()->create([
            'webhook_config_id' => $this->webhookConfig->id,
            'status' => 'failed',
            'http_status_code' => 401,
        ]);

        $successfulMetrics = WebhookDeliveryMetric::successful()->get();
        
        $this->assertCount(1, $successfulMetrics);
        $this->assertEquals($successMetric->id, $successfulMetrics->first()->id);
    }

    /** @test */
    public function it_can_scope_to_failed_deliveries()
    {
        $successMetric = WebhookDeliveryMetric::factory()->create([
            'webhook_config_id' => $this->webhookConfig->id,
            'status' => 'success',
            'http_status_code' => 200,
        ]);

        $errorMetric = WebhookDeliveryMetric::factory()->create([
            'webhook_config_id' => $this->webhookConfig->id,
            'status' => 'error',
            'http_status_code' => 500,
        ]);

        $failedMetric = WebhookDeliveryMetric::factory()->create([
            'webhook_config_id' => $this->webhookConfig->id,
            'status' => 'failed',
            'http_status_code' => 401,
        ]);

        $failedMetrics = WebhookDeliveryMetric::failed()->get();
        
        $this->assertCount(2, $failedMetrics);
        $this->assertContains($errorMetric->id, $failedMetrics->pluck('id'));
        $this->assertContains($failedMetric->id, $failedMetrics->pluck('id'));
    }

    /** @test */
    public function it_can_scope_to_recent_metrics()
    {
        $recentMetric = WebhookDeliveryMetric::factory()->create([
            'webhook_config_id' => $this->webhookConfig->id,
            'delivered_at' => now()->subMinutes(30),
        ]);

        $oldMetric = WebhookDeliveryMetric::factory()->create([
            'webhook_config_id' => $this->webhookConfig->id,
            'delivered_at' => now()->subHours(2),
        ]);

        $veryOldMetric = WebhookDeliveryMetric::factory()->create([
            'webhook_config_id' => $this->webhookConfig->id,
            'delivered_at' => now()->subDays(1),
        ]);

        $recentMetrics = WebhookDeliveryMetric::recent()->get();
        
        $this->assertCount(2, $recentMetrics);
        $this->assertContains($recentMetric->id, $recentMetrics->pluck('id'));
        $this->assertContains($oldMetric->id, $recentMetrics->pluck('id'));
        $this->assertNotContains($veryOldMetric->id, $recentMetrics->pluck('id'));
    }

    /** @test */
    public function it_can_scope_to_metrics_in_date_range()
    {
        $startDate = now()->subDays(2);
        $endDate = now()->subDay();

        $withinRangeMetric = WebhookDeliveryMetric::factory()->create([
            'webhook_config_id' => $this->webhookConfig->id,
            'delivered_at' => now()->subHours(12), // Within range
        ]);

        $beforeRangeMetric = WebhookDeliveryMetric::factory()->create([
            'webhook_config_id' => $this->webhookConfig->id,
            'delivered_at' => now()->subDays(3), // Before range
        ]);

        $afterRangeMetric = WebhookDeliveryMetric::factory()->create([
            'webhook_config_id' => $this->webhookConfig->id,
            'delivered_at' => now(), // After range
        ]);

        $rangeMetrics = WebhookDeliveryMetric::betweenDates($startDate, $endDate)->get();
        
        $this->assertCount(1, $rangeMetrics);
        $this->assertEquals($withinRangeMetric->id, $rangeMetrics->first()->id);
    }

    /** @test */
    public function it_can_check_if_delivery_was_successful()
    {
        $successMetric = WebhookDeliveryMetric::factory()->create([
            'webhook_config_id' => $this->webhookConfig->id,
            'status' => 'success',
            'http_status_code' => 200,
        ]);

        $errorMetric = WebhookDeliveryMetric::factory()->create([
            'webhook_config_id' => $this->webhookConfig->id,
            'status' => 'error',
            'http_status_code' => 500,
        ]);

        $this->assertTrue($successMetric->isSuccessful());
        $this->assertFalse($errorMetric->isSuccessful());
    }

    /** @test */
    public function it_can_check_if_delivery_was_failed()
    {
        $successMetric = WebhookDeliveryMetric::factory()->create([
            'webhook_config_id' => $this->webhookConfig->id,
            'status' => 'success',
            'http_status_code' => 200,
        ]);

        $errorMetric = WebhookDeliveryMetric::factory()->create([
            'webhook_config_id' => $this->webhookConfig->id,
            'status' => 'error',
            'http_status_code' => 500,
        ]);

        $failedMetric = WebhookDeliveryMetric::factory()->create([
            'webhook_config_id' => $this->webhookConfig->id,
            'status' => 'failed',
            'http_status_code' => 401,
        ]);

        $this->assertFalse($successMetric->isFailed());
        $this->assertTrue($errorMetric->isFailed());
        $this->assertTrue($failedMetric->isFailed());
    }

    /** @test */
    public function it_can_get_formatted_response_time()
    {
        $metric = WebhookDeliveryMetric::factory()->create([
            'webhook_config_id' => $this->webhookConfig->id,
            'response_time' => 150.5,
        ]);

        $this->assertEquals('150.50ms', $metric->getFormattedResponseTime());
    }

    /** @test */
    public function it_handles_null_response_time()
    {
        $metric = WebhookDeliveryMetric::factory()->create([
            'webhook_config_id' => $this->webhookConfig->id,
            'response_time' => null,
        ]);

        $this->assertEquals('N/A', $metric->getFormattedResponseTime());
    }

    /** @test */
    public function it_can_get_formatted_payload_size()
    {
        $metric = WebhookDeliveryMetric::factory()->create([
            'webhook_config_id' => $this->webhookConfig->id,
            'payload_size' => 1024,
        ]);

        $this->assertEquals('1.00 KB', $metric->getFormattedPayloadSize());
    }

    /** @test */
    public function it_formats_payload_size_in_bytes()
    {
        $metric = WebhookDeliveryMetric::factory()->create([
            'webhook_config_id' => $this->webhookConfig->id,
            'payload_size' => 512,
        ]);

        $this->assertEquals('512 B', $metric->getFormattedPayloadSize());
    }

    /** @test */
    public function it_formats_payload_size_in_mb()
    {
        $metric = WebhookDeliveryMetric::factory()->create([
            'webhook_config_id' => $this->webhookConfig->id,
            'payload_size' => 2097152, // 2 MB
        ]);

        $this->assertEquals('2.00 MB', $metric->getFormattedPayloadSize());
    }

    /** @test */
    public function it_handles_null_payload_size()
    {
        $metric = WebhookDeliveryMetric::factory()->create([
            'webhook_config_id' => $this->webhookConfig->id,
            'payload_size' => null,
        ]);

        $this->assertEquals('N/A', $metric->getFormattedPayloadSize());
    }

    /** @test */
    public function it_can_get_error_details()
    {
        $errorDetails = [
            'error' => 'Invalid signature',
            'code' => 'AUTH_FAILED',
        ];

        $metric = WebhookDeliveryMetric::factory()->create([
            'webhook_config_id' => $this->webhookConfig->id,
            'status' => 'error',
            'error_details' => $errorDetails,
        ]);

        $this->assertEquals($errorDetails, $metric->getErrorDetails());
        $this->assertEquals('Invalid signature', $metric->getErrorDetails('error'));
        $this->assertEquals('AUTH_FAILED', $metric->getErrorDetails('code'));
        $this->assertNull($metric->getErrorDetails('nonexistent'));
        $this->assertEquals('default', $metric->getErrorDetails('nonexistent', 'default'));
    }

    /** @test */
    public function it_handles_null_error_details()
    {
        $metric = WebhookDeliveryMetric::factory()->create([
            'webhook_config_id' => $this->webhookConfig->id,
            'error_details' => null,
        ]);

        $this->assertNull($metric->getErrorDetails());
        $this->assertEquals('default', $metric->getErrorDetails('any', 'default'));
    }

    /** @test */
    public function it_can_get_status_color_class()
    {
        $successMetric = WebhookDeliveryMetric::factory()->create([
            'webhook_config_id' => $this->webhookConfig->id,
            'status' => 'success',
        ]);

        $errorMetric = WebhookDeliveryMetric::factory()->create([
            'webhook_config_id' => $this->webhookConfig->id,
            'status' => 'error',
        ]);

        $failedMetric = WebhookDeliveryMetric::factory()->create([
            'webhook_config_id' => $this->webhookConfig->id,
            'status' => 'failed',
        ]);

        $pendingMetric = WebhookDeliveryMetric::factory()->create([
            'webhook_config_id' => $this->webhookConfig->id,
            'status' => 'pending',
        ]);

        $this->assertEquals('text-green-600', $successMetric->getStatusColorClass());
        $this->assertEquals('text-red-600', $errorMetric->getStatusColorClass());
        $this->assertEquals('text-red-600', $failedMetric->getStatusColorClass());
        $this->assertEquals('text-yellow-600', $pendingMetric->getStatusColorClass());
    }
}