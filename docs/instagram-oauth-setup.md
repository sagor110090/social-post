# Instagram OAuth Setup Guide

## Overview

This application supports Instagram OAuth integration for posting content to Instagram Business accounts. Instagram uses Facebook's OAuth system, so you need to set up a Facebook App first.

## Prerequisites

1. Facebook Developer Account
2. Facebook App with Instagram Basic Display API configured
3. ngrok or similar service for local development (already configured)

## Configuration

### 1. Facebook App Setup

1. Go to [Facebook Developers](https://developers.facebook.com/)
2. Create a new app or use existing one
3. Add "Instagram Basic Display" product
4. Configure OAuth redirects:
    - Production: `https://yourdomain.com/oauth/instagram/callback`
    - Development: `https://d00be9778b24.ngrok-free.app/oauth/instagram/callback`

### 2. Environment Variables

Add these to your `.env` file:

```env
INSTAGRAM_CLIENT_ID=your_facebook_app_id
INSTAGRAM_CLIENT_SECRET=your_facebook_app_secret
INSTAGRAM_REDIRECT_URI=${APP_URL}/oauth/instagram/callback
```

### 3. Required Permissions

The app requests these Instagram scopes:

- `instagram_basic` - Basic profile information
- `instagram_content_publish` - Publish content
- `public_profile` - Public profile access
- `user_profile` - User profile data
- `user_media` - User media access

## How It Works

### OAuth Flow

1. User clicks "Connect Instagram"
2. Redirected to Facebook/Instagram OAuth
3. User grants permissions
4. Callback receives authorization code
5. Exchange code for access token
6. Exchange short-lived token for long-lived token (60 days)
7. Store encrypted token in database

### Instagram Business Features

For full Instagram Business features (posting to business accounts, analytics):

1. First connect Facebook page
2. Then connect Instagram account
3. The system will automatically discover Instagram business accounts linked to Facebook pages

## API Endpoints

### OAuth Routes

- `GET /oauth/instagram` - Redirect to Instagram OAuth
- `GET /oauth/instagram/callback` - Handle OAuth callback
- `DELETE /oauth/instagram/disconnect` - Disconnect Instagram account

### Instagram API Routes

- `GET /instagram/accounts` - Get connected business accounts
- `POST /instagram/accounts/{accountId}/media` - Create media container
- `POST /instagram/accounts/{accountId}/publish` - Publish media
- `GET /instagram/media/{mediaId}/insights` - Get media insights
- `GET /instagram/accounts/{accountId}/insights` - Get account insights
- `POST /instagram/upload-image` - Upload image for posting

## Database Schema

The `social_accounts` table stores:

- `platform` - 'instagram'
- `platform_id` - Instagram user ID
- `username` - Instagram username
- `access_token` - Encrypted long-lived access token
- `token_expires_at` - Token expiration (60 days)
- `additional_data` - Extra metadata (API version, token type, etc.)

## Token Management

- Short-lived tokens (1 hour) are automatically exchanged for long-lived tokens (60 days)
- Tokens are encrypted in the database
- System checks token expiration before API calls
- Users can reconnect to refresh tokens

## Limitations

1. Instagram Basic Display API has limitations compared to Instagram Graph API
2. Posting requires Instagram Business accounts
3. Some features require Facebook page connection
4. Rate limits apply (200 calls per hour per user)

## Testing

Run the Instagram OAuth tests:

```bash
php artisan test tests/Feature/Auth/InstagramOAuthTest.php
```

## Troubleshooting

### Common Issues

1. **"Invalid platform app"** - App not configured for Instagram Basic Display API
2. "Invalid redirect URI" - Check Facebook app settings
3. "Missing permissions" - Verify app has required scopes
4. "Token expired" - User needs to reconnect
5. "No business accounts found" - Connect Facebook page first

### Debug Steps

1. Run diagnostic command: `php artisan instagram:diagnose-oauth`
2. Check Laravel logs: `storage/logs/laravel.log`
3. Verify environment variables
4. Test OAuth flow with Facebook's [Graph API Explorer](https://developers.facebook.com/tools/explorer/)
5. Check token status in database

### "Invalid Platform App" Fix

If you encounter "Invalid platform app" error:

1. **Add Instagram Basic Display Product**:
    - Go to your Facebook App dashboard
    - Click "Add Product" → "Instagram Basic Display"
    - Configure redirect URI: `https://yourdomain.com/oauth/instagram/callback`

2. **Remove Conflicting Products**:
    - Remove "Facebook Login" if not needed
    - Ensure app is configured for Instagram, not Facebook Graph API

3. **Check App Mode**:
    - Set to "Development" for testing
    - Add test users in App Settings

4. **Verify Permissions**:
    - Add `user_profile` and `user_media` permissions
    - Submit for review if needed for production

## Security Considerations

- All tokens are encrypted using Laravel's encryption
- HTTPS is required for production
- Redirect URIs must be whitelisted in Facebook app
- Regular token refresh is automated
