<?php

namespace Database\Factories;

use App\Models\SocialAccount;
use App\Models\WebhookConfig;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\WebhookConfig>
 */
class WebhookConfigFactory extends Factory
{
    protected $model = WebhookConfig::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'social_account_id' => SocialAccount::factory(),
            'webhook_url' => $this->faker->url() . '/webhook',
            'secret' => bin2hex(random_bytes(32)),
            'events' => $this->faker->randomElements([
                'page_posts',
                'page_comments',
                'media_comments',
                'tweet_events',
                'share_updates',
            ], $this->faker->numberBetween(1, 3)),
            'is_active' => true,
            'metadata' => [
                'version' => '1.0',
                'created_by' => 'system',
            ],
            'last_verified_at' => $this->faker->dateTimeBetween('-1 month', 'now'),
        ];
    }

    /**
     * Indicate that the webhook config is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    /**
     * Indicate that the webhook config has no secret.
     */
    public function withoutSecret(): static
    {
        return $this->state(fn (array $attributes) => [
            'secret' => null,
        ]);
    }

    /**
     * Indicate that the webhook config is for Facebook.
     */
    public function forFacebook(): static
    {
        return $this->state(fn (array $attributes) => [
            'events' => $this->faker->randomElements([
                'page_posts',
                'page_comments',
                'page_likes',
                'page_messages',
                'lead_generation',
            ], $this->faker->numberBetween(1, 3)),
        ]);
    }

    /**
     * Indicate that the webhook config is for Instagram.
     */
    public function forInstagram(): static
    {
        return $this->state(fn (array $attributes) => [
            'events' => $this->faker->randomElements([
                'media_comments',
                'media_mentions',
                'story_replies',
                'business_account_updates',
            ], $this->faker->numberBetween(1, 3)),
        ]);
    }

    /**
     * Indicate that the webhook config is for Twitter.
     */
    public function forTwitter(): static
    {
        return $this->state(fn (array $attributes) => [
            'events' => $this->faker->randomElements([
                'tweet_events',
                'tweet_mentions',
                'tweet_replies',
                'direct_messages',
            ], $this->faker->numberBetween(1, 3)),
        ]);
    }

    /**
     * Indicate that the webhook config is for LinkedIn.
     */
    public function forLinkedIn(): static
    {
        return $this->state(fn (array $attributes) => [
            'events' => $this->faker->randomElements([
                'person_updates',
                'organization_updates',
                'share_updates',
                'comment_updates',
            ], $this->faker->numberBetween(1, 3)),
        ]);
    }
}