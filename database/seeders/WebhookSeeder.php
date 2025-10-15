<?php

namespace Database\Seeders;

use App\Models\SocialAccount;
use App\Models\User;
use App\Models\WebhookConfig;
use App\Models\WebhookSubscription;
use Illuminate\Database\Seeder;

class WebhookSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get existing social accounts or create some for testing
        $socialAccounts = SocialAccount::with('user')->get();
        
        if ($socialAccounts->isEmpty()) {
            $this->command->info('No social accounts found. Please run social account seeder first.');
            return;
        }

        foreach ($socialAccounts as $socialAccount) {
            // Create webhook config for each social account
            $webhookConfig = WebhookConfig::factory()->create([
                'social_account_id' => $socialAccount->id,
                'webhook_url' => config('app.url') . "/webhooks/{$socialAccount->platform}",
            ]);

            // Create subscriptions based on platform
            $this->createSubscriptionsForPlatform($webhookConfig, $socialAccount->platform);
        }

        $this->command->info('Webhook configs and subscriptions created successfully.');
    }

    /**
     * Create subscriptions for a specific platform.
     */
    private function createSubscriptionsForPlatform(WebhookConfig $config, string $platform): void
    {
        $eventTypes = match ($platform) {
            'facebook' => [
                'page_posts',
                'page_comments',
                'page_likes',
                'page_messages',
                'lead_generation',
            ],
            'instagram' => [
                'media_comments',
                'media_mentions',
                'story_replies',
                'business_account_updates',
            ],
            'twitter' => [
                'tweet_events',
                'tweet_mentions',
                'tweet_replies',
                'direct_messages',
            ],
            'linkedin' => [
                'person_updates',
                'organization_updates',
                'share_updates',
                'comment_updates',
            ],
            default => [],
        };

        foreach ($eventTypes as $eventType) {
            WebhookSubscription::factory()->create([
                'webhook_config_id' => $config->id,
                'platform' => $platform,
                'event_type' => $eventType,
                'subscription_id' => 'sub_' . uniqid(),
                'status' => 'active',
                'subscribed_at' => now(),
                'expires_at' => now()->addDays(30),
            ]);
        }
    }
}