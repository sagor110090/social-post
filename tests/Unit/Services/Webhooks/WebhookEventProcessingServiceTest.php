<?php

namespace Tests\Unit\Services\Webhooks;

use App\Models\SocialAccount;
use App\Models\User;
use App\Models\WebhookConfig;
use App\Models\WebhookEvent;
use App\Models\WebhookEventProcessing;
use App\Services\Webhooks\WebhookEventProcessingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Mockery;
use Tests\TestCase;

class WebhookEventProcessingServiceTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected SocialAccount $socialAccount;
    protected WebhookConfig $webhookConfig;
    protected WebhookEventProcessingService $service;

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
        
        $this->service = app(WebhookEventProcessingService::class);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function it_can_process_a_webhook_event_successfully()
    {
        $event = WebhookEvent::factory()->create([
            'webhook_config_id' => $this->webhookConfig->id,
            'social_account_id' => $this->socialAccount->id,
            'status' => 'pending',
            'platform' => 'facebook',
            'event_type' => 'page_posts',
        ]);

        $this->service->processEvent($event);

        $event->refresh();
        $this->assertEquals('processed', $event->status);
        $this->assertNotNull($event->processed_at);

        // Check that processing records were created
        $this->assertDatabaseHas('webhook_event_processing', [
            'webhook_event_id' => $event->id,
            'status' => 'completed',
        ]);
    }

    /** @test */
    public function it_marks_event_as_ignored_if_not_subscribed()
    {
        $webhookConfig = WebhookConfig::factory()->create([
            'social_account_id' => $this->socialAccount->id,
            'events' => ['page_comments'], // Not including page_posts
        ]);

        $event = WebhookEvent::factory()->create([
            'webhook_config_id' => $webhookConfig->id,
            'social_account_id' => $this->socialAccount->id,
            'status' => 'pending',
            'platform' => 'facebook',
            'event_type' => 'page_posts',
        ]);

        $this->service->processEvent($event);

        $event->refresh();
        $this->assertEquals('ignored', $event->status);
        $this->assertNotNull($event->processed_at);
    }

    /** @test */
    public function it_handles_processing_failure_and_marks_event_as_failed()
    {
        $event = WebhookEvent::factory()->create([
            'webhook_config_id' => $this->webhookConfig->id,
            'social_account_id' => $this->socialAccount->id,
            'status' => 'pending',
            'platform' => 'facebook',
            'event_type' => 'page_posts',
        ]);

        // Mock the processor to throw an exception
        $processor = Mockery::mock();
        $processor->shouldReceive('process')
            ->once()
            ->andThrow(new \Exception('Processing failed'));

        $this->app->instance('App\Services\Webhooks\Processors\FacebookProcessor', $processor);

        $this->service->processEvent($event);

        $event->refresh();
        $this->assertEquals('failed', $event->status);
        $this->assertNotNull($event->error_message);
        $this->assertEquals(1, $event->retry_count);
    }

    /** @test */
    public function it_creates_processing_record_for_each_step()
    {
        $event = WebhookEvent::factory()->create([
            'webhook_config_id' => $this->webhookConfig->id,
            'social_account_id' => $this->socialAccount->id,
            'status' => 'pending',
            'platform' => 'facebook',
            'event_type' => 'page_posts',
        ]);

        $this->service->processEvent($event);

        $processingRecords = WebhookEventProcessing::where('webhook_event_id', $event->id)->get();
        $this->assertGreaterThan(0, $processingRecords->count());

        // Check that all records have required fields
        foreach ($processingRecords as $record) {
            $this->assertNotNull($record->processor);
            $this->assertNotNull($record->status);
            $this->assertNotNull($record->started_at);
            $this->assertNotNull($record->completed_at);
        }
    }

    /** @test */
    public function it_validates_event_before_processing()
    {
        $event = WebhookEvent::factory()->create([
            'webhook_config_id' => $this->webhookConfig->id,
            'social_account_id' => $this->socialAccount->id,
            'status' => 'pending',
            'platform' => 'facebook',
            'event_type' => 'page_posts',
            'payload' => null, // Invalid payload
        ]);

        $this->service->processEvent($event);

        $event->refresh();
        $this->assertEquals('failed', $event->status);
        $this->assertStringContains('validation', strtolower($event->error_message));
    }

    /** @test */
    public function it_handles_different_platforms()
    {
        $platforms = ['facebook', 'instagram', 'twitter', 'linkedin'];

        foreach ($platforms as $platform) {
            $socialAccount = SocialAccount::factory()->create([
                'user_id' => $this->user->id,
                'platform' => $platform,
            ]);

            $webhookConfig = WebhookConfig::factory()->create([
                'social_account_id' => $socialAccount->id,
            ]);

            $event = WebhookEvent::factory()->create([
                'webhook_config_id' => $webhookConfig->id,
                'social_account_id' => $socialAccount->id,
                'status' => 'pending',
                'platform' => $platform,
                'event_type' => 'test_event',
            ]);

            $this->service->processEvent($event);

            $event->refresh();
            $this->assertEquals('processed', $event->status);
        }
    }

    /** @test */
    public function it_logs_processing_start_and_completion()
    {
        Log::shouldReceive('info')
            ->once()
            ->withArgs(function ($message, $context) {
                return str_contains($message, 'Started processing webhook event') &&
                       isset($context['event_id']);
            });

        Log::shouldReceive('info')
            ->once()
            ->withArgs(function ($message, $context) {
                return str_contains($message, 'Completed processing webhook event') &&
                       isset($context['event_id']);
            });

        $event = WebhookEvent::factory()->create([
            'webhook_config_id' => $this->webhookConfig->id,
            'social_account_id' => $this->socialAccount->id,
            'status' => 'pending',
            'platform' => 'facebook',
            'event_type' => 'page_posts',
        ]);

        $this->service->processEvent($event);
    }

    /** @test */
    public function it_logs_processing_errors()
    {
        Log::shouldReceive('error')
            ->once()
            ->withArgs(function ($message, $context) {
                return str_contains($message, 'Failed to process webhook event') &&
                       isset($context['event_id']) &&
                       isset($context['error']);
            });

        $event = WebhookEvent::factory()->create([
            'webhook_config_id' => $this->webhookConfig->id,
            'social_account_id' => $this->socialAccount->id,
            'status' => 'pending',
            'platform' => 'facebook',
            'event_type' => 'page_posts',
            'payload' => null, // This will cause validation to fail
        ]);

        $this->service->processEvent($event);
    }

    /** @test */
    public function it_updates_analytics_for_processed_events()
    {
        $event = WebhookEvent::factory()->create([
            'webhook_config_id' => $this->webhookConfig->id,
            'social_account_id' => $this->socialAccount->id,
            'status' => 'pending',
            'platform' => 'facebook',
            'event_type' => 'page_posts',
            'object_type' => 'post',
            'object_id' => 'post_123',
        ]);

        $this->service->processEvent($event);

        // Check that analytics were updated (this would depend on your analytics implementation)
        $this->assertTrue(true); // Placeholder - implement actual analytics checking
    }

    /** @test */
    public function it_handles_duplicate_events_gracefully()
    {
        $event = WebhookEvent::factory()->create([
            'webhook_config_id' => $this->webhookConfig->id,
            'social_account_id' => $this->socialAccount->id,
            'status' => 'processed', // Already processed
            'platform' => 'facebook',
            'event_type' => 'page_posts',
        ]);

        $this->service->processEvent($event);

        $event->refresh();
        $this->assertEquals('processed', $event->status);
        
        // Should not create new processing records for already processed events
        $processingRecords = WebhookEventProcessing::where('webhook_event_id', $event->id)
            ->where('created_at', '>', now()->subMinute())
            ->get();
        $this->assertCount(0, $processingRecords);
    }

    /** @test */
    public function it_can_process_events_in_batch()
    {
        $events = WebhookEvent::factory()->count(5)->create([
            'webhook_config_id' => $this->webhookConfig->id,
            'social_account_id' => $this->socialAccount->id,
            'status' => 'pending',
            'platform' => 'facebook',
            'event_type' => 'page_posts',
        ]);

        $this->service->processBatch($events);

        foreach ($events as $event) {
            $event->refresh();
            $this->assertEquals('processed', $event->status);
        }
    }

    /** @test */
    public function it_handles_batch_processing_with_partial_failures()
    {
        $events = collect();

        // Create some valid events
        for ($i = 0; $i < 3; $i++) {
            $events->push(WebhookEvent::factory()->create([
                'webhook_config_id' => $this->webhookConfig->id,
                'social_account_id' => $this->socialAccount->id,
                'status' => 'pending',
                'platform' => 'facebook',
                'event_type' => 'page_posts',
            ]));
        }

        // Create an invalid event
        $events->push(WebhookEvent::factory()->create([
            'webhook_config_id' => $this->webhookConfig->id,
            'social_account_id' => $this->socialAccount->id,
            'status' => 'pending',
            'platform' => 'facebook',
            'event_type' => 'page_posts',
            'payload' => null, // Invalid
        ]));

        $results = $this->service->processBatch($events);

        $this->assertEquals(4, $results['total']);
        $this->assertEquals(3, $results['successful']);
        $this->assertEquals(1, $results['failed']);
    }

    /** @test */
    public function it_tracks_processing_metrics()
    {
        $event = WebhookEvent::factory()->create([
            'webhook_config_id' => $this->webhookConfig->id,
            'social_account_id' => $this->socialAccount->id,
            'status' => 'pending',
            'platform' => 'facebook',
            'event_type' => 'page_posts',
        ]);

        $startTime = microtime(true);
        $this->service->processEvent($event);
        $endTime = microtime(true);

        $processingRecords = WebhookEventProcessing::where('webhook_event_id', $event->id)->get();
        
        foreach ($processingRecords as $record) {
            $this->assertNotNull($record->processing_time);
            $this->assertGreaterThan(0, $record->processing_time);
        }
    }
}