# Webhook Controllers Documentation

This document explains the webhook controller system for handling incoming events from social media platforms.

## Overview

The webhook system consists of:

1. **Base Webhook Controller** - Common functionality for all platforms
2. **Platform-specific Controllers** - Handle platform-specific logic
3. **Webhook Processing Job** - Async processing of webhook events
4. **Request Validation** - Platform-specific validation
5. **Rate Limiting** - Protection against abuse
6. **Management API** - Configure and monitor webhooks

## Platform Controllers

### Facebook Webhook Controller

**Route:** `/webhooks/facebook`

**Events Handled:**

- Page feed updates (posts, comments, likes, shares)
- Direct messages
- Lead generation
- Postbacks and optins
- Live video updates

**Verification:**

- Uses `X-Hub-Signature-256` header
- Challenge-response with `hub.verify_token`

**Example Payload:**

```json
{
    "object": "page",
    "entry": [
        {
            "id": "123456789",
            "time": 1609459200,
            "changes": [
                {
                    "field": "feed",
                    "value": {
                        "post_id": "post_123",
                        "verb": "add",
                        "item": "status",
                        "like_count": 10,
                        "comment_count": 5
                    }
                }
            ]
        }
    ]
}
```

### Instagram Webhook Controller

**Route:** `/webhooks/instagram`

**Events Handled:**

- Media updates (photos, videos, stories)
- Comments and mentions
- Story insights
- User insights
- Business account updates
- Direct messages

**Verification:**

- Uses `X-Hub-Signature-256` header
- Challenge-response with `hub.verify_token`

**Example Payload:**

```json
{
    "object": "instagram",
    "entry": [
        {
            "id": "123456789",
            "time": 1609459200,
            "changes": [
                {
                    "field": "media",
                    "value": {
                        "media_id": "media_123",
                        "verb": "added",
                        "media_type": "image"
                    }
                }
            ]
        }
    ]
}
```

### Twitter/X Webhook Controller

**Route:** `/webhooks/twitter`

**Events Handled:**

- Tweet creation, deletion, updates
- Direct messages
- Favorites, retweets, quotes
- Follow/unfollow events
- User updates
- List events

**Verification:**

- Uses `X-Twitter-Webhooks-Signature` header for events
- CRC token challenge for setup

**Example Payload:**

```json
{
    "for_user_id": "123456789",
    "tweet_create_events": [
        {
            "id_str": "tweet_123",
            "text": "Hello world!",
            "created_at": "Wed Oct 15 12:00:00 +0000 2025",
            "user": {
                "id_str": "123456789",
                "name": "Test User",
                "screen_name": "testuser"
            },
            "retweet_count": 5,
            "favorite_count": 10
        }
    ]
}
```

### LinkedIn Webhook Controller

**Route:** `/webhooks/linkedin`

**Events Handled:**

- Share updates (posts)
- Comment updates
- Reaction updates
- Person profile updates
- Organization updates
- UGC (User Generated Content) updates

**Verification:**

- Uses `X-LI-Signature` header
- Challenge-response with `challenge_code`

**Example Payload:**

```json
{
    "shareUpdate": {
        "updateType": "CREATED",
        "shareId": "share_123",
        "updateKey": "update_key_123",
        "owner": "owner_123",
        "shareText": "Check out this post!",
        "numLikes": 15,
        "numComments": 3
    }
}
```

## Webhook Processing

### Event Storage

All webhook events are stored in the `webhook_events` table with:

- Platform and event type
- Raw payload
- Signature verification status
- Processing status (pending, processing, processed, failed, ignored)
- Retry count and error messages

### Async Processing

Events are processed asynchronously via the `ProcessWebhookEventJob`:

1. Extracts relevant data from payload
2. Updates or creates `PostAnalytics` records
3. Updates `SocialAccount` information
4. Handles platform-specific business logic
5. Marks event as processed or failed

### Error Handling

- Failed events are retried up to 3 times with exponential backoff
- Detailed error logging for debugging
- Metrics tracking for monitoring

## Configuration

### Webhook Config Model

Each social account can have a `WebhookConfig` with:

- Webhook URL (auto-generated)
- Secret key for signature verification
- Subscribed events
- Platform-specific metadata
- Active/inactive status

### Management API

**Protected endpoints** (require authentication):

- `GET /webhooks/manage/configs` - List configurations
- `POST /webhooks/manage/configs` - Create configuration
- `PUT /webhooks/manage/configs/{id}` - Update configuration
- `DELETE /webhooks/manage/configs/{id}` - Delete configuration
- `POST /webhooks/manage/configs/{id}/regenerate-secret` - Regenerate secret

**Monitoring endpoints:**

- `GET /webhooks/manage/events` - List webhook events
- `GET /webhooks/manage/events/{id}` - Get event details
- `POST /webhooks/manage/events/{id}/retry` - Retry failed event
- `GET /webhooks/manage/metrics` - Delivery metrics
- `GET /webhooks/manage/stats` - Statistics dashboard

## Rate Limiting

Platform-specific rate limits are configured:

- Facebook/Instagram: 100 requests/minute
- Twitter: 60 requests/minute
- LinkedIn: 50 requests/minute
- Default: 30 requests/minute

## Security

### Signature Verification

Each platform uses different signature methods:

- **Facebook/Instagram**: HMAC-SHA256 with `X-Hub-Signature-256`
- **Twitter**: HMAC-SHA256 with `X-Twitter-Webhooks-Signature`
- **LinkedIn**: HMAC-SHA256 with `X-LI-Signature`

### Best Practices

1. Always verify webhook signatures
2. Use HTTPS endpoints
3. Rotate secrets regularly
4. Monitor for unusual activity
5. Implement proper error handling
6. Use rate limiting
7. Log all webhook events

## Testing

### Unit Tests

Run webhook tests:

```bash
php artisan test tests/Feature/Webhooks/WebhookTest.php
```

### Manual Testing

Use the test endpoint:

```bash
curl -X POST http://localhost:8000/webhooks/test \
  -H "Content-Type: application/json" \
  -d '{"test": "data"}'
```

### Health Check

Check webhook system health:

```bash
curl http://localhost:8000/webhooks/health
```

## Setup

### 1. Configure Social Media Apps

Set up developer apps on each platform and get:

- App ID/Client ID
- App Secret/Client Secret
- Webhook verification tokens

### 2. Update Environment

```env
# Facebook/Instagram
FACEBOOK_CLIENT_ID=your_app_id
FACEBOOK_CLIENT_SECRET=your_app_secret

# Twitter
TWITTER_CLIENT_ID=your_client_id
TWITTER_CLIENT_SECRET=your_client_secret

# LinkedIn
LINKEDIN_CLIENT_ID=your_client_id
LINKEDIN_CLIENT_SECRET=your_client_secret
```

### 3. Run Seeder

Create webhook configurations:

```bash
php artisan db:seed --class=WebhookConfigSeeder
```

### 4. Configure Webhook URLs

Set these URLs in your platform developer dashboards:

- Facebook: `https://your-domain.com/webhooks/facebook`
- Instagram: `https://your-domain.com/webhooks/instagram`
- Twitter: `https://your-domain.com/webhooks/twitter`
- LinkedIn: `https://your-domain.com/webhooks/linkedin`

## Troubleshooting

### Common Issues

1. **Signature Verification Failed**
    - Check secret key matches platform configuration
    - Ensure payload is not modified before verification
    - Verify signature header format

2. **Webhook Not Receiving Events**
    - Check webhook URL is accessible
    - Verify SSL certificate is valid
    - Check platform subscription status

3. **Events Not Processing**
    - Check queue worker is running
    - Review job failure logs
    - Verify event data structure

4. **Rate Limiting**
    - Monitor request rates
    - Implement exponential backoff
    - Consider webhook batching

### Debugging

Enable debug logging:

```php
// config/logging.php
'channels' => [
    'webhooks' => [
        'driver' => 'single',
        'path' => storage_path('logs/webhooks.log'),
        'level' => 'debug',
    ],
],
```

Log webhook events:

```php
Log::channel('webhooks')->debug('Webhook received', [
    'platform' => 'facebook',
    'payload' => $request->all(),
]);
```

## Monitoring

### Metrics to Track

- Webhook delivery success rate
- Event processing time
- Error rates by platform
- Queue depth and processing time
- Signature verification failures

### Alerts

Set up alerts for:

- High error rates (>5%)
- Queue backup (>1000 jobs)
- Signature verification failures
- Unusual request patterns

## Performance Optimization

### Queue Configuration

Use dedicated queue for webhooks:

```env
QUEUE_CONNECTION=redis
WEBHOOK_QUEUE=webhooks
```

### Database Optimization

Add indexes for:

- `webhook_events.platform`
- `webhook_events.status`
- `webhook_events.received_at`
- `webhook_delivery_metrics.delivered_at`

### Caching

Cache webhook configurations:

```php
WebhookConfig::remember(3600, function () {
    return WebhookConfig::active()->get();
});
```

## Future Enhancements

1. **Webhook Batching** - Process multiple events together
2. **Event Replay** - Replay events for debugging
3. **Custom Event Handlers** - Plugin system for custom logic
4. **Real-time Dashboard** - Live webhook monitoring
5. **Event Filtering** - Client-side event filtering
6. **Multi-tenant Support** - Isolate webhook data by organization
