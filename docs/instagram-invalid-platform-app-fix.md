# Instagram OAuth "Invalid Platform App" Error - Complete Fix Guide

## 🚨 Problem Analysis

The error `Invalid platform app` occurs when your Facebook App (ID: 1040819511408345) is **not properly configured for Instagram Basic Display API**. This is a Facebook App configuration issue, not a code issue.

**Error URL:** https://www.instagram.com/oauth/authorize/first_party/error/?message=Invalid%20request%3A%20Request%20parameters%20are%20invalid%3A%20Invalid%20platform%20app

## 🔍 Root Cause Analysis

This error specifically means:

- The Facebook App exists but is not configured for Instagram Basic Display API
- OR the app is configured but missing required settings
- OR there's a mismatch between app configuration and OAuth parameters

## 📋 Step-by-Step Fix Guide

### Step 1: Verify Facebook App Configuration

1. **Go to Facebook Developers Dashboard**
    - URL: https://developers.facebook.com/apps/1040819511408345/
    - Login with your Facebook account

2. **Check App Status**
    - Ensure app is **NOT in "Disabled"** status
    - App should be in **Development** mode (for testing)
    - App should be **Live** mode (for production)

3. **Add Instagram Basic Display Product**
    - Click **"Add Product"** in the left sidebar
    - Find and click **"Instagram Basic Display"**
    - Click **"Set Up"**

### Step 2: Configure Instagram Basic Display

1. **Basic Display Settings**
    - Go to **Products → Instagram Basic Display → Settings**
    - **App Domains**: Add your domain (e.g., `localhost` for local development)
    - **Valid OAuth Redirect URIs**: Add EXACTLY:

        ```
        http://localhost:8000/oauth/instagram/callback
        ```

        - For production: `https://yourdomain.com/oauth/instagram/callback`
        - **IMPORTANT**: No trailing slash, exact match required

2. **Permissions**
    - Ensure these permissions are enabled:
        - `user_profile` (Required)
        - `user_media` (Required for media access)

3. **Deauthorize Callback URL** (Optional but recommended)
    - Add: `http://localhost:8000/oauth/instagram/deauthorize`
    - This handles when users disconnect your app

4. **Data Deletion Request URL** (Required for App Review)
    - Add: `http://localhost:8000/oauth/instagram/data-deletion`
    - Create a simple endpoint that returns success

### Step 3: App Review and Permissions

1. **Development Mode** (Immediate Testing)
    - Add yourself as a **Test User**:
        - Go to **Roles → Test Users**
        - Click **"Add Test Users"**
        - Add your Facebook account
    - You can now test immediately without App Review

2. **Production Mode** (Public Access)
    - Submit your app for **App Review**
    - Provide:
        - App screenshots
        - Video demonstration
        - Privacy policy URL
        - Terms of service URL

### Step 4: Verify Environment Configuration

Check your `.env` file:

```env
INSTAGRAM_CLIENT_ID=1040819511408345
INSTAGRAM_CLIENT_SECRET=your_app_secret_here
INSTAGRAM_REDIRECT_URI=http://localhost:8000/oauth/instagram/callback
```

**Important:**

- Client ID should be your Facebook App ID
- Client Secret is from Facebook App Dashboard
- Redirect URI must EXACTLY match what's in Facebook App settings

### Step 5: Test the Configuration

Run the diagnostic command:

```bash
php artisan instagram:diagnose-oauth
```

This will verify:

- ✅ Environment variables are set
- ✅ OAuth URL is correctly generated
- ✅ App is accessible via Facebook API
- ✅ Redirect URI is properly formatted

## 🔧 Alternative Solutions

### Option A: Create New Facebook App

If current app can't be fixed:

1. **Create New App**
    - Go to: https://developers.facebook.com/apps/
    - Click **"Create App"**
    - Choose **"Business"** app type
    - Enter app name and contact email

2. **Add Instagram Basic Display**
    - Follow Step 2 above with new app

3. **Update Environment Variables**
    ```env
    INSTAGRAM_CLIENT_ID=new_app_id
    INSTAGRAM_CLIENT_SECRET=new_app_secret
    ```

### Option B: Use Instagram Graph API (Business Accounts)

For Instagram Business/Creator accounts:

1. **Add Facebook Login Product**
    - Instead of Instagram Basic Display
    - Use Facebook Login with Instagram permissions

2. **Required Permissions**
    - `instagram_basic`
    - `instagram_content_publish`
    - `pages_show_list`

3. **Update OAuth Flow**
    - Use Facebook OAuth first
    - Then get Instagram accounts via Facebook Graph API

## 🧪 Quick Test

Use this URL to test (replace with your actual client ID):

```
https://api.instagram.com/oauth/authorize?app_id=1040819511408345&redirect_uri=http://localhost:8000/oauth/instagram/callback&scope=user_profile,user_media&response_type=code&state=test
```

If you get "Invalid platform app" error, the issue is in Facebook App configuration.
If you get the login screen, your app is configured correctly.

## 🚨 Common Mistakes to Avoid

1. **Wrong Redirect URI**
    - Must EXACTLY match Facebook App settings
    - No trailing slashes
    - HTTP vs HTTPS must match

2. **Missing Instagram Basic Display Product**
    - Just having Facebook Login is not enough
    - Must add Instagram Basic Display specifically

3. **App in Wrong Mode**
    - Development mode: Only test users can access
    - Live mode: Requires App Review

4. **Incorrect Permissions**
    - `user_profile` and `user_media` are required
    - Check they're enabled in Instagram Basic Display settings

## 📞 Getting Help

1. **Facebook Developers Support**
    - https://developers.facebook.com/support/
    - They can help with app configuration issues

2. **Check App Status**
    - Go to: https://developers.facebook.com/apps/1040819511408345/dashboard/
    - Look for any error messages or warnings

3. **Review App Logs**
    - Check Laravel logs: `storage/logs/laravel.log`
    - Look for specific error messages

## ✅ Verification Checklist

- [ ] Facebook App ID: 1040819511408345
- [ ] Instagram Basic Display product added
- [ ] Valid OAuth Redirect URI configured
- [ ] App in Development mode with test users
- [ ] Environment variables correctly set
- [ ] Diagnostic command passes all checks
- [ ] OAuth URL loads login screen (not error)

Once all these steps are completed, the "Invalid platform app" error should be resolved.
