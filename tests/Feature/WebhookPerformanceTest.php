<?php

namespace Tests\Feature;

use App\Jobs\ProcessWebhookEventJob;
use App\Models\SocialAccount;
use App\Models\User;
use App\Models\WebhookConfig;
use App\Models\WebhookDeliveryMetric;
use App\Models\WebhookEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class WebhookPerformanceTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected SocialAccount $socialAccount;
    protected WebhookConfig $webhookConfig;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->user = $this->createUser();
        $this->socialAccount = $this->createSocialAccount($this->user, [
            'platform' => 'facebook',
        ]);
        $this->webhookConfig = $this->createWebhookConfig($this->socialAccount);
    }

    /** @test */
    public function it_handles_high_volume_webhook_requests()
    {
        $requestCount = 100;
        $payloads = [];
        $signatures = [];

        // Generate test data
        for ($i = 0; $i < $requestCount; $i++) {
            $payload = $this->createTestWebhookPayload('facebook', [
                'entry' => [
                    [
                        'id' => "12345678{$i}",
                        'changes' => [
                            [
                                'value' => [
                                    'post_id' => "post_{$i}",
                                    'message' => "Test message {$i}",
                                ],
                            ],
                        ],
                    ],
                ],
            ]);

            $payloads[] = $payload;
            $signatures[] = $this->generateWebhookSignature(
                json_encode($payload),
                $this->webhookConfig->secret
            );
        }

        $startTime = microtime(true);
        $successCount = 0;
        $errorCount = 0;

        // Send all requests
        foreach ($payloads as $index => $payload) {
            $response = $this->postJson('/webhooks/facebook', $payload, [
                'X-Hub-Signature-256' => $signatures[$index],
            ]);

            if ($response->getStatusCode() === 200) {
                $successCount++;
            } else {
                $errorCount++;
            }
        }

        $endTime = microtime(true);
        $totalTime = $endTime - $startTime;
        $requestsPerSecond = $requestCount / $totalTime;

        // Performance assertions
        $this->assertGreaterThan(80, $successCount); // At least 80% success rate
        $this->assertLessThan(10, $totalTime); // Should complete in under 10 seconds
        $this->assertGreaterThan(10, $requestsPerSecond); // At least 10 requests per second

        // Database assertions
        $this->assertEquals($successCount, WebhookEvent::count());
        $this->assertEquals($requestCount, WebhookDeliveryMetric::count());
    }

    /** @test */
    public function it_processes_webhook_jobs_efficiently()
    {
        $jobCount = 50;
        $webhookEvents = [];

        // Create webhook events
        for ($i = 0; $i < $jobCount; $i++) {
            $webhookEvents[] = $this->createWebhookEvent($this->webhookConfig, [
                'event_type' => 'page_posts',
                'object_id' => "post_{$i}",
                'payload' => ['test' => "data_{$i}"],
            ]);
        }

        Queue::fake();

        $startTime = microtime(true);

        // Dispatch all jobs
        foreach ($webhookEvents as $event) {
            ProcessWebhookEventJob::dispatch($event);
        }

        $dispatchTime = microtime(true) - $startTime;

        // Assert job dispatch performance
        $this->assertLessThan(1, $dispatchTime); // Should dispatch quickly
        Queue::assertPushed(ProcessWebhookEventJob::class, $jobCount);

        // Process jobs
        $startTime = microtime(true);
        
        $this->artisan('queue:work', [
            '--once' => true,
            '--queue' => 'default',
        ]);

        $processingTime = microtime(true) - $startTime;

        // Assert processing performance
        $this->assertLessThan(5, $processingTime); // Should process quickly

        // Verify results
        $processedCount = WebhookEvent::where('status', 'processed')->count();
        $this->assertGreaterThan(40, $processedCount); // At least 80% processed
    }

    /** @test */
    public function it_handles_large_payloads_efficiently()
    {
        $sizes = [1024, 10240, 102400, 512000]; // 1KB, 10KB, 100KB, 500KB
        $results = [];

        foreach ($sizes as $size) {
            $largePayload = [
                'data' => str_repeat('x', $size),
                'metadata' => array_fill(0, 100, 'item'),
            ];

            $signature = $this->generateWebhookSignature(
                json_encode($largePayload),
                $this->webhookConfig->secret
            );

            $startTime = microtime(true);

            $response = $this->postJson('/webhooks/facebook', $largePayload, [
                'X-Hub-Signature-256' => $signature,
            ]);

            $endTime = microtime(true);
            $processingTime = $endTime - $startTime;

            $results[$size] = [
                'status' => $response->getStatusCode(),
                'time' => $processingTime,
            ];

            // Clean up for next iteration
            WebhookEvent::where('webhook_config_id', $this->webhookConfig->id)->delete();
            WebhookDeliveryMetric::where('webhook_config_id', $this->webhookConfig->id)->delete();
        }

        // Assert performance degrades gracefully
        $this->assertEquals(200, $results[1024]['status']);
        $this->assertEquals(200, $results[10240]['status']);
        $this->assertEquals(200, $results[102400]['status']);
        
        // Large payload might be rejected based on configuration
        $this->assertContains($results[512000]['status'], [200, 413]);

        // Processing time should remain reasonable
        foreach ($results as $size => $result) {
            if ($result['status'] === 200) {
                $this->assertLessThan(2, $result['time'], "Payload size {$size} took too long to process");
            }
        }
    }

    /** @test */
    public function it_maintains_performance_under_concurrent_load()
    {
        $concurrentRequests = 20;
        $requestsPerThread = 5;

        $startTime = microtime(true);
        $processes = [];

        // Simulate concurrent requests using multiple processes
        for ($i = 0; $i < $concurrentRequests; $i++) {
            $payload = $this->createTestWebhookPayload('facebook', [
                'entry' => [
                    [
                        'id' => "concurrent_{$i}",
                        'changes' => [
                            [
                                'value' => [
                                    'post_id' => "post_concurrent_{$i}",
                                ],
                            ],
                        ],
                    ],
                ],
            ]);

            $signature = $this->generateWebhookSignature(
                json_encode($payload),
                $this->webhookConfig->secret
            );

            // Send requests concurrently
            $response = $this->postJson('/webhooks/facebook', $payload, [
                'X-Hub-Signature-256' => $signature,
            ]);

            $processes[] = [
                'thread' => $i,
                'status' => $response->getStatusCode(),
            ];
        }

        $endTime = microtime(true);
        $totalTime = $endTime - $startTime;

        // Analyze results
        $successCount = collect($processes)->filter(function ($process) {
            return $process['status'] === 200;
        })->count();

        // Performance assertions
        $this->assertGreaterThan(15, $successCount); // At least 75% success rate
        $this->assertLessThan(5, $totalTime); // Should complete in under 5 seconds

        // Database integrity
        $this->assertEquals($successCount, WebhookEvent::count());
        $this->assertEquals($concurrentRequests, WebhookDeliveryMetric::count());
    }

    /** @test */
    public function it_handles_memory_efficiently_during_batch_processing()
    {
        $initialMemory = memory_get_usage(true);
        $batchSize = 100;

        // Create a large batch of webhook events
        for ($i = 0; $i < $batchSize; $i++) {
            $this->createWebhookEvent($this->webhookConfig, [
                'payload' => [
                    'data' => str_repeat('x', 1000), // 1KB per event
                    'index' => $i,
                ],
            ]);
        }

        $beforeProcessingMemory = memory_get_usage(true);

        // Process the batch
        $this->artisan('webhooks:process-batch', [
            '--batch-size' => 20,
        ]);

        $afterProcessingMemory = memory_get_usage(true);
        $peakMemory = memory_get_peak_usage(true);

        // Memory assertions
        $memoryIncrease = $afterProcessingMemory - $beforeProcessingMemory;
        $this->assertLessThan(50 * 1024 * 1024, $memoryIncrease); // Less than 50MB increase
        $this->assertLessThan(100 * 1024 * 1024, $peakMemory - $initialMemory); // Less than 100MB peak

        // Verify processing results
        $processedCount = WebhookEvent::where('status', 'processed')->count();
        $this->assertGreaterThan(80, $processedCount); // At least 80% processed
    }

    /** @test */
    public function it_optimizes_database_queries_for_webhook_listing()
    {
        // Create a large number of webhook events
        $eventCount = 1000;
        WebhookEvent::factory()->count($eventCount)->create([
            'webhook_config_id' => $this->webhookConfig->id,
            'social_account_id' => $this->socialAccount->id,
        ]);

        $startTime = microtime(true);

        // Test webhook listing with pagination
        $response = $this->actingAs($this->user)
            ->get('/webhooks/manage/events?page=1&per_page=50');

        $endTime = microtime(true);
        $queryTime = $endTime - $startTime;

        // Performance assertions
        $response->assertStatus(200);
        $this->assertLessThan(1, $queryTime); // Query should complete in under 1 second
        $response->assertJsonStructure([
            'data',
            'current_page',
            'last_page',
            'per_page',
            'total',
        ]);

        // Verify pagination works correctly
        $responseData = $response->json();
        $this->assertEquals(50, count($responseData['data']));
        $this->assertEquals($eventCount, $responseData['total']);
    }

    /** @test */
    public function it_caches_webhook_statistics_efficiently()
    {
        // Create test data
        WebhookEvent::factory()->count(100)->create([
            'webhook_config_id' => $this->webhookConfig->id,
            'social_account_id' => $this->socialAccount->id,
            'status' => 'processed',
        ]);

        WebhookDeliveryMetric::factory()->count(100)->create([
            'webhook_config_id' => $this->webhookConfig->id,
            'status' => 'success',
        ]);

        // First request - should cache the results
        $startTime = microtime(true);
        $response1 = $this->actingAs($this->user)
            ->get('/settings/webhooks/api/stats');
        $firstRequestTime = microtime(true) - $startTime;

        // Second request - should use cache
        $startTime = microtime(true);
        $response2 = $this->actingAs($this->user)
            ->get('/settings/webhooks/api/stats');
        $secondRequestTime = microtime(true) - $startTime;

        // Both requests should succeed
        $response1->assertStatus(200);
        $response2->assertStatus(200);

        // Second request should be faster due to caching
        $this->assertLessThan($firstRequestTime, $secondRequestTime);
        $this->assertLessThan(0.5, $secondRequestTime); // Cached request should be very fast

        // Responses should be identical
        $this->assertEquals($response1->json(), $response2->json());
    }

    /** @test */
    public function it_handles_webhook_search_efficiently()
    {
        // Create diverse test data
        $platforms = ['facebook', 'instagram', 'twitter', 'linkedin'];
        $statuses = ['pending', 'processed', 'failed', 'ignored'];

        foreach ($platforms as $platform) {
            foreach ($statuses as $status) {
                WebhookEvent::factory()->count(25)->create([
                    'webhook_config_id' => $this->webhookConfig->id,
                    'social_account_id' => $this->socialAccount->id,
                    'platform' => $platform,
                    'status' => $status,
                    'event_type' => "{$platform}_event",
                ]);
            }
        }

        // Test various search combinations
        $searchTests = [
            ['platform' => 'facebook'],
            ['status' => 'processed'],
            ['platform' => 'twitter', 'status' => 'failed'],
            ['event_type' => 'instagram_event'],
        ];

        foreach ($searchTests as $searchParams) {
            $startTime = microtime(true);
            
            $response = $this->actingAs($this->user)
                ->get('/webhooks/manage/events?' . http_build_query($searchParams));
            
            $endTime = microtime(true);
            $queryTime = $endTime - $startTime;

            // Performance assertions
            $response->assertStatus(200);
            $this->assertLessThan(0.5, $queryTime); // Search should be fast
            $response->assertJsonStructure(['data', 'total']);
        }
    }

    /** @test */
    public function it_maintains_performance_during_webhook_cleanup()
    {
        // Create old webhook data
        $oldEventCount = 1000;
        WebhookEvent::factory()->count($oldEventCount)->create([
            'webhook_config_id' => $this->webhookConfig->id,
            'social_account_id' => $this->socialAccount->id,
            'created_at' => now()->subMonths(2),
        ]);

        WebhookDeliveryMetric::factory()->count($oldEventCount)->create([
            'webhook_config_id' => $this->webhookConfig->id,
            'delivered_at' => now()->subMonths(2),
        ]);

        // Create recent data that should be kept
        $recentEventCount = 100;
        WebhookEvent::factory()->count($recentEventCount)->create([
            'webhook_config_id' => $this->webhookConfig->id,
            'social_account_id' => $this->socialAccount->id,
            'created_at' => now()->subDays(5),
        ]);

        $startTime = microtime(true);

        // Run cleanup command
        $this->artisan('webhooks:cleanup', [
            '--days' => 30,
            '--batch-size' => 100,
        ]);

        $endTime = microtime(true);
        $cleanupTime = $endTime - $startTime;

        // Performance assertions
        $this->assertLessThan(10, $cleanupTime); // Cleanup should complete in reasonable time

        // Verify cleanup results
        $this->assertEquals($recentEventCount, WebhookEvent::count());
        $this->assertEquals(0, WebhookDeliveryMetric::count());
    }
}