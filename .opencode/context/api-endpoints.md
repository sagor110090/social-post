# API Endpoints Documentation

## Authentication Routes (`/auth`)

- `POST /login` - User authentication
- `POST /register` - User registration
- `POST /logout` - User logout
- `POST /forgot-password` - Password reset request
- `POST /reset-password` - Password reset confirmation
- `GET /two-factor/challenge` - 2FA challenge
- `POST /two-factor/authenticate` - 2FA verification

## Social Media Routes (`/social`)

- `GET /` - List connected social accounts
- `POST /connect/{platform}` - Connect social platform
- `GET /{platform}/callback` - OAuth callback
- `DELETE /{account}` - Disconnect social account
- `POST /{account}/post` - Publish post to platform
- `GET /{account}/analytics` - Fetch platform analytics

## Post Management Routes (`/posts`)

- `GET /` - List user posts
- `POST /` - Create new post
- `GET /{post}` - Show post details
- `PUT /{post}` - Update post
- `DELETE /{post}` - Delete post
- `POST /{post}/duplicate` - Duplicate post
- `GET /{post}/analytics` - Post performance data

## Scheduled Posts Routes (`/scheduled-posts`)

- `GET /` - List scheduled posts
- `POST /` - Schedule new post
- `GET /{post}` - Show scheduled post details
- `PUT /{post}` - Update scheduled post
- `DELETE /{post}` - Cancel scheduled post
- `POST /{post}/publish-now` - Publish immediately

## Media Management Routes (`/media`)

- `GET /` - List media files
- `POST /upload` - Upload media file
- `GET /{media}` - Get media details
- `PUT /{media}` - Update media metadata
- `DELETE /{media}` - Delete media file
- `POST /{media}/optimize` - Optimize media file

## Analytics Routes (`/analytics`)

- `GET /dashboard` - Main analytics dashboard
- `GET /posts` - Post performance analytics
- `GET /accounts` - Account performance analytics
- `GET /engagement` - Engagement metrics
- `GET /growth` - Follower growth analytics
- `GET /export` - Export analytics data

## AI Routes (`/ai`)

- `POST /generate-content` - Generate post content
- `POST /suggest-hashtags` - Suggest relevant hashtags
- `POST /optimize-content` - Optimize existing content
- `POST /generate-image` - Generate AI images
- `POST /analyze-sentiment` - Analyze content sentiment

## Calendar Routes (`/calendar`)

- `GET /` - Calendar view with scheduled posts
- `POST /schedule` - Schedule post via calendar
- `GET /events` - Calendar events
- `PUT /event/{event}` - Update calendar event
- `DELETE /event/{event}` - Remove calendar event

## Team Management Routes (`/teams`)

- `GET /` - List user teams
- `POST /` - Create new team
- `GET /{team}` - Team details
- `PUT /{team}` - Update team
- `DELETE /{team}` - Delete team
- `GET /{team}/members` - Team members
- `POST /{team}/invite` - Invite team member
- `DELETE /{team}/members/{user}` - Remove team member

## Settings Routes (`/settings`)

- `GET /profile` - User profile settings
- `PUT /profile` - Update profile
- `GET /preferences` - User preferences
- `PUT /preferences` - Update preferences
- `GET /notifications` - Notification settings
- `PUT /notifications` - Update notification settings
- `GET /security` - Security settings
- `PUT /security` - Update security settings

## Admin Routes (`/admin`)

- `GET /dashboard` - Admin dashboard
- `GET /users` - User management
- `GET /analytics` - System analytics
- `GET /logs` - System logs
- `POST /maintenance` - Toggle maintenance mode

## API Response Format

### Success Response

```json
{
    "success": true,
    "data": {},
    "message": "Operation completed successfully"
}
```

### Error Response

```json
{
    "success": false,
    "error": {
        "code": "ERROR_CODE",
        "message": "Error description",
        "details": {}
    }
}
```

### Validation Error Response

```json
{
    "success": false,
    "error": {
        "code": "VALIDATION_ERROR",
        "message": "The given data was invalid",
        "details": {
            "field": ["Error message"]
        }
    }
}
```

## Rate Limiting

- Authentication endpoints: 5 requests per minute
- Content creation: 30 requests per minute
- Analytics endpoints: 60 requests per minute
- File uploads: 10 requests per minute
- General API: 100 requests per minute

## Authentication

All API endpoints (except auth routes) require:

- Bearer token in Authorization header
- Valid session cookie
- CSRF token for web requests

## Pagination

List endpoints support pagination:

- `page` parameter (default: 1)
- `per_page` parameter (default: 15, max: 100)
- Response includes pagination metadata
