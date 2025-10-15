<?php

namespace Tests\Feature\Webhooks;

use App\Http\Controllers\Webhooks\BaseWebhookController;
use App\Models\SocialAccount;
use App\Models\User;
use App\Models\WebhookConfig;
use App\Models\WebhookDeliveryMetric;
use App\Models\WebhookEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Tests\TestCase;

class BaseWebhookControllerTest extends TestCase
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

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function it_handles_webhook_verification_challenge()
    {
        $controller = $this->createMockController();
        
        $request = Request::create('/webhooks/facebook', 'GET', [
            'hub_challenge' => 'test_challenge',
        ]);

        $controller->shouldReceive('handleVerification')
            ->once()
            ->andReturn(new JsonResponse(['hub.challenge' => 'test_challenge'], 200));

        $response = $controller->handle($request);

        $this->assertEquals(200, $response->getStatusCode());
    }

    /** @test */
    public function it_returns_error_when_no_webhook_config_found()
    {
        $controller = $this->createMockController();
        
        $request = Request::create('/webhooks/facebook', 'POST', [
            'test' => 'data',
        ]);

        $controller->shouldReceive('isVerificationRequest')->andReturn(false);
        $controller->shouldReceive('getWebhookConfig')->andReturn(null);

        $response = $controller->handle($request);

        $this->assertEquals(404, $response->getStatusCode());
        $responseData = json_decode($response->getContent(), true);
        $this->assertEquals('error', $responseData['status']);
        $this->assertEquals('Webhook not configured', $responseData['message']);
    }

    /** @test */
    public function it_returns_error_when_signature_verification_fails()
    {
        $controller = $this->createMockController();
        
        $request = Request::create('/webhooks/facebook', 'POST', [
            'test' => 'data',
        ]);

        $controller->shouldReceive('isVerificationRequest')->andReturn(false);
        $controller->shouldReceive('getWebhookConfig')->andReturn($this->webhookConfig);
        $controller->shouldReceive('verifySignature')->andReturn(false);

        $response = $controller->handle($request);

        $this->assertEquals(401, $response->getStatusCode());
        $responseData = json_decode($response->getContent(), true);
        $this->assertEquals('error', $responseData['status']);
        $this->assertEquals('Invalid signature', $responseData['message']);
    }

    /** @test */
    public function it_processes_valid_webhook_successfully()
    {
        Queue::fake();

        $controller = $this->createMockController();
        
        $requestData = [
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

        $request = Request::create('/webhooks/facebook', 'POST', $requestData);

        $eventData = [
            'event_type' => 'page_posts',
            'event_id' => 'event_123',
            'object_type' => 'post',
            'object_id' => '123456789',
        ];

        $controller->shouldReceive('isVerificationRequest')->andReturn(false);
        $controller->shouldReceive('getWebhookConfig')->andReturn($this->webhookConfig);
        $controller->shouldReceive('verifySignature')->andReturn(true);
        $controller->shouldReceive('extractEventData')->andReturn($eventData);

        $response = $controller->handle($request);

        $this->assertEquals(200, $response->getStatusCode());
        $responseData = json_decode($response->getContent(), true);
        $this->assertEquals('success', $responseData['status']);
        $this->assertEquals('Webhook received', $responseData['message']);

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
        Queue::assertPushed(\App\Jobs\ProcessWebhookEventJob::class);
    }

    /** @test */
    public function it_handles_validation_errors()
    {
        $controller = $this->createMockController();
        
        $request = Request::create('/webhooks/facebook', 'POST', [
            'invalid' => 'data',
        ]);

        $eventData = [
            // Missing required event_type
            'event_id' => 'event_123',
        ];

        $controller->shouldReceive('isVerificationRequest')->andReturn(false);
        $controller->shouldReceive('getWebhookConfig')->andReturn($this->webhookConfig);
        $controller->shouldReceive('verifySignature')->andReturn(true);
        $controller->shouldReceive('extractEventData')->andReturn($eventData);

        $response = $controller->handle($request);

        $this->assertEquals(422, $response->getStatusCode());
        $responseData = json_decode($response->getContent(), true);
        $this->assertEquals('error', $responseData['status']);
        $this->assertEquals('Validation failed', $responseData['message']);
    }

    /** @test */
    public function it_handles_processing_exceptions()
    {
        $controller = $this->createMockController();
        
        $request = Request::create('/webhooks/facebook', 'POST', [
            'test' => 'data',
        ]);

        $controller->shouldReceive('isVerificationRequest')->andReturn(false);
        $controller->shouldReceive('getWebhookConfig')->andThrow(new \Exception('Database error'));

        $response = $controller->handle($request);

        $this->assertEquals(500, $response->getStatusCode());
        $responseData = json_decode($response->getContent(), true);
        $this->assertEquals('error', $responseData['status']);
        $this->assertEquals('Internal server error', $responseData['message']);
    }

    /** @test */
    public function it_gets_webhook_config_from_query_params()
    {
        $controller = $this->createConcreteController();
        
        $request = Request::create('/webhooks/facebook', 'POST', [
            'webhook_config_id' => $this->webhookConfig->id,
        ]);

        $config = $controller->getWebhookConfig($request);

        $this->assertEquals($this->webhookConfig->id, $config->id);
    }

    /** @test */
    public function it_gets_webhook_config_from_headers()
    {
        $controller = $this->createConcreteController();
        
        $request = Request::create('/webhooks/facebook', 'POST', [], [], [], [
            'HTTP_X_WEBHOOK_CONFIG_ID' => $this->webhookConfig->id,
        ]);

        $config = $controller->getWebhookConfig($request);

        $this->assertEquals($this->webhookConfig->id, $config->id);
    }

    /** @test */
    public function it_falls_back_to_active_config_for_platform()
    {
        $controller = $this->createConcreteController();
        
        $request = Request::create('/webhooks/facebook', 'POST', []);

        $config = $controller->getWebhookConfig($request);

        $this->assertEquals($this->webhookConfig->id, $config->id);
    }

    /** @test */
    public function it_detects_verification_requests()
    {
        $controller = $this->createConcreteController();

        $challengeRequest = Request::create('/webhooks/facebook', 'GET', [
            'hub_challenge' => 'test',
        ]);

        $normalRequest = Request::create('/webhooks/facebook', 'POST', [
            'test' => 'data',
        ]);

        $this->assertTrue($controller->isVerificationRequest($challengeRequest));
        $this->assertFalse($controller->isVerificationRequest($normalRequest));
    }

    /** @test */
    public function it_validates_event_data()
    {
        $controller = $this->createConcreteController();

        $validData = [
            'event_type' => 'page_posts',
            'event_id' => 'event_123',
            'object_type' => 'post',
            'object_id' => 'post_123',
        ];

        $invalidData = [
            'event_id' => 'event_123',
            // Missing event_type
        ];

        $this->assertEquals($validData, $controller->validateEventData($validData));

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $controller->validateEventData($invalidData);
    }

    /** @test */
    public function it_stores_webhook_event()
    {
        $controller = $this->createConcreteController();
        
        $requestData = [
            'entry' => [
                [
                    'id' => '123456789',
                    'time' => time(),
                ],
            ],
        ];

        $request = Request::create('/webhooks/facebook', 'POST', $requestData, [], [], [
            'HTTP_X_HUB_SIGNATURE' => 'sha256=test_signature',
        ]);

        $eventData = [
            'event_type' => 'page_posts',
            'event_id' => 'event_123',
            'object_type' => 'post',
            'object_id' => 'post_123',
        ];

        $webhookEvent = $controller->storeWebhookEvent($request, $this->webhookConfig, $eventData);

        $this->assertInstanceOf(WebhookEvent::class, $webhookEvent);
        $this->assertEquals($this->webhookConfig->id, $webhookEvent->webhook_config_id);
        $this->assertEquals($this->socialAccount->id, $webhookEvent->social_account_id);
        $this->assertEquals('facebook', $webhookEvent->platform);
        $this->assertEquals('page_posts', $webhookEvent->event_type);
        $this->assertEquals('event_123', $webhookEvent->event_id);
        $this->assertEquals('post', $webhookEvent->object_type);
        $this->assertEquals('post_123', $webhookEvent->object_id);
        $this->assertEquals('pending', $webhookEvent->status);
        $this->assertEquals('sha256=test_signature', $webhookEvent->signature);
        $this->assertNotNull($webhookEvent->received_at);
    }

    /** @test */
    public function it_gets_signature_from_request()
    {
        $controller = $this->createConcreteController();

        $facebookRequest = Request::create('/webhooks/facebook', 'POST', [], [], [], [
            'HTTP_X_HUB_SIGNATURE_256' => 'sha256=facebook_signature',
        ]);

        $twitterRequest = Request::create('/webhooks/twitter', 'POST', [], [], [], [
            'HTTP_X_TWITTER_WEBHOOKS_SIGNATURE' => 'twitter_signature',
        ]);

        $linkedinRequest = Request::create('/webhooks/linkedin', 'POST', [], [], [], [
            'HTTP_X_LI_SIGNATURE' => 'linkedin_signature',
        ]);

        $this->assertEquals('sha256=facebook_signature', $controller->getSignatureFromRequest($facebookRequest));
        $this->assertEquals('twitter_signature', $controller->getSignatureFromRequest($twitterRequest));
        $this->assertEquals('linkedin_signature', $controller->getSignatureFromRequest($linkedinRequest));
    }

    /** @test */
    public function it_records_delivery_metrics()
    {
        $controller = $this->createConcreteController();

        $controller->recordDeliveryMetric($this->webhookConfig, 'success', 200);

        $this->assertDatabaseHas('webhook_delivery_metrics', [
            'webhook_config_id' => $this->webhookConfig->id,
            'status' => 'success',
            'http_status_code' => 200,
        ]);
    }

    /** @test */
    public function it_returns_success_response()
    {
        $controller = $this->createConcreteController();

        $response = $controller->successResponse('Test success', 201);

        $this->assertEquals(201, $response->getStatusCode());
        $responseData = json_decode($response->getContent(), true);
        $this->assertEquals('success', $responseData['status']);
        $this->assertEquals('Test success', $responseData['message']);
    }

    /** @test */
    public function it_returns_error_response()
    {
        $controller = $this->createConcreteController();

        $response = $controller->errorResponse('Test error', 400);

        $this->assertEquals(400, $response->getStatusCode());
        $responseData = json_decode($response->getContent(), true);
        $this->assertEquals('error', $responseData['status']);
        $this->assertEquals('Test error', $responseData['message']);
    }

    /** @test */
    public function it_returns_challenge_response()
    {
        $controller = $this->createConcreteController();

        $response = $controller->challengeResponse('test_challenge');

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('text/plain', $response->headers->get('Content-Type'));
        $this->assertEquals('test_challenge', $response->getContent());
    }

    /** @test */
    public function it_normalizes_event_types()
    {
        $controller = $this->createConcreteController();

        // Test with no mappings (default behavior)
        $this->assertEquals('page_posts', $controller->normalizeEventType('page_posts'));

        // Test with custom mappings
        $customController = new class extends BaseWebhookController {
            protected string $platform = 'facebook';

            protected function getEventMappings(): array
            {
                return [
                    'feed_post' => 'page_posts',
                    'comment_add' => 'page_comments',
                ];
            }

            protected function verifySignature(Request $request, WebhookConfig $config): bool
            {
                return true;
            }

            protected function handleVerification(Request $request): JsonResponse
            {
                return response()->json(['status' => 'success']);
            }

            protected function extractEventData(Request $request): array
            {
                return [];
            }
        };

        $this->assertEquals('page_posts', $customController->normalizeEventType('feed_post'));
        $this->assertEquals('page_comments', $customController->normalizeEventType('comment_add'));
        $this->assertEquals('custom_event', $customController->normalizeEventType('custom_event'));
    }

    /**
     * Create a mock controller for testing abstract methods.
     */
    protected function createMockController()
    {
        $controller = Mockery::mock(BaseWebhookController::class)->makePartial();
        $controller->shouldAllowMockingProtectedMethods();
        
        return $controller;
    }

    /**
     * Create a concrete controller implementation for testing non-abstract methods.
     */
    protected function createConcreteController()
    {
        return new class extends BaseWebhookController {
            protected string $platform = 'facebook';

            protected function verifySignature(Request $request, WebhookConfig $config): bool
            {
                return true;
            }

            protected function handleVerification(Request $request): JsonResponse
            {
                return response()->json(['status' => 'success']);
            }

            protected function extractEventData(Request $request): array
            {
                return [
                    'event_type' => 'page_posts',
                    'event_id' => 'event_123',
                    'object_type' => 'post',
                    'object_id' => 'post_123',
                ];
            }
        };
    }
}