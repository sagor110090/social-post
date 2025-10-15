<?php

namespace Tests\Feature\Webhooks;

use App\Models\User;
use App\Models\SocialAccount;
use App\Models\WebhookConfig;
use App\Services\Webhooks\WebhookSecurityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Redis;
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
            'secret' => 'test_webhook_secret',
        ]);
    }

    /** @test */
    public function it_blocks_requests_without_signature()
    {
        $response = $this->post('/webhooks/facebook', [
            'test' => 'data',
        ]);

        $response->assertStatus(401)
            ->assertJson([
                'status' => 'error',
                'message' => 'Invalid signature',
            ]);
    }

    /** @test */
    public function it_blocks_requests_with_invalid_signature()
    {
        $response = $this->withHeaders([
            'X-Hub-Signature-256' => 'invalid_signature',
        ])->post('/webhooks/facebook', [
            'test' => 'data',
        ]);

        $response->assertStatus(401)
            ->assertJson([
                'status' => 'error',
                'message' => 'Invalid signature',
            ]);
    }

    /** @test */
    public function it_allows_requests_with_valid_signature()
    {
        $payload = json_encode(['test' => 'data']);
        $signature = 'sha256=' . hash_hmac('sha256', $payload, $this->webhookConfig->secret);

        $response = $this->withHeaders([
            'X-Hub-Signature-256' => $signature,
            'Content-Type' => 'application/json',
        ])->post('/webhooks/facebook', $payload);

        $response->assertStatus(200);
    }

    /** @test */
    public function it_enforces_rate_limiting()
    {
        // Make multiple requests quickly to trigger rate limiting
        $payload = json_encode(['test' => 'data']);
        $signature = 'sha256=' . hash_hmac('sha256', $payload, $this->webhookConfig->secret);

        $responses = collect(range(1, 150))->map(function () use ($payload, $signature) {
            return $this->withHeaders([
                'X-Hub-Signature-256' => $signature,
                'Content-Type' => 'application/json',
            ])->post('/webhooks/facebook', $payload);
        });

        // Some requests should be rate limited
        $rateLimitedCount = $responses->filter(fn($r) => $r->status() === 429)->count();
        $this->assertGreaterThan(0, $rateLimitedCount);
    }

    /** @test */
    public function it_blocks_requests_from_non_whitelisted_ips_in_strict_mode()
    {
        config(['webhooks.security.ip_whitelist.strict_mode' => true]);

        $payload = json_encode(['test' => 'data']);
        $signature = 'sha256=' . hash_hmac('sha256', $payload, $this->webhookConfig->secret);

        $response = $this->withHeaders([
            'X-Hub-Signature-256' => $signature,
            'Content-Type' => 'application/json',
        ])->post('/webhooks/facebook', $payload, [
            'REMOTE_ADDR' => '192.168.1.100', // Non-whitelisted IP
        ]);

        $response->assertStatus(403);
    }

    /** @test */
    public function it_validates_request_payload_size()
    {
        // Create a large payload that exceeds the limit
        $largePayload = str_repeat('x', 2 * 1024 * 1024); // 2MB
        $signature = 'sha256=' . hash_hmac('sha256', $largePayload, $this->webhookConfig->secret);

        $response = $this->withHeaders([
            'X-Hub-Signature-256' => $signature,
            'Content-Type' => 'application/json',
        ])->post('/webhooks/facebook', $largePayload);

        $response->assertStatus(422)
            ->assertJsonFragment([
                'message' => 'Request validation failed',
            ]);
    }

    /** @test */
    public function it_adds_security_headers_to_responses()
    {
        $payload = json_encode(['test' => 'data']);
        $signature = 'sha256=' . hash_hmac('sha256', $payload, $this->webhookConfig->secret);

        $response = $this->withHeaders([
            'X-Hub-Signature-256' => $signature,
            'Content-Type' => 'application/json',
        ])->post('/webhooks/facebook', $payload);

        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('X-Frame-Options', 'DENY');
        $response->assertHeader('X-XSS-Protection', '1; mode=block');
        $response->assertHeader('Strict-Transport-Security');
        $response->assertHeader('Content-Security-Policy');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
    }

    /** @test */
    public function it_logs_webhook_activity()
    {
        $payload = json_encode(['test' => 'data']);
        $signature = 'sha256=' . hash_hmac('sha256', $payload, $this->webhookConfig->secret);

        $this->withHeaders([
            'X-Hub-Signature-256' => $signature,
            'Content-Type' => 'application/json',
        ])->post('/webhooks/facebook', $payload);

        // Check that log entries were created
        $this->assertDatabaseHas('webhook_delivery_metrics', [
            'webhook_config_id' => $this->webhookConfig->id,
            'status' => 'delivered',
        ]);
    }

    /** @test */
    public function it_prevents_replay_attacks()
    {
        $payload = json_encode(['test' => 'data']);
        $signature = 'sha256=' . hash_hmac('sha256', $payload, $this->webhookConfig->secret);

        // First request should succeed
        $response1 = $this->withHeaders([
            'X-Hub-Signature-256' => $signature,
            'Content-Type' => 'application/json',
        ])->post('/webhooks/facebook', $payload);

        $response1->assertStatus(200);

        // Second request with same signature should be blocked
        $response2 = $this->withHeaders([
            'X-Hub-Signature-256' => $signature,
            'Content-Type' => 'application/json',
        ])->post('/webhooks/facebook', $payload);

        $response2->assertStatus(401);
    }

    /** @test */
    public function it_handles_webhook_verification_challenges()
    {
        $response = $this->get('/webhooks/facebook?hub_challenge=test_challenge');

        $response->assertStatus(200)
            ->assertContent('test_challenge');
    }

    /** @test */
    public function security_service_can_record_violations()
    {
        $securityService = new WebhookSecurityService();

        $result = $securityService->recordViolation(
            WebhookSecurityService::VIOLATION_SIGNATURE,
            [
                'ip' => '192.168.1.100',
                'platform' => 'facebook',
                'config_id' => $this->webhookConfig->id,
            ]
        );

        $this->assertEquals(WebhookSecurityService::VIOLATION_SIGNATURE, $result['type']);
        $this->assertEquals(1, $result['count']);
        $this->assertArrayHasKey('alert_triggered', $result);
    }

    /** @test */
    public function security_service_can_block_ips()
    {
        $securityService = new WebhookSecurityService();
        $ip = '192.168.1.100';

        $this->assertFalse($securityService->isIpBlocked($ip));

        $securityService->blockIp($ip, 3600);

        $this->assertTrue($securityService->isIpBlocked($ip));

        $securityService->unblockIp($ip);

        $this->assertFalse($securityService->isIpBlocked($ip));
    }

    /** @test */
    public function security_service_performs_health_checks()
    {
        $securityService = new WebhookSecurityService();

        $health = $securityService->healthCheck();

        $this->assertArrayHasKey('status', $health);
        $this->assertArrayHasKey('checks', $health);
        $this->assertArrayHasKey('timestamp', $health);
        $this->assertContains($health['status'], ['healthy', 'degraded', 'unhealthy']);
    }

    /** @test */
    public function it_validates_json_structure()
    {
        $malformedJson = '{"test": "data", "invalid": }';
        $signature = 'sha256=' . hash_hmac('sha256', $malformedJson, $this->webhookConfig->secret);

        $response = $this->withHeaders([
            'X-Hub-Signature-256' => $signature,
            'Content-Type' => 'application/json',
        ])->post('/webhooks/facebook', $malformedJson);

        $response->assertStatus(422)
            ->assertJsonFragment([
                'message' => 'Request validation failed',
            ]);
    }

    /** @test */
    public function it_handles_twitter_webhook_signatures()
    {
        // Create Twitter webhook config
        $twitterAccount = SocialAccount::factory()->create([
            'user_id' => $this->user->id,
            'platform' => 'twitter',
        ]);
        $twitterConfig = WebhookConfig::factory()->create([
            'social_account_id' => $twitterAccount->id,
            'secret' => 'test_twitter_secret',
        ]);

        $timestamp = time();
        $nonce = 'test_nonce';
        $payload = json_encode(['test' => 'data']);
        $baseString = $timestamp . $nonce . $payload;
        $signature = base64_encode(hash_hmac('sha256', $baseString, $twitterConfig->secret, true));

        $response = $this->withHeaders([
            'X-Twitter-Webhooks-Signature' => $signature,
            'X-Twitter-Webhooks-Timestamp' => $timestamp,
            'X-Twitter-Webhooks-Nonce' => $nonce,
            'Content-Type' => 'application/json',
        ])->post('/webhooks/twitter', $payload);

        $response->assertStatus(200);
    }

    /** @test */
    public function it_handles_linkedin_webhook_signatures()
    {
        // Create LinkedIn webhook config
        $linkedinAccount = SocialAccount::factory()->create([
            'user_id' => $this->user->id,
            'platform' => 'linkedin',
        ]);
        $linkedinConfig = WebhookConfig::factory()->create([
            'social_account_id' => $linkedinAccount->id,
            'secret' => 'test_linkedin_secret',
        ]);

        $payload = json_encode(['test' => 'data']);
        $signature = base64_encode(hash_hmac('sha256', $payload, $linkedinConfig->secret, true));

        $response = $this->withHeaders([
            'X-LI-Signature' => $signature,
            'Content-Type' => 'application/json',
        ])->post('/webhooks/linkedin', $payload);

        $response->assertStatus(200);
    }

    /** @test */
    public function management_endpoints_have_cors_headers()
    {
        $this->actingAs($this->user)
            ->withHeaders([
                'Origin' => 'https://example.com',
            ])
            ->get('/webhooks/manage/configs')
            ->assertHeader('Access-Control-Allow-Origin');
    }

    /** @test */
    public function it_adds_rate_limit_headers()
    {
        $payload = json_encode(['test' => 'data']);
        $signature = 'sha256=' . hash_hmac('sha256', $payload, $this->webhookConfig->secret);

        $response = $this->withHeaders([
            'X-Hub-Signature-256' => $signature,
            'Content-Type' => 'application/json',
        ])->post('/webhooks/facebook', $payload);

        $response->assertHeader('X-RateLimit-Limit');
        $response->assertHeader('X-RateLimit-Remaining');
        $response->assertHeader('X-RateLimit-Reset');
    }
}