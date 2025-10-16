# Instagram OAuth Fix - Action Required

## 🚨 Issue Fixed

The error "Invalid Scopes: user_profile, user_media" has been resolved by removing these invalid scopes from the Instagram OAuth configuration.

## 📋 What Changed

- Removed invalid scopes: `user_profile`, `user_media`
- Kept valid scopes: `instagram_basic`, `instagram_content_publish`, `public_profile`
- Updated Instagram OAuth flow to use Facebook page connection

## 🔧 Next Steps for You

### 1. Update Facebook App Permissions

Go to your Facebook Developer App (https://developers.facebook.com/apps/):

1. Select your app (App ID: 1040819511408345)
2. Go to "Facebook Login" → "Permissions"
3. Ensure these permissions are added:
    - ✅ `instagram_basic`
    - ✅ `instagram_content_publish`
    - ✅ `public_profile`
    - ✅ `email`

### 2. Add Instagram Product

1. In your Facebook App Dashboard
2. Click "Add Product" → "Instagram"
3. Choose "Instagram Basic Display"
4. Configure the redirect URIs:
    - `https://d00be9778b24.ngrok-free.app/oauth/facebook/callback`
    - `https://d00be9778b24.ngrok-free.app/oauth/instagram/callback`

### 3. For Instagram Posting (Business Accounts)

To post to Instagram, you need:

1. A Facebook Page
2. An Instagram Business or Creator account
3. Link the Instagram account to the Facebook page

## 🚀 How to Connect Instagram

### Option 1: Connect via Facebook Page (Recommended)

1. Go to `/social/accounts`
2. Click "Connect Facebook"
3. Select a Facebook page that has an Instagram Business account linked
4. The system will automatically discover and connect the Instagram account

### Option 2: Direct Instagram Connection

1. Go to `/social/accounts`
2. Click "Connect Instagram"
3. You'll be redirected to Facebook OAuth (Instagram uses Facebook's system)
4. Complete the OAuth flow

## 🔍 Testing the Fix

1. Clear your browser cookies for the domain
2. Go to your app: `https://d00be9778b24.ngrok-free.app`
3. Navigate to `/social/accounts`
4. Try connecting Instagram again

## ✅ Expected Behavior

- No more "Invalid Scopes" error
- Successful OAuth redirect
- Instagram account appears in connected accounts

## 📞 If Issues Persist

Check these items:

1. Facebook App is in "Live" mode (not Development)
2. All redirect URIs are added to the Facebook app
3. App has the required permissions
4. Instagram account is a Business/Creator account (for posting)

The OAuth flow should now work correctly!
