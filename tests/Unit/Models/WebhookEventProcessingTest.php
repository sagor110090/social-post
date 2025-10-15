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

class WebhookEventProcessingTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected SocialAccount $socialAccount;
    protected WebhookConfig $webhookConfig;
    protected WebhookEvent $webhookEvent;

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
        $this->webhookEvent = WebhookEvent::factory()->create([
            'webhook_config_id' => $this->webhookConfig->id,
            'social_account_id' => $this->socialAccount->id,
        ]);
    }

    /** @test */
    public function it_can_create_a_webhook_event_processing_record()
    {
        $processing = WebhookEventProcessing::factory()->create([
            'webhook_event_id' => $this->webhookEvent->id,
        ]);

        $this->assertInstanceOf(WebhookEventProcessing::class, $processing);
        $this->assertEquals($this->webhookEvent->id, $processing->webhook_event_id);
        $this->assertNotNull($processing->processor);
        $this->assertEquals('pending', $processing->status);
    }

    /** @test */
    public function it_casts_attributes_correctly()
    {
        $processing = WebhookEventProcessing::factory()->create([
            'webhook_event_id' => $this->webhookEvent->id,
            'started_at' => now(),
            'completed_at' => now()->addSeconds(5),
            'processing_time' => 150.5,
            'metadata' => ['step' => 'validation'],
        ]);

        $this->assertInstanceOf(Carbon::class, $processing->started_at);
        $this->assertInstanceOf(Carbon::class, $processing->completed_at);
        $this->assertIsFloat($processing->processing_time);
        $this->assertIsArray($processing->metadata);
    }

    /** @test */
    public function it_belongs_to_webhook_event()
    {
        $processing = WebhookEventProcessing::factory()->create([
            'webhook_event_id' => $this->webhookEvent->id,
        ]);

        $this->assertInstanceOf(WebhookEvent::class, $processing->webhookEvent);
        $this->assertEquals($this->webhookEvent->id, $processing->webhookEvent->id);
    }

    /** @test */
    public function it_can_scope_to_pending_processing()
    {
        $pendingProcessing = WebhookEventProcessing::factory()->create([
            'webhook_event_id' => $this->webhookEvent->id,
            'status' => 'pending',
        ]);

        $completedProcessing = WebhookEventProcessing::factory()->create([
            'webhook_event_id' => $this->webhookEvent->id,
            'status' => 'completed',
        ]);

        $failedProcessing = WebhookEventProcessing::factory()->create([
            'webhook_event_id' => $this->webhookEvent->id,
            'status' => 'failed',
        ]);

        $pendingProcessings = WebhookEventProcessing::pending()->get();
        
        $this->assertCount(1, $pendingProcessings);
        $this->assertEquals($pendingProcessing->id, $pendingProcessings->first()->id);
    }

    /** @test */
    public function it_can_scope_to_completed_processing()
    {
        $pendingProcessing = WebhookEventProcessing::factory()->create([
            'webhook_event_id' => $this->webhookEvent->id,
            'status' => 'pending',
        ]);

        $completedProcessing = WebhookEventProcessing::factory()->create([
            'webhook_event_id' => $this->webhookEvent->id,
            'status' => 'completed',
        ]);

        $failedProcessing = WebhookEventProcessing::factory()->create([
            'webhook_event_id' => $this->webhookEvent->id,
            'status' => 'failed',
        ]);

        $completedProcessings = WebhookEventProcessing::completed()->get();
        
        $this->assertCount(1, $completedProcessings);
        $this->assertEquals($completedProcessing->id, $completedProcessings->first()->id);
    }

    /** @test */
    public function it_can_scope_to_failed_processing()
    {
        $pendingProcessing = WebhookEventProcessing::factory()->create([
            'webhook_event_id' => $this->webhookEvent->id,
            'status' => 'pending',
        ]);

        $completedProcessing = WebhookEventProcessing::factory()->create([
            'webhook_event_id' => $this->webhookEvent->id,
            'status' => 'completed',
        ]);

        $failedProcessing = WebhookEventProcessing::factory()->create([
            'webhook_event_id' => $this->webhookEvent->id,
            'status' => 'failed',
        ]);

        $failedProcessings = WebhookEventProcessing::failed()->get();
        
        $this->assertCount(1, $failedProcessings);
        $this->assertEquals($failedProcessing->id, $failedProcessings->first()->id);
    }

    /** @test */
    public function it_can_scope_to_processing_by_processor()
    {
        $eventProcessor = WebhookEventProcessing::factory()->create([
            'webhook_event_id' => $this->webhookEvent->id,
            'processor' => 'EventProcessor',
        ]);

        $analyticsProcessor = WebhookEventProcessing::factory()->create([
            'webhook_event_id' => $this->webhookEvent->id,
            'processor' => 'AnalyticsProcessor',
        ]);

        $eventProcessings = WebhookEventProcessing::byProcessor('EventProcessor')->get();
        
        $this->assertCount(1, $eventProcessings);
        $this->assertEquals($eventProcessor->id, $eventProcessings->first()->id);
    }

    /** @test */
    public function it_can_start_processing()
    {
        $processing = WebhookEventProcessing::factory()->create([
            'webhook_event_id' => $this->webhookEvent->id,
            'status' => 'pending',
            'started_at' => null,
        ]);

        $processing->start('TestProcessor');

        $freshProcessing = $processing->fresh();
        $this->assertEquals('processing', $freshProcessing->status);
        $this->assertEquals('TestProcessor', $freshProcessing->processor);
        $this->assertNotNull($freshProcessing->started_at);
        $this->assertInstanceOf(Carbon::class, $freshProcessing->started_at);
    }

    /** @test */
    public function it_can_complete_processing()
    {
        $processing = WebhookEventProcessing::factory()->create([
            'webhook_event_id' => $this->webhookEvent->id,
            'status' => 'processing',
            'started_at' => now()->subSeconds(5),
            'completed_at' => null,
        ]);

        $processing->complete();

        $freshProcessing = $processing->fresh();
        $this->assertEquals('completed', $freshProcessing->status);
        $this->assertNotNull($freshProcessing->completed_at);
        $this->assertInstanceOf(Carbon::class, $freshProcessing->completed_at);
        $this->assertNotNull($freshProcessing->processing_time);
        $this->assertIsFloat($freshProcessing->processing_time);
    }

    /** @test */
    public function it_can_fail_processing()
    {
        $processing = WebhookEventProcessing::factory()->create([
            'webhook_event_id' => $this->webhookEvent->id,
            'status' => 'processing',
            'error_message' => null,
        ]);

        $errorMessage = 'Processing failed due to invalid data';
        $processing->fail($errorMessage);

        $freshProcessing = $processing->fresh();
        $this->assertEquals('failed', $freshProcessing->status);
        $this->assertEquals($errorMessage, $freshProcessing->error_message);
        $this->assertNotNull($freshProcessing->completed_at);
    }

    /** @test */
    public function it_can_check_if_processing_is_completed()
    {
        $pendingProcessing = WebhookEventProcessing::factory()->create([
            'webhook_event_id' => $this->webhookEvent->id,
            'status' => 'pending',
        ]);

        $completedProcessing = WebhookEventProcessing::factory()->create([
            'webhook_event_id' => $this->webhookEvent->id,
            'status' => 'completed',
        ]);

        $failedProcessing = WebhookEventProcessing::factory()->create([
            'webhook_event_id' => $this->webhookEvent->id,
            'status' => 'failed',
        ]);

        $this->assertFalse($pendingProcessing->isCompleted());
        $this->assertTrue($completedProcessing->isCompleted());
        $this->assertTrue($failedProcessing->isCompleted());
    }

    /** @test */
    public function it_can_check_if_processing_is_successful()
    {
        $completedProcessing = WebhookEventProcessing::factory()->create([
            'webhook_event_id' => $this->webhookEvent->id,
            'status' => 'completed',
        ]);

        $failedProcessing = WebhookEventProcessing::factory()->create([
            'webhook_event_id' => $this->webhookEvent->id,
            'status' => 'failed',
        ]);

        $this->assertTrue($completedProcessing->isSuccessful());
        $this->assertFalse($failedProcessing->isSuccessful());
    }

    /** @test */
    public function it_can_get_formatted_processing_time()
    {
        $processing = WebhookEventProcessing::factory()->create([
            'webhook_event_id' => $this->webhookEvent->id,
            'processing_time' => 150.5,
        ]);

        $this->assertEquals('150.50ms', $processing->getFormattedProcessingTime());
    }

    /** @test */
    public function it_handles_null_processing_time()
    {
        $processing = WebhookEventProcessing::factory()->create([
            'webhook_event_id' => $this->webhookEvent->id,
            'processing_time' => null,
        ]);

        $this->assertEquals('N/A', $processing->getFormattedProcessingTime());
    }

    /** @test */
    public function it_can_get_metadata_value()
    {
        $metadata = [
            'step' => 'validation',
            'attempts' => 3,
            'details' => [
                'validated_fields' => ['event_type', 'payload'],
            ],
        ];

        $processing = WebhookEventProcessing::factory()->create([
            'webhook_event_id' => $this->webhookEvent->id,
            'metadata' => $metadata,
        ]);

        $this->assertEquals('validation', $processing->getMetadata('step'));
        $this->assertEquals(3, $processing->getMetadata('attempts'));
        $this->assertEquals(['event_type', 'payload'], $processing->getMetadata('details.validated_fields'));
        $this->assertNull($processing->getMetadata('nonexistent'));
        $this->assertEquals('default', $processing->getMetadata('nonexistent', 'default'));
    }

    /** @test */
    public function it_handles_empty_metadata()
    {
        $processing = WebhookEventProcessing::factory()->create([
            'webhook_event_id' => $this->webhookEvent->id,
            'metadata' => [],
        ]);

        $this->assertNull($processing->getMetadata('any.key'));
        $this->assertEquals('default', $processing->getMetadata('any.key', 'default'));
    }

    /** @test */
    public function it_handles_null_metadata()
    {
        $processing = WebhookEventProcessing::factory()->create([
            'webhook_event_id' => $this->webhookEvent->id,
            'metadata' => null,
        ]);

        $this->assertNull($processing->getMetadata('any.key'));
        $this->assertEquals('default', $processing->getMetadata('any.key', 'default'));
    }

    /** @test */
    public function it_can_set_metadata_value()
    {
        $processing = WebhookEventProcessing::factory()->create([
            'webhook_event_id' => $this->webhookEvent->id,
            'metadata' => ['initial' => 'value'],
        ]);

        $processing->setMetadata('new_key', 'new_value');
        $processing->setMetadata('nested.key', 'nested_value');

        $freshProcessing = $processing->fresh();
        $this->assertEquals('new_value', $freshProcessing->getMetadata('new_key'));
        $this->assertEquals('nested_value', $freshProcessing->getMetadata('nested.key'));
        $this->assertEquals('value', $freshProcessing->getMetadata('initial'));
    }

    /** @test */
    public function it_can_get_status_color_class()
    {
        $pendingProcessing = WebhookEventProcessing::factory()->create([
            'webhook_event_id' => $this->webhookEvent->id,
            'status' => 'pending',
        ]);

        $processingProcessing = WebhookEventProcessing::factory()->create([
            'webhook_event_id' => $this->webhookEvent->id,
            'status' => 'processing',
        ]);

        $completedProcessing = WebhookEventProcessing::factory()->create([
            'webhook_event_id' => $this->webhookEvent->id,
            'status' => 'completed',
        ]);

        $failedProcessing = WebhookEventProcessing::factory()->create([
            'webhook_event_id' => $this->webhookEvent->id,
            'status' => 'failed',
        ]);

        $this->assertEquals('text-yellow-600', $pendingProcessing->getStatusColorClass());
        $this->assertEquals('text-blue-600', $processingProcessing->getStatusColorClass());
        $this->assertEquals('text-green-600', $completedProcessing->getStatusColorClass());
        $this->assertEquals('text-red-600', $failedProcessing->getStatusColorClass());
    }

    /** @test */
    public function it_can_calculate_processing_duration()
    {
        $startedAt = now()->subSeconds(10);
        $completedAt = now()->subSeconds(5);

        $processing = WebhookEventProcessing::factory()->create([
            'webhook_event_id' => $this->webhookEvent->id,
            'started_at' => $startedAt,
            'completed_at' => $completedAt,
        ]);

        $duration = $processing->getDuration();
        
        $this->assertEquals(5, $duration);
        $this->assertIsInt($duration);
    }

    /** @test */
    public function it_returns_null_duration_if_not_completed()
    {
        $processing = WebhookEventProcessing::factory()->create([
            'webhook_event_id' => $this->webhookEvent->id,
            'started_at' => now()->subSeconds(5),
            'completed_at' => null,
        ]);

        $this->assertNull($processing->getDuration());
    }
}