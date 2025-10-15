<?php

namespace Database\Factories;

use App\Models\SocialAccount;
use App\Models\WebhookConfig;
use App\Models\WebhookEvent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\WebhookEvent>
 */
class WebhookEventFactory extends Factory
{
    protected $model = WebhookEvent::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $platforms = ['facebook', 'instagram', 'twitter', 'linkedin'];
        $platform = $this->faker->randomElement($platforms);

        return [
            'social_account_id' => SocialAccount::factory(),
            'webhook_config_id' => WebhookConfig::factory(),
            'platform' => $platform,
            'event_type' => $this->faker->randomElement($this->getEventTypesForPlatform($platform)),
            'event_id' => $this->faker->uuid(),
            'object_type' => $this->faker->randomElement(['page', 'user', 'post', 'media', 'tweet']),
            'object_id' => $this->faker->uuid(),
            'payload' => $this->generatePayload($platform),
            'signature' => 'sha256=' . bin2hex(random_bytes(32)),
            'status' => 'pending',
            'error_message' => null,
            'retry_count' => 0,
            'received_at' => $this->faker->dateTimeBetween('-1 week', 'now'),
            'processed_at' => null,
        ];
    }

    /**
     * Get event types for a specific platform.
     */
    private function getEventTypesForPlatform(string $platform): array
    {
        return match ($platform) {
            'facebook' => ['page_posts', 'page_comments', 'page_likes', 'page_messages', 'lead_generation'],
            'instagram' => ['media_comments', 'media_mentions', 'story_replies', 'business_account_updates'],
            'twitter' => ['tweet_events', 'tweet_mentions', 'tweet_replies', 'direct_messages'],
            'linkedin' => ['person_updates', 'organization_updates', 'share_updates', 'comment_updates'],
            default => [],
        };
    }

    /**
     * Generate realistic payload for different platforms.
     */
    private function generatePayload(string $platform): array
    {
        return match ($platform) {
            'facebook' => [
                'entry' => [
                    [
                        'id' => $this->faker->uuid(),
                        'time' => $this->faker->unixTime(),
                        'messaging' => [
                            [
                                'sender' => ['id' => $this->faker->uuid()],
                                'recipient' => ['id' => $this->faker->uuid()],
                                'timestamp' => $this->faker->unixTime(),
                                'message' => [
                                    'mid' => $this->faker->uuid(),
                                    'text' => $this->faker->sentence(),
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
                        'id' => $this->faker->uuid(),
                        'time' => $this->faker->unixTime(),
                        'changes' => [
                            [
                                'field' => 'comments',
                                'value' => [
                                    'id' => $this->faker->uuid(),
                                    'text' => $this->faker->sentence(),
                                    'from' => ['username' => $this->faker->userName()],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            'twitter' => [
                'for_user_id' => $this->faker->uuid(),
                'tweet_create_events' => [
                    [
                        'created_at' => $this->faker->dateTime()->format(DATE_RFC822),
                        'id_str' => $this->faker->uuid(),
                        'text' => $this->faker->sentence(),
                        'user' => [
                            'id_str' => $this->faker->uuid(),
                            'screen_name' => $this->faker->userName(),
                        ],
                    ],
                ],
            ],
            'linkedin' => [
                'event' => 'SHARE_UPDATE',
                'data' => [
                    'actor' => $this->faker->uuid(),
                    'object' => $this->faker->uuid(),
                    'timestamp' => $this->faker->unixTime(),
                ],
            ],
            default => [],
        };
    }

    /**
     * Indicate that the webhook event is processed.
     */
    public function processed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'processed',
            'processed_at' => $this->faker->dateTimeBetween('-1 week', 'now'),
        ]);
    }

    /**
     * Indicate that the webhook event has failed.
     */
    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'failed',
            'error_message' => $this->faker->sentence(),
            'retry_count' => $this->faker->numberBetween(1, 3),
        ]);
    }

    /**
     * Indicate that the webhook event is ignored.
     */
    public function ignored(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'ignored',
            'processed_at' => $this->faker->dateTimeBetween('-1 week', 'now'),
        ]);
    }

    /**
     * Indicate that the webhook event needs retry.
     */
    public function needsRetry(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'failed',
            'retry_count' => $this->faker->numberBetween(1, 4),
            'error_message' => 'Temporary failure',
        ]);
    }
}