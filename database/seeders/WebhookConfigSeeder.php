<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\WebhookConfig;
use App\Models\SocialAccount;
use Illuminate\Support\Str;

class WebhookConfigSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get all social accounts
        $socialAccounts = SocialAccount::all();

        foreach ($socialAccounts as $socialAccount) {
            // Create webhook config for each social account
            $webhookConfig = WebhookConfig::create([
                'social_account_id' => $socialAccount->id,
                'webhook_url' => route('webhooks.' . $socialAccount->platform . '.handle'),
                'secret' => Str::random(64),
                'events' => $this->getDefaultEvents($socialAccount->platform),
                'is_active' => true,
                'metadata' => $this->getDefaultMetadata($socialAccount->platform),
                'last_verified_at' => now(),
            ]);

            $this->command->info("Created webhook config for {$socialAccount->platform} account: {$socialAccount->username}");
        }
    }

    /**
     * Get default events for each platform.
     */
    private function getDefaultEvents(string $platform): array
    {
        return match($platform) {
            'facebook' => [
                'feed',
                'messages',
                'messaging_postbacks',
                'messaging_optins',
                'messaging_referrals',
                'leadgen',
                'ratings',
                'live_videos',
            ],
            'instagram' => [
                'media',
                'comments',
                'mentions',
                'story_insights',
                'user_insights',
                'business_account',
                'messaging_handover',
                'messaging_referrals',
                'messaging_postbacks',
                'messaging_optins',
            ],
            'twitter' => [
                'direct_message_events',
                'direct_message_indicate_typing_events',
                'direct_message_mark_read_events',
                'tweet_create_events',
                'tweet_delete_events',
                'tweet_retweet_events',
                'quote_tweet_events',
                'favorite_events',
                'follow_events',
                'user_update_events',
                'list_events',
            ],
            'linkedin' => [
                'SHARE_CREATED',
                'SHARE_UPDATED',
                'SHARE_DELETED',
                'COMMENT_CREATED',
                'COMMENT_UPDATED',
                'COMMENT_DELETED',
                'REACTION_CREATED',
                'REACTION_DELETED',
                'PERSON_UPDATED',
                'ORGANIZATION_UPDATED',
                'UGC_PUBLISHED',
                'UGC_UPDATED',
                'UGC_DELETED',
            ],
            default => [],
        };
    }

    /**
     * Get default metadata for each platform.
     */
    private function getDefaultMetadata(string $platform): array
    {
        return match($platform) {
            'facebook' => [
                'verify_token' => Str::random(32),
                'app_id' => config('services.facebook.client_id'),
                'app_version' => 'v18.0',
            ],
            'instagram' => [
                'verify_token' => Str::random(32),
                'app_id' => config('services.facebook.client_id'),
                'app_version' => 'v18.0',
            ],
            'twitter' => [
                'ignore_own_tweets' => false,
                'ignore_retweets' => false,
                'environment' => 'dev', // dev or prod
                'webhook_id' => null, // Will be set when webhook is created
            ],
            'linkedin' => [
                'ignore_own_shares' => false,
                'ignore_comments_on_own_posts' => false,
                'allowed_reaction_types' => ['LIKE', 'PRAISE', 'APPRECIATION', 'EMPATHY', 'INTEREST', 'ENTERTAINMENT'],
                'app_id' => config('services.linkedin.client_id'),
            ],
            default => [],
        };
    }
}