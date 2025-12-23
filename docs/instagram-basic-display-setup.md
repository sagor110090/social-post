# Instagram Basic Display API Setup Guide

This guide explains how to set up Instagram OAuth using the Basic Display API, which works with regular Instagram accounts (not just Business accounts).

## Overview

The Instagram integration has been simplified to use the Instagram Basic Display API directly, eliminating the need for Facebook pages and Instagram Business accounts. This approach:

- Works with personal Instagram accounts
- Simpler OAuth flow
- No Facebook page requirement
- Direct Instagram API integration

## Prerequisites

1. Instagram Developer Account
2. Facebook App (required for Instagram API access)
3. Valid redirect URI registered in your app

## Setup Steps

### 1. Create Facebook App

1. Go to [Facebook Developers](https://developers.facebook.com/)
2. Create a new app or use an existing one
3. Add "Instagram Basic Display" product to your app

### 2. Configure Instagram Basic Display

1. In your Facebook App dashboard, go to "Products" → "Instagram Basic Display"
2. Configure the following:
    - **Valid OAuth Redirect URIs**: Add your callback URL
        ```
        https://yourdomain.com/oauth/instagram/callback
        ```
    - **Deauthorize Callback URL**: Optional but recommended
    - **Data Deletion Request URL**: Optional but recommended

### 3. Get App Credentials

1. Go to "Settings" → "Basic"
2. Note down:
    - **App ID** (Client ID)
    - **App Secret** (Client Secret)

### 4. Environment Configuration

Add the following to your `.env` file:

```env
INSTAGRAM_CLIENT_ID=your_instagram_app_id
INSTAGRAM_CLIENT_SECRET=your_instagram_app_secret
INSTAGRAM_REDIRECT_URI=https://yourdomain.com/oauth/instagram/callback
```

### 5. App Review and Permissions

For production use:

1. Submit your app for review
2. Request the following permissions:
    - `user_profile` - Access user's profile information
    - `user_media` - Access user's media

For development/testing, you can use test users without app review.

## OAuth Flow

### 1. Authorization Request

The user is redirected to Instagram's authorization page:

```
https://api.instagram.com/oauth/authorize
  ?app_id=APP_ID
  &redirect_uri=REDIRECT_URI
  &scope=user_profile,user_media
  &response_type=code
  &state=RANDOM_STATE
```

### 2. Authorization Code Exchange

After user authorization, Instagram redirects back with a code. Exchange it for an access token:

```
POST https://api.instagram.com/oauth/access_token
```

### 3. Long-Lived Token Exchange

Exchange the short-lived token for a long-lived token (valid for 60 days):

```
GET https://graph.instagram.com/access_token
  ?grant_type=ig_exchange_token
  &client_secret=CLIENT_SECRET
  &access_token=SHORT_LIVED_TOKEN
```

### 4. User Information

Get user profile information:

```
GET https://graph.instagram.com/me
  ?fields=id,username,account_type,media_count
  &access_token=ACCESS_TOKEN
```

## Features Available

With Basic Display API, you can:

- ✅ Read user profile information
- ✅ Read user's media (photos/videos)
- ✅ Get basic media metrics
- ✅ Post content (requires additional permissions)
- ❌ Advanced analytics (requires Instagram Graph API)
- ❌ Hashtag search (requires Instagram Graph API)

## Limitations

1. **No Business Features**: Advanced business features require Instagram Graph API
2. **Limited Analytics**: Basic engagement metrics only
3. **Content Publishing**: Requires additional review and permissions
4. **Rate Limits**: Standard API rate limits apply

## Testing

1. Create test users in your Facebook App dashboard
2. Use the test users to simulate the OAuth flow
3. Verify token exchange and user data retrieval

## Troubleshooting

### Common Issues

1. **"Invalid redirect URI"**
    - Ensure the redirect URI exactly matches what's configured in your app
    - Check for trailing slashes and HTTP vs HTTPS

2. **"Invalid authorization code"**
    - Authorization codes expire quickly (use immediately)
    - Ensure state parameter matches between request and callback

3. **"Missing permissions"**
    - Verify requested permissions are approved for your app
    - Check if user granted all requested permissions

### Debug Tips

1. Check Laravel logs for detailed error messages
2. Verify all HTTP requests are using HTTPS
3. Ensure app is in live mode for production testing
4. Use Facebook's Access Token Debugger to validate tokens

## Migration from Facebook OAuth

If migrating from the previous Facebook-based Instagram OAuth:

1. Update environment variables
2. Existing connections will need to be re-established
3. User experience is now simpler (no Facebook page requirement)
4. Token format and storage remain compatible

## Security Considerations

1. Always use HTTPS for redirect URIs
2. Store client secrets securely
3. Validate state parameter to prevent CSRF
4. Implement proper token refresh logic
5. Log and monitor OAuth failures

## Support

For issues related to:

- **App Configuration**: Facebook Developers Documentation
- **API Usage**: Instagram Basic Display API Documentation
- **Implementation**: Check Laravel logs and error messages
