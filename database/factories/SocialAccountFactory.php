<?php

namespace Database\Factories;

use App\Models\SocialAccount;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\SocialAccount>
 */
class SocialAccountFactory extends Factory
{
    protected $model = SocialAccount::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $platforms = ['facebook', 'instagram', 'twitter', 'linkedin'];
        
        return [
            'user_id' => User::factory(),
            'platform' => $this->faker->randomElement($platforms),
            'platform_id' => $this->faker->unique()->numerify('##########'),
            'username' => $this->faker->userName,
            'access_token' => ['token' => $this->faker->sha256],
            'refresh_token' => ['token' => $this->faker->sha256],
            'token_expires_at' => $this->faker->dateTimeBetween('+1 month', '+1 year'),
            'is_active' => true,
        ];
    }
}