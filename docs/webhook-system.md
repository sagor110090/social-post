# Webhook System Documentation

## Overview

The webhook system provides comprehensive support for handling real-time events from Facebook, Instagram, X/Twitter, and LinkedIn platforms. It enables the application to receive and process webhook events for enhanced analytics, engagement tracking, and automated responses.

## Database Schema

### Core Tables

#### 1. `webhook_configs`

Stores webhook configurations per social account.

**Columns:**

- `id` - Primary key
- `social_account_id` - Foreign key to social_accounts
- `webhook_url` - URL where webhooks are sent
- `secret` - Webhook secret for signature verification
- `events` - JSON array of subscribed events
- `is_active` - Boolean flag for active status
- `metadata` - Platform-specific configuration
- `last_verified_at` - Last verification timestamp
- `timestamps` - Created/updated timestamps

**Relationships:**

- `socialAccount` - BelongsTo SocialAccount
- `subscriptions` - HasMany WebhookSubscription
- `webhookEvents` - HasMany WebhookEvent
- `deliveryMetrics` - HasMany WebhookDeliveryMetric

#### 2. `webhook_subscriptions`

Manages webhook subscriptions for different event types.

**Columns:**

- `id` - Primary key
- `webhook_config_id` - Foreign key to webhook_configs
- `platform` - Platform name (facebook, instagram, twitter, linkedin)
- `event_type` - Type of event (page_posts, media_comments, etc.)
- `subscription_id` - Platform-specific subscription ID
- `status` - Subscription status (active, inactive, expired, failed)
- `subscribed_at` - Subscription start time
- `expires_at` - Subscription expiration time
- `subscription_data` - Platform-specific subscription details
- `timestamps` - Created/updated timestamps

**Relationships:**

- `webhookConfig` - BelongsTo WebhookConfig

#### 3. `webhook_events`

Logs all incoming webhook events for debugging and processing.

**Columns:**

- `id` - Primary key
- `social_account_id` - Foreign key to social_accounts
- `webhook_config_id` - Foreign key to webhook_configs
- `platform` - Platform name
- `event_type` - Type of event
- `event_id` - Platform-specific event ID
- `object_type` - Type of object (page, user, post, etc.)
- `object_id` - Platform-specific object ID
- `payload` - Full webhook payload (JSON)
- `signature` - Webhook signature for verification
- `status` - Processing status (pending, processing, processed, failed, ignored)
- `error_message` - Error message if failed
- `retry_count` - Number of retry attempts
- `received_at` - When the webhook was received
- `processed_at` - When processing was completed
- `timestamps` - Created/updated timestamps

**Relationships:**

- `socialAccount` - BelongsTo SocialAccount
- `webhookConfig` - BelongsTo WebhookConfig
- `processing` - HasMany WebhookEventProcessing

#### 4. `webhook_event_processing`

Tracks processing status and retry mechanisms for webhook events.

**Columns:**

- `id` - Primary key
- `webhook_event_id` - Foreign key to webhook_events
- `post_id` - Foreign key to posts (nullable)
- `scheduled_post_id` - Foreign key to scheduled_posts (nullable)
- `post_analytics_id` - Foreign key to post_analytics (nullable)
- `processor_type` - Type of processor (analytics_updater, post_sync, etc.)
- `status` - Processing status (pending, processing, completed, failed)
- `processing_data` - Data extracted for processing (JSON)
- `result` - Processing result (JSON)
- `error_message` - Error message if failed
- `attempt` - Current attempt number
- `next_attempt_at` - When to retry next
- `started_at` - When processing started
- `completed_at` - When processing completed
- `timestamps` - Created/updated timestamps

**Relationships:**

- `webhookEvent` - BelongsTo WebhookEvent
- `post` - BelongsTo Post
- `scheduledPost` - BelongsTo ScheduledPost
- `postAnalytics` - BelongsTo PostAnalytics

#### 5. `webhook_delivery_metrics`

Tracks webhook delivery metrics and performance analytics.

**Columns:**

- `id` - Primary key
- `webhook_config_id` - Foreign key to webhook_configs
- `social_account_id` - Foreign key to social_accounts
- `platform` - Platform name
- `date` - Date for metrics aggregation
- `total_received` - Total webhooks received
- `successfully_processed` - Successfully processed count
- `failed` - Failed processing count
- `ignored` - Ignored events count
- `retry_attempts` - Total retry attempts
- `average_processing_time` - Average processing time in seconds
- `event_type_breakdown` - Count by event type (JSON)
- `timestamps` - Created/updated timestamps

**Relationships:**

- `webhookConfig` - BelongsTo WebhookConfig
- `socialAccount` - BelongsTo SocialAccount

## Platform-Specific Event Types

### Facebook

- `page_posts` - New posts on the page
- `page_comments` - Comments on page posts
- `page_likes` - Likes on page posts
- `page_messages` - Direct messages to the page
- `lead_generation` - Lead generation forms
- `page_updates` - Page information updates

### Instagram

- `media_comments` - Comments on media posts
- `media_mentions` - Mentions in posts/stories
- `story_replies` - Replies to stories
- `business_account_updates` - Business account changes
- `media_insights` - Media performance insights

### X/Twitter

- `tweet_events` - Tweet activities
- `tweet_mentions` - Mention notifications
- `tweet_replies` - Reply notifications
- `tweet_likes` - Like notifications
- `tweet_retweets` - Retweet notifications
- `direct_messages` - Direct message events
- `account_updates` - Account status changes

### LinkedIn

- `person_updates` - Profile updates
- `organization_updates` - Company page updates
- `share_updates` - Post/share activities
- `comment_updates` - Comment activities
- `reaction_updates` - Reaction activities

## Model Relationships

### SocialAccount

```php
socialAccount()->webhookConfigs() - HasMany webhook configurations
socialAccount()->webhookEvents() - HasMany webhook events
socialAccount()->webhookDeliveryMetrics() - HasMany delivery metrics
```

### Post

```php
post()->webhookEventProcessing() - HasMany processing records
post()->relatedWebhookEvents() - Find related webhook events
```

### PostAnalytics

```php
postAnalytics()->webhookEventProcessing() - HasMany processing records
```

### ScheduledPost

```php
scheduledPost()->webhookEventProcessing() - HasMany processing records
```

## Security Features

### Signature Verification

- Each webhook config can have a secret key
- HMAC-SHA256 signature verification
- Automatic secret generation

### Data Encryption

- Sensitive data stored using Laravel's encrypted casts
- Access tokens and refresh tokens are encrypted

### Rate Limiting

- Built-in retry mechanisms with exponential backoff
- Maximum retry limits to prevent infinite loops

## Performance Optimizations

### Database Indexes

- Composite indexes for common query patterns
- Optimized for time-based queries
- Efficient status-based filtering

### Processing Queue

- Asynchronous processing using Laravel queues
- Retry mechanisms with configurable delays
- Batch processing for high-volume scenarios

### Metrics Aggregation

- Daily aggregation of delivery metrics
- Event type breakdowns for analytics
- Performance monitoring capabilities

## Usage Examples

### Creating a Webhook Config

```php
$config = WebhookConfig::create([
    'social_account_id' => $socialAccount->id,
    'webhook_url' => 'https://your-app.com/webhooks/facebook',
    'events' => ['page_posts', 'page_comments', 'page_likes'],
    'is_active' => true,
]);

$secret = $config->generateSecret();
```

### Processing Webhook Events

```php
$event = WebhookEvent::create([
    'social_account_id' => $socialAccount->id,
    'platform' => 'facebook',
    'event_type' => 'page_posts',
    'payload' => $request->all(),
    'signature' => $request->header('X-Hub-Signature-256'),
]);

// Verify signature
if ($config->verifySignature($request->getContent(), $event->signature)) {
    // Process the event
    $event->markAsProcessed();
} else {
    $event->markAsFailed('Invalid signature');
}
```

### Tracking Delivery Metrics

```php
WebhookDeliveryMetric::incrementMetrics(
    $config,
    'page_posts',
    'processed',
    1.5 // processing time in seconds
);
```

## Queue Configuration

Add these queue workers to your `config/horizon.php`:

```php
'environments' => [
    'production' => [
        'supervisor-1' => [
            'connection' => 'redis',
            'queue' => ['webhooks'],
            'balance' => 'auto',
            'processes' => 3,
            'tries' => 3,
        ],
    ],
],
```

## Monitoring and Debugging

### Webhook Event Logs

All webhook events are stored with full payloads for debugging:

- View raw webhook data
- Track processing status
- Monitor retry attempts

### Delivery Metrics Dashboard

Monitor webhook performance:

- Success/failure rates
- Processing times
- Event type breakdowns

### Error Handling

- Comprehensive error logging
- Automatic retry mechanisms
- Failure notifications

## Best Practices

1. **Always verify webhook signatures** before processing events
2. **Use queues for processing** to handle high-volume scenarios
3. **Monitor delivery metrics** to identify issues early
4. **Implement proper error handling** with retry mechanisms
5. **Regular cleanup** of old webhook events and logs
6. **Rate limiting** to prevent abuse
7. **Secure webhook URLs** with HTTPS and authentication

## Testing

Use the provided factories for testing:

```php
// Create webhook config with events
$config = WebhookConfig::factory()->forFacebook()->create();

// Create webhook events
$event = WebhookEvent::factory()
    ->processed()
    ->create(['webhook_config_id' => $config->id]);
```

Run the seeder to populate test data:

```bash
php artisan db:seed --class=WebhookSeeder
```
