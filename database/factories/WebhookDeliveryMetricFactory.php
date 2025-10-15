<?php

namespace Database\Factories;

use App\Models\WebhookConfig;
use App\Models\WebhookDeliveryMetric;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\WebhookDeliveryMetric>
 */
class WebhookDeliveryMetricFactory extends Factory
{
    protected $model = WebhookDeliveryMetric::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $platforms = ['facebook', 'instagram', 'twitter', 'linkedin'];
        
        return [
            'webhook_config_id' => WebhookConfig::factory(),
            'social_account_id' => fn(array $attributes) => WebhookConfig::find($attributes['webhook_config_id'])->social_account_id,
            'platform' => $this->faker->randomElement($platforms),
            'date' => $this->faker->date(),
            'total_received' => $this->faker->numberBetween(0, 100),
            'successfully_processed' => $this->faker->numberBetween(0, 100),
            'failed' => $this->faker->numberBetween(0, 10),
            'ignored' => $this->faker->numberBetween(0, 5),
            'retry_attempts' => $this->faker->numberBetween(0, 3),
            'average_processing_time' => $this->faker->randomFloat(3, 0.1, 5.0), // seconds
            'event_type_breakdown' => [
                'page_post' => $this->faker->numberBetween(0, 50),
                'comment' => $this->faker->numberBetween(0, 30),
                'like' => $this->faker->numberBetween(0, 100),
            ],
        ];
    }
}