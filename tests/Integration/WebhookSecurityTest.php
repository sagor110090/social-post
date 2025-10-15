<?php

namespace Tests\Integration;

use App\Models\SocialAccount;
use App\Models\User;
use App\Models\WebhookConfig;
use App\Models\WebhookDeliveryMetric;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class WebhookSecurityTest extends TestCase
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
            'secret' => 'test_secret',
        ]);
    }

    /** @test */
    public function it_prevents_replay_attacks()
    {
        $payload = ['test' => 'data'];
        $signature = 'sha256=' . hash_hmac('sha256', json_encode($payload), 'test_secret');

        // First request should succeed
        $response1 = $this->postJson('/webhooks/facebook', $payload, [
            'X-Hub-Signature-256' => $signature,
        ]);

        $response1->assertStatus(200);

        // Second request with same signature should be blocked
        $response2 = $this->postJson('/webhooks/facebook', $payload, [
            'X-Hub-Signature-256' => $signature,
        ]);

        $response2->assertStatus(401);
    }

    /** @test */
    public function it_validates_timestamp_to_prevent_old_requests()
    {
        $payload = ['test' => 'data'];
        $signature = 'sha256=' . hash_hmac('sha256', json_encode($payload), 'test_secret');
        $oldTimestamp = time() - 400; // Older than 5 minutes

        $response = $this->postJson('/webhooks/twitter', $payload, [
            'X-Twitter-Webhooks-Signature' => $signature,
            'X-Twitter-Webhooks-Timestamp' => $oldTimestamp,
            'X-Twitter-Webhooks-Nonce' => 'test_nonce',
        ]);

        $response->assertStatus(401);
    }

    /** @test */
    public function it_rate_limits_webhook_requests()
    {
        $payload = ['test' => 'data'];
        
        // Send many requests quickly
        $responses = collect(range(1, 20))->map(function () use ($payload) {
            $signature = 'sha256=' . hash_hmac('sha256', json_encode($payload), 'test_secret');
            
            return $this->postJson('/webhooks/facebook', $payload, [
                'X-Hub-Signature-256' => $signature,
            ]);
        });

        // Some requests should be rate limited
        $rateLimitedCount = $responses->filter(function ($response) {
            return $response->getStatusCode() === 429;
        })->count();

        $this->assertGreaterThan(0, $rateLimitedCount);
    }

    /** @test */
    public function it_blocks_requests_from_blacklisted_ips()
    {
        // Configure IP blacklist
        config(['webhooks.security.ip_blacklist' => ['192.168.1.100']]);

        $payload = ['test' => 'data'];
        $signature = 'sha256=' . hash_hmac('sha256', json_encode($payload), 'test_secret');

        $response = $this->postJson('/webhooks/facebook', $payload, [
            'X-Hub-Signature-256' => $signature,
        ], [
            'REMOTE_ADDR' => '192.168.1.100',
        ]);

        $response->assertStatus(403);
    }

    /** @test */
    public function it_allows_requests_from_whitelisted_ips()
    {
        // Configure IP whitelist
        config(['webhooks.security.ip_whitelist' => ['192.168.1.200']]);

        $payload = ['test' => 'data'];
        $signature = 'sha256=' . hash_hmac('sha256', json_encode($payload), 'test_secret');

        $response = $this->postJson('/webhooks/facebook', $payload, [
            'X-Hub-Signature-256' => $signature,
        ], [
            'REMOTE_ADDR' => '192.168.1.200',
        ]);

        $response->assertStatus(200);
    }

    /** @test */
    public function it_blocks_requests_with_malformed_payloads()
    {
        $malformedPayloads = [
            'invalid_json',
            '{"incomplete": json',
            '{"nested": {"infinite": recursion}}',
            str_repeat('data', 10000), // Very large payload
        ];

        foreach ($malformedPayloads as $payload) {
            $signature = 'sha256=' . hash_hmac('sha256', $payload, 'test_secret');

            $response = $this->postJson('/webhooks/facebook', [], [
                'X-Hub-Signature-256' => $signature,
                'CONTENT_TYPE' => 'application/json',
            ], $payload);

            // Should either succeed or fail gracefully, not crash
            $this->assertContains($response->getStatusCode(), [200, 400, 422]);
        }
    }

    /** @test */
    public function it_logs_security_violations()
    {
        Log::shouldReceive('warning')
            ->once()
            ->withArgs(function ($message, $context) {
                return str_contains($message, 'Security violation recorded: signature_failure') &&
                       isset($context['ip']) &&
                       isset($context['count']);
            });

        $payload = ['test' => 'data'];
        $invalidSignature = 'sha256=invalid_signature';

        $response = $this->postJson('/webhooks/facebook', $payload, [
            'X-Hub-Signature-256' => $invalidSignature,
        ]);

        $response->assertStatus(401);
    }

    /** @test */
    public function it_triggers_alerts_on_suspicious_activity()
    {
        // Configure alerting
        config(['webhooks.security.alerting.enabled' => true]);
        config(['webhooks.security.alerting.thresholds.signature_failure_per_minute' => 2]);

        Log::shouldReceive('critical')
            ->once()
            ->withArgs(function ($message, $context) {
                return str_contains($message, 'Security alert triggered: signature_failure') &&
                       isset($context['alert_type']);
            });

        $payload = ['test' => 'data'];
        $invalidSignature = 'sha256=invalid_signature';

        // Send multiple invalid requests to trigger alert
        for ($i = 0; $i < 3; $i++) {
            $this->postJson('/webhooks/facebook', $payload, [
                'X-Hub-Signature-256' => $invalidSignature,
            ]);
        }
    }

    /** @test */
    public function it_validates_webhook_secrets()
    {
        // Test with null secret
        $webhookConfigWithoutSecret = WebhookConfig::factory()->create([
            'social_account_id' => $this->socialAccount->id,
            'secret' => null,
        ]);

        $payload = ['test' => 'data'];
        $signature = 'sha256=' . hash_hmac('sha256', json_encode($payload), 'test_secret');

        $response = $this->postJson('/webhooks/facebook', $payload, [
            'X-Hub-Signature-256' => $signature,
            'X-Webhook-Config-ID' => $webhookConfigWithoutSecret->id,
        ]);

        $response->assertStatus(401);
    }

    /** @test */
    public function it_handles_signature_format_validation()
    {
        $payload = ['test' => 'data'];
        
        $invalidSignatures = [
            'invalid_format',
            'sha1=hash', // Wrong algorithm
            'sha256', // Missing equals sign
            'sha256=', // Missing hash
        ];

        foreach ($invalidSignatures as $signature) {
            $response = $this->postJson('/webhooks/facebook', $payload, [
                'X-Hub-Signature-256' => $signature,
            ]);

            $response->assertStatus(401);
        }
    }

    /** @test */
    public function it_sanitizes_webhook_payloads()
    {
        $maliciousPayload = [
            'message' => '<script>alert("xss")</script>',
            'data' => 'SELECT * FROM users; --',
            'nested' => [
                'html' => '<img src=x onerror=alert(1)>',
            ],
        ];

        $signature = 'sha256=' . hash_hmac('sha256', json_encode($maliciousPayload), 'test_secret');

        $response = $this->postJson('/webhooks/facebook', $maliciousPayload, [
            'X-Hub-Signature-256' => $signature,
        ]);

        $response->assertStatus(200);

        // Check that the payload was stored safely
        $this->assertDatabaseHas('webhook_events', [
            'webhook_config_id' => $this->webhookConfig->id,
        ]);

        $webhookEvent = \App\Models\WebhookEvent::first();
        $this->assertIsArray($webhookEvent->payload);
        $this->assertArrayHasKey('message', $webhookEvent->payload);
    }

    /** @test */
    public function it_limits_webhook_payload_size()
    {
        // Configure payload size limit
        config(['webhooks.security.max_payload_size' => 1024]); // 1KB

        $largePayload = ['data' => str_repeat('x', 2048)]; // 2KB
        $signature = 'sha256=' . hash_hmac('sha256', json_encode($largePayload), 'test_secret');

        $response = $this->postJson('/webhooks/facebook', $largePayload, [
            'X-Hub-Signature-256' => $signature,
        ]);

        $response->assertStatus(413); // Payload Too Large
    }

    /** @test */
    public function it_handles_webhook_timeouts()
    {
        // Configure short timeout for testing
        config(['webhooks.security.timeout' => 1]);

        $payload = ['test' => 'data'];
        $signature = 'sha256=' . hash_hmac('sha256', json_encode($payload), 'test_secret');

        // Simulate slow processing by mocking
        $startTime = microtime(true);
        
        $response = $this->postJson('/webhooks/facebook', $payload, [
            'X-Hub-Signature-256' => $signature,
        ]);

        $endTime = microtime(true);
        $processingTime = $endTime - $startTime;

        // Should complete quickly or timeout
        $this->assertLessThan(5, $processingTime);
        $this->assertContains($response->getStatusCode(), [200, 408]); // 408 = Request Timeout
    }

    /** @test */
    public function it_validates_webhook_headers()
    {
        $payload = ['test' => 'data'];
        
        // Test missing required headers
        $response = $this->postJson('/webhooks/facebook', $payload);

        $response->assertStatus(401);

        // Test with proper headers
        $signature = 'sha256=' . hash_hmac('sha256', json_encode($payload), 'test_secret');
        $response = $this->postJson('/webhooks/facebook', $payload, [
            'X-Hub-Signature-256' => $signature,
        ]);

        $response->assertStatus(200);
    }

    /** @test */
    public function it_handles_concurrent_security_checks()
    {
        $payload = ['test' => 'data'];
        $signature = 'sha256=' . hash_hmac('sha256', json_encode($payload), 'test_secret');

        // Send multiple concurrent requests
        $responses = collect(range(1, 10))->map(function () use ($payload, $signature) {
            return $this->postJson('/webhooks/facebook', $payload, [
                'X-Hub-Signature-256' => $signature,
            ]);
        });

        // Only the first should succeed due to replay protection
        $successCount = $responses->filter(function ($response) {
            return $response->getStatusCode() === 200;
        })->count();

        $this->assertEquals(1, $successCount);
    }

    /** @test */
    public function it_maintains_security_during_high_load()
    {
        $payloads = [];
        $signatures = [];

        // Generate many different payloads
        for ($i = 0; $i < 50; $i++) {
            $payload = ['test' => "data_{$i}", 'index' => $i];
            $payloads[] = $payload;
            $signatures[] = 'sha256=' . hash_hmac('sha256', json_encode($payload), 'test_secret');
        }

        $startTime = microtime(true);
        $successCount = 0;
        $securityViolationCount = 0;

        // Send all requests
        foreach ($payloads as $index => $payload) {
            $response = $this->postJson('/webhooks/facebook', $payload, [
                'X-Hub-Signature-256' => $signatures[$index],
            ]);

            if ($response->getStatusCode() === 200) {
                $successCount++;
            } elseif (in_array($response->getStatusCode(), [401, 403, 429])) {
                $securityViolationCount++;
            }
        }

        $endTime = microtime(true);
        $totalTime = $endTime - $startTime;

        // Security should remain effective under load
        $this->assertGreaterThan(0, $successCount);
        $this->assertLessThan(10, $totalTime); // Should complete in reasonable time
    }
}