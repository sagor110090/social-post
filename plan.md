# AutoPost AI - Complete Web Application Development Plan

## 📋 Project Overview
AutoPost AI is a comprehensive social media management platform that allows users to connect multiple social media accounts, generate AI-powered posts, schedule content, and auto-publish across major platforms.

## 🏗️ Project Structure

```
social-post/
├── app/
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── Auth/
│   │   │   ├── Dashboard/
│   │   │   │   ├── DashboardController.php
│   │   │   │   ├── AnalyticsController.php
│   │   │   │   └── CalendarController.php
│   │   │   ├── Social/
│   │   │   │   ├── SocialAccountController.php
│   │   │   │   ├── OAuthController.php
│   │   │   │   └── PostController.php
│   │   │   ├── AI/
│   │   │   │   └── AIController.php
│   │   │   ├── Billing/
│   │   │   │   ├── SubscriptionController.php
│   │   │   │   └── PaymentController.php
│   │   │   └── Admin/
│   │   │       ├── AdminController.php
│   │   │       ├── UserController.php
│   │   │       └── PostManagementController.php
│   │   └── Middleware/
│   ├── Models/
│   │   ├── User.php
│   │   ├── SocialAccount.php
│   │   ├── Post.php
│   │   ├── ScheduledPost.php
│   │   ├── PostAnalytics.php
│   │   ├── Subscription.php
│   │   └── Team.php
│   ├── Jobs/
│   │   ├── PublishPostJob.php
│   │   ├── GenerateAIContentJob.php
│   │   └── ProcessAnalyticsJob.php
│   ├── Services/
│   │   ├── OpenAIService.php
│   │   ├── SocialPublishers/
│   │   │   ├── FacebookPublisher.php
│   │   │   ├── InstagramPublisher.php
│   │   │   ├── LinkedInPublisher.php
│   │   │   └── TwitterPublisher.php
│   │   ├── OAuth/
│   │   │   ├── FacebookOAuth.php
│   │   │   ├── InstagramOAuth.php
│   │   │   ├── LinkedInOAuth.php
│   │   │   └── TwitterOAuth.php
│   │   └── AnalyticsService.php
│   └── Policies/
├── database/
│   ├── migrations/
│   │   ├── create_social_accounts_table.php
│   │   ├── create_posts_table.php
│   │   ├── create_scheduled_posts_table.php
│   │   ├── create_post_analytics_table.php
│   │   ├── create_subscriptions_table.php
│   │   └── create_teams_table.php
│   └── seeders/
├── resources/
│   ├── js/
│   │   ├── Pages/
│   │   │   ├── Dashboard.vue
│   │   │   ├── Social/
│   │   │   │   ├── ConnectAccounts.vue
│   │   │   │   ├── PostComposer.vue
│   │   │   │   └── Calendar.vue
│   │   │   ├── AI/
│   │   │   │   └── AIGenerator.vue
│   │   │   ├── Analytics/
│   │   │   │   └── Analytics.vue
│   │   │   ├── Billing/
│   │   │   │   ├── Plans.vue
│   │   │   │   └── Subscription.vue
│   │   │   └── Admin/
│   │   │       ├── Dashboard.vue
│   │   │       ├── Users.vue
│   │   │       └── Posts.vue
│   │   ├── Components/
│   │   │   ├── UI/
│   │   │   ├── Social/
│   │   │   │   ├── AccountCard.vue
│   │   │   │   ├── PostPreview.vue
│   │   │   │   └── PlatformSelector.vue
│   │   │   ├── Calendar/
│   │   │   │   ├── CalendarView.vue
│   │   │   │   └── PostEvent.vue
│   │   │   └── Charts/
│   │   │       ├── AnalyticsChart.vue
│   │   │       └── EngagementChart.vue
│   │   └── Composables/
│   │       ├── useSocialAccounts.js
│   │       ├── useAI.js
│   │       ├── useCalendar.js
│   │       └── useAnalytics.js
│   └── views/
└── tests/
    ├── Feature/
    │   ├── Social/
    │   ├── AI/
    │   ├── Billing/
    │   └── Admin/
    └── Unit/
```

## 🔧 Backend Implementation

### 1. Database Models & Migrations

#### SocialAccount Model
```php
// database/migrations/create_social_accounts_table.php
Schema::create('social_accounts', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained()->onDelete('cascade');
    $table->string('platform'); // facebook, instagram, linkedin, twitter
    $table->string('platform_id');
    $table->string('username');
    $table->json('access_token'); // encrypted
    $table->json('refresh_token')->nullable(); // encrypted
    $table->timestamp('token_expires_at')->nullable();
    $table->boolean('is_active')->default(true);
    $table->timestamps();

    $table->unique(['user_id', 'platform', 'platform_id']);
});
```

#### Post Model
```php
// database/migrations/create_posts_table.php
Schema::create('posts', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained()->onDelete('cascade');
    $table->foreignId('team_id')->nullable()->constrained()->onDelete('cascade');
    $table->string('title')->nullable();
    $table->text('content');
    $table->json('hashtags')->nullable();
    $table->string('image_path')->nullable();
    $table->enum('status', ['draft', 'scheduled', 'published', 'failed'])->default('draft');
    $table->json('platforms'); // which platforms to publish to
    $table->timestamp('published_at')->nullable();
    $table->timestamps();
});
```

#### ScheduledPost Model
```php
// database/migrations/create_scheduled_posts_table.php
Schema::create('scheduled_posts', function (Blueprint $table) {
    $table->id();
    $table->foreignId('post_id')->constrained()->onDelete('cascade');
    $table->foreignId('social_account_id')->constrained()->onDelete('cascade');
    $table->timestamp('scheduled_for');
    $table->enum('status', ['pending', 'processing', 'completed', 'failed'])->default('pending');
    $table->text('error_message')->nullable();
    $table->timestamp('processed_at')->nullable();
    $table->timestamps();
});
```

### 2. Core Controllers

#### DashboardController
```php
// app/Http/Controllers/Dashboard/DashboardController.php
namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Post;
use App\Models\SocialAccount;
use App\Models\PostAnalytics;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index()
    {
        $user = auth()->user();

        return Inertia::render('Dashboard', [
            'stats' => [
                'total_posts' => $user->posts()->count(),
                'published_posts' => $user->posts()->where('status', 'published')->count(),
                'scheduled_posts' => $user->posts()->where('status', 'scheduled')->count(),
                'connected_accounts' => $user->socialAccounts()->where('is_active', true)->count(),
            ],
            'recent_posts' => $user->posts()
                ->with('analytics')
                ->latest()
                ->take(5)
                ->get(),
            'connected_accounts' => $user->socialAccounts()
                ->where('is_active', true)
                ->get(),
        ]);
    }
}
```

#### AIController
```php
// app/Http/Controllers/AI/AIController.php
namespace App\Http\Controllers\AI;

use App\Http\Controllers\Controller;
use App\Services\OpenAIService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AIController extends Controller
{
    public function __construct(private OpenAIService $openAI) {}

    public function generate(Request $request)
    {
        $request->validate([
            'prompt' => 'required|string|max:1000',
            'platform' => 'required|in:facebook,instagram,linkedin,twitter',
            'tone' => 'nullable|in:professional,casual,friendly,humorous',
            'include_hashtags' => 'boolean',
            'include_image' => 'boolean',
        ]);

        $result = $this->openAI->generatePost([
            'prompt' => $request->prompt,
            'platform' => $request->platform,
            'tone' => $request->tone ?? 'professional',
            'include_hashtags' => $request->include_hashtags ?? true,
            'include_image' => $request->include_image ?? false,
        ]);

        return response()->json($result);
    }

    public function index()
    {
        return Inertia::render('AI/AIGenerator');
    }
}
```

### 3. Services

#### OpenAIService
```php
// app/Services/OpenAIService.php
namespace App\Services;

use OpenAI;
use Illuminate\Support\Str;

class OpenAIService
{
    private $client;

    public function __construct()
    {
        $this->client = OpenAI::client(config('services.openai.api_key'));
    }

    public function generatePost(array $options): array
    {
        $prompt = $this->buildPrompt($options);

        $response = $this->client->chat()->create([
            'model' => 'gpt-4',
            'messages' => [
                [
                    'role' => 'system',
                    'content' => 'You are a social media expert who creates engaging content for various platforms.'
                ],
                [
                    'role' => 'user',
                    'content' => $prompt
                ]
            ],
            'max_tokens' => 500,
            'temperature' => 0.7,
        ]);

        $content = $response->choices[0]->message->content;

        return [
            'content' => $this->extractContent($content),
            'hashtags' => $options['include_hashtags'] ? $this->extractHashtags($content) : [],
            'image_prompt' => $options['include_image'] ? $this->generateImagePrompt($options['prompt']) : null,
        ];
    }

    private function buildPrompt(array $options): string
    {
        $platformRules = [
            'facebook' => 'Keep it conversational and engaging, suitable for Facebook\'s algorithm.',
            'instagram' => 'Make it visually appealing with emojis, perfect for Instagram captions.',
            'linkedin' => 'Keep it professional and industry-focused for LinkedIn.',
            'twitter' => 'Keep it concise and impactful for Twitter (280 characters max).',
        ];

        return "Create a {$options['tone']} social media post about: {$options['prompt']}.
                {$platformRules[$options['platform']]}
                " . ($options['include_hashtags'] ? 'Include relevant hashtags.' : '') . "
                Return the response in JSON format with 'content' and 'hashtags' fields.";
    }

    private function extractHashtags(string $content): array
    {
        preg_match_all('/#(\w+)/', $content, $matches);
        return $matches[1] ?? [];
    }

    private function extractContent(string $content): string
    {
        // Remove hashtags from content for clean text
        return preg_replace('/#(\w+)/', '', $content);
    }

    private function generateImagePrompt(string $originalPrompt): string
    {
        return "Create a professional, modern social media image related to: {$originalPrompt}.
                Style: clean, minimalist, business-oriented.";
    }
}
```

### 4. Jobs

#### PublishPostJob
```php
// app/Jobs/PublishPostJob.php
namespace App\Jobs;

use App\Models\Post;
use App\Models\ScheduledPost;
use App\Services\SocialPublishers\SocialPublisherFactory;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class PublishPostJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $backoff = [60, 300, 900];

    public function __construct(
        private ScheduledPost $scheduledPost
    ) {}

    public function handle(): void
    {
        $this->scheduledPost->update(['status' => 'processing']);

        try {
            $publisher = SocialPublisherFactory::create($this->scheduledPost->socialAccount->platform);

            $result = $publisher->publish(
                $this->scheduledPost->post,
                $this->scheduledPost->socialAccount
            );

            if ($result['success']) {
                $this->scheduledPost->update([
                    'status' => 'completed',
                    'processed_at' => now(),
                ]);

                // Update post status if all scheduled posts are completed
                $this->checkAndUpdatePostStatus();
            } else {
                throw new \Exception($result['error'] ?? 'Unknown error occurred');
            }

        } catch (\Exception $e) {
            $this->scheduledPost->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);

            if ($this->attempts() === $this->tries) {
                $this->checkAndUpdatePostStatus();
            }

            throw $e;
        }
    }

    private function checkAndUpdatePostStatus(): void
    {
        $post = $this->scheduledPost->post;
        $allCompleted = $post->scheduledPosts()
            ->where('status', '!=', 'completed')
            ->count() === 0;

        if ($allCompleted) {
            $post->update([
                'status' => 'published',
                'published_at' => now(),
            ]);
        }
    }
}
```

## 🎨 Frontend Implementation

### 1. Dashboard Page
```vue
<!-- resources/js/Pages/Dashboard.vue -->
<script setup>
import { Link } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { ref, computed } from 'vue'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/Components/ui/card'
import { Badge } from '@/Components/ui/badge'
import { Button } from '@/Components/ui/button'
import { Calendar, Users, FileText, Zap } from 'lucide-vue-next'

const props = defineProps({
    stats: Object,
    recentPosts: Array,
    connectedAccounts: Array,
})

const quickActions = [
    { title: 'Create Post', href: route('posts.create'), icon: FileText },
    { title: 'Connect Account', href: route('social.accounts.index'), icon: Users },
    { title: 'View Calendar', href: route('calendar.index'), icon: Calendar },
    { title: 'AI Generator', href: route('ai.generator'), icon: Zap },
]
</script>

<template>
    <AuthenticatedLayout title="Dashboard">
        <div class="space-y-6">
            <!-- Stats Grid -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                <Card>
                    <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle class="text-sm font-medium">Total Posts</CardTitle>
                        <FileText class="h-4 w-4 text-muted-foreground" />
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold">{{ stats.total_posts }}</div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle class="text-sm font-medium">Published</CardTitle>
                        <Zap class="h-4 w-4 text-muted-foreground" />
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold">{{ stats.published_posts }}</div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle class="text-sm font-medium">Scheduled</CardTitle>
                        <Calendar class="h-4 w-4 text-muted-foreground" />
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold">{{ stats.scheduled_posts }}</div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle class="text-sm font-medium">Connected Accounts</CardTitle>
                        <Users class="h-4 w-4 text-muted-foreground" />
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold">{{ stats.connected_accounts }}</div>
                    </CardContent>
                </Card>
            </div>

            <!-- Quick Actions -->
            <Card>
                <CardHeader>
                    <CardTitle>Quick Actions</CardTitle>
                    <CardDescription>Get started with these common tasks</CardDescription>
                </CardHeader>
                <CardContent>
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                        <Link v-for="action in quickActions" :key="action.title" :href="action.href">
                            <Button variant="outline" class="w-full h-20 flex flex-col gap-2">
                                <component :is="action.icon" class="h-6 w-6" />
                                <span class="text-xs">{{ action.title }}</span>
                            </Button>
                        </Link>
                    </div>
                </CardContent>
            </Card>

            <!-- Recent Posts -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <Card>
                    <CardHeader>
                        <CardTitle>Recent Posts</CardTitle>
                        <CardDescription>Your latest social media posts</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div class="space-y-4">
                            <div v-for="post in recentPosts" :key="post.id" class="flex items-center justify-between p-3 border rounded-lg">
                                <div class="flex-1">
                                    <p class="text-sm font-medium truncate">{{ post.content }}</p>
                                    <div class="flex items-center gap-2 mt-1">
                                        <Badge :variant="post.status === 'published' ? 'default' : 'secondary'">
                                            {{ post.status }}
                                        </Badge>
                                        <span class="text-xs text-muted-foreground">
                                            {{ new Date(post.created_at).toLocaleDateString() }}
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Connected Accounts</CardTitle>
                        <CardDescription>Your linked social media profiles</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div class="space-y-4">
                            <div v-for="account in connectedAccounts" :key="account.id" class="flex items-center justify-between p-3 border rounded-lg">
                                <div class="flex items-center gap-3">
                                    <div class="w-8 h-8 rounded-full bg-blue-100 flex items-center justify-center">
                                        <span class="text-xs font-bold">{{ account.platform[0].toUpperCase() }}</span>
                                    </div>
                                    <div>
                                        <p class="text-sm font-medium">{{ account.username }}</p>
                                        <p class="text-xs text-muted-foreground">{{ account.platform }}</p>
                                    </div>
                                </div>
                                <Badge variant="outline">Active</Badge>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
```

### 2. AI Generator Component
```vue
<!-- resources/js/Pages/AI/AIGenerator.vue -->
<script setup>
import { ref } from 'vue'
import { router } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/Components/ui/card'
import { Button } from '@/Components/ui/button'
import { Input } from '@/Components/ui/input'
import { Textarea } from '@/Components/ui/textarea'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/ui/select'
import { Badge } from '@/Components/ui/badge'
import { Loader2, Wand2, Copy, Edit } from 'lucide-vue-next'

const form = ref({
    prompt: '',
    platform: 'facebook',
    tone: 'professional',
    include_hashtags: true,
    include_image: false,
})

const generatedContent = ref(null)
const isGenerating = ref(false)

const platforms = [
    { value: 'facebook', label: 'Facebook' },
    { value: 'instagram', label: 'Instagram' },
    { value: 'linkedin', label: 'LinkedIn' },
    { value: 'twitter', label: 'Twitter/X' },
]

const tones = [
    { value: 'professional', label: 'Professional' },
    { value: 'casual', label: 'Casual' },
    { value: 'friendly', label: 'Friendly' },
    { value: 'humorous', label: 'Humorous' },
]

const generateContent = async () => {
    isGenerating.value = true

    try {
        const response = await fetch('/api/ai/generate', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
            },
            body: JSON.stringify(form.value),
        })

        const data = await response.json()
        generatedContent.value = data
    } catch (error) {
        console.error('Error generating content:', error)
    } finally {
        isGenerating.value = false
    }
}

const useContent = () => {
    router.post('/posts/create', {
        content: generatedContent.value.content,
        hashtags: generatedContent.value.hashtags,
        platforms: [form.value.platform],
    })
}

const copyToClipboard = (text) => {
    navigator.clipboard.writeText(text)
}
</script>

<template>
    <AuthenticatedLayout title="AI Content Generator">
        <div class="max-w-4xl mx-auto space-y-6">
            <Card>
                <CardHeader>
                    <CardTitle class="flex items-center gap-2">
                        <Wand2 class="h-5 w-5" />
                        AI Content Generator
                    </CardTitle>
                    <CardDescription>
                        Generate engaging social media content powered by AI
                    </CardDescription>
                </CardHeader>
                <CardContent class="space-y-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="space-y-2">
                            <label class="text-sm font-medium">Platform</label>
                            <Select v-model="form.platform">
                                <SelectTrigger>
                                    <SelectValue placeholder="Select platform" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem v-for="platform in platforms" :key="platform.value" :value="platform.value">
                                        {{ platform.label }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </div>

                        <div class="space-y-2">
                            <label class="text-sm font-medium">Tone</label>
                            <Select v-model="form.tone">
                                <SelectTrigger>
                                    <SelectValue placeholder="Select tone" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem v-for="tone in tones" :key="tone.value" :value="tone.value">
                                        {{ tone.label }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                    </div>

                    <div class="space-y-2">
                        <label class="text-sm font-medium">What do you want to post about?</label>
                        <Textarea
                            v-model="form.prompt"
                            placeholder="e.g., Launching our new product line this week..."
                            rows="4"
                        />
                    </div>

                    <div class="flex items-center gap-6">
                        <label class="flex items-center gap-2">
                            <input
                                type="checkbox"
                                v-model="form.include_hashtags"
                                class="rounded"
                            />
                            <span class="text-sm">Include hashtags</span>
                        </label>

                        <label class="flex items-center gap-2">
                            <input
                                type="checkbox"
                                v-model="form.include_image"
                                class="rounded"
                            />
                            <span class="text-sm">Generate image idea</span>
                        </label>
                    </div>

                    <Button
                        @click="generateContent"
                        :disabled="!form.prompt || isGenerating"
                        class="w-full"
                    >
                        <Loader2 v-if="isGenerating" class="mr-2 h-4 w-4 animate-spin" />
                        <Wand2 v-else class="mr-2 h-4 w-4" />
                        {{ isGenerating ? 'Generating...' : 'Generate Content' }}
                    </Button>
                </CardContent>
            </Card>

            <Card v-if="generatedContent">
                <CardHeader>
                    <CardTitle>Generated Content</CardTitle>
                    <CardDescription>Review and use your AI-generated content</CardDescription>
                </CardHeader>
                <CardContent class="space-y-4">
                    <div class="p-4 bg-gray-50 rounded-lg">
                        <div class="flex items-center justify-between mb-2">
                            <span class="text-sm font-medium">Content</span>
                            <Button variant="ghost" size="sm" @click="copyToClipboard(generatedContent.content)">
                                <Copy class="h-4 w-4" />
                            </Button>
                        </div>
                        <p class="text-sm">{{ generatedContent.content }}</p>
                    </div>

                    <div v-if="generatedContent.hashtags?.length" class="p-4 bg-gray-50 rounded-lg">
                        <div class="flex items-center justify-between mb-2">
                            <span class="text-sm font-medium">Hashtags</span>
                            <Button variant="ghost" size="sm" @click="copyToClipboard(generatedContent.hashtags.map(tag => `#${tag}`).join(' '))">
                                <Copy class="h-4 w-4" />
                            </Button>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <Badge v-for="tag in generatedContent.hashtags" :key="tag" variant="secondary">
                                #{{ tag }}
                            </Badge>
                        </div>
                    </div>

                    <div v-if="generatedContent.image_prompt" class="p-4 bg-gray-50 rounded-lg">
                        <span class="text-sm font-medium">Image Idea</span>
                        <p class="text-sm mt-1">{{ generatedContent.image_prompt }}</p>
                    </div>

                    <div class="flex gap-2">
                        <Button @click="useContent" class="flex-1">
                            <Edit class="mr-2 h-4 w-4" />
                            Use This Content
                        </Button>
                        <Button variant="outline" @click="generatedContent = null">
                            Generate Again
                        </Button>
                    </div>
                </CardContent>
            </Card>
        </div>
    </AuthenticatedLayout>
</template>
```

### 3. Calendar Component
```vue
<!-- resources/js/Pages/Social/Calendar.vue -->
<script setup>
import { ref, computed, onMounted } from 'vue'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/Components/ui/card'
import { Button } from '@/Components/ui/button'
import { Badge } from '@/Components/ui/badge'
import { ChevronLeft, ChevronRight, Plus } from 'lucide-vue-next'

const currentDate = ref(new Date())
const selectedDate = ref(null)
const posts = ref([])

const currentMonth = computed(() => {
    return currentDate.value.toLocaleDateString('en-US', { month: 'long', year: 'numeric' })
})

const calendarDays = computed(() => {
    const year = currentDate.value.getFullYear()
    const month = currentDate.value.getMonth()
    const firstDay = new Date(year, month, 1)
    const lastDay = new Date(year, month + 1, 0)
    const startDate = new Date(firstDay)
    startDate.setDate(startDate.getDate() - firstDay.getDay())

    const days = []
    const current = new Date(startDate)

    while (current <= lastDay || current.getDay() !== 0) {
        days.push({
            date: new Date(current),
            isCurrentMonth: current.getMonth() === month,
            posts: getPostsForDate(current),
        })
        current.setDate(current.getDate() + 1)
    }

    return days
})

const getPostsForDate = (date) => {
    const dateStr = date.toISOString().split('T')[0]
    return posts.value.filter(post => {
        const postDate = new Date(post.scheduled_for).toISOString().split('T')[0]
        return postDate === dateStr
    })
}

const previousMonth = () => {
    currentDate.value = new Date(currentDate.value.getFullYear(), currentDate.value.getMonth() - 1)
}

const nextMonth = () => {
    currentDate.value = new Date(currentDate.value.getFullYear(), currentDate.value.getMonth() + 1)
}

const fetchPosts = async () => {
    // Fetch posts for the current month
    const response = await fetch(`/api/posts/calendar?month=${currentDate.value.getMonth() + 1}&year=${currentDate.value.getFullYear()}`)
    posts.value = await response.json()
}

onMounted(() => {
    fetchPosts()
})
</script>

<template>
    <AuthenticatedLayout title="Content Calendar">
        <div class="space-y-6">
            <Card>
                <CardHeader>
                    <div class="flex items-center justify-between">
                        <div>
                            <CardTitle>Content Calendar</CardTitle>
                            <CardDescription>View and manage your scheduled posts</CardDescription>
                        </div>
                        <Button>
                            <Plus class="mr-2 h-4 w-4" />
                            Schedule Post
                        </Button>
                    </div>
                </CardHeader>
                <CardContent>
                    <div class="flex items-center justify-between mb-6">
                        <Button variant="outline" @click="previousMonth">
                            <ChevronLeft class="h-4 w-4" />
                        </Button>
                        <h2 class="text-lg font-semibold">{{ currentMonth }}</h2>
                        <Button variant="outline" @click="nextMonth">
                            <ChevronRight class="h-4 w-4" />
                        </Button>
                    </div>

                    <div class="grid grid-cols-7 gap-px bg-gray-200 rounded-lg overflow-hidden">
                        <!-- Day headers -->
                        <div v-for="day in ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat']" :key="day" class="bg-gray-50 p-2 text-center text-sm font-medium text-gray-700">
                            {{ day }}
                        </div>

                        <!-- Calendar days -->
                        <div
                            v-for="day in calendarDays"
                            :key="day.date.toISOString()"
                            :class="[
                                'bg-white p-2 min-h-[100px] cursor-pointer hover:bg-gray-50',
                                !day.isCurrentMonth && 'bg-gray-50 text-gray-400'
                            ]"
                            @click="selectedDate = day.date"
                        >
                            <div class="text-sm font-medium mb-1">{{ day.date.getDate() }}</div>
                            <div class="space-y-1">
                                <div
                                    v-for="post in day.posts.slice(0, 2)"
                                    :key="post.id"
                                    class="text-xs p-1 rounded bg-blue-100 text-blue-800 truncate"
                                >
                                    {{ post.content.substring(0, 20) }}...
                                </div>
                                <div v-if="day.posts.length > 2" class="text-xs text-gray-500">
                                    +{{ day.posts.length - 2 }} more
                                </div>
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>
    </AuthenticatedLayout>
</template>
```

## 🔐 Authentication & Authorization

### Laravel Breeze Configuration
```bash
# Install Laravel Breeze with Inertia stack
composer require laravel/breeze --dev
php artisan breeze:install inertia

# Install dependencies
npm install
npm run build
php artisan migrate
```

### Subscription Middleware
```php
// app/Http/Middleware/CheckSubscription.php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckSubscription
{
    public function handle(Request $request, Closure $next, string $plan): Response
    {
        $user = $request->user();

        if (!$user->subscription || !$user->subscription->active()) {
            return redirect()->route('billing.plans')->with('error', 'Please subscribe to access this feature.');
        }

        if ($plan === 'pro' && !$user->subscription->isPro()) {
            return redirect()->route('billing.plans')->with('error', 'This feature requires a Pro subscription.');
        }

        if ($plan === 'agency' && !$user->subscription->isAgency()) {
            return redirect()->route('billing.plans')->with('error', 'This feature requires an Agency subscription.');
        }

        return $next($request);
    }
}
```

## 💳 Stripe Integration

### Subscription Plans Configuration
```php
// config/services.php
'stripe' => [
    'key' => env('STRIPE_KEY'),
    'secret' => env('STRIPE_SECRET'),
    'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
    'plans' => [
        'free' => [
            'name' => 'Free',
            'price' => 0,
            'features' => ['3 posts per month', '1 social account'],
        ],
        'pro' => [
            'name' => 'Pro',
            'price' => 29,
            'stripe_price_id' => env('STRIPE_PRO_PRICE_ID'),
            'features' => ['Unlimited posts', '5 social accounts', 'AI generator', 'Analytics'],
        ],
        'agency' => [
            'name' => 'Agency',
            'price' => 99,
            'stripe_price_id' => env('STRIPE_AGENCY_PRICE_ID'),
            'features' => ['Everything in Pro', 'Unlimited accounts', 'Team collaboration', 'White label'],
        ],
    ],
],
```

### SubscriptionController
```php
// app/Http/Controllers/Billing/SubscriptionController.php
namespace App\Http\Controllers\Billing;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Laravel\Cashier\Cashier;

class SubscriptionController extends Controller
{
    public function index()
    {
        return Inertia::render('Billing/Plans', [
            'plans' => config('services.stripe.plans'),
            'currentSubscription' => auth()->user()->subscription,
        ]);
    }

    public function subscribe(Request $request)
    {
        $request->validate([
            'plan' => 'required|in:pro,agency',
        ]);

        $user = auth()->user();
        $plan = config("services.stripe.plans.{$request->plan}");

        return $user->newSubscription('default', $plan['stripe_price_id'])
            ->checkout([
                'success_url' => route('billing.success'),
                'cancel_url' => route('billing.plans'),
            ]);
    }

    public function portal()
    {
        return auth()->user()->redirectToBillingPortal();
    }
}
```

## 🗄️ Environment Configuration

### .env Configuration
```env
# App Configuration
APP_NAME="AutoPost AI"
APP_ENV=local
APP_KEY=base64:your-app-key
APP_DEBUG=true
APP_URL=http://localhost:8000

# Database
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=autopost_ai
DB_USERNAME=root
DB_PASSWORD=password

# Redis
REDIS_CLIENT=phpredis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379

# Queue
QUEUE_CONNECTION=redis

# OpenAI
OPENAI_API_KEY=sk-your-openai-key

# Stripe
STRIPE_KEY=pk_your-stripe-key
STRIPE_SECRET=sk_your-stripe-secret
STRIPE_WEBHOOK_SECRET=whsec_your-webhook-secret
STRIPE_PRO_PRICE_ID=price_pro_id
STRIPE_AGENCY_PRICE_ID=price_agency_id

# Social Media APIs
FACEBOOK_CLIENT_ID=your-facebook-app-id
FACEBOOK_CLIENT_SECRET=your-facebook-app-secret
FACEBOOK_REDIRECT_URI=http://localhost:8000/auth/facebook/callback

INSTAGRAM_CLIENT_ID=your-instagram-app-id
INSTAGRAM_CLIENT_SECRET=your-instagram-app-secret

LINKEDIN_CLIENT_ID=your-linkedin-app-id
LINKEDIN_CLIENT_SECRET=your-linkedin-app-secret

TWITTER_CLIENT_ID=your-twitter-app-id
TWITTER_CLIENT_SECRET=your-twitter-app-secret
TWITTER_CLIENT_BEARER_TOKEN=your-twitter-bearer-token

# File Storage
FILESYSTEM_DISK=public

# Mail
MAIL_MAILER=smtp
MAIL_HOST=mailpit
MAIL_PORT=1025
MAIL_USERNAME=null
MAIL_PASSWORD=null
MAIL_ENCRYPTION=null
MAIL_FROM_ADDRESS="hello@autopost.ai"
MAIL_FROM_NAME="${APP_NAME}"
```

## 🚀 Deployment & Production Setup

### Required Packages
```bash
# Core Laravel packages
composer require laravel/cashier
composer require laravel/socialite
composer require spatie/laravel-crypto

# Queue and caching
composer require predis/predis

# Image processing
composer require intervention/image

# Additional services
composer require pusher/pusher-php-server
```

### Frontend Dependencies
```bash
npm install @headlessui/vue
npm install @vueuse/core
npm install date-fns
npm install fullcalendar
npm install @fullcalendar/vue3
npm install chart.js
npm install vue-chartjs
```

### Queue Configuration
```php
// config/queue.php
'connections' => [
    'redis' => [
        'driver' => 'redis',
        'connection' => 'default',
        'queue' => env('REDIS_QUEUE', 'default'),
        'retry_after' => 90,
        'block_for' => null,
    ],
],

'failed' => [
    'driver' => 'database',
    'table' => 'failed_jobs',
    'database' => env('DB_CONNECTION', 'mysql'),
],
```

### Scheduler Setup
```bash
# Add to crontab
* * * * * cd /path-to-your-project && php artisan schedule:run >> /dev/null 2>&1

# Start queue workers
php artisan queue:work --tries=3 --backoff=60,300,900
```

## 📊 Analytics Implementation

### AnalyticsService
```php
// app/Services/AnalyticsService.php
namespace App\Services;

use App\Models\Post;
use App\Models\PostAnalytics;
use Illuminate\Support\Collection;

class AnalyticsService
{
    public function getPostAnalytics(int $postId): array
    {
        $post = Post::with(['analytics', 'scheduledPosts.socialAccount'])->findOrFail($postId);

        return [
            'post' => $post,
            'total_engagement' => $post->analytics->sum('engagement'),
            'total_reach' => $post->analytics->sum('reach'),
            'platform_performance' => $this->getPlatformPerformance($post),
            'engagement_trend' => $this->getEngagementTrend($post),
        ];
    }

    private function getPlatformPerformance(Post $post): Collection
    {
        return $post->scheduledPosts()
            ->with(['socialAccount', 'analytics'])
            ->get()
            ->map(function ($scheduledPost) {
                return [
                    'platform' => $scheduledPost->socialAccount->platform,
                    'engagement' => $scheduledPost->analytics->sum('engagement'),
                    'reach' => $scheduledPost->analytics->sum('reach'),
                ];
            });
    }

    private function getEngagementTrend(Post $post): array
    {
        return $post->analytics()
            ->orderBy('recorded_at')
            ->get()
            ->groupBy(function ($item) {
                return $item->recorded_at->format('Y-m-d');
            })
            ->map(function ($day) {
                return $day->sum('engagement');
            })
            ->toArray();
    }
}
```

## 🔒 Security Considerations

1. **Token Encryption**: Store OAuth tokens encrypted using Laravel's encryption
2. **API Rate Limiting**: Implement rate limiting for social media APIs
3. **Input Validation**: Validate all user inputs and AI prompts
4. **CORS Configuration**: Properly configure CORS for API endpoints
5. **Content Moderation**: Implement content filtering for AI-generated content

## 🧪 Testing Strategy

### Feature Tests
```php
// tests/Feature/AIContentGenerationTest.php
namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

class AIContentGenerationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_generate_ai_content()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->post('/api/ai/generate', [
                'prompt' => 'Test post about new product',
                'platform' => 'facebook',
                'tone' => 'professional',
            ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'content',
                'hashtags',
            ]);
    }
}
```

## 📈 Performance Optimization

1. **Database Indexing**: Add indexes on frequently queried columns
2. **Caching**: Cache social media API responses and analytics
3. **Queue Optimization**: Use Redis queues for better performance
4. **Image Optimization**: Compress and optimize uploaded images
5. **CDN**: Use CDN for static assets and user uploads
