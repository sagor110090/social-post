<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Webhook Monitoring Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for webhook monitoring, health checks, metrics collection,
    | alerting, and observability features.
    |
    */

    'enabled' => env('WEBHOOK_MONITORING_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Health Monitoring
    |--------------------------------------------------------------------------
    |
    | Configuration for health checks and system monitoring.
    |
    */
    'health' => [
        'enabled' => env('WEBHOOK_HEALTH_CHECKS_ENABLED', true),
        'check_interval' => env('WEBHOOK_HEALTH_CHECK_INTERVAL', 60), // seconds
        'timeout' => env('WEBHOOK_HEALTH_CHECK_TIMEOUT', 10), // seconds
        'failure_threshold' => env('WEBHOOK_HEALTH_FAILURE_THRESHOLD', 3), // consecutive failures
        
        'checks' => [
            'webhook_endpoints' => [
                'enabled' => true,
                'endpoints' => [
                    'facebook' => '/webhooks/facebook',
                    'instagram' => '/webhooks/instagram',
                    'twitter' => '/webhooks/twitter',
                    'linkedin' => '/webhooks/linkedin',
                ],
            ],
            'queue_health' => [
                'enabled' => true,
                'max_size' => 1000,
                'max_wait_time' => 300, // seconds
            ],
            'database_health' => [
                'enabled' => true,
                'max_connection_time' => 1, // seconds
            ],
            'redis_health' => [
                'enabled' => true,
                'max_connection_time' => 1, // seconds
            ],
            'disk_space' => [
                'enabled' => true,
                'warning_threshold' => 80, // percentage
                'critical_threshold' => 90, // percentage
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Metrics Collection
    |--------------------------------------------------------------------------
    |
    | Configuration for metrics collection and aggregation.
    |
    */
    'metrics' => [
        'enabled' => env('WEBHOOK_METRICS_ENABLED', true),
        'collection_interval' => env('WEBHOOK_METRICS_INTERVAL', 30), // seconds
        'retention_days' => env('WEBHOOK_METRICS_RETENTION_DAYS', 30),
        
        'storage' => [
            'driver' => env('WEBHOOK_METRICS_DRIVER', 'redis'),
            'prefix' => 'webhook_metrics:',
            'ttl' => env('WEBHOOK_METRICS_TTL', 86400 * 30), // 30 days
        ],

        'aggregation' => [
            'intervals' => [
                '1m' => 60,      // 1 minute
                '5m' => 300,     // 5 minutes
                '15m' => 900,    // 15 minutes
                '1h' => 3600,    // 1 hour
                '6h' => 21600,   // 6 hours
                '1d' => 86400,   // 1 day
            ],
        ],

        'types' => [
            'request_volume' => [
                'enabled' => true,
                'dimensions' => ['platform', 'event_type', 'status'],
            ],
            'response_times' => [
                'enabled' => true,
                'percentiles' => [50, 75, 90, 95, 99],
            ],
            'error_rates' => [
                'enabled' => true,
                'dimensions' => ['platform', 'error_type'],
            ],
            'queue_metrics' => [
                'enabled' => true,
                'metrics' => ['size', 'wait_time', 'processing_time'],
            ],
            'security_events' => [
                'enabled' => true,
                'dimensions' => ['platform', 'violation_type'],
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Alerting System
    |--------------------------------------------------------------------------
    |
    | Configuration for alerting and notifications.
    |
    */
    'alerting' => [
        'enabled' => env('WEBHOOK_ALERTING_ENABLED', true),
        'evaluation_interval' => env('WEBHOOK_ALERT_EVALUATION_INTERVAL', 60), // seconds
        
        'channels' => [
            'email' => [
                'enabled' => env('WEBHOOK_ALERT_EMAIL_ENABLED', true),
                'to' => explode(',', env('WEBHOOK_ALERT_EMAIL_TO', '')),
                'from' => env('WEBHOOK_ALERT_EMAIL_FROM', 'alerts@example.com'),
            ],
            'slack' => [
                'enabled' => env('WEBHOOK_ALERT_SLACK_ENABLED', false),
                'webhook_url' => env('WEBHOOK_ALERT_SLACK_WEBHOOK_URL'),
                'channel' => env('WEBHOOK_ALERT_SLACK_CHANNEL', '#alerts'),
                'username' => env('WEBHOOK_ALERT_SLACK_USERNAME', 'Webhook Monitor'),
            ],
            'webhook' => [
                'enabled' => env('WEBHOOK_ALERT_WEBHOOK_ENABLED', false),
                'url' => env('WEBHOOK_ALERT_WEBHOOK_URL'),
                'timeout' => 10,
                'retry_attempts' => 3,
            ],
        ],

        'rules' => [
            'high_error_rate' => [
                'enabled' => true,
                'threshold' => 10, // percentage
                'window' => 300, // seconds
                'severity' => 'warning',
                'cooldown' => 900, // seconds
            ],
            'queue_backlog' => [
                'enabled' => true,
                'threshold' => 500,
                'severity' => 'warning',
                'cooldown' => 600,
            ],
            'endpoint_down' => [
                'enabled' => true,
                'threshold' => 1, // any failure
                'severity' => 'critical',
                'cooldown' => 300,
            ],
            'slow_response_time' => [
                'enabled' => true,
                'threshold' => 5000, // milliseconds
                'percentile' => 95,
                'window' => 300,
                'severity' => 'warning',
                'cooldown' => 900,
            ],
            'security_violations' => [
                'enabled' => true,
                'threshold' => 5, // count
                'window' => 60,
                'severity' => 'critical',
                'cooldown' => 300,
            ],
            'disk_space_critical' => [
                'enabled' => true,
                'threshold' => 90, // percentage
                'severity' => 'critical',
                'cooldown' => 600,
            ],
        ],

        'suppression' => [
            'enabled' => true,
            'default_duration' => 3600, // seconds
            'max_duration' => 86400, // seconds
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Performance Monitoring
    |--------------------------------------------------------------------------
    |
    | Configuration for performance monitoring and profiling.
    |
    */
    'performance' => [
        'enabled' => env('WEBHOOK_PERFORMANCE_MONITORING', true),
        'profiling' => [
            'enabled' => env('WEBHOOK_PROFILING_ENABLED', false),
            'sample_rate' => env('WEBHOOK_PROFILING_SAMPLE_RATE', 0.1), // 10%
            'slow_query_threshold' => 1000, // milliseconds
        ],
        'memory' => [
            'enabled' => true,
            'warning_threshold' => 512, // MB
            'critical_threshold' => 1024, // MB
        ],
        'cpu' => [
            'enabled' => true,
            'warning_threshold' => 80, // percentage
            'critical_threshold' => 95, // percentage
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Distributed Tracing
    |--------------------------------------------------------------------------
    |
    | Configuration for distributed tracing and correlation.
    |
    */
    'tracing' => [
        'enabled' => env('WEBHOOK_TRACING_ENABLED', true),
        'header_name' => 'X-Request-ID',
        'sample_rate' => env('WEBHOOK_TRACING_SAMPLE_RATE', 1.0), // 100%
        'max_spans' => 100,
        'storage' => [
            'driver' => 'redis',
            'prefix' => 'webhook_traces:',
            'ttl' => 86400, // 24 hours
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Dashboard Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for monitoring dashboard and visualization.
    |
    */
    'dashboard' => [
        'enabled' => true,
        'refresh_interval' => 30, // seconds
        'time_ranges' => [
            '1h' => 'Last Hour',
            '6h' => 'Last 6 Hours',
            '24h' => 'Last 24 Hours',
            '7d' => 'Last 7 Days',
            '30d' => 'Last 30 Days',
        ],
        'widgets' => [
            'overview' => [
                'enabled' => true,
                'metrics' => ['request_rate', 'error_rate', 'response_time', 'queue_size'],
            ],
            'platforms' => [
                'enabled' => true,
                'metrics' => ['platform_volume', 'platform_errors', 'platform_response_time'],
            ],
            'security' => [
                'enabled' => true,
                'metrics' => ['security_events', 'violations', 'blocked_requests'],
            ],
            'performance' => [
                'enabled' => true,
                'metrics' => ['response_time_distribution', 'throughput', 'resource_usage'],
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Log Management
    |--------------------------------------------------------------------------
    |
    | Configuration for log management and archival.
    |
    */
    'logs' => [
        'cleanup' => [
            'enabled' => true,
            'schedule' => '0 2 * * *', // Daily at 2 AM
            'retention_days' => [
                'webhook-events' => 30,
                'webhook-processing' => 30,
                'webhook-security' => 90,
                'webhook-performance' => 14,
                'webhook-errors' => 60,
                'webhook-metrics' => 7,
            ],
        ],
        'archival' => [
            'enabled' => env('WEBHOOK_LOG_ARCHIVAL_ENABLED', false),
            'storage' => env('WEBHOOK_LOG_ARCHIVAL_STORAGE', 's3'),
            'compression' => true,
            'encryption' => false,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | External Integrations
    |--------------------------------------------------------------------------
    |
    | Configuration for external monitoring and observability services.
    |
    */
    'integrations' => [
        'datadog' => [
            'enabled' => env('DATADOG_ENABLED', false),
            'api_key' => env('DATADOG_API_KEY'),
            'app_key' => env('DATADOG_APP_KEY'),
            'host' => env('DATADOG_HOST', 'api.datadoghq.com'),
            'tags' => explode(',', env('DATADOG_TAGS', '')),
        ],
        'newrelic' => [
            'enabled' => env('NEWRELIC_ENABLED', false),
            'app_name' => env('NEWRELIC_APP_NAME', 'Webhook Service'),
            'license_key' => env('NEWRELIC_LICENSE_KEY'),
        ],
        'prometheus' => [
            'enabled' => env('PROMETHEUS_ENABLED', false),
            'push_gateway' => env('PROMETHEUS_PUSH_GATEWAY'),
            'metrics_path' => '/metrics/webhooks',
        ],
        'sentry' => [
            'enabled' => env('SENTRY_ENABLED', false),
            'dsn' => env('SENTRY_LARAVEL_DSN'),
            'environment' => env('SENTRY_ENVIRONMENT', 'production'),
            'sample_rate' => env('SENTRY_SAMPLE_RATE', 1.0),
        ],
    ],
];