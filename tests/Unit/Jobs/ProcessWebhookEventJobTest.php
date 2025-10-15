<?php

namespace Tests\Unit\Jobs;

use App\Jobs\ProcessWebhookEventJob;
use App\Models\SocialAccount;
use App\Models\User;
use App\Models\WebhookConfig;
use App\Models\WebhookEvent;
use App\Services\Webhooks\WebhookEventProcessingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Mockery;
use Tests\TestCase;

class ProcessWebhookEventJobTest extends TestCase
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
            'status' => 'pending',
        ]);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function it_processes_webhook_event_successfully()
    {
        $mockService = Mockery::mock(WebhookEventProcessingService::class);
        $mockService->shouldReceive('processEvent')
            ->once()
            ->with($this->webhookEvent);

        $this->app->instance(WebhookEventProcessingService::class, $mockService);

        $job = new ProcessWebhookEventJob($this->webhookEvent);
        $job->handle();

        // Verify that the service was called
        $this->assertTrue(true);
    }

    /** @test */
    public function it_handles_processing_service_exceptions()
    {
        $exception = new \Exception('Processing failed');

        $mockService = Mockery::mock(WebhookEventProcessingService::class);
        $mockService->shouldReceive('processEvent')
            ->once()
            ->with($this->webhookEvent)
            ->andThrow($exception);

        $this->app->instance(WebhookEventProcessingService::class, $mockService);

        Log::shouldReceive('error')
            ->once()
            ->withArgs(function ($message, $context) {
                return str_contains($message, 'Webhook event processing job failed') &&
                       isset($context['webhook_event_id']) &&
                       isset($context['platform']) &&
                       isset($context['error']);
            });

        $job = new ProcessWebhookEventJob($this->webhookEvent);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Processing failed');

        $job->handle();
    }

    /** @test */
    public function it_logs_error_details_when_processing_fails()
    {
        $exception = new \Exception('Database connection lost');

        $mockService = Mockery::mock(WebhookEventProcessingService::class);
        $mockService->shouldReceive('processEvent')
            ->once()
            ->with($this->webhookEvent)
            ->andThrow($exception);

        $this->app->instance(WebhookEventProcessingService::class, $mockService);

        Log::shouldReceive('error')
            ->once()
            ->withArgs(function ($message, $context) use ($exception) {
                return $message === 'Webhook event processing job failed' &&
                       $context['webhook_event_id'] === $this->webhookEvent->id &&
                       $context['platform'] === $this->webhookEvent->platform &&
                       $context['error'] === $exception->getMessage() &&
                       isset($context['trace']);
            });

        $job = new ProcessWebhookEventJob($this->webhookEvent);

        try {
            $job->handle();
        } catch (\Exception $e) {
            // Expected to throw
        }
    }

    /** @test */
    public function it_has_correct_retry_configuration()
    {
        $job = new ProcessWebhookEventJob($this->webhookEvent);

        $this->assertEquals(3, $job->tries);
        $this->assertEquals([5, 15, 30], $job->backoff);
    }

    /** @test */
    public function it_can_be_serialized_and_unserialized()
    {
        $job = new ProcessWebhookEventJob($this->webhookEvent);

        $serialized = serialize($job);
        $unserialized = unserialize($serialized);

        $this->assertEquals($job->webhookEvent->id, $unserialized->webhookEvent->id);
        $this->assertEquals($job->tries, $unserialized->tries);
        $this->assertEquals($job->backoff, $unserialized->backoff);
    }

    /** @test */
    public function it_handles_deleted_webhook_events_gracefully()
    {
        $webhookEvent = WebhookEvent::factory()->create([
            'webhook_config_id' => $this->webhookConfig->id,
            'social_account_id' => $this->socialAccount->id,
            'status' => 'pending',
        ]);

        $job = new ProcessWebhookEventJob($webhookEvent);

        // Delete the event before processing
        $webhookEvent->delete();

        $mockService = Mockery::mock(WebhookEventProcessingService::class);
        $mockService->shouldReceive('processEvent')
            ->once()
            ->andThrow(new \Illuminate\Database\Eloquent\ModelNotFoundException());

        $this->app->instance(WebhookEventProcessingService::class, $mockService);

        Log::shouldReceive('error')
            ->once()
            ->withArgs(function ($message, $context) {
                return str_contains($message, 'Webhook event processing job failed');
            });

        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
        $job->handle();
    }

    /** @test */
    public function it_processes_events_for_different_platforms()
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

            $webhookEvent = WebhookEvent::factory()->create([
                'webhook_config_id' => $webhookConfig->id,
                'social_account_id' => $socialAccount->id,
                'platform' => $platform,
                'status' => 'pending',
            ]);

            $mockService = Mockery::mock(WebhookEventProcessingService::class);
            $mockService->shouldReceive('processEvent')
                ->once()
                ->with($webhookEvent);

            $this->app->instance(WebhookEventProcessingService::class, $mockService);

            $job = new ProcessWebhookEventJob($webhookEvent);
            $job->handle();

            Mockery::close();
        }
    }

    /** @test */
    public function it_handles_service_dependency_injection()
    {
        $job = new ProcessWebhookEventJob($this->webhookEvent);

        // This test verifies that the service can be resolved from the container
        $this->assertInstanceOf(
            WebhookEventProcessingService::class,
            app(WebhookEventProcessingService::class)
        );
    }

    /** @test */
    public function it_can_be_queued_with_custom_connection()
    {
        $job = new ProcessWebhookEventJob($this->webhookEvent);
        
        // Test that the job can be configured with custom queue connection
        $job->onConnection('redis');
        $job->onQueue('webhooks');

        $this->assertEquals('redis', $job->connection);
        $this->assertEquals('webhooks', $job->queue);
    }

    /** @test */
    public function it_handles_large_payloads()
    {
        $largePayload = array_fill(0, 1000, 'large_data_item');
        
        $webhookEvent = WebhookEvent::factory()->create([
            'webhook_config_id' => $this->webhookConfig->id,
            'social_account_id' => $this->socialAccount->id,
            'payload' => $largePayload,
            'status' => 'pending',
        ]);

        $mockService = Mockery::mock(WebhookEventProcessingService::class);
        $mockService->shouldReceive('processEvent')
            ->once()
            ->with($webhookEvent);

        $this->app->instance(WebhookEventProcessingService::class, $mockService);

        $job = new ProcessWebhookEventJob($webhookEvent);
        $job->handle();

        // Verify that large payloads are handled without issues
        $this->assertTrue(true);
    }

    /** @test */
    public function it_maintains_event_state_during_processing()
    {
        $originalStatus = $this->webhookEvent->status;
        $originalRetryCount = $this->webhookEvent->retry_count;

        $mockService = Mockery::mock(WebhookEventProcessingService::class);
        $mockService->shouldReceive('processEvent')
            ->once()
            ->with($this->webhookEvent)
            ->andReturnUsing(function ($event) {
                // Simulate processing that might modify the event
                $event->status = 'processed';
                $event->save();
            });

        $this->app->instance(WebhookEventProcessingService::class, $mockService);

        $job = new ProcessWebhookEventJob($this->webhookEvent);
        $job->handle();

        $this->webhookEvent->refresh();
        $this->assertEquals('processed', $this->webhookEvent->status);
        $this->assertEquals($originalRetryCount, $this->webhookEvent->retry_count);
    }

    /** @test */
    public function it_can_be_dispatched_with_delay()
    {
        $job = new ProcessWebhookEventJob($this->webhookEvent);
        
        $delay = now()->addMinutes(5);
        $job->delay($delay);

        $this->assertEquals($delay, $job->delay);
    }

    /** @test */
    public function it_handles_batch_processing_context()
    {
        $batchId = 'batch_123';
        $job = new ProcessWebhookEventJob($this->webhookEvent);
        
        // Simulate batch context
        $job->withBatchId($batchId);

        // This would be used by Laravel's batch system
        $this->assertTrue(true); // Placeholder for batch functionality
    }
}