<?php

namespace Tests\Feature;

use App\Http\Middleware\Webhooks\VerifyWebhookSignature;
use App\Models\SocialAccount;
use App\Models\User;
use App\Models\WebhookConfig;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Mockery;
use Tests\TestCase;

class WebhookMiddlewareTest extends TestCase
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

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function it_allows_requests_with_valid_signature()
    {
        $middleware = new VerifyWebhookSignature();
        
        $payload = json_encode(['test' => 'data']);
        $signature = 'sha256=' . hash_hmac('sha256', $payload, 'test_secret');

        $request = Request::create('/webhooks/facebook', 'POST', [], [], [], [
            'HTTP_X_HUB_SIGNATURE_256' => $signature,
            'CONTENT_TYPE' => 'application/json',
        ], $payload);

        $response = $middleware->handle($request, function () {
            return new Response('Success', 200);
        });

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('Success', $response->getContent());
    }

    /** @test */
    public function it_blocks_requests_with_invalid_signature()
    {
        $middleware = new VerifyWebhookSignature();
        
        $payload = json_encode(['test' => 'data']);
        $signature = 'sha256=' . hash_hmac('sha256', $payload, 'wrong_secret');

        $request = Request::create('/webhooks/facebook', 'POST', [], [], [], [
            'HTTP_X_HUB_SIGNATURE_256' => $signature,
            'CONTENT_TYPE' => 'application/json',
        ], $payload);

        $response = $middleware->handle($request, function () {
            return new Response('Should not reach here', 200);
        });

        $this->assertEquals(401, $response->getStatusCode());
        $responseData = json_decode($response->getContent(), true);
        $this->assertEquals('error', $responseData['status']);
        $this->assertEquals('Invalid signature', $responseData['message']);
    }

    /** @test */
    public function it_blocks_requests_without_signature()
    {
        $middleware = new VerifyWebhookSignature();
        
        $payload = json_encode(['test' => 'data']);

        $request = Request::create('/webhooks/facebook', 'POST', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], $payload);

        $response = $middleware->handle($request, function () {
            return new Response('Should not reach here', 200);
        });

        $this->assertEquals(401, $response->getStatusCode());
        $responseData = json_decode($response->getContent(), true);
        $this->assertEquals('error', $responseData['status']);
        $this->assertEquals('Invalid signature', $responseData['message']);
    }

    /** @test */
    public function it_allows_verification_challenges_without_signature()
    {
        $middleware = new VerifyWebhookSignature();
        
        $request = Request::create('/webhooks/facebook', 'GET', [
            'hub_challenge' => 'test_challenge',
        ]);

        $response = $middleware->handle($request, function () {
            return new Response('Challenge response', 200);
        });

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('Challenge response', $response->getContent());
    }

    /** @test */
    public function it_blocks_requests_for_unknown_platform()
    {
        $middleware = new VerifyWebhookSignature();
        
        $request = Request::create('/webhooks/unknown', 'POST', [
            'test' => 'data',
        ]);

        $response = $middleware->handle($request, function () {
            return new Response('Should not reach here', 200);
        });

        $this->assertEquals(400, $response->getStatusCode());
        $responseData = json_decode($response->getContent(), true);
        $this->assertEquals('error', $responseData['status']);
        $this->assertEquals('Unable to determine platform', $responseData['message']);
    }

    /** @test */
    public function it_blocks_requests_without_webhook_config()
    {
        $middleware = new VerifyWebhookSignature();
        
        // Delete the webhook config
        $this->webhookConfig->delete();
        
        $payload = json_encode(['test' => 'data']);
        $signature = 'sha256=' . hash_hmac('sha256', $payload, 'test_secret');

        $request = Request::create('/webhooks/facebook', 'POST', [], [], [], [
            'HTTP_X_HUB_SIGNATURE_256' => $signature,
            'CONTENT_TYPE' => 'application/json',
        ], $payload);

        $response = $middleware->handle($request, function () {
            return new Response('Should not reach here', 200);
        });

        $this->assertEquals(404, $response->getStatusCode());
        $responseData = json_decode($response->getContent(), true);
        $this->assertEquals('error', $responseData['status']);
        $this->assertEquals('Webhook not configured', $responseData['message']);
    }

    /** @test */
    public function it_extracts_platform_from_request_path()
    {
        $middleware = new VerifyWebhookSignature();
        
        $facebookRequest = Request::create('/webhooks/facebook', 'POST');
        $twitterRequest = Request::create('/webhooks/twitter', 'POST');
        $linkedinRequest = Request::create('/webhooks/linkedin', 'POST');

        // Use reflection to access protected method
        $reflection = new \ReflectionClass($middleware);
        $method = $reflection->getMethod('extractPlatformFromRequest');
        $method->setAccessible(true);

        $this->assertEquals('facebook', $method->invoke($middleware, $facebookRequest));
        $this->assertEquals('twitter', $method->invoke($middleware, $twitterRequest));
        $this->assertEquals('linkedin', $method->invoke($middleware, $linkedinRequest));
    }

    /** @test */
    public function it_extracts_platform_from_custom_header()
    {
        $middleware = new VerifyWebhookSignature();
        
        $request = Request::create('/webhooks/custom', 'POST', [], [], [], [
            'HTTP_X_WEBHOOK_PLATFORM' => 'instagram',
        ]);

        // Use reflection to access protected method
        $reflection = new \ReflectionClass($middleware);
        $method = $reflection->getMethod('extractPlatformFromRequest');
        $method->setAccessible(true);

        $this->assertEquals('instagram', $method->invoke($middleware, $request));
    }

    /** @test */
    public function it_validates_timestamp_to_prevent_replay_attacks()
    {
        $middleware = new VerifyWebhookSignature();
        
        $oldTimestamp = time() - 400; // Older than 5 minutes (300 seconds)
        $request = Request::create('/webhooks/twitter', 'POST', [], [], [], [
            'HTTP_X_TWITTER_WEBHOOKS_TIMESTAMP' => $oldTimestamp,
        ]);

        // Use reflection to access protected method
        $reflection = new \ReflectionClass($middleware);
        $method = $reflection->getMethod('validateTimestamp');
        $method->setAccessible(true);

        $this->assertFalse($method->invoke($middleware, $request, 'twitter'));
    }

    /** @test */
    public function it_allows_requests_with_valid_timestamp()
    {
        $middleware = new VerifyWebhookSignature();
        
        $validTimestamp = time() - 60; // Within 5 minutes
        $request = Request::create('/webhooks/twitter', 'POST', [], [], [], [
            'HTTP_X_TWITTER_WEBHOOKS_TIMESTAMP' => $validTimestamp,
        ]);

        // Use reflection to access protected method
        $reflection = new \ReflectionClass($middleware);
        $method = $reflection->getMethod('validateTimestamp');
        $method->setAccessible(true);

        $this->assertTrue($method->invoke($middleware, $request, 'twitter'));
    }

    /** @test */
    public function it_detects_replay_attacks()
    {
        $middleware = new VerifyWebhookSignature();
        
        $payload = json_encode(['test' => 'data']);
        $signature = 'sha256=' . hash_hmac('sha256', $payload, 'test_secret');

        $request = Request::create('/webhooks/facebook', 'POST', [], [], [], [
            'HTTP_X_HUB_SIGNATURE_256' => $signature,
            'CONTENT_TYPE' => 'application/json',
        ], $payload);

        // Use reflection to access protected method
        $reflection = new \ReflectionClass($middleware);
        $method = $reflection->getMethod('isReplayAttack');
        $method->setAccessible(true);

        // First call should return false
        $this->assertFalse($method->invoke($middleware, $request, $this->webhookConfig));

        // Second call with same signature should return true
        $this->assertTrue($method->invoke($middleware, $request, $this->webhookConfig));
    }

    /** @test */
    public function it_verifies_facebook_signature_correctly()
    {
        $middleware = new VerifyWebhookSignature();
        
        $payload = 'test payload';
        $signature = 'sha256=' . hash_hmac('sha256', $payload, 'test_secret');

        // Use reflection to access protected method
        $reflection = new \ReflectionClass($middleware);
        $method = $reflection->getMethod('verifyFacebookSignature');
        $method->setAccessible(true);

        $this->assertTrue($method->invoke($middleware, $payload, $signature, 'test_secret'));
        $this->assertFalse($method->invoke($middleware, $payload, $signature, 'wrong_secret'));
        $this->assertFalse($method->invoke($middleware, $payload, 'invalid_format', 'test_secret'));
    }

    /** @test */
    public function it_verifies_twitter_signature_correctly()
    {
        $middleware = new VerifyWebhookSignature();
        
        $payload = 'test payload';
        $timestamp = time();
        $nonce = 'test_nonce_123';
        
        $baseString = $timestamp . $nonce . $payload;
        $signature = base64_encode(hash_hmac('sha256', $baseString, 'test_secret', true));

        $request = Request::create('/webhooks/twitter', 'POST', [], [], [], [
            'HTTP_X_TWITTER_WEBHOOKS_TIMESTAMP' => $timestamp,
            'HTTP_X_TWITTER_WEBHOOKS_NONCE' => $nonce,
        ], $payload);

        // Use reflection to access protected method
        $reflection = new \ReflectionClass($middleware);
        $method = $reflection->getMethod('verifyTwitterSignature');
        $method->setAccessible(true);

        $this->assertTrue($method->invoke($middleware, $request, $signature, 'test_secret'));
        $this->assertFalse($method->invoke($middleware, $request, 'invalid_signature', 'test_secret'));
    }

    /** @test */
    public function it_verifies_linkedin_signature_correctly()
    {
        $middleware = new VerifyWebhookSignature();
        
        $payload = 'test payload';
        $signature = base64_encode(hash_hmac('sha256', $payload, 'test_secret', true));

        // Use reflection to access protected method
        $reflection = new \ReflectionClass($middleware);
        $method = $reflection->getMethod('verifyLinkedInSignature');
        $method->setAccessible(true);

        $this->assertTrue($method->invoke($middleware, $payload, $signature, 'test_secret'));
        $this->assertFalse($method->invoke($middleware, $payload, 'invalid_signature', 'test_secret'));
    }

    /** @test */
    public function it_logs_signature_failures()
    {
        Log::shouldReceive('warning')
            ->once()
            ->withArgs(function ($message, $context) {
                return str_contains($message, 'Webhook signature verification failed') &&
                       isset($context['platform']) &&
                       isset($context['config_id']) &&
                       isset($context['ip']);
            });

        $middleware = new VerifyWebhookSignature();
        
        $payload = json_encode(['test' => 'data']);
        $signature = 'sha256=' . hash_hmac('sha256', $payload, 'wrong_secret');

        $request = Request::create('/webhooks/facebook', 'POST', [], [], [], [
            'HTTP_X_HUB_SIGNATURE_256' => $signature,
            'CONTENT_TYPE' => 'application/json',
        ], $payload);

        $middleware->handle($request, function () {
            return new Response('Should not reach here', 200);
        });
    }

    /** @test */
    public function it_records_security_violations()
    {
        Cache::shouldReceive('increment')
            ->once()
            ->withArgs(function ($key, $value, $ttl) {
                return str_contains($key, 'security_violation:signature_failure:') &&
                       $value === 1 &&
                       $ttl === 60;
            })
            ->andReturn(1);

        Log::shouldReceive('warning')
            ->once()
            ->withArgs(function ($message, $context) {
                return str_contains($message, 'Security violation recorded: signature_failure') &&
                       isset($context['count']);
            });

        $middleware = new VerifyWebhookSignature();
        
        $payload = json_encode(['test' => 'data']);
        $signature = 'sha256=' . hash_hmac('sha256', $payload, 'wrong_secret');

        $request = Request::create('/webhooks/facebook', 'POST', [], [], [], [
            'HTTP_X_HUB_SIGNATURE_256' => $signature,
            'CONTENT_TYPE' => 'application/json',
        ], $payload);

        $middleware->handle($request, function () {
            return new Response('Should not reach here', 200);
        });
    }

    /** @test */
    public function it_adds_webhook_attributes_to_request()
    {
        $middleware = new VerifyWebhookSignature();
        
        $payload = json_encode(['test' => 'data']);
        $signature = 'sha256=' . hash_hmac('sha256', $payload, 'test_secret');

        $request = Request::create('/webhooks/facebook', 'POST', [], [], [], [
            'HTTP_X_HUB_SIGNATURE_256' => $signature,
            'CONTENT_TYPE' => 'application/json',
        ], $payload);

        $middleware->handle($request, function ($req) {
            $this->assertNotNull($req->attributes->get('webhook_config'));
            $this->assertNotNull($req->attributes->get('webhook_platform'));
            $this->assertEquals('facebook', $req->attributes->get('webhook_platform'));
            
            return new Response('Success', 200);
        });
    }

    /** @test */
    public function it_handles_different_platform_signature_headers()
    {
        $middleware = new VerifyWebhookSignature();
        
        // Test Facebook
        $facebookRequest = Request::create('/webhooks/facebook', 'POST', [], [], [], [
            'HTTP_X_HUB_SIGNATURE_256' => 'sha256=signature',
        ]);

        // Test Twitter
        $twitterRequest = Request::create('/webhooks/twitter', 'POST', [], [], [], [
            'HTTP_X_TWITTER_WEBHOOKS_SIGNATURE' => 'signature',
        ]);

        // Test LinkedIn
        $linkedinRequest = Request::create('/webhooks/linkedin', 'POST', [], [], [], [
            'HTTP_X_LI_SIGNATURE' => 'signature',
        ]);

        // Use reflection to access protected method
        $reflection = new \ReflectionClass($middleware);
        $method = $reflection->getMethod('getSignatureFromRequest');
        $method->setAccessible(true);

        $this->assertEquals('sha256=signature', $method->invoke($middleware, $facebookRequest, 'facebook'));
        $this->assertEquals('signature', $method->invoke($middleware, $twitterRequest, 'twitter'));
        $this->assertEquals('signature', $method->invoke($middleware, $linkedinRequest, 'linkedin'));
    }
}