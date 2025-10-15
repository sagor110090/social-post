<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Webhook Security Configuration
    |--------------------------------------------------------------------------
    |
    | This file contains configuration for webhook security including rate limits,
    | IP whitelisting, signature verification, and other security measures.
    |
    */

    'security' => [
        /*
        |--------------------------------------------------------------------------
        | Signature Verification
        |--------------------------------------------------------------------------
        |
        | Configuration for webhook signature verification across different platforms.
        |
        */
        'signature' => [
            'tolerance' => env('WEBHOOK_SIGNATURE_TOLERANCE', 300), // 5 minutes
            'algorithms' => [
                'facebook' => 'sha256',
                'instagram' => 'sha256',
                'twitter' => 'sha256',
                'linkedin' => 'sha256',
            ],
            'headers' => [
                'facebook' => 'X-Hub-Signature-256',
                'instagram' => 'X-Hub-Signature-256',
                'twitter' => 'X-Twitter-Webhooks-Signature',
                'linkedin' => 'X-LI-Signature',
            ],
        ],

        /*
        |--------------------------------------------------------------------------
        | Rate Limiting
        |--------------------------------------------------------------------------
        |
        | Platform-specific rate limits for webhook endpoints.
        |
        */
        'rate_limits' => [
            'facebook' => [
                'requests_per_minute' => 100,
                'requests_per_hour' => 1000,
                'burst_limit' => 150,
            ],
            'instagram' => [
                'requests_per_minute' => 100,
                'requests_per_hour' => 1000,
                'burst_limit' => 150,
            ],
            'twitter' => [
                'requests_per_minute' => 30,
                'requests_per_hour' => 300,
                'burst_limit' => 50,
            ],
            'linkedin' => [
                'requests_per_minute' => 60,
                'requests_per_hour' => 600,
                'burst_limit' => 100,
            ],
            'default' => [
                'requests_per_minute' => 60,
                'requests_per_hour' => 600,
                'burst_limit' => 100,
            ],
        ],

        /*
        |--------------------------------------------------------------------------
        | IP Whitelisting
        |--------------------------------------------------------------------------
        |
        | Official IP ranges for social media platforms. These are updated
        | periodically and can be refreshed automatically.
        |
        */
        'ip_whitelist' => [
            'enabled' => env('WEBHOOK_IP_WHITELIST_ENABLED', true),
            'strict_mode' => env('WEBHOOK_IP_WHITELIST_STRICT', false), // Block if not in whitelist
            'auto_update' => env('WEBHOOK_IP_AUTO_UPDATE', true),
            'update_interval' => env('WEBHOOK_IP_UPDATE_INTERVAL', 86400), // 24 hours
            
            'platforms' => [
                'facebook' => [
                    'ranges' => [
                        '31.13.0.0/16',
                        '45.64.40.0/22',
                        '66.220.144.0/20',
                        '69.63.176.0/20',
                        '69.171.224.0/19',
                        '74.119.76.0/22',
                        '103.4.96.0/22',
                        '157.240.0.0/16',
                        '173.252.64.0/18',
                        '179.60.192.0/22',
                        '185.60.216.0/22',
                        '204.15.20.0/22',
                    ],
                    'last_updated' => null,
                ],
                'instagram' => [
                    'ranges' => [
                        // Instagram uses Facebook's infrastructure
                        '31.13.0.0/16',
                        '45.64.40.0/22',
                        '66.220.144.0/20',
                        '69.63.176.0/20',
                        '69.171.224.0/19',
                        '74.119.76.0/22',
                        '103.4.96.0/22',
                        '157.240.0.0/16',
                        '173.252.64.0/18',
                        '179.60.192.0/22',
                        '185.60.216.0/22',
                        '204.15.20.0/22',
                    ],
                    'last_updated' => null,
                ],
                'twitter' => [
                    'ranges' => [
                        '104.244.42.0/24',
                        '192.133.76.0/22',
                        '199.16.156.0/22',
                        '199.59.148.0/22',
                        '199.59.149.0/24',
                        '199.59.150.0/24',
                        '199.59.151.0/24',
                        '202.160.128.0/22',
                        '209.237.192.0/19',
                        '209.237.224.0/19',
                    ],
                    'last_updated' => null,
                ],
                'linkedin' => [
                    'ranges' => [
                        '108.174.0.0/16',
                        '108.174.10.0/24',
                        '108.174.11.0/24',
                        '108.174.12.0/24',
                        '108.174.13.0/24',
                        '108.174.14.0/24',
                        '108.174.15.0/24',
                        '108.174.2.0/24',
                        '108.174.3.0/24',
                        '108.174.4.0/24',
                        '108.174.5.0/24',
                        '108.174.6.0/24',
                        '108.174.7.0/24',
                        '108.174.8.0/24',
                        '108.174.9.0/24',
                    ],
                    'last_updated' => null,
                ],
            ],
        ],

        /*
        |--------------------------------------------------------------------------
        | Request Validation
        |--------------------------------------------------------------------------
        |
        | Validation rules for incoming webhook requests.
        |
        */
        'validation' => [
            'max_payload_size' => env('WEBHOOK_MAX_PAYLOAD_SIZE', 1024 * 1024), // 1MB
            'allowed_content_types' => [
                'application/json',
                'application/x-www-form-urlencoded',
            ],
            'required_headers' => [
                'User-Agent',
                'Content-Type',
            ],
            'timeout' => env('WEBHOOK_REQUEST_TIMEOUT', 30), // seconds
        ],

        /*
        |--------------------------------------------------------------------------
        | Security Headers
        |--------------------------------------------------------------------------
        |
        | Security headers to add to webhook responses.
        |
        */
        'security_headers' => [
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'DENY',
            'X-XSS-Protection' => '1; mode=block',
            'Strict-Transport-Security' => 'max-age=31536000; includeSubDomains',
            'Content-Security-Policy' => "default-src 'none'",
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
        ],

        /*
        |--------------------------------------------------------------------------
        | Logging & Monitoring
        |--------------------------------------------------------------------------
        |
        | Configuration for security logging and monitoring.
        |
        */
        'logging' => [
            'enabled' => env('WEBHOOK_SECURITY_LOGGING', true),
            'log_level' => env('WEBHOOK_SECURITY_LOG_LEVEL', 'info'),
            'log_all_requests' => env('WEBHOOK_LOG_ALL_REQUESTS', false),
            'log_failed_requests' => true,
            'log_rate_limit_violations' => true,
            'log_signature_failures' => true,
            'log_ip_violations' => true,
            'retention_days' => env('WEBHOOK_LOG_RETENTION_DAYS', 30),
        ],

        /*
        |--------------------------------------------------------------------------
        | Replay Attack Prevention
        |--------------------------------------------------------------------------
        |
        | Settings to prevent replay attacks on webhook endpoints.
        |
        */
        'replay_protection' => [
            'enabled' => env('WEBHOOK_REPLAY_PROTECTION', true),
            'window' => env('WEBHOOK_REPLAY_WINDOW', 300), // 5 minutes
            'cache_prefix' => 'webhook_replay:',
            'cleanup_interval' => 3600, // 1 hour
        ],

        /*
        |--------------------------------------------------------------------------
        | Alerting
        |--------------------------------------------------------------------------
        |
        | Configuration for security alerts and notifications.
        |
        */
        'alerting' => [
            'enabled' => env('WEBHOOK_SECURITY_ALERTS', false),
            'channels' => ['slack', 'email'],
            'thresholds' => [
                'signature_failures_per_minute' => 10,
                'rate_limit_violations_per_minute' => 50,
                'ip_violations_per_minute' => 20,
                'payload_size_violations_per_hour' => 5,
            ],
        ],

        /*
        |--------------------------------------------------------------------------
        | CORS Configuration
        |--------------------------------------------------------------------------
        |
        | CORS settings for webhook management endpoints.
        |
        */
        'cors' => [
            'allowed_origins' => env('WEBHOOK_CORS_ALLOWED_ORIGINS', '*'),
            'allowed_methods' => ['GET', 'POST', 'PUT', 'DELETE', 'OPTIONS'],
            'allowed_headers' => ['Content-Type', 'Authorization', 'X-Requested-With'],
            'exposed_headers' => ['X-RateLimit-Limit', 'X-RateLimit-Remaining', 'X-RateLimit-Reset'],
            'max_age' => 86400,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Performance Configuration
    |--------------------------------------------------------------------------
    |
    | Performance-related settings for webhook processing.
    |
    */
    'performance' => [
        'cache_driver' => env('WEBHOOK_CACHE_DRIVER', 'redis'),
        'queue_connection' => env('WEBHOOK_QUEUE_CONNECTION', 'redis'),
        'batch_size' => env('WEBHOOK_BATCH_SIZE', 100),
        'concurrent_jobs' => env('WEBHOOK_CONCURRENT_JOBS', 10),
    ],

    /*
    |--------------------------------------------------------------------------
    | Webhook Event Processing Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for webhook event processing across different platforms.
    |
    */

    'processing' => [
        // Queue name for webhook processing jobs
        'queue' => 'webhooks',

        // Maximum retry attempts for failed webhook events
        'max_retries' => 5,

        // Backoff strategy for retries (in seconds)
        'retry_backoff' => [5, 15, 30, 60, 120],

        // Batch processing settings
        'batch_size' => 100,

        // Cleanup settings for old processed events
        'cleanup_days' => 30,

        // Duplicate event prevention (in hours)
        'duplicate_prevention_hours' => 24,
    ],

    'platforms' => [
        'facebook' => [
            'enabled' => true,
            'supported_events' => [
                'post_created', 'post_updated', 'post_deleted',
                'comment_created', 'comment_updated', 'comment_deleted',
                'message_received', 'message_sent', 'message_read',
                'lead_generated', 'user_followed', 'user_unfollowed',
                'story_created', 'story_updated', 'story_deleted',
                'account_updated', 'account_verified', 'account_suspended',
            ],
            'engagement_thresholds' => [
                'viral_min_engagement_rate' => 10,
                'viral_min_reach' => 1000,
                'high_engagement_rate' => 5,
                'high_engagement_reach' => 500,
            ],
            'milestones' => [100, 500, 1000, 5000, 10000, 50000, 100000],
        ],

        'instagram' => [
            'enabled' => true,
            'supported_events' => [
                'media_created', 'media_updated', 'media_deleted',
                'comment_created', 'comment_updated', 'comment_deleted',
                'message_received', 'message_sent', 'message_read',
                'user_followed', 'user_unfollowed', 'user_updated',
                'story_created', 'story_updated', 'story_deleted',
                'account_updated', 'account_verified', 'account_suspended',
            ],
            'engagement_thresholds' => [
                'viral_min_engagement_rate' => 5,
                'viral_min_reach' => 5000,
                'high_engagement_rate' => 3,
                'high_engagement_reach' => 1000,
            ],
            'milestones' => [50, 100, 500, 1000, 5000, 10000, 50000, 100000],
        ],

        'twitter' => [
            'enabled' => true,
            'supported_events' => [
                'tweet_created', 'tweet_deleted', 'tweet_updated',
                'comment_created', 'comment_updated', 'comment_deleted',
                'message_received', 'message_sent', 'message_read',
                'user_followed', 'user_unfollowed', 'user_updated',
                'account_updated', 'account_verified', 'account_suspended',
            ],
            'engagement_thresholds' => [
                'viral_min_engagement_rate' => 3,
                'viral_min_impressions' => 10000,
                'high_engagement_rate' => 2,
                'high_engagement_impressions' => 5000,
            ],
            'milestones' => [100, 500, 1000, 5000, 10000, 50000, 100000],
        ],

        'linkedin' => [
            'enabled' => true,
            'supported_events' => [
                'share_created', 'share_updated', 'share_deleted',
                'comment_created', 'comment_updated', 'comment_deleted',
                'message_received', 'message_sent', 'message_read',
                'user_followed', 'user_unfollowed', 'user_updated',
                'lead_generated', 'account_updated', 'account_verified',
                'account_suspended',
            ],
            'engagement_thresholds' => [
                'viral_min_engagement_rate' => 2,
                'viral_min_impressions' => 5000,
                'high_engagement_rate' => 1,
                'high_engagement_impressions' => 1000,
            ],
            'milestones' => [50, 100, 500, 1000, 5000, 10000, 25000, 50000],
        ],
    ],

    'notifications' => [
        // Enable/disable notifications
        'enabled' => true,

        // Rate limiting for notifications (in minutes)
        'rate_limit_minutes' => 30,

        // Notification types
        'types' => [
            'engagement_milestones' => [
                'enabled' => true,
                'channels' => ['database', 'email'],
            ],
            'viral_content' => [
                'enabled' => true,
                'channels' => ['database', 'email', 'slack'],
            ],
            'negative_sentiment' => [
                'enabled' => true,
                'channels' => ['database', 'email'],
            ],
            'lead_generated' => [
                'enabled' => true,
                'channels' => ['database', 'email', 'slack'],
            ],
            'account_status_changed' => [
                'enabled' => true,
                'channels' => ['database', 'email'],
            ],
            'critical_alerts' => [
                'enabled' => true,
                'channels' => ['database', 'email', 'slack'],
            ],
            'urgent_message' => [
                'enabled' => true,
                'channels' => ['database', 'email'],
            ],
            'new_follower' => [
                'enabled' => false, // Can be noisy
                'channels' => ['database'],
            ],
            'mention' => [
                'enabled' => true,
                'channels' => ['database'],
            ],
        ],
    ],

    'analytics' => [
        // Cache duration for aggregated analytics (in minutes)
        'cache_duration' => 15,

        // Trend analysis periods (in hours)
        'trend_periods' => [
            'hourly' => 48, // Keep 48 hours of hourly data
            'daily' => 30,  // Keep 30 days of daily data
            'weekly' => 12, // Keep 12 weeks of weekly data
        ],

        // Growth calculation periods
        'growth_periods' => [
            '1h' => '1 hour',
            '24h' => '1 day',
            '7d' => '1 week',
            '30d' => '1 month',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Monitoring & Observability
    |--------------------------------------------------------------------------
    |
    | Configuration for webhook monitoring, logging, metrics, and alerting.
    | This section integrates with the main monitoring configuration.
    |
    */
    'monitoring' => [
        'enabled' => env('WEBHOOK_MONITORING_ENABLED', true),
        
        'logging' => [
            'structured' => env('WEBHOOK_STRUCTURED_LOGGING', true),
            'correlation_ids' => env('WEBHOOK_CORRELATION_IDS', true),
            'performance_logging' => env('WEBHOOK_PERFORMANCE_LOGGING', true),
            'context_enrichment' => env('WEBHOOK_CONTEXT_ENRICHMENT', true),
        ],
        
        'metrics' => [
            'collection' => env('WEBHOOK_METRICS_COLLECTION', true),
            'real_time' => env('WEBHOOK_REAL_TIME_METRICS', true),
            'aggregation' => env('WEBHOOK_METRICS_AGGREGATION', true),
            'retention_days' => env('WEBHOOK_METRICS_RETENTION_DAYS', 30),
        ],
        
        'tracing' => [
            'enabled' => env('WEBHOOK_DISTRIBUTED_TRACING', true),
            'sample_rate' => env('WEBHOOK_TRACING_SAMPLE_RATE', 1.0),
            'max_spans' => env('WEBHOOK_MAX_SPANS', 100),
        ],
        
        'alerting' => [
            'enabled' => env('WEBHOOK_ALERTING_ENABLED', true),
            'real_time' => env('WEBHOOK_REAL_TIME_ALERTS', true),
            'channels' => explode(',', env('WEBHOOK_ALERT_CHANNELS', 'email,slack')),
        ],
        
        'health_checks' => [
            'enabled' => env('WEBHOOK_HEALTH_CHECKS', true),
            'interval' => env('WEBHOOK_HEALTH_CHECK_INTERVAL', 60),
            'timeout' => env('WEBHOOK_HEALTH_CHECK_TIMEOUT', 10),
        ],
        
        'performance' => [
            'monitoring' => env('WEBHOOK_PERFORMANCE_MONITORING', true),
            'profiling' => env('WEBHOOK_PROFILING', false),
            'slow_query_threshold' => env('WEBHOOK_SLOW_QUERY_THRESHOLD', 1000),
        ],
    ],
];