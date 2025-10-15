# Social Media OAuth Setup Guide

This guide will help you set up OAuth credentials for each social media platform to enable the "Connect Accounts" functionality.

## 🚀 Quick Setup

### 1. Update Environment Variables

Add the following to your `.env` file (replace with your actual credentials):

```bash
# Facebook/Instagram
FACEBOOK_CLIENT_ID=your_facebook_app_id
FACEBOOK_CLIENT_SECRET=your_facebook_app_secret
FACEBOOK_REDIRECT_URI=https://your-domain.com/oauth/facebook/callback

# Instagram (uses Facebook's OAuth)
INSTAGRAM_CLIENT_ID=your_facebook_app_id
INSTAGRAM_CLIENT_SECRET=your_facebook_app_secret
INSTAGRAM_REDIRECT_URI=https://your-domain.com/oauth/instagram/callback

# LinkedIn
LINKEDIN_CLIENT_ID=your_linkedin_client_id
LINKEDIN_CLIENT_SECRET=your_linkedin_client_secret
LINKEDIN_REDIRECT_URI=https://your-domain.com/oauth/linkedin/callback

# Twitter/X
TWITTER_CLIENT_ID=your_twitter_client_id
TWITTER_CLIENT_SECRET=your_twitter_client_secret
TWITTER_REDIRECT_URI=https://your-domain.com/oauth/twitter/callback
```

### 2. Clear Configuration Cache

```bash
php artisan config:clear
php artisan route:clear
```

## 📋 Platform-Specific Setup

### Facebook & Instagram

1. **Create Facebook App**
    - Go to [Facebook Developers](https://developers.facebook.com/)
    - Create a new app: "Business" type
    - Add "Facebook Login" product
    - Add "Instagram Basic Display" product

2. **Configure OAuth Settings**
    - App Domains: Add your domain (e.g., `your-domain.com`)
    - Valid OAuth Redirect URIs:
        - `https://your-domain.com/oauth/facebook/callback`
        - `https://your-domain.com/oauth/instagram/callback`

3. **Required Permissions**
    - Facebook: `pages_manage_posts`, `pages_read_engagement`, `instagram_basic`, `instagram_content_publish`
    - Instagram: `instagram_basic`, `instagram_content_publish`

### LinkedIn

1. **Create LinkedIn App**
    - Go to [LinkedIn Developers](https://www.linkedin.com/developers/)
    - Create new app
    - Add "Sign In with LinkedIn" product

2. **Configure OAuth Settings**
    - Authorized Redirect URLs: `https://your-domain.com/oauth/linkedin/callback`

3. **Required Permissions**
    - `r_liteprofile`, `r_emailaddress`, `w_member_social`

### Twitter/X

1. **Create Twitter App**
    - Go to [Twitter Developers](https://developer.twitter.com/)
    - Create new project and app
    - Set app type to "Web App"

2. **Configure OAuth Settings**
    - Callback URI: `https://your-domain.com/oauth/twitter/callback`
    - Enable OAuth 2.0

3. **Required Permissions**
    - `tweet.read`, `tweet.write`, `users.read`, `offline.access`

## 🔧 Testing the Connection

### 1. Access the Accounts Page

Navigate to `/social/accounts` in your application

### 2. Test Connection Flow

1. Click "Connect [Platform]" button
2. You should be redirected to the platform's OAuth page
3. Authorize the application
4. You should be redirected back to your app
5. Check if the account appears as "Connected"

### 3. Debug Common Issues

#### "Provider not supported" Error

- Check if the provider is in the `validateProvider()` method
- Verify the route parameter matches the provider name

#### "Invalid client credentials" Error

- Verify your CLIENT_ID and CLIENT_SECRET are correct
- Check if the redirect URI matches exactly what's configured in the app

#### "Redirect URI mismatch" Error

- Ensure the redirect URI in your .env matches the one in the developer console
- Check for trailing slashes and HTTP vs HTTPS

#### "Scope insufficient" Error

- Verify you've requested all required permissions
- Check if your app has been approved for those permissions

## 🛠 Development Tips

### Using Ngrok for Local Development

```bash
# Install ngrok
npm install -g ngrok

# Start your Laravel app
php artisan serve

# In another terminal, expose your local server
ngrok http 8000

# Update your .env with the ngrok URL
APP_URL=https://your-ngrok-url.ngrok-free.app
```

### Testing with Fake Credentials

For development, you can test the OAuth flow without real credentials:

```bash
# Add to .env for testing
FACEBOOK_CLIENT_ID=test_client_id
FACEBOOK_CLIENT_SECRET=test_client_secret
```

This will allow you to test the redirect flow, but you'll get an error at the actual OAuth step.

## 📊 Database Schema

The `social_accounts` table stores:

- `user_id` - The user who owns the account
- `platform` - Social media platform (facebook, instagram, etc.)
- `platform_id` - The platform's user ID
- `username` - The username/handle
- `display_name` - The display name
- `email` - Email associated with the account
- `avatar` - Profile picture URL
- `access_token` - Encrypted OAuth access token
- `refresh_token` - Encrypted OAuth refresh token
- `token_expires_at` - When the access token expires
- `additional_data` - Platform-specific data (JSON)
- `is_active` - Whether the account is active
- `last_synced_at` - Last time data was synced

## 🔒 Security Considerations

1. **Encrypted Tokens**: Access and refresh tokens are encrypted in the database
2. **HTTPS Required**: Always use HTTPS in production
3. **Scope Limitation**: Only request necessary permissions
4. **Token Refresh**: Implement token refresh logic for long-lived access
5. **Rate Limiting**: Respect platform rate limits

## 🚨 Troubleshooting Checklist

- [ ] Environment variables are set correctly
- [ ] OAuth apps are configured with correct redirect URIs
- [ ] App has required permissions/scopes
- [ ] Laravel caches are cleared
- [ ] Database migrations are run
- [ ] Socialite is installed (`composer show | grep socialite`)
- [ ] Routes are registered (`php artisan route:list | grep oauth`)

## 📞 Support

If you encounter issues:

1. Check Laravel logs: `php artisan log:tail`
2. Enable debug mode: `APP_DEBUG=true`
3. Check the specific error messages in the browser console
4. Verify the OAuth app settings in each platform's developer console
