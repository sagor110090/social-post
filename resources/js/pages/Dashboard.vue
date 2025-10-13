<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Link } from '@inertiajs/vue3';
import {
    AlertCircle,
    Calendar,
    CheckCircle,
    Clock,
    Crown,
    Edit,
    Eye,
    FileText,
    TrendingUp,
    Users,
} from 'lucide-vue-next';
import { computed } from 'vue';

const props = defineProps({
    stats: Object,
    recent_posts: Array,
    connected_accounts: Array,
    upcoming_posts: Array,
    analytics_summary: Object,
    subscription: Object,
    quick_actions: Array,
});

const engagementRate = computed(() => {
    if (!props.analytics_summary?.total_reach) return 0;
    return (
        (props.analytics_summary.total_engagement /
            props.analytics_summary.total_reach) *
        100
    ).toFixed(1);
});

const getStatusIcon = (status) => {
    switch (status) {
        case 'published':
            return CheckCircle;
        case 'scheduled':
            return Clock;
        case 'draft':
            return Edit;
        case 'failed':
            return AlertCircle;
        default:
            return FileText;
    }
};

const getStatusColor = (status) => {
    switch (status) {
        case 'published':
            return 'default';
        case 'scheduled':
            return 'secondary';
        case 'draft':
            return 'outline';
        case 'failed':
            return 'destructive';
        default:
            return 'outline';
    }
};

const getPlatformIcon = (platform) => {
    switch (platform) {
        case 'facebook':
            return '📘';
        case 'instagram':
            return '📷';
        case 'linkedin':
            return '💼';
        case 'twitter':
            return '🐦';
        default:
            return '🌐';
    }
};

const formatDate = (dateString) => {
    if (!dateString) return 'N/A';
    return new Date(dateString).toLocaleDateString();
};

const formatDateTime = (dateString) => {
    if (!dateString) return 'N/A';
    return new Date(dateString).toLocaleString();
};

const formatNumber = (num) => {
    if (num >= 1000000) return (num / 1000000).toFixed(1) + 'M';
    if (num >= 1000) return (num / 1000).toFixed(1) + 'K';
    return num?.toString() || '0';
};
</script>

<template>
    <AppLayout title="Dashboard">
        <div class="space-y-6">
            <!-- Welcome Section -->
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-3xl font-bold">Welcome back!</h1>
                    <p class="mt-2 text-muted-foreground">
                        Here's what's happening with your social media accounts
                        today.
                    </p>
                </div>

                <!-- Subscription Status -->
                <div v-if="subscription" class="text-right">
                    <Badge
                        :variant="
                            subscription.is_active ? 'default' : 'secondary'
                        "
                        class="mb-2"
                    >
                        {{
                            subscription.type.charAt(0).toUpperCase() +
                            subscription.type.slice(1)
                        }}
                        Plan
                    </Badge>
                    <p class="text-sm text-muted-foreground">
                        {{ subscription.is_active ? 'Active' : 'Inactive' }}
                    </p>
                </div>
            </div>

            <!-- Stats Grid -->
            <div class="grid grid-cols-1 gap-6 md:grid-cols-2 lg:grid-cols-4">
                <Card>
                    <CardHeader
                        class="flex flex-row items-center justify-between space-y-0 pb-2"
                    >
                        <CardTitle class="text-sm font-medium"
                            >Total Posts</CardTitle
                        >
                        <FileText class="h-4 w-4 text-muted-foreground" />
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold">
                            {{ stats.total_posts }}
                        </div>
                        <p class="text-xs text-muted-foreground">
                            {{ stats.published_posts }} published
                        </p>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader
                        class="flex flex-row items-center justify-between space-y-0 pb-2"
                    >
                        <CardTitle class="text-sm font-medium"
                            >Connected Accounts</CardTitle
                        >
                        <Users class="h-4 w-4 text-muted-foreground" />
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold">
                            {{ stats.connected_accounts }}
                        </div>
                        <p class="text-xs text-muted-foreground">
                            Active connections
                        </p>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader
                        class="flex flex-row items-center justify-between space-y-0 pb-2"
                    >
                        <CardTitle class="text-sm font-medium"
                            >Total Engagement</CardTitle
                        >
                        <TrendingUp class="h-4 w-4 text-muted-foreground" />
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold">
                            {{ formatNumber(stats.total_engagement) }}
                        </div>
                        <p class="text-xs text-muted-foreground">
                            {{ engagementRate }}% engagement rate
                        </p>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader
                        class="flex flex-row items-center justify-between space-y-0 pb-2"
                    >
                        <CardTitle class="text-sm font-medium"
                            >Scheduled</CardTitle
                        >
                        <Calendar class="h-4 w-4 text-muted-foreground" />
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold">
                            {{ stats.scheduled_posts }}
                        </div>
                        <p class="text-xs text-muted-foreground">
                            Posts queued
                        </p>
                    </CardContent>
                </Card>
            </div>

            <!-- Quick Actions -->
            <Card>
                <CardHeader>
                    <CardTitle>Quick Actions</CardTitle>
                    <CardDescription
                        >Get started with these common tasks</CardDescription
                    >
                </CardHeader>
                <CardContent>
                    <div
                        class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3"
                    >
                        <div
                            v-for="action in quick_actions"
                            :key="action.title"
                        >
                            <Link :href="action.href">
                                <Button
                                    variant="outline"
                                    class="relative flex h-20 w-full flex-col gap-2"
                                    :class="{
                                        'ring-2 ring-yellow-400':
                                            action.featured,
                                    }"
                                >
                                    <component
                                        :is="action.icon"
                                        class="h-6 w-6"
                                    />
                                    <div class="text-center">
                                        <span class="text-xs font-medium">{{
                                            action.title
                                        }}</span>
                                        <p
                                            class="text-xs text-muted-foreground"
                                        >
                                            {{ action.description }}
                                        </p>
                                    </div>
                                    <Crown
                                        v-if="action.featured"
                                        class="absolute top-1 right-1 h-4 w-4 text-yellow-500"
                                    />
                                </Button>
                            </Link>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
                <!-- Recent Posts -->
                <Card class="lg:col-span-2">
                    <CardHeader>
                        <div class="flex items-center justify-between">
                            <div>
                                <CardTitle>Recent Posts</CardTitle>
                                <CardDescription
                                    >Your latest social media
                                    activity</CardDescription
                                >
                            </div>
                            <Link href="/social/posts/history">
                                <Button variant="outline" size="sm">
                                    <Eye class="mr-2 h-4 w-4" />
                                    View All
                                </Button>
                            </Link>
                        </div>
                    </CardHeader>
                    <CardContent>
                        <div class="space-y-4">
                            <div
                                v-if="recent_posts.length === 0"
                                class="py-8 text-center text-muted-foreground"
                            >
                                <FileText
                                    class="mx-auto mb-4 h-12 w-12 opacity-50"
                                />
                                <p>
                                    No posts yet. Create your first post to get
                                    started!
                                </p>
                            </div>

                            <div
                                v-for="post in recent_posts"
                                :key="post.id"
                                class="flex items-center justify-between rounded-lg border p-4"
                            >
                                <div class="flex-1">
                                    <div class="mb-2 flex items-center gap-2">
                                        <component
                                            :is="getStatusIcon(post.status)"
                                            class="h-4 w-4"
                                        />
                                        <Badge
                                            :variant="
                                                getStatusColor(post.status)
                                            "
                                        >
                                            {{ post.status }}
                                        </Badge>
                                        <div class="flex gap-1">
                                            <span
                                                v-for="platform in post.platforms"
                                                :key="platform"
                                                class="text-lg"
                                            >
                                                {{ getPlatformIcon(platform) }}
                                            </span>
                                        </div>
                                    </div>
                                    <p class="mb-2 text-sm">
                                        {{ post.content }}
                                    </p>
                                    <div
                                        class="flex items-center gap-4 text-xs text-muted-foreground"
                                    >
                                        <span>{{
                                            formatDate(post.created_at)
                                        }}</span>
                                        <span v-if="post.scheduled_for">
                                            Scheduled:
                                            {{
                                                formatDateTime(
                                                    post.scheduled_for,
                                                )
                                            }}
                                        </span>
                                        <span v-if="post.engagement > 0">
                                            {{ formatNumber(post.engagement) }}
                                            engagement
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <!-- Connected Accounts & Upcoming Posts -->
                <div class="space-y-6">
                    <!-- Connected Accounts -->
                    <Card>
                        <CardHeader>
                            <div class="flex items-center justify-between">
                                <CardTitle>Connected Accounts</CardTitle>
                                <Link href="/social/accounts">
                                    <Button variant="outline" size="sm"
                                        >Manage</Button
                                    >
                                </Link>
                            </div>
                        </CardHeader>
                        <CardContent>
                            <div class="space-y-3">
                                <div
                                    v-if="connected_accounts.length === 0"
                                    class="py-4 text-center text-muted-foreground"
                                >
                                    <Users
                                        class="mx-auto mb-2 h-8 w-8 opacity-50"
                                    />
                                    <p class="text-sm">No accounts connected</p>
                                </div>

                                <div
                                    v-for="account in connected_accounts"
                                    :key="account.id"
                                    class="flex items-center justify-between rounded-lg border p-3"
                                >
                                    <div class="flex items-center gap-3">
                                        <span class="text-lg">{{
                                            getPlatformIcon(account.platform)
                                        }}</span>
                                        <div>
                                            <p class="text-sm font-medium">
                                                {{ account.username }}
                                            </p>
                                            <p
                                                class="text-xs text-muted-foreground"
                                            >
                                                {{
                                                    account.platform_display_name
                                                }}
                                            </p>
                                        </div>
                                    </div>
                                    <div class="text-right">
                                        <Badge
                                            variant="outline"
                                            class="text-xs"
                                        >
                                            {{
                                                account.is_token_expired
                                                    ? 'Expired'
                                                    : 'Active'
                                            }}
                                        </Badge>
                                    </div>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Upcoming Posts -->
                    <Card>
                        <CardHeader>
                            <CardTitle>Upcoming Posts</CardTitle>
                            <CardDescription
                                >Posts scheduled for the next few
                                days</CardDescription
                            >
                        </CardHeader>
                        <CardContent>
                            <div class="space-y-3">
                                <div
                                    v-if="upcoming_posts.length === 0"
                                    class="py-4 text-center text-muted-foreground"
                                >
                                    <Calendar
                                        class="mx-auto mb-2 h-8 w-8 opacity-50"
                                    />
                                    <p class="text-sm">No upcoming posts</p>
                                </div>

                                <div
                                    v-for="post in upcoming_posts"
                                    :key="post.id"
                                    class="rounded-lg border p-3"
                                >
                                    <div class="mb-2 flex items-center gap-2">
                                        <span class="text-lg">{{
                                            getPlatformIcon(post.platform)
                                        }}</span>
                                        <span
                                            class="text-xs text-muted-foreground"
                                            >@{{ post.account_username }}</span
                                        >
                                    </div>
                                    <p class="mb-2 text-sm">
                                        {{ post.content }}
                                    </p>
                                    <div
                                        class="flex items-center gap-2 text-xs text-muted-foreground"
                                    >
                                        <Clock class="h-3 w-3" />
                                        <span>{{
                                            formatDateTime(post.scheduled_for)
                                        }}</span>
                                    </div>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </div>

            <!-- Analytics Summary (if available) -->
            <Card v-if="analytics_summary.posts_with_analytics > 0">
                <CardHeader>
                    <CardTitle>Analytics Summary (Last 30 Days)</CardTitle>
                    <CardDescription
                        >Performance metrics across all
                        platforms</CardDescription
                    >
                </CardHeader>
                <CardContent>
                    <div class="grid grid-cols-2 gap-4 md:grid-cols-5">
                        <div class="text-center">
                            <div class="text-2xl font-bold">
                                {{
                                    formatNumber(analytics_summary.total_likes)
                                }}
                            </div>
                            <p class="text-sm text-muted-foreground">Likes</p>
                        </div>
                        <div class="text-center">
                            <div class="text-2xl font-bold">
                                {{
                                    formatNumber(
                                        analytics_summary.total_comments,
                                    )
                                }}
                            </div>
                            <p class="text-sm text-muted-foreground">
                                Comments
                            </p>
                        </div>
                        <div class="text-center">
                            <div class="text-2xl font-bold">
                                {{
                                    formatNumber(analytics_summary.total_shares)
                                }}
                            </div>
                            <p class="text-sm text-muted-foreground">Shares</p>
                        </div>
                        <div class="text-center">
                            <div class="text-2xl font-bold">
                                {{
                                    formatNumber(analytics_summary.total_reach)
                                }}
                            </div>
                            <p class="text-sm text-muted-foreground">Reach</p>
                        </div>
                        <div class="text-center">
                            <div class="text-2xl font-bold">
                                {{ engagementRate }}%
                            </div>
                            <p class="text-sm text-muted-foreground">
                                Engagement Rate
                            </p>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
