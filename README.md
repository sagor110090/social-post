# AutoPost AI - Social Media Management & AI Content Platform

AutoPost AI is a comprehensive social media management platform built with Laravel 12, Vue.js 3, and Inertia.js. It allows users to connect multiple social media accounts, generate engaging content using AI, schedule posts, and track performance with detailed analytics.

## **Key Features**

### **Social Media Integration**
- **Multi-Platform Support**: Connect and post to Facebook, Instagram, LinkedIn, and Twitter/X.
- **OAuth Authentication**: Secure connection to social platforms using Laravel Socialite.
- **Account Management**: Easily connect, disconnect, and manage multiple social profiles.

### **AI Content Generation**
- **AI Post Generator**: Create platform-specific content using Groq/OpenAI.
- **Tone Customization**: Choose from various tones (Professional, Casual, Friendly, Humorous).
- **Hashtag Suggestions**: Automatically generate relevant hashtags for each platform.
- **Image Prompts**: Generate detailed AI image prompts for your posts.

### **Post Scheduling & Calendar**
- **Smart Scheduling**: Schedule posts for future dates and times.
- **Interactive Calendar**: View and manage all scheduled posts in a beautiful calendar interface (FullCalendar).
- **Automated Publishing**: Background jobs handle the actual publishing to social platforms.

### **Analytics & Insights**
- **Comprehensive Dashboard**: Track total posts, engagement, reach, and performance trends.
- **Platform Performance**: Compare how your content performs across different social networks.
- **Detailed Post Analytics**: View likes, comments, shares, and reach for individual posts.
- **Visual Charts**: Data-driven insights powered by Chart.js.

### **Media Management**
- **Media Library**: Upload and manage images and videos.
- **Platform Optimization**: Automatic image processing and optimization for social media.

### **Team Collaboration**
- **Team Management**: Create teams and invite members.
- **Shared Analytics**: Track performance across the entire team.

### **Admin Panel**
- **User Management**: Monitor and manage all users.
- **Post Monitoring**: Overview of all posts published through the platform.
- **Revenue Tracking**: Monitor subscriptions and revenue (Stripe integration).

## **Tech Stack**

- **Backend**: Laravel 12, Fortify (Auth), Socialite (OAuth), Redis (Queue/Cache).
- **Frontend**: Vue.js 3 (Composition API), Inertia.js, Tailwind CSS 4, Radix Vue.
- **Database**: SQLite (default), MySQL/PostgreSQL support.
- **AI**: Groq API / OpenAI PHP Client.
- **Real-time**: Pusher / Laravel Echo.
- **Testing**: Pest PHP.

## **Prerequisites**

- PHP 8.2 or higher
- Composer
- Node.js & NPM
- Redis (for queues and caching)
- SQLite or MySQL

## **Installation**

1. **Clone the repository**:
   ```bash
   git clone https://github.com/yourusername/social-post.git
   cd social-post
   ```

2. **Install PHP dependencies**:
   ```bash
   composer install
   ```

3. **Install JavaScript dependencies**:
   ```bash
   npm install
   ```

4. **Set up environment variables**:
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```

5. **Configure the database**:
   By default, the project uses SQLite. Create the database file:
   ```bash
   touch database/database.sqlite
   ```

6. **Run migrations**:
   ```bash
   php artisan migrate
   ```

7. **Build frontend assets**:
   ```bash
   npm run build
   ```

## **Environment Configuration**

Ensure you set up the following in your `.env` file:

- **AI Configuration**:
  ```env
  GROQ_API_KEY=your_groq_api_key
  OPENAI_API_KEY=your_openai_api_key
  ```

- **Social Media OAuth**:
  ```env
  FACEBOOK_CLIENT_ID=...
  FACEBOOK_CLIENT_SECRET=...
  INSTAGRAM_CLIENT_ID=...
  INSTAGRAM_CLIENT_SECRET=...
  LINKEDIN_CLIENT_ID=...
  LINKEDIN_CLIENT_SECRET=...
  TWITTER_CLIENT_ID=...
  TWITTER_CLIENT_SECRET=...
  ```

- **Redis & Queue**:
  ```env
  QUEUE_CONNECTION=redis
  CACHE_STORE=redis
  ```

## **Usage**

1. **Start the development server**:
   ```bash
   php artisan serve
   ```

2. **Start the Vite dev server**:
   ```bash
   npm run dev
   ```

3. **Run the queue worker** (required for scheduling):
   ```bash
   php artisan queue:work
   ```

4. **Run the scheduler**:
   ```bash
   php artisan schedule:work
   ```

## **Development**

- **Linting**: `npm run lint`
- **Formatting**: `npm run format`
- **Testing**: `php artisan test`

## **License**

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
