# Webhook Monitoring & Observability System

This document describes the comprehensive webhook monitoring and observability system implemented for the social media webhook application.

## Overview

The monitoring system provides full observability into webhook processing, including:

- **Structured Logging**: JSON-formatted logs with correlation IDs and context
- **Real-time Metrics**: Collection and aggregation of performance metrics
- **Health Monitoring**: Continuous health checks for all system components
- **Alerting**: Multi-channel alerting for critical issues
- **Distributed Tracing**: Request correlation across services
- **Dashboard Integration**: Real-time monitoring dashboard

## Architecture

### Core Components

1. **WebhookLoggingService**: Centralized logging with structured format
2. **WebhookMonitoringService**: Health checks and system monitoring
3. **WebhookMetricsService**: Metrics collection and aggregation
4. **WebhookAlertingService**: Alert rule evaluation and notifications

### Supporting Components

1. **Middleware**: Request logging and performance monitoring
2. **Jobs**: Background processing for metrics, health checks, and alerts
3. **Commands**: CLI tools for management and debugging
4. **Controllers**: API endpoints for monitoring data

## Configuration

### Environment Variables

See `.env.monitoring.example` for all available configuration options.

Key variables:

```bash
# Enable monitoring
WEBHOOK_MONITORING_ENABLED=true

# Logging
WEBHOOK_STRUCTURED_LOGGING=true
WEBHOOK_CORRELATION_IDS=true

# Metrics
WEBHOOK_METRICS_ENABLED=true
WEBHOOK_REAL_TIME_METRICS=true

# Alerting
WEBHOOK_ALERTING_ENABLED=true
WEBHOOK_ALERT_CHANNELS=email,slack
```

### Configuration Files

- `config/monitoring.php`: Main monitoring configuration
- `config/logging.php`: Log channel configuration
- `config/webhooks.php`: Webhook-specific monitoring settings

## Logging System

### Log Channels

The system uses dedicated log channels for different aspects:

- `webhook-events`: Incoming webhook events
- `webhook-processing`: Event processing activities
- `webhook-security`: Security-related events
- `webhook-performance`: Performance metrics
- `webhook-errors`: Error tracking
- `webhook-metrics`: Metrics collection logs

### Structured Format

All logs are formatted as JSON with consistent structure:

```json
{
    "timestamp": "2025-01-15T10:30:45.123456+00:00",
    "level": "INFO",
    "message": "Webhook event received",
    "channel": "webhook-events",
    "context": {
        "platform": "facebook",
        "event_type": "post_created"
    },
    "correlation": {
        "request_id": "uuid-here",
        "trace_id": "trace-uuid"
    },
    "performance": {
        "memory_usage": 52428800,
        "execution_time": 45.67
    }
}
```

### Log Management

Commands for log management:

```bash
# View recent logs
php artisan webhook:logs view --channel=webhook-events --lines=100

# Search logs
php artisan webhook:logs search --pattern="error" --channel=webhook-errors

# Clean up old logs
php artisan webhook:logs cleanup

# Archive logs
php artisan webhook:logs archive
```

## Metrics System

### Metric Types

1. **Request Volume**: Counter for incoming requests
2. **Response Times**: Histogram with percentiles
3. **Error Rates**: Counters by error type
4. **Queue Metrics**: Queue size and processing times
5. **Security Events**: Security violations and events

### Collection and Storage

- Real-time collection via Redis
- Aggregation at multiple intervals (1m, 5m, 15m, 1h, 6h, 1d)
- Configurable retention periods
- Automatic cleanup of old data

### Commands

```bash
# Show metrics
php artisan webhook:metrics show --type=request_volume --interval=1h

# Collect system metrics
php artisan webhook:metrics collect

# Clean up old metrics
php artisan webhook:metrics cleanup

# Generate dashboard data
php artisan webhook:metrics dashboard --interval=24h
```

## Health Monitoring

### Health Checks

The system monitors:

1. **Webhook Endpoints**: HTTP health checks for all platforms
2. **Queue Health**: Queue size and wait times
3. **Database Health**: Connection performance
4. **Redis Health**: Connection and memory usage
5. **Disk Space**: Available storage

### Configuration

```php
'health' => [
    'enabled' => true,
    'check_interval' => 60, // seconds
    'timeout' => 10, // seconds
    'failure_threshold' => 3,
],
```

### Commands

```bash
# Run all health checks
php artisan webhook:health

# Run specific check
php artisan webhook:health --check=webhook_endpoints

# Get JSON output
php artisan webhook:health --json

# Send alerts on failures
php artisan webhook:health --notify
```

## Alerting System

### Alert Rules

Pre-configured alert rules:

1. **High Error Rate**: >10% error rate in 5 minutes
2. **Queue Backlog**: >500 jobs in queue
3. **Endpoint Down**: Any endpoint health check failure
4. **Slow Response Time**: 95th percentile >5 seconds
5. **Security Violations**: >5 violations in 1 minute
6. **Disk Space Critical**: >90% disk usage

### Alert Channels

1. **Email**: SMTP notifications
2. **Slack**: Webhook integration
3. **Webhook**: Custom webhook endpoints

### Alert Management

```bash
# Evaluate alert rules
php artisan webhook:alerts evaluate

# Trigger manual alert
php artisan webhook:alerts trigger --rule="custom_rule" --message="Manual test alert"

# List alert configuration
php artisan webhook:alerts list

# Suppress alerts
php artisan webhook:alerts suppress --rule="high_error_rate" --duration=3600

# Clear suppression
php artisan webhook:alerts clear --rule="high_error_rate"
```

## API Endpoints

### Monitoring API

```bash
# Get dashboard data
GET /monitoring/dashboard?time_range=24h

# Get health status
GET /monitoring/health
GET /monitoring/health/webhook_endpoints

# Get metrics
GET /monitoring/metrics?type=request_volume&interval=1h&platform=facebook

# Get system metrics
GET /monitoring/metrics/system

# Manage alerts
GET /monitoring/alerts
POST /monitoring/alerts/evaluate
POST /monitoring/alerts/trigger
```

### Logs API

```bash
# Get logs
GET /monitoring/logs?channel=webhook-events&lines=100&search=error

# Search logs
GET /monitoring/logs/search?query=facebook&limit=50

# Get log statistics
GET /monitoring/logs/stats?channel=webhook-events&since=24h

# Get available channels
GET /monitoring/logs/channels

# Download logs
GET /monitoring/logs/download?channel=webhook-events
```

## Middleware Integration

### Request Logging Middleware

Automatically logs all webhook requests with:

- Request metadata (method, URL, headers)
- Platform detection
- Event type extraction
- Correlation IDs
- Performance metrics

### Performance Monitoring Middleware

Tracks performance for all monitored routes:

- Execution time
- Memory usage
- Database query count
- Performance issue detection

## Scheduled Tasks

The system includes automated tasks:

```php
// Health checks - every minute
Schedule::job(new CheckWebhookHealthJob())->everyMinute();

// Log cleanup - daily at 2 AM
Schedule::job(new CleanupWebhookLogsJob())->dailyAt('02:00');

// Metrics cleanup - daily at 3 AM
Schedule::command('webhook:metrics cleanup')->dailyAt('03:00');

// Alert evaluation - every minute
Schedule::command('webhook:alerts evaluate')->everyMinute();

// System metrics - every 5 minutes
Schedule::command('webhook:metrics collect')->everyFiveMinutes();
```

## External Integrations

### Supported Services

1. **Datadog**: Metrics and log forwarding
2. **New Relic**: APM and error tracking
3. **Prometheus**: Metrics exposition
4. **Sentry**: Error tracking and performance
5. **Custom Webhooks**: Flexible webhook notifications

### Configuration Example

```php
'integrations' => [
    'datadog' => [
        'enabled' => env('DATADOG_ENABLED', false),
        'api_key' => env('DATADOG_API_KEY'),
        'app_key' => env('DATADOG_APP_KEY'),
    ],
    'sentry' => [
        'enabled' => env('SENTRY_ENABLED', false),
        'dsn' => env('SENTRY_LARAVEL_DSN'),
    ],
],
```

## Performance Considerations

### Optimization Tips

1. **Sampling**: Use sampling for high-volume metrics
2. **Retention**: Configure appropriate retention periods
3. **Aggregation**: Use appropriate aggregation intervals
4. **Filtering**: Filter logs to reduce volume
5. **Async Processing**: Use queues for all background tasks

### Resource Usage

- **Memory**: ~50-100MB for metrics storage
- **CPU**: Minimal impact with proper configuration
- **Storage**: Depends on log volume and retention
- **Network**: Minimal for local monitoring

## Troubleshooting

### Common Issues

1. **High Memory Usage**: Check metric retention settings
2. **Slow Queries**: Review database health checks
3. **Missing Logs**: Verify log channel configuration
4. **Alert Fatigue**: Adjust thresholds and suppression

### Debug Commands

```bash
# Check system status
php artisan webhook:health --json

# View recent errors
php artisan webhook:logs view --channel=webhook-errors --lines=50

# Test alert system
php artisan webhook:alerts trigger --rule=test --message="Test alert"

# Check metrics collection
php artisan webhook:metrics show --type=system_metrics
```

## Security Considerations

### Data Protection

1. **Sensitive Data**: Automatically redacted in logs
2. **Access Control**: Restrict monitoring endpoints
3. **Encryption**: Optional log archival encryption
4. **Retention**: Comply with data retention policies

### Access Control

```php
// Protect monitoring routes
Route::middleware(['auth', 'admin'])->prefix('monitoring')->group(function () {
    // Monitoring routes
});
```

## Best Practices

### Implementation

1. **Start Simple**: Enable basic logging first
2. **Gradual Rollout**: Add features incrementally
3. **Monitor the Monitor**: Track monitoring system performance
4. **Regular Review**: Update thresholds and rules
5. **Documentation**: Keep configuration documented

### Operations

1. **Regular Health Checks**: Monitor the monitoring system
2. **Log Rotation**: Prevent disk space issues
3. **Alert Tuning**: Reduce false positives
4. **Capacity Planning**: Scale with application growth
5. **Backup**: Critical for log and metric data

## Future Enhancements

### Planned Features

1. **Machine Learning**: Anomaly detection
2. **Advanced Dashboards**: Custom visualizations
3. **Distributed Tracing**: Full request tracing
4. **Auto-Remediation**: Automated issue resolution
5. **Predictive Analytics**: Capacity planning

### Extensibility

The system is designed for easy extension:

- Custom metric types
- Additional alert channels
- New health checks
- Custom log formatters
- External integrations

## Support and Maintenance

### Regular Tasks

1. **Daily**: Review alerts and metrics
2. **Weekly**: Check log volumes and performance
3. **Monthly**: Review and update configurations
4. **Quarterly**: Audit retention and compliance

### Emergency Procedures

1. **High Error Rate**: Check system health and recent deployments
2. **Queue Backlog**: Verify queue workers and processing
3. **Disk Space**: Clean up logs and check retention
4. **Alert Storm**: Suppress non-critical alerts

This comprehensive monitoring system provides full observability into the webhook application, enabling proactive issue detection, rapid troubleshooting, and continuous performance optimization.
