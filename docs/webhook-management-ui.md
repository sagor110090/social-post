# Webhook Management UI

A comprehensive webhook management interface for the Laravel Inertia.js application that allows users to configure, monitor, and manage webhook integrations with social media platforms.

## Features

### 🎯 Core Functionality

- **Webhook Configuration Management**: Create, edit, enable/disable webhook configurations
- **Real-time Event Monitoring**: View and filter webhook events with live updates
- **Analytics Dashboard**: Track delivery metrics, success rates, and performance trends
- **Security Management**: Monitor security events and configure security settings
- **Multi-Platform Support**: Facebook, Instagram, Twitter, and LinkedIn integrations

### 📊 Analytics & Monitoring

- **Delivery Metrics**: Track successful deliveries, failures, and pending events
- **Performance Charts**: Visualize webhook performance over time
- **Platform Statistics**: Compare performance across different social platforms
- **Real-time Updates**: Auto-refreshing event logs and metrics
- **Export Capabilities**: Export analytics and event data as CSV

### 🔒 Security Features

- **Signature Verification**: Ensure webhook authenticity
- **IP Whitelisting**: Restrict access to specific IP addresses
- **Rate Limiting**: Prevent abuse with configurable rate limits
- **Security Event Logging**: Track and resolve security violations
- **Configurable Timeouts**: Set webhook response timeouts

## Architecture

### Frontend Components

#### Vue Components

- `WebhookStatusCard`: Display webhook configuration status and actions
- `EventListItem`: Show individual webhook events with retry functionality
- `PlatformIcon`: Platform-specific icons (Facebook, Instagram, Twitter, LinkedIn)
- `EventFilter`: Advanced filtering controls for event logs
- `MetricsChart`: Interactive charts for analytics visualization
- `WebhookForm`: Configuration form for creating/editing webhooks
- `SecurityLog`: Display security events and violations

#### Pages

- `Index.vue`: Overview dashboard with quick stats and navigation
- `Configs.vue`: Webhook configuration management interface
- `Events.vue`: Event monitoring and filtering interface
- `Analytics.vue`: Performance metrics and charts
- `Security.vue`: Security settings and event monitoring

### Backend Integration

#### Controllers

- `WebhookManageController`: Main controller for UI operations
- `SocialAccountController`: Manage connected social accounts
- Existing webhook controllers for platform-specific handling

#### Routes

- Web UI routes under `/settings/webhooks/*`
- API endpoints for data fetching and management
- Integration with existing webhook management routes

## Installation & Setup

### 1. Backend Setup

The webhook management system integrates with the existing webhook infrastructure. Ensure you have:

1. **Webhook Models**: All webhook-related models should be migrated

    ```bash
    php artisan migrate
    ```

2. **Social Accounts**: Users need connected social accounts

    ```bash
    php artisan db:seed --class=SocialAccountSeeder
    ```

3. **Routes**: The routes are automatically included in `routes/settings.php`

### 2. Frontend Setup

The Vue components are already integrated into the existing Inertia.js application. No additional setup is required.

### 3. Navigation

The webhook management is accessible through:

- Settings → Webhooks (main entry point)
- Direct URLs:
    - `/settings/webhooks` - Overview dashboard
    - `/settings/webhooks/configs` - Configuration management
    - `/settings/webhooks/events` - Event monitoring
    - `/settings/webhooks/analytics` - Performance analytics
    - `/settings/webhooks/security` - Security settings

## Usage Guide

### Creating Webhook Configurations

1. Navigate to **Settings → Webhooks → Configurations**
2. Click **Add Configuration**
3. Select a social account from the dropdown
4. Choose events to subscribe to:
    - Feed Updates
    - Messages
    - Comments
    - Likes
    - Mentions
    - Follows
5. Generate a webhook secret or provide your own
6. Enable the webhook and save

### Monitoring Events

1. Go to **Settings → Webhooks → Event Logs**
2. Use filters to narrow down events:
    - Platform (Facebook, Instagram, etc.)
    - Status (Pending, Processing, Processed, Failed)
    - Date range
    - Search terms
3. Click on events to view detailed payloads
4. Retry failed events directly from the interface

### Viewing Analytics

1. Access **Settings → Webhooks → Analytics**
2. Select time range (7 days, 30 days, 90 days)
3. Filter by platform if needed
4. View:
    - Delivery metrics over time
    - Success rates and response times
    - Platform-specific performance
    - Recent delivery attempts

### Managing Security

1. Navigate to **Settings → Webhooks → Security**
2. Configure security settings:
    - Enable/disable signature verification
    - Set up rate limiting
    - Configure IP whitelisting
    - Set webhook timeouts
3. Monitor security events and resolve violations

## API Endpoints

### Configuration Management

- `GET /webhooks/manage/configs` - List webhook configurations
- `POST /webhooks/manage/configs` - Create new configuration
- `PUT /webhooks/manage/configs/{id}` - Update configuration
- `DELETE /webhooks/manage/configs/{id}` - Delete configuration
- `POST /webhooks/manage/configs/{id}/regenerate-secret` - Regenerate secret

### Event Management

- `GET /webhooks/manage/events` - List webhook events
- `GET /webhooks/manage/events/{id}` - Get event details
- `POST /webhooks/manage/events/{id}/retry` - Retry failed event

### Analytics

- `GET /webhooks/manage/analytics` - Get analytics data
- `GET /webhooks/manage/analytics/export` - Export analytics
- `GET /webhooks/manage/stats` - Get statistics

### Security

- `GET /webhooks/manage/security/events` - Get security events
- `GET /webhooks/manage/security/settings` - Get security settings
- `PUT /webhooks/manage/security/settings` - Update security settings

## Customization

### Adding New Platforms

1. Update `PlatformIcon.vue` with new platform icons
2. Add platform-specific events in `WebhookForm.vue`
3. Update platform filters and statistics
4. Add platform handling in backend controllers

### Extending Analytics

1. Add new metrics to `WebhookManageController::getAnalytics()`
2. Update `MetricsChart.vue` for new chart types
3. Add new filters and export options

### Security Enhancements

1. Implement additional security event types
2. Add more granular permission controls
3. Integrate with external security monitoring tools

## Troubleshooting

### Common Issues

1. **Webhook events not showing**
    - Check if webhook configurations are active
    - Verify social account connections
    - Check webhook URL accessibility

2. **High failure rates**
    - Review webhook endpoint response times
    - Check signature verification
    - Verify rate limiting settings

3. **Security events**
    - Review IP whitelist configuration
    - Check rate limit settings
    - Verify webhook secret configuration

### Debug Mode

Enable debug mode by adding to `.env`:

```env
WEBHOOK_DEBUG=true
```

This will provide additional logging and error details.

## Performance Considerations

### Database Optimization

- Add indexes on frequently queried columns
- Implement pagination for large event datasets
- Consider archiving old events

### Frontend Optimization

- Implement virtual scrolling for large event lists
- Use debounced search and filtering
- Cache analytics data where appropriate

### Security Best Practices

- Regularly rotate webhook secrets
- Monitor for unusual activity patterns
- Implement proper access controls
- Use HTTPS for all webhook endpoints

## Contributing

When contributing to the webhook management system:

1. Follow the existing Vue 3 Composition API patterns
2. Maintain TypeScript type safety
3. Add proper error handling and loading states
4. Include comprehensive testing
5. Update documentation for new features

## License

This webhook management system is part of the larger application and follows the same licensing terms.
