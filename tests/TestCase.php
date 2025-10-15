<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication, RefreshDatabase;

    /**
     * Setup the test environment.
     */
    protected function setUp(): void
    {
        parent::setUp();

        // Reset facades
        Queue::fake();
        Cache::flush();
        Event::fake();
        Mail::fake();
        Storage::fake();

        // Set test configuration
        $this->configureTestEnvironment();
    }

    /**
     * Clean up the test environment.
     */
    protected function tearDown(): void
    {
        parent::tearDown();
    }

    /**
     * Configure test environment settings.
     */
    protected function configureTestEnvironment(): void
    {
        // Set testing configuration
        config([
            'app.env' => 'testing',
            'app.debug' => true,
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
            'queue.default' => 'sync',
            'cache.default' => 'array',
            'session.driver' => 'array',
            'mail.driver' => 'array',
            'filesystems.default' => 'local',
            
            // Webhook test configuration
            'webhooks.security.signature.tolerance' => 300,
            'webhooks.security.replay_protection.enabled' => true,
            'webhooks.security.replay_protection.window' => 300,
            'webhooks.security.logging.log_signature_failures' => true,
            'webhooks.security.alerting.enabled' => false,
            'webhooks.security.max_payload_size' => 1048576, // 1MB
            'webhooks.security.timeout' => 30,
            
            // Rate limiting
            'webhooks.security.rate_limit.enabled' => true,
            'webhooks.security.rate_limit.requests' => 60,
            'webhooks.security.rate_limit.window' => 60,
        ]);
    }

    /**
     * Create a user with the given attributes.
     */
    protected function createUser(array $attributes = []): \App\Models\User
    {
        return \App\Models\User::factory()->create($attributes);
    }

    /**
     * Create a social account for the given user.
     */
    protected function createSocialAccount(\App\Models\User $user, array $attributes = []): \App\Models\SocialAccount
    {
        return \App\Models\SocialAccount::factory()->create(array_merge([
            'user_id' => $user->id,
        ], $attributes));
    }

    /**
     * Create a webhook configuration for the given social account.
     */
    protected function createWebhookConfig(\App\Models\SocialAccount $socialAccount, array $attributes = []): \App\Models\WebhookConfig
    {
        return \App\Models\WebhookConfig::factory()->create(array_merge([
            'social_account_id' => $socialAccount->id,
        ], $attributes));
    }

    /**
     * Create a webhook event for the given webhook configuration.
     */
    protected function createWebhookEvent(\App\Models\WebhookConfig $webhookConfig, array $attributes = []): \App\Models\WebhookEvent
    {
        return \App\Models\WebhookEvent::factory()->create(array_merge([
            'webhook_config_id' => $webhookConfig->id,
            'social_account_id' => $webhookConfig->social_account_id,
        ], $attributes));
    }

    /**
     * Generate a webhook signature for the given payload and secret.
     */
    protected function generateWebhookSignature(string $payload, string $secret, string $platform = 'facebook'): string
    {
        return match ($platform) {
            'facebook', 'instagram' => 'sha256=' . hash_hmac('sha256', $payload, $secret),
            'twitter' => base64_encode(hash_hmac('sha256', time() . 'nonce' . $payload, $secret, true)),
            'linkedin' => base64_encode(hash_hmac('sha256', $payload, $secret, true)),
            default => hash_hmac('sha256', $payload, $secret),
        };
    }

    /**
     * Assert that a webhook event was created with the given attributes.
     */
    protected function assertWebhookEventCreated(array $attributes): void
    {
        $this->assertDatabaseHas('webhook_events', $attributes);
    }

    /**
     * Assert that a webhook delivery metric was recorded.
     */
    protected function assertWebhookMetricRecorded(array $attributes): void
    {
        $this->assertDatabaseHas('webhook_delivery_metrics', $attributes);
    }

    /**
     * Assert that a webhook processing record was created.
     */
    protected function assertWebhookProcessingRecorded(array $attributes): void
    {
        $this->assertDatabaseHas('webhook_event_processing', $attributes);
    }

    /**
     * Assert that a job was dispatched for webhook processing.
     */
    protected function assertWebhookJobDispatched(): void
    {
        Queue::assertPushed(\App\Jobs\ProcessWebhookEventJob::class);
    }

    /**
     * Assert that a webhook job was not dispatched.
     */
    protected function assertWebhookJobNotDispatched(): void
    {
        Queue::assertNotPushed(\App\Jobs\ProcessWebhookEventJob::class);
    }

    /**
     * Create a mock webhook service.
     */
    protected function mockWebhookService(): \Mockery\MockInterface
    {
        return \Mockery::mock(\App\Services\Webhooks\WebhookEventProcessingService::class);
    }



    /**
     * Assert that the response contains webhook success structure.
     */
    protected function assertWebhookSuccessResponse($response, string $message = 'Webhook received'): void
    {
        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'message' => $message,
            ]);
    }

    /**
     * Assert that the response contains webhook error structure.
     */
    protected function assertWebhookErrorResponse($response, int $status, string $message): void
    {
        $response->assertStatus($status)
            ->assertJson([
                'status' => 'error',
                'message' => $message,
            ]);
    }

    /**
     * Create test webhook payload for the given platform.
     */
    protected function createTestWebhookPayload(string $platform, array $customData = []): array
    {
        $basePayload = match ($platform) {
            'facebook' => [
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
            ],
            'instagram' => [
                'object' => 'instagram',
                'entry' => [
                    [
                        'id' => '123456789',
                        'time' => time(),
                        'changes' => [
                            [
                                'field' => 'comments',
                                'value' => [
                                    'media_id' => 'media_123',
                                    'text' => 'Test comment',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            'twitter' => [
                'for_user_id' => '123456789',
                'tweet_create_events' => [
                    [
                        'id_str' => 'tweet_123',
                        'text' => 'Test tweet',
                        'user' => [
                            'id_str' => 'user_123',
                            'screen_name' => 'testuser',
                        ],
                    ],
                ],
            ],
            'linkedin' => [
                'event' => 'SHARE_UPDATE',
                'data' => [
                    'author' => 'urn:li:person:123456789',
                    'share' => [
                        'id' => 'share_123',
                        'text' => 'Test share',
                    ],
                ],
            ],
            default => [],
        };

        return array_merge_recursive($basePayload, $customData);
    }

    /**
     * Assert that webhook security headers are present.
     */
    protected function assertWebhookSecurityHeaders($response): void
    {
        $response->assertHeaderMissing('X-Powered-By');
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('X-Frame-Options', 'DENY');
        $response->assertHeader('X-XSS-Protection', '1; mode=block');
    }

    /**
     * Create a test user with webhook permissions.
     */
    protected function createWebhookUser(array $attributes = []): \App\Models\User
    {
        return $this->createUser(array_merge([
            'email' => 'webhook-test@example.com',
        ], $attributes));
    }

    /**
     * Assert that webhook analytics data is correct.
     */
    protected function assertWebhookAnalytics(int $expectedTotal, float $expectedSuccessRate): void
    {
        $totalMetrics = \App\Models\WebhookDeliveryMetric::count();
        $successMetrics = \App\Models\WebhookDeliveryMetric::where('status', 'success')->count();
        $actualSuccessRate = $totalMetrics > 0 ? ($successMetrics / $totalMetrics) * 100 : 0;

        $this->assertEquals($expectedTotal, $totalMetrics);
        $this->assertEqualsWithDelta($expectedSuccessRate, $actualSuccessRate, 0.1);
    }
}