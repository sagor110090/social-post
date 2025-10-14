# Deployment Task Templates

## Production Deployment Checklist

### Pre-Deployment Preparation

- [ ] Backup current production database
- [ ] Review migration files for safety
- [ ] Test deployment in staging environment
- [ ] Verify all environment variables
- [ ] Check SSL certificate validity
- [ ] Review error monitoring setup
- [ ] Prepare rollback plan
- [ ] Schedule maintenance window if needed

### Code Deployment Steps

1. **Code Preparation**
    - Merge development to main branch
    - Tag release version
    - Run automated tests
    - Build production assets
    - Optimize for production

2. **Server Deployment**
    - Pull latest code
    - Install/update dependencies
    - Run database migrations
    - Clear application cache
    - Restart queue workers
    - Update web server configuration

3. **Post-Deployment Verification**
    - Test critical user flows
    - Verify API endpoints
    - Check background jobs
    - Monitor error rates
    - Verify performance metrics

## Environment Setup

### Production Environment Variables

```bash
APP_NAME=SocialPost
APP_ENV=production
APP_KEY=base64:...
APP_DEBUG=false
APP_URL=https://socialpost.com

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=socialpost_production
DB_USERNAME=...
DB_PASSWORD=...

BROADCAST_DRIVER=log
CACHE_DRIVER=redis
FILESYSTEM_DISK=s3
QUEUE_CONNECTION=redis
SESSION_DRIVER=redis
SESSION_LIFETIME=120

REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379

MAIL_MAILER=smtp
MAIL_HOST=...
MAIL_PORT=587
MAIL_USERNAME=...
MAIL_PASSWORD=...
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=...
MAIL_FROM_NAME="${APP_NAME}"

OPENAI_API_KEY=...
TWITTER_API_KEY=...
TWITTER_API_SECRET=...
FACEBOOK_CLIENT_ID=...
FACEBOOK_CLIENT_SECRET=...
LINKEDIN_CLIENT_ID=...
LINKEDIN_CLIENT_SECRET=...
INSTAGRAM_CLIENT_ID=...
INSTAGRAM_CLIENT_SECRET=...
```

### Server Configuration

#### Nginx Configuration

```nginx
server {
    listen 80;
    server_name socialpost.com www.socialpost.com;
    return 301 https://$server_name$request_uri;
}

server {
    listen 443 ssl http2;
    server_name socialpost.com www.socialpost.com;

    root /var/www/socialpost/public;
    index index.php;

    ssl_certificate /etc/letsencrypt/live/socialpost.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/socialpost.com/privkey.pem;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.3-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.ht {
        deny all;
    }
}
```

#### Supervisor Configuration (Queue Workers)

```ini
[program:socialpost-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/socialpost/artisan queue:work --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=4
redirect_stderr=true
stdout_logfile=/var/www/socialpost/storage/logs/worker.log
stopwaitsecs=3600
```

## Database Management

### Migration Safety

```bash
# Check pending migrations
php artisan migrate:status

# Run migrations with rollback option
php artisan migrate --step

# Test migrations in staging first
php artisan migrate --force
```

### Database Backup Strategy

```bash
# Daily backup script
#!/bin/bash
DATE=$(date +%Y%m%d_%H%M%S)
mysqldump -u username -p socialpost_production > /backups/socialpost_$DATE.sql
find /backups -name "socialpost_*.sql" -mtime +7 -delete
```

## Performance Optimization

### Caching Configuration

```bash
# Clear all caches
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear

# Optimize for production
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan optimize
```

### Asset Optimization

```bash
# Build production assets
npm run build

# Version assets for cache busting
php artisan asset:publish
```

## Monitoring and Logging

### Application Monitoring

- Set up Laravel Telescope
- Configure error tracking (Sentry, Bugsnag)
- Monitor queue health
- Track performance metrics
- Set up uptime monitoring

### Log Management

```bash
# Configure log rotation
sudo nano /etc/logrotate.d/laravel

# Monitor application logs
tail -f storage/logs/laravel.log

# Monitor queue worker logs
tail -f storage/logs/worker.log
```

## Security Hardening

### SSL/TLS Configuration

- Use Let's Encrypt for SSL certificates
- Configure automatic renewal
- Implement HSTS headers
- Disable weak ciphers

### Firewall Configuration

```bash
# UFW firewall rules
sudo ufw allow 22/tcp
sudo ufw allow 80/tcp
sudo ufw allow 443/tcp
sudo ufw enable
```

### File Permissions

```bash
# Set proper file permissions
sudo chown -R www-data:www-data /var/www/socialpost
sudo find /var/www/socialpost -type f -exec chmod 644 {} \;
sudo find /var/www/socialpost -type d -exec chmod 755 {} \;
sudo chmod -R 775 /var/www/socialpost/storage
sudo chmod -R 775 /var/www/socialpost/bootstrap/cache
```

## Scaling Considerations

### Load Balancing

- Configure multiple application servers
- Set up load balancer (Nginx, HAProxy)
- Implement session affinity if needed
- Monitor server health

### Database Scaling

- Implement read replicas
- Consider database sharding
- Use connection pooling
- Monitor query performance

### CDN Implementation

- Configure CloudFlare or similar CDN
- Cache static assets
- Implement geographic distribution
- Monitor CDN performance

## Rollback Procedures

### Emergency Rollback

```bash
# Rollback to previous commit
git checkout previous_tag

# Rollback database migrations
php artisan migrate:rollback --step=1

# Restart services
sudo systemctl restart nginx
sudo systemctl restart php8.3-fpm
sudo supervisorctl restart socialpost-worker:*
```

### Database Rollback

```bash
# Restore from backup
mysql -u username -p socialpost_production < backup.sql

# Verify data integrity
php artisan migrate:status
```

## Maintenance Mode

### Enable Maintenance Mode

```bash
# Enable maintenance mode
php artisan down --message="Upgrading system" --retry=60

# Disable maintenance mode
php artisan up
```

### Maintenance Page

```html
<!-- storage/framework/views/maintenance.html -->
<!DOCTYPE html>
<html>
    <head>
        <title>Maintenance Mode</title>
        <style>
            body {
                font-family: Arial, sans-serif;
                text-align: center;
                padding: 50px;
            }
            h1 {
                color: #333;
            }
            p {
                color: #666;
            }
        </style>
    </head>
    <body>
        <h1>System Maintenance</h1>
        <p>
            We're currently upgrading our system. Please check back in a few
            minutes.
        </p>
    </body>
</html>
```

## Deployment Automation

### Deployment Script

```bash
#!/bin/bash
# deploy.sh

set -e

echo "Starting deployment..."

# Pull latest code
git pull origin main

# Install dependencies
composer install --no-dev --optimize-autoloader
npm ci && npm run build

# Run migrations
php artisan migrate --force

# Clear and cache
php artisan cache:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Restart services
sudo systemctl reload nginx
sudo systemctl reload php8.3-fpm
sudo supervisorctl restart social-post-worker:*

echo "Deployment completed successfully!"
```

### CI/CD Pipeline

- Set up GitHub Actions or similar
- Automated testing on push
- Staging deployment on PR
- Production deployment on merge
- Rollback on failure
