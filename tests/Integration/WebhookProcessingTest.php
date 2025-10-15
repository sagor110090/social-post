<?php

namespace Tests\Integration;

use App\Jobs\ProcessWebhookEventJob;
use App\Models\SocialAccount;
use App\Models\User;
use App\Models\WebhookConfig;
use App\Models\WebhookDeliveryMetric;
use App\Models\WebhookEvent;
use App\Models\WebhookEventProcessing;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class WebhookProcessingTest extends TestCase
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
            'events' => ['page_posts', 'page_comments'],
        ]);
    }

    /** @test */
    public function it_processes_webhook_end_to_end()
    {
        Queue::fake();

        $payload = [
            'object' => 'page',
            'entry' => [
                [
                    'id' => '123456789',
                    'time' => time(),
                    'changes' => [
                        [
                            'field' => 'feed',
                            'value' => [
                                'post_id' => 'post_123',
                                'message' => 'Test message',
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $signature = 'sha256=' . hash_hmac('sha256', json_encode($payload), $this->webhookConfig->secret);

        $response = $this->postJson('/webhooks/facebook', $payload, [
            'X-Hub-Signature-256' => $signature,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'message' => 'Webhook received',
            ]);

        // Check that webhook event was created
        $this->assertDatabaseHas('webhook_events', [
            'webhook_config_id' => $this->webhookConfig->id,
            'platform' => 'facebook',
            'event_type' => 'page_posts',
            'status' => 'pending',
        ]);

        // Check that delivery metric was recorded
        $this->assertDatabaseHas('webhook_delivery_metrics', [
            'webhook_config_id' => $this->webhookConfig->id,
            'status' => 'received',
            'http_status_code' => 200,
        ]);

        // Check that processing job was dispatched
        Queue::assertPushed(ProcessWebhookEventJob::class);
    }

    /** @test */
    public function it_handles_webhook_verification_challenge()
    {
        $response = $this->get('/webhooks/facebook', [
            'hub_mode' => 'subscribe',
            'hub_challenge' => 'test_challenge_123',
            'hub_verify_token' => 'verify_token',
        ]);

        $response->assertStatus(200)
            ->assertSee('test_challenge_123');
    }

    /** @test */
    public function it_rejects_webhooks_with_invalid_signature()
    {
        $payload = ['test' => 'data'];
        $invalidSignature = 'sha256=invalid_signature';

        $response = $this->postJson('/webhooks/facebook', $payload, [
            'X-Hub-Signature-256' => $invalidSignature,
        ]);

        $response->assertStatus(401)
            ->assertJson([
                'status' => 'error',
                'message' => 'Invalid signature',
            ]);

        // Check that no webhook event was created
        $this->assertDatabaseMissing('webhook_events', [
            'webhook_config_id' => $this->webhookConfig->id,
        ]);

        // Check that failure metric was recorded
        $this->assertDatabaseHas('webhook_delivery_metrics', [
            'webhook_config_id' => $this->webhookConfig->id,
            'status' => 'error',
            'http_status_code' => 401,
        ]);
    }

    /** @test */
    public function it_processes_webhook_event_through_job()
    {
        $webhookEvent = WebhookEvent::factory()->create([
            'webhook_config_id' => $this->webhookConfig->id,
            'social_account_id' => $this->socialAccount->id,
            'platform' => 'facebook',
            'event_type' => 'page_posts',
            'status' => 'pending',
            'payload' => [
                'object' => 'page',
                'entry' => [
                    [
                        'changes' => [
                            [
                                'field' => 'feed',
                                'value' => [
                                    'post_id' => 'post_123',
                                    'message' => 'Test message',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        // Dispatch the job
        ProcessWebhookEventJob::dispatch($webhookEvent);

        // Process the job
        $this->artisan('queue:work', [
            '--once' => true,
            '--queue' => 'default',
        ]);

        // Check that the event was processed
        $webhookEvent->refresh();
        $this->assertEquals('processed', $webhookEvent->status);
        $this->assertNotNull($webhookEvent->processed_at);

        // Check that processing records were created
        $this->assertDatabaseHas('webhook_event_processing', [
            'webhook_event_id' => $webhookEvent->id,
            'status' => 'completed',
        ]);
    }

    /** @test */
    public function it_handles_failed_webhook_processing()
    {
        $webhookEvent = WebhookEvent::factory()->create([
            'webhook_config_id' => $this->webhookConfig->id,
            'social_account_id' => $this->socialAccount->id,
            'platform' => 'facebook',
            'event_type' => 'page_posts',
            'status' => 'pending',
            'payload' => null, // Invalid payload will cause processing to fail
        ]);

        // Dispatch the job
        ProcessWebhookEventJob::dispatch($webhookEvent);

        // Process the job
        $this->artisan('queue:work', [
            '--once' => true,
            '--queue' => 'default',
        ]);

        // Check that the event was marked as failed
        $webhookEvent->refresh();
        $this->assertEquals('failed', $webhookEvent->status);
        $this->assertNotNull($webhookEvent->error_message);
        $this->assertEquals(1, $webhookEvent->retry_count);

        // Check that failure processing record was created
        $this->assertDatabaseHas('webhook_event_processing', [
            'webhook_event_id' => $webhookEvent->id,
            'status' => 'failed',
        ]);
    }

    /** @test */
    public function it_ignores_unsubscribed_events()
    {
        $webhookConfig = WebhookConfig::factory()->create([
            'social_account_id' => $this->socialAccount->id,
            'events' => ['page_comments'], // Not including page_posts
        ]);

        $payload = [
            'object' => 'page',
            'entry' => [
                [
                    'changes' => [
                        [
                            'field' => 'feed',
                            'value' => [
                                'post_id' => 'post_123',
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $signature = 'sha256=' . hash_hmac('sha256', json_encode($payload), $webhookConfig->secret);

        $response = $this->postJson('/webhooks/facebook', $payload, [
            'X-Hub-Signature-256' => $signature,
        ]);

        $response->assertStatus(200);

        // Check that event was created but marked as ignored
        $this->assertDatabaseHas('webhook_events', [
            'webhook_config_id' => $webhookConfig->id,
            'event_type' => 'page_posts',
            'status' => 'ignored',
        ]);
    }

    /** @test */
    public function it_handles_multiple_platforms()
    {
        $platforms = [
            'facebook' => ['X-Hub-Signature-256', 'sha256='],
            'twitter' => ['X-Twitter-Webhooks-Signature', ''],
            'linkedin' => ['X-LI-Signature', ''],
        ];

        foreach ($platforms as $platform => [$header, $prefix]) {
            $socialAccount = SocialAccount::factory()->create([
                'user_id' => $this->user->id,
                'platform' => $platform,
            ]);

            $webhookConfig = WebhookConfig::factory()->create([
                'social_account_id' => $socialAccount->id,
            ]);

            $payload = ['test' => 'data', 'platform' => $platform];
            
            if ($platform === 'facebook') {
                $signature = $prefix . hash_hmac('sha256', json_encode($payload), $webhookConfig->secret);
            } else {
                $signature = base64_encode(hash_hmac('sha256', json_encode($payload), $webhookConfig->secret, true));
            }

            $response = $this->postJson("/webhooks/{$platform}", $payload, [
                $header => $signature,
            ]);

            $response->assertStatus(200);

            $this->assertDatabaseHas('webhook_events', [
                'webhook_config_id' => $webhookConfig->id,
                'platform' => $platform,
            ]);
        }
    }

    /** @test */
    public function it_handles_concurrent_webhook_processing()
    {
        Queue::fake();

        $payloads = [];
        $signatures = [];

        // Create 5 webhook requests
        for ($i = 0; $i < 5; $i++) {
            $payload = [
                'object' => 'page',
                'entry' => [
                    [
                        'id' => "12345678{$i}",
                        'time' => time(),
                        'changes' => [
                            [
                                'field' => 'feed',
                                'value' => [
                                    'post_id' => "post_{$i}",
                                    'message' => "Test message {$i}",
                                ],
                            ],
                        ],
                    ],
                ],
            ];

            $payloads[] = $payload;
            $signatures[] = 'sha256=' . hash_hmac('sha256', json_encode($payload), $this->webhookConfig->secret);
        }

        // Send all requests concurrently
        $responses = collect($payloads)->map(function ($payload, $index) use ($signatures) {
            return $this->postJson('/webhooks/facebook', $payload, [
                'X-Hub-Signature-256' => $signatures[$index],
            ]);
        });

        // All requests should succeed
        $responses->each(function ($response) {
            $response->assertStatus(200);
        });

        // Check that all events were created
        $this->assertEquals(5, WebhookEvent::where('webhook_config_id', $this->webhookConfig->id)->count());

        // Check that all jobs were dispatched
        Queue::assertPushed(ProcessWebhookEventJob::class, 5);
    }

    /** @test */
    public function it_tracks_webhook_processing_metrics()
    {
        $webhookEvent = WebhookEvent::factory()->create([
            'webhook_config_id' => $this->webhookConfig->id,
            'social_account_id' => $this->socialAccount->id,
            'platform' => 'facebook',
            'event_type' => 'page_posts',
            'status' => 'pending',
        ]);

        $startTime = microtime(true);

        // Process the job
        ProcessWebhookEventJob::dispatch($webhookEvent);
        $this->artisan('queue:work', [
            '--once' => true,
            '--queue' => 'default',
        ]);

        $endTime = microtime(true);

        // Check that processing metrics were recorded
        $processingRecords = WebhookEventProcessing::where('webhook_event_id', $webhookEvent->id)->get();
        
        $this->assertGreaterThan(0, $processingRecords->count());
        
        foreach ($processingRecords as $record) {
            $this->assertNotNull($record->processing_time);
            $this->assertGreaterThan(0, $record->processing_time);
            $this->assertNotNull($record->started_at);
            $this->assertNotNull($record->completed_at);
        }
    }

    /** @test */
    public function it_handles_webhook_retries()
    {
        $webhookEvent = WebhookEvent::factory()->create([
            'webhook_config_id' => $this->webhookConfig->id,
            'social_account_id' => $this->socialAccount->id,
            'platform' => 'facebook',
            'event_type' => 'page_posts',
            'status' => 'failed',
            'retry_count' => 2,
            'updated_at' => now()->subMinutes(10), // Old enough for retry
        ]);

        // Trigger retry
        $this->postJson("/webhooks/manage/events/{$webhookEvent->id}/retry")
            ->assertStatus(200);

        $webhookEvent->refresh();
        $this->assertEquals('pending', $webhookEvent->status);
        $this->assertEquals(2, $webhookEvent->retry_count); // Should not increment on manual retry
    }

    /** @test */
    public function it_prevents_excessive_retries()
    {
        $webhookEvent = WebhookEvent::factory()->create([
            'webhook_config_id' => $this->webhookConfig->id,
            'social_account_id' => $this->socialAccount->id,
            'platform' => 'facebook',
            'event_type' => 'page_posts',
            'status' => 'failed',
            'retry_count' => 5, // Max retries reached
        ]);

        // Attempt retry
        $response = $this->postJson("/webhooks/manage/events/{$webhookEvent->id}/retry");

        $response->assertStatus(422)
            ->assertJson([
                'message' => 'Maximum retry attempts reached',
            ]);

        $webhookEvent->refresh();
        $this->assertEquals('failed', $webhookEvent->status);
        $this->assertEquals(5, $webhookEvent->retry_count);
    }

    /** @test */
    public function it_cleans_up_old_webhook_data()
    {
        // Create old webhook events
        WebhookEvent::factory()->count(10)->create([
            'webhook_config_id' => $this->webhookConfig->id,
            'social_account_id' => $this->socialAccount->id,
            'created_at' => now()->subMonths(2),
        ]);

        // Create recent webhook events
        WebhookEvent::factory()->count(5)->create([
            'webhook_config_id' => $this->webhookConfig->id,
            'social_account_id' => $this->socialAccount->id,
            'created_at' => now()->subDays(5),
        ]);

        // Run cleanup command
        $this->artisan('webhooks:cleanup')
            ->assertExitCode(0);

        // Check that old events were deleted
        $this->assertEquals(5, WebhookEvent::where('webhook_config_id', $this->webhookConfig->id)->count());
    }
}