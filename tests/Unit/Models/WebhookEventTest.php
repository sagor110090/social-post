<?php

namespace Tests\Unit\Models;

use App\Models\SocialAccount;
use App\Models\User;
use App\Models\WebhookConfig;
use App\Models\WebhookEvent;
use App\Models\WebhookEventProcessing;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WebhookEventTest extends TestCase
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
    public function it_can_create_a_webhook_event()
    {
        $event = WebhookEvent::factory()->create([
            'webhook_config_id' => $this->webhookConfig->id,
            'social_account_id' => $this->socialAccount->id,
        ]);

        $this->assertInstanceOf(WebhookEvent::class, $event);
        $this->assertEquals($this->webhookConfig->id, $event->webhook_config_id);
        $this->assertEquals($this->socialAccount->id, $event->social_account_id);
        $this->assertEquals('facebook', $event->platform);
        $this->assertEquals('pending', $event->status);
    }

    /** @test */
    public function it_casts_attributes_correctly()
    {
        $event = WebhookEvent::factory()->create([
            'webhook_config_id' => $this->webhookConfig->id,
            'social_account_id' => $this->socialAccount->id,
            'payload' => ['test' => 'data'],
            'received_at' => now(),
            'processed_at' => now(),
        ]);

        $this->assertIsArray($event->payload);
        $this->assertInstanceOf(Carbon::class, $event->received_at);
        $this->assertInstanceOf(Carbon::class, $event->processed_at);
    }

    /** @test */
    public function it_belongs_to_social_account()
    {
        $event = WebhookEvent::factory()->create([
            'webhook_config_id' => $this->webhookConfig->id,
            'social_account_id' => $this->socialAccount->id,
        ]);

        $this->assertInstanceOf(SocialAccount::class, $event->socialAccount);
        $this->assertEquals($this->socialAccount->id, $event->socialAccount->id);
    }

    /** @test */
    public function it_belongs_to_webhook_config()
    {
        $event = WebhookEvent::factory()->create([
            'webhook_config_id' => $this->webhookConfig->id,
            'social_account_id' => $this->socialAccount->id,
        ]);

        $this->assertInstanceOf(WebhookConfig::class, $event->webhookConfig);
        $this->assertEquals($this->webhookConfig->id, $event->webhookConfig->id);
    }

    /** @test */
    public function it_has_many_processing_records()
    {
        $event = WebhookEvent::factory()->create([
            'webhook_config_id' => $this->webhookConfig->id,
            'social_account_id' => $this->socialAccount->id,
        ]);

        $processing1 = WebhookEventProcessing::factory()->create([
            'webhook_event_id' => $event->id,
        ]);

        $processing2 = WebhookEventProcessing::factory()->create([
            'webhook_event_id' => $event->id,
        ]);

        $processingRecords = $event->processing;
        $this->assertCount(2, $processingRecords);
        $this->assertInstanceOf(WebhookEventProcessing::class, $processingRecords->first());
    }

    /** @test */
    public function it_can_scope_to_pending_events()
    {
        $pendingEvent = WebhookEvent::factory()->create([
            'webhook_config_id' => $this->webhookConfig->id,
            'social_account_id' => $this->socialAccount->id,
            'status' => 'pending',
        ]);

        $processedEvent = WebhookEvent::factory()->create([
            'webhook_config_id' => $this->webhookConfig->id,
            'social_account_id' => $this->socialAccount->id,
            'status' => 'processed',
        ]);

        $failedEvent = WebhookEvent::factory()->create([
            'webhook_config_id' => $this->webhookConfig->id,
            'social_account_id' => $this->socialAccount->id,
            'status' => 'failed',
        ]);

        $pendingEvents = WebhookEvent::pending()->get();
        
        $this->assertCount(1, $pendingEvents);
        $this->assertEquals($pendingEvent->id, $pendingEvents->first()->id);
    }

    /** @test */
    public function it_can_scope_to_failed_events()
    {
        $pendingEvent = WebhookEvent::factory()->create([
            'webhook_config_id' => $this->webhookConfig->id,
            'social_account_id' => $this->socialAccount->id,
            'status' => 'pending',
        ]);

        $processedEvent = WebhookEvent::factory()->create([
            'webhook_config_id' => $this->webhookConfig->id,
            'social_account_id' => $this->socialAccount->id,
            'status' => 'processed',
        ]);

        $failedEvent = WebhookEvent::factory()->create([
            'webhook_config_id' => $this->webhookConfig->id,
            'social_account_id' => $this->socialAccount->id,
            'status' => 'failed',
        ]);

        $failedEvents = WebhookEvent::failed()->get();
        
        $this->assertCount(1, $failedEvents);
        $this->assertEquals($failedEvent->id, $failedEvents->first()->id);
    }

    /** @test */
    public function it_can_scope_to_events_that_need_retry()
    {
        $recentFailedEvent = WebhookEvent::factory()->create([
            'webhook_config_id' => $this->webhookConfig->id,
            'social_account_id' => $this->socialAccount->id,
            'status' => 'failed',
            'retry_count' => 2,
            'updated_at' => now()->subMinutes(2), // Recent failure
        ]);

        $oldFailedEvent = WebhookEvent::factory()->create([
            'webhook_config_id' => $this->webhookConfig->id,
            'social_account_id' => $this->socialAccount->id,
            'status' => 'failed',
            'retry_count' => 2,
            'updated_at' => now()->subMinutes(10), // Old enough for retry
        ]);

        $maxRetryEvent = WebhookEvent::factory()->create([
            'webhook_config_id' => $this->webhookConfig->id,
            'social_account_id' => $this->socialAccount->id,
            'status' => 'failed',
            'retry_count' => 5, // Max retries reached
            'updated_at' => now()->subMinutes(10),
        ]);

        $processedEvent = WebhookEvent::factory()->create([
            'webhook_config_id' => $this->webhookConfig->id,
            'social_account_id' => $this->socialAccount->id,
            'status' => 'processed',
        ]);

        $retryEvents = WebhookEvent::needsRetry()->get();
        
        $this->assertCount(1, $retryEvents);
        $this->assertEquals($oldFailedEvent->id, $retryEvents->first()->id);
    }

    /** @test */
    public function it_can_mark_as_processed()
    {
        $event = WebhookEvent::factory()->create([
            'webhook_config_id' => $this->webhookConfig->id,
            'social_account_id' => $this->socialAccount->id,
            'status' => 'pending',
            'processed_at' => null,
        ]);

        $event->markAsProcessed();

        $freshEvent = $event->fresh();
        $this->assertEquals('processed', $freshEvent->status);
        $this->assertNotNull($freshEvent->processed_at);
        $this->assertInstanceOf(Carbon::class, $freshEvent->processed_at);
    }

    /** @test */
    public function it_can_mark_as_failed()
    {
        $event = WebhookEvent::factory()->create([
            'webhook_config_id' => $this->webhookConfig->id,
            'social_account_id' => $this->socialAccount->id,
            'status' => 'pending',
            'retry_count' => 2,
            'error_message' => null,
        ]);

        $errorMessage = 'Processing failed';
        $event->markAsFailed($errorMessage);

        $freshEvent = $event->fresh();
        $this->assertEquals('failed', $freshEvent->status);
        $this->assertEquals($errorMessage, $freshEvent->error_message);
        $this->assertEquals(3, $freshEvent->retry_count);
    }

    /** @test */
    public function it_can_mark_as_ignored()
    {
        $event = WebhookEvent::factory()->create([
            'webhook_config_id' => $this->webhookConfig->id,
            'social_account_id' => $this->socialAccount->id,
            'status' => 'pending',
            'processed_at' => null,
        ]);

        $event->markAsIgnored();

        $freshEvent = $event->fresh();
        $this->assertEquals('ignored', $freshEvent->status);
        $this->assertNotNull($freshEvent->processed_at);
    }

    /** @test */
    public function it_can_check_if_can_be_retried()
    {
        $retryableEvent = WebhookEvent::factory()->create([
            'webhook_config_id' => $this->webhookConfig->id,
            'social_account_id' => $this->socialAccount->id,
            'status' => 'failed',
            'retry_count' => 2,
        ]);

        $maxRetryEvent = WebhookEvent::factory()->create([
            'webhook_config_id' => $this->webhookConfig->id,
            'social_account_id' => $this->socialAccount->id,
            'status' => 'failed',
            'retry_count' => 5,
        ]);

        $processedEvent = WebhookEvent::factory()->create([
            'webhook_config_id' => $this->webhookConfig->id,
            'social_account_id' => $this->socialAccount->id,
            'status' => 'processed',
            'retry_count' => 0,
        ]);

        $this->assertTrue($retryableEvent->canRetry());
        $this->assertFalse($maxRetryEvent->canRetry());
        $this->assertFalse($processedEvent->canRetry());
    }

    /** @test */
    public function it_can_get_platform_data()
    {
        $payload = [
            'entry' => [
                [
                    'changes' => [
                        [
                            'field' => 'feed',
                            'value' => [
                                'post_id' => '123456789',
                                'message' => 'Test message',
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $event = WebhookEvent::factory()->create([
            'webhook_config_id' => $this->webhookConfig->id,
            'social_account_id' => $this->socialAccount->id,
            'payload' => $payload,
        ]);

        $this->assertEquals('123456789', $event->getPlatformData('entry.0.changes.0.value.post_id'));
        $this->assertEquals('Test message', $event->getPlatformData('entry.0.changes.0.value.message'));
        $this->assertNull($event->getPlatformData('nonexistent.key'));
        $this->assertEquals('default', $event->getPlatformData('nonexistent.key', 'default'));
    }

    /** @test */
    public function it_can_check_if_relates_to_post()
    {
        $payload = [
            'post_id' => 'post_123',
            'media' => [
                'id' => 'media_456',
            ],
        ];

        $event = WebhookEvent::factory()->create([
            'webhook_config_id' => $this->webhookConfig->id,
            'social_account_id' => $this->socialAccount->id,
            'object_id' => 'object_789',
            'payload' => $payload,
        ]);

        $this->assertTrue($event->relatesToPost('object_789')); // object_id match
        $this->assertTrue($event->relatesToPost('post_123')); // payload post_id match
        $this->assertTrue($event->relatesToPost('media_456')); // payload media.id match
        $this->assertFalse($event->relatesToPost('nonexistent_post'));
    }

    /** @test */
    public function it_handles_empty_payload_when_getting_platform_data()
    {
        $event = WebhookEvent::factory()->create([
            'webhook_config_id' => $this->webhookConfig->id,
            'social_account_id' => $this->socialAccount->id,
            'payload' => [],
        ]);

        $this->assertNull($event->getPlatformData('any.key'));
        $this->assertEquals('default', $event->getPlatformData('any.key', 'default'));
    }

    /** @test */
    public function it_handles_null_payload_when_getting_platform_data()
    {
        $event = WebhookEvent::factory()->create([
            'webhook_config_id' => $this->webhookConfig->id,
            'social_account_id' => $this->socialAccount->id,
            'payload' => null,
        ]);

        $this->assertNull($event->getPlatformData('any.key'));
        $this->assertEquals('default', $event->getPlatformData('any.key', 'default'));
    }
}