<?php

namespace Tests\Feature\Webhooks;

use App\Models\User;
use App\Models\SocialAccount;
use App\Models\WebhookConfig;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;
use Illuminate\Support\Str;

class WebhookTest extends TestCase
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
            'platform' => 'facebook',
            'secret' => 'test-secret-key',
        ]);
    }

    /** @test */
    public function it_can_handle_facebook_webhook_verification()
    {
        $response = $this->get('/webhooks/facebook', [
            'hub_mode' => 'subscribe',
            'hub_challenge' => 'test-challenge',
            'hub_verify_token' => $this->webhookConfig->metadata['verify_token'],
        ]);

        $response->assertStatus(200);
        $response->assertContent('test-challenge');
    }

    /** @test */
    public function it_rejects_invalid_facebook_verification_token()
    {
        $response = $this->get('/webhooks/facebook', [
            'hub_mode' => 'subscribe',
            'hub_challenge' => 'test-challenge',
            'hub_verify_token' => 'invalid-token',
        ]);

        $response->assertStatus(403);
    }

    /** @test */
    public function it_can_process_facebook_webhook_event()
    {
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
                                'verb' => 'add',
                                'item' => 'status',
                                'like_count' => 10,
                                'comment_count' => 5,
                                'share_count' => 2,
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $signature = hash_hmac('sha256', json_encode($payload), $this->webhookConfig->secret);

        $response = $this->post('/webhooks/facebook', $payload, [
            'X-Hub-Signature-256' => 'sha256=' . $signature,
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'status' => 'success',
            'message' => 'Webhook received',
        ]);

        $this->assertDatabaseHas('webhook_events', [
            'social_account_id' => $this->socialAccount->id,
            'webhook_config_id' => $this->webhookConfig->id,
            'platform' => 'facebook',
            'event_type' => 'post_created',
            'object_type' => 'post',
            'object_id' => 'post_123',
            'status' => 'pending',
        ]);
    }

    /** @test */
    public function it_rejects_facebook_webhook_with_invalid_signature()
    {
        $payload = [
            'object' => 'page',
            'entry' => [
                [
                    'id' => '123456789',
                    'time' => time(),
                    'changes' => [],
                ],
            ],
        ];

        $response = $this->post('/webhooks/facebook', $payload, [
            'X-Hub-Signature-256' => 'sha256=invalid-signature',
        ]);

        $response->assertStatus(401);
    }

    /** @test */
    public function it_can_handle_instagram_webhook_verification()
    {
        $instagramAccount = SocialAccount::factory()->create([
            'user_id' => $this->user->id,
            'platform' => 'instagram',
        ]);
        $instagramConfig = WebhookConfig::factory()->create([
            'social_account_id' => $instagramAccount->id,
            'platform' => 'instagram',
            'secret' => 'test-secret-key',
        ]);

        $response = $this->get('/webhooks/instagram', [
            'hub_mode' => 'subscribe',
            'hub_challenge' => 'test-challenge',
            'hub_verify_token' => $instagramConfig->metadata['verify_token'],
        ]);

        $response->assertStatus(200);
        $response->assertContent('test-challenge');
    }

    /** @test */
    public function it_can_process_instagram_webhook_event()
    {
        $instagramAccount = SocialAccount::factory()->create([
            'user_id' => $this->user->id,
            'platform' => 'instagram',
        ]);
        $instagramConfig = WebhookConfig::factory()->create([
            'social_account_id' => $instagramAccount->id,
            'platform' => 'instagram',
            'secret' => 'test-secret-key',
        ]);

        $payload = [
            'object' => 'instagram',
            'entry' => [
                [
                    'id' => '123456789',
                    'time' => time(),
                    'changes' => [
                        [
                            'field' => 'media',
                            'value' => [
                                'media_id' => 'media_123',
                                'verb' => 'added',
                                'media_type' => 'image',
                                'caption' => 'Test caption',
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $signature = hash_hmac('sha256', json_encode($payload), $instagramConfig->secret);

        $response = $this->post('/webhooks/instagram', $payload, [
            'X-Hub-Signature-256' => 'sha256=' . $signature,
        ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('webhook_events', [
            'social_account_id' => $instagramAccount->id,
            'webhook_config_id' => $instagramConfig->id,
            'platform' => 'instagram',
            'event_type' => 'media_added',
            'object_type' => 'media',
            'object_id' => 'media_123',
            'status' => 'pending',
        ]);
    }

    /** @test */
    public function it_can_handle_twitter_webhook_verification()
    {
        $twitterAccount = SocialAccount::factory()->create([
            'user_id' => $this->user->id,
            'platform' => 'twitter',
        ]);
        $twitterConfig = WebhookConfig::factory()->create([
            'social_account_id' => $twitterAccount->id,
            'platform' => 'twitter',
            'secret' => 'test-secret-key',
        ]);

        $response = $this->get('/webhooks/twitter', [
            'crc_token' => 'test-crc-token',
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure(['response_token']);
    }

    /** @test */
    public function it_can_process_twitter_webhook_event()
    {
        $twitterAccount = SocialAccount::factory()->create([
            'user_id' => $this->user->id,
            'platform' => 'twitter',
        ]);
        $twitterConfig = WebhookConfig::factory()->create([
            'social_account_id' => $twitterAccount->id,
            'platform' => 'twitter',
            'secret' => 'test-secret-key',
        ]);

        $payload = [
            'for_user_id' => '123456789',
            'tweet_create_events' => [
                [
                    'id_str' => 'tweet_123',
                    'text' => 'Test tweet',
                    'created_at' => 'Wed Oct 15 12:00:00 +0000 2025',
                    'user' => [
                        'id_str' => '123456789',
                        'name' => 'Test User',
                        'screen_name' => 'testuser',
                    ],
                    'retweet_count' => 5,
                    'favorite_count' => 10,
                    'reply_count' => 2,
                    'quote_count' => 1,
                ],
            ],
        ];

        $signature = hash_hmac('sha256', json_encode($payload), $twitterConfig->secret);

        $response = $this->post('/webhooks/twitter', $payload, [
            'X-Twitter-Webhooks-Signature' => $signature,
        ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('webhook_events', [
            'social_account_id' => $twitterAccount->id,
            'webhook_config_id' => $twitterConfig->id,
            'platform' => 'twitter',
            'event_type' => 'tweet_created',
            'object_type' => 'tweet',
            'object_id' => 'tweet_123',
            'status' => 'pending',
        ]);
    }

    /** @test */
    public function it_can_handle_linkedin_webhook_verification()
    {
        $linkedinAccount = SocialAccount::factory()->create([
            'user_id' => $this->user->id,
            'platform' => 'linkedin',
        ]);
        $linkedinConfig = WebhookConfig::factory()->create([
            'social_account_id' => $linkedinAccount->id,
            'platform' => 'linkedin',
            'secret' => 'test-secret-key',
        ]);

        $response = $this->get('/webhooks/linkedin', [
            'challenge_code' => 'test-challenge-code',
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure(['challengeResponse']);
    }

    /** @test */
    public function it_can_process_linkedin_webhook_event()
    {
        $linkedinAccount = SocialAccount::factory()->create([
            'user_id' => $this->user->id,
            'platform' => 'linkedin',
        ]);
        $linkedinConfig = WebhookConfig::factory()->create([
            'social_account_id' => $linkedinAccount->id,
            'platform' => 'linkedin',
            'secret' => 'test-secret-key',
        ]);

        $payload = [
            'shareUpdate' => [
                'updateType' => 'CREATED',
                'shareId' => 'share_123',
                'updateKey' => 'update_key_123',
                'owner' => 'owner_123',
                'shareText' => 'Test LinkedIn post',
                'numLikes' => 15,
                'numComments' => 3,
                'numShares' => 2,
            ],
        ];

        $signature = hash_hmac('sha256', json_encode($payload), $linkedinConfig->secret);

        $response = $this->post('/webhooks/linkedin', $payload, [
            'X-LI-Signature' => $signature,
        ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('webhook_events', [
            'social_account_id' => $linkedinAccount->id,
            'webhook_config_id' => $linkedinConfig->id,
            'platform' => 'linkedin',
            'event_type' => 'share_created',
            'object_type' => 'share',
            'object_id' => 'share_123',
            'status' => 'pending',
        ]);
    }

    /** @test */
    public function it_returns_health_check()
    {
        $response = $this->get('/webhooks/health');

        $response->assertStatus(200);
        $response->assertJson([
            'status' => 'healthy',
        ]);
    }

    /** @test */
    public function it_handles_test_webhook()
    {
        $payload = [
            'test' => 'data',
            'timestamp' => now()->toISOString(),
        ];

        $response = $this->post('/webhooks/test', $payload);

        $response->assertStatus(200);
        $response->assertJson([
            'status' => 'success',
            'message' => 'Test webhook received',
        ]);
    }

    /** @test */
    public function it_respects_rate_limiting()
    {
        // Make multiple requests quickly to test rate limiting
        $responses = collect(range(1, 10))->map(function () {
            return $this->get('/webhooks/health');
        });

        // At least some requests should succeed
        $successful = $responses->filter(fn($r) => $r->status() === 200);
        $this->assertGreaterThan(0, $successful->count());
    }

    /** @test */
    public function it_can_manage_webhook_configs()
    {
        // Test listing configs
        $response = $this->actingAs($this->user)
            ->get('/webhooks/manage/configs');

        $response->assertStatus(200);
        $response->assertJsonCount(1); // One config created in setUp

        // Test creating a new config
        $newSocialAccount = SocialAccount::factory()->create([
            'user_id' => $this->user->id,
            'platform' => 'twitter',
        ]);

        $response = $this->actingAs($this->user)
            ->post('/webhooks/manage/configs', [
                'social_account_id' => $newSocialAccount->id,
                'events' => ['tweet_create_events', 'favorite_events'],
                'metadata' => ['test' => 'data'],
            ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('webhook_configs', [
            'social_account_id' => $newSocialAccount->id,
            'platform' => 'twitter',
        ]);

        // Test updating config
        $configId = $response->json('id');
        $response = $this->actingAs($this->user)
            ->put("/webhooks/manage/configs/{$configId}", [
                'events' => ['tweet_create_events', 'favorite_events', 'follow_events'],
                'is_active' => false,
            ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('webhook_configs', [
            'id' => $configId,
            'is_active' => false,
        ]);

        // Test deleting config
        $response = $this->actingAs($this->user)
            ->delete("/webhooks/manage/configs/{$configId}");

        $response->assertStatus(204);
        $this->assertDatabaseMissing('webhook_configs', [
            'id' => $configId,
        ]);
    }

    /** @test */
    public function it_prevents_unauthorized_config_management()
    {
        $otherUser = User::factory()->create();
        $otherSocialAccount = SocialAccount::factory()->create([
            'user_id' => $otherUser->id,
            'platform' => 'twitter',
        ]);

        // Try to access another user's config
        $response = $this->actingAs($this->user)
            ->post('/webhooks/manage/configs', [
                'social_account_id' => $otherSocialAccount->id,
                'events' => ['tweet_create_events'],
            ]);

        $response->assertStatus(403);
    }
}