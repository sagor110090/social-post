# Webhook Security System

This document describes the comprehensive security system implemented for webhook endpoints in the social media management application.

## Overview

The webhook security system provides multiple layers of protection to ensure that incoming webhook requests are authentic, authorized, and safe to process. The system includes:

1. **Signature Verification** - Cryptographic verification of webhook authenticity
2. **Rate Limiting** - Prevention of abuse and DoS attacks
3. **IP Whitelisting** - Restriction to known platform IP ranges
4. **Request Validation** - Validation of payload structure and content
5. **Security Headers** - HTTP security headers for responses
6. **Logging & Monitoring** - Comprehensive logging and alerting

## Architecture

### Middleware Stack

The security middleware is applied in the following order:

1. **LogWebhookActivity** - Logs all incoming requests and responses
2. **ValidateWebhookRequest** - Validates request structure and content
3. **WhitelistWebhookIps** - Checks IP against whitelist
4. **WebhookRateLimiting** - Enforces rate limits
5. **VerifyWebhookSignature** - Verifies cryptographic signatures
6. **WebhookSecurityHeaders** - Adds security headers to responses

### Configuration

All security settings are configured in `config/webhooks.php`. The configuration includes:

- Platform-specific rate limits
- IP whitelist settings
- Signature verification parameters
- Request validation rules
- Security headers
- Logging and alerting settings

## Platform-Specific Security

### Facebook/Instagram

- **Signature Method**: HMAC-SHA256
- **Header**: `X-Hub-Signature-256`
- **Format**: `sha256=<hash>`
- **Challenge**: Hub challenge verification for webhook setup

### Twitter/X

- **Signature Method**: HMAC-SHA256
- **Headers**:
    - `X-Twitter-Webhooks-Signature`
    - `X-Twitter-Webhooks-Timestamp`
    - `X-Twitter-Webhooks-Nonce`
- **Challenge**: CRC token response for webhook setup

### LinkedIn

- **Signature Method**: HMAC-SHA256
- **Header**: `X-LI-Signature`
- **Format**: Base64 encoded HMAC
- **Challenge**: Challenge code verification

## Security Features

### 1. Signature Verification

Each webhook request must include a valid cryptographic signature that proves it originated from the expected platform. The system:

- Verifies the signature using the platform's secret key
- Checks timestamp to prevent replay attacks
- Maintains a cache of processed signatures to detect duplicates
- Supports signature rotation and key updates

### 2. Rate Limiting

Platform-specific rate limits prevent abuse:

- **Facebook**: 100 requests/minute, 1000 requests/hour
- **Instagram**: 100 requests/minute, 1000 requests/hour
- **Twitter**: 30 requests/minute, 300 requests/hour
- **LinkedIn**: 60 requests/minute, 600 requests/hour

Rate limiting includes:

- Per-minute and per-hour limits
- Burst protection
- IP-based tracking
- Redis-backed distributed rate limiting
- Proper HTTP headers (`X-RateLimit-*`)

### 3. IP Whitelisting

Only requests from official platform IP ranges are accepted:

- **Automatic Updates**: IP ranges are updated periodically
- **Fallback Handling**: Graceful degradation if IP ranges are unavailable
- **Strict Mode**: Optional strict mode to block all non-whitelisted requests
- **Dynamic Updates**: Support for real-time IP range updates

### 4. Request Validation

All incoming requests are validated for:

- **Content-Type**: Only allowed content types accepted
- **Payload Size**: Maximum payload size enforcement (default 1MB)
- **JSON Structure**: Valid JSON format and structure
- **Required Headers**: Essential headers must be present
- **Security Patterns**: Detection of suspicious content

### 5. Security Headers

Responses include comprehensive security headers:

```
X-Content-Type-Options: nosniff
X-Frame-Options: DENY
X-XSS-Protection: 1; mode=block
Strict-Transport-Security: max-age=31536000; includeSubDomains
Content-Security-Policy: default-src 'none'
Referrer-Policy: strict-origin-when-cross-origin
```

### 6. Logging & Monitoring

Comprehensive logging includes:

- **Request Logging**: All incoming requests with security context
- **Response Logging**: Response details and processing time
- **Violation Tracking**: Security violations with counts and thresholds
- **Performance Metrics**: Processing times and payload sizes
- **Alert Integration**: Configurable alerts for security events

## Management Commands

### View Security Statistics

```bash
php artisan webhook:security stats
```

### Clear Security Violations

```bash
# Clear all violations
php artisan webhook:security clear

# Clear specific type
php artisan webhook:security clear --type=signature

# Clear violations for specific IP
php artisan webhook:security clear --ip=192.168.1.100
```

### Block/Unblock IPs

```bash
# Block an IP for 1 hour
php artisan webhook:security block --ip=192.168.1.100 --duration=3600

# Unblock an IP
php artisan webhook:security unblock --ip=192.168.1.100
```

### Health Check

```bash
php artisan webhook:security health
```

### Cleanup Old Data

```bash
php artisan webhook:security cleanup --days=30
```

## Configuration Examples

### Basic Configuration

```php
// config/webhooks.php
'security' => [
    'signature' => [
        'tolerance' => 300, // 5 minutes
        'algorithms' => [
            'facebook' => 'sha256',
            'twitter' => 'sha256',
            'linkedin' => 'sha256',
        ],
    ],
    'rate_limits' => [
        'facebook' => [
            'requests_per_minute' => 100,
            'requests_per_hour' => 1000,
        ],
    ],
    'ip_whitelist' => [
        'enabled' => true,
        'strict_mode' => false,
        'auto_update' => true,
    ],
],
```

### Alert Configuration

```php
'alerting' => [
    'enabled' => true,
    'channels' => ['slack', 'email'],
    'thresholds' => [
        'signature_failures_per_minute' => 10,
        'rate_limit_violations_per_minute' => 50,
        'ip_violations_per_minute' => 20,
    ],
],
```

## Security Best Practices

### 1. Secret Management

- Use strong, random secrets for webhook configurations
- Rotate secrets periodically
- Store secrets securely (encrypted at rest)
- Never expose secrets in logs or responses

### 2. Monitoring

- Monitor security violation counts
- Set up alerts for suspicious activity
- Regularly review security logs
- Track IP whitelist updates

### 3. Rate Limiting

- Adjust rate limits based on platform requirements
- Monitor rate limit violations
- Implement backoff strategies for clients
- Consider platform-specific limits

### 4. IP Whitelisting

- Keep IP ranges up to date
- Monitor IP changes from platforms
- Implement graceful fallback for IP changes
- Consider geographic distribution

### 5. Validation

- Validate all input data
- Sanitize output to prevent XSS
- Implement proper error handling
- Use parameterized queries

## Troubleshooting

### Common Issues

1. **Signature Verification Failures**
    - Check webhook secret configuration
    - Verify signature format
    - Check timestamp tolerance
    - Review platform documentation

2. **Rate Limiting Issues**
    - Monitor rate limit headers
    - Check Redis connectivity
    - Review rate limit configuration
    - Consider distributed deployment

3. **IP Whitelist Problems**
    - Verify current IP ranges
    - Check proxy configurations
    - Review strict mode settings
    - Monitor IP range updates

4. **Validation Errors**
    - Check payload size limits
    - Verify content-type headers
    - Review JSON structure
    - Check for malformed data

### Debug Mode

Enable debug mode for detailed logging:

```php
// config/webhooks.php
'logging' => [
    'log_all_requests' => true,
    'log_level' => 'debug',
],
```

### Health Monitoring

Regular health checks ensure system reliability:

```bash
# Check system health
php artisan webhook:security health

# Monitor Redis connectivity
redis-cli ping

# Check queue processing
php artisan queue:monitor webhooks
```

## Performance Considerations

### Caching

- Redis for rate limiting and violation tracking
- Cached IP ranges for whitelist checking
- Replay attack prevention cache
- Configuration caching

### Optimization

- Efficient signature verification
- Minimal database queries
- Async logging where possible
- Connection pooling for Redis

### Scaling

- Distributed rate limiting
- Horizontal scaling support
- Load balancer considerations
- Database optimization

## Security Audits

Regular security audits should include:

1. **Review violation logs** for patterns
2. **Check IP whitelist** currency
3. **Verify rate limits** effectiveness
4. **Audit secret management** practices
5. **Test alert systems** functionality
6. **Validate monitoring** coverage

## Compliance

The system helps with compliance requirements:

- **SOC 2**: Security monitoring and logging
- **PCI DSS**: Data protection and access control
- **GDPR**: Data processing and privacy
- **HIPAA**: Healthcare data protection

## Integration

### External Alerting

Integrate with external systems:

```php
// Slack integration
'services' => [
    'slack' => [
        'webhook_url' => env('SLACK_WEBHOOK_URL'),
    ],
],
```

### SIEM Integration

Forward security events to SIEM:

```php
'alerting' => [
    'channels' => ['webhook'],
    'webhook_url' => env('SIEM_WEBHOOK_URL'),
],
```

### Monitoring Dashboards

Create dashboards for:

- Security violation trends
- Rate limit utilization
- IP whitelist status
- Processing performance

## Future Enhancements

Planned improvements include:

1. **Machine Learning**: Anomaly detection for webhook patterns
2. **Geo-blocking**: Geographic-based IP filtering
3. **Advanced Rate Limiting**: Adaptive rate limiting based on behavior
4. **Threat Intelligence**: Integration with threat feeds
5. **Automated Response**: Automated blocking of malicious actors

## Support

For security issues or questions:

1. Check the logs for detailed error information
2. Run health checks to verify system status
3. Review configuration settings
4. Monitor security metrics
5. Contact the security team for critical issues

Remember: Security is an ongoing process. Regular updates, monitoring, and improvements are essential to maintain a secure webhook system.
