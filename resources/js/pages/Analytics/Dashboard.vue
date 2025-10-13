<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import AppLayout from '@/layouts/AppLayout.vue';
import { router } from '@inertiajs/vue3';
import {
    ArcElement,
    BarElement,
    CategoryScale,
    Legend as ChartLegend,
    Tooltip as ChartTooltip,
    LinearScale,
    LineElement,
    PointElement,
    Title,
} from 'chart.js';
import {
    Calendar,
    Download,
    Eye,
    Filter,
    Heart,
    TrendingUp,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { Bar, Line, Pie } from 'vue-chartjs';

ChartJS.register(
    CategoryScale,
    LinearScale,
    PointElement,
    LineElement,
    BarElement,
    Title,
    ChartTooltip,
    ChartLegend,
    ArcElement,
);

interface AnalyticsData {
    total_posts: number;
    published_posts: number;
    scheduled_posts: number;
    total_engagement: number;
    total_reach: number;
    best_performing_post: any;
    platform_performance: Record<string, any>;
    engagement_over_time: any[];
    post_types_performance: Record<string, any>;
}

const props = defineProps<{
    analytics: AnalyticsData;
    date_range: {
        start_date: string;
        end_date: string;
    };
}>();

const loading = ref(false);
const showFilters = ref(false);
const dateRange = ref({
    start: props.date_range.start_date,
    end: props.date_range.end_date,
});

const engagementChartData = computed(() => ({
    labels: props.analytics.engagement_over_time.map((item) =>
        new Date(item.period).toLocaleDateString(),
    ),
    datasets: [
        {
            label: 'Engagement',
            data: props.analytics.engagement_over_time.map(
                (item) => item.engagement,
            ),
            borderColor: 'rgb(59, 130, 246)',
            backgroundColor: 'rgba(59, 130, 246, 0.1)',
            tension: 0.4,
        },
        {
            label: 'Posts',
            data: props.analytics.engagement_over_time.map(
                (item) => item.posts,
            ),
            borderColor: 'rgb(16, 185, 129)',
            backgroundColor: 'rgba(16, 185, 129, 0.1)',
            tension: 0.4,
        },
    ],
}));

const platformChartData = computed(() => ({
    labels: Object.keys(props.analytics.platform_performance),
    datasets: [
        {
            label: 'Engagement',
            data: Object.values(props.analytics.platform_performance).map(
                (p: any) => p.total_engagement,
            ),
            backgroundColor: [
                'rgba(59, 130, 246, 0.8)',
                'rgba(29, 161, 242, 0.8)',
                'rgba(228, 64, 95, 0.8)',
                'rgba(0, 119, 181, 0.8)',
            ],
        },
    ],
}));

const postTypeChartData = computed(() => ({
    labels: Object.keys(props.analytics.post_types_performance),
    datasets: [
        {
            label: 'Average Engagement',
            data: Object.values(props.analytics.post_types_performance).map(
                (p: any) => p.average_engagement,
            ),
            backgroundColor: 'rgba(147, 51, 234, 0.8)',
        },
    ],
}));

const pieChartData = computed(() => ({
    labels: Object.keys(props.analytics.platform_performance),
    datasets: [
        {
            data: Object.values(props.analytics.platform_performance).map(
                (p: any) => p.posts_count,
            ),
            backgroundColor: [
                'rgba(59, 130, 246, 0.8)',
                'rgba(29, 161, 242, 0.8)',
                'rgba(228, 64, 95, 0.8)',
                'rgba(0, 119, 181, 0.8)',
            ],
        },
    ],
}));

function applyFilters() {
    loading.value = true;
    router.get(
        route('analytics.dashboard'),
        {
            start_date: dateRange.value.start,
            end_date: dateRange.value.end,
        },
        {
            preserveState: true,
            onFinish: () => {
                loading.value = false;
            },
        },
    );
}

function exportData() {
    router.post(route('analytics.export'), {
        start_date: dateRange.value.start,
        end_date: dateRange.value.end,
        format: 'csv',
    });
}

function goToPost(postId: number) {
    router.visit(route('social.posts.show', postId));
}

const formatNumber = (num: number) => {
    if (num >= 1000000) {
        return (num / 1000000).toFixed(1) + 'M';
    } else if (num >= 1000) {
        return (num / 1000).toFixed(1) + 'K';
    }
    return num.toString();
};

const setQuickDate = (period: string) => {
    const end = new Date();
    const start = new Date();

    switch (period) {
        case '7d':
            start.setDate(end.getDate() - 7);
            break;
        case '30d':
            start.setDate(end.getDate() - 30);
            break;
        case '90d':
            start.setDate(end.getDate() - 90);
            break;
        case '1y':
            start.setFullYear(end.getFullYear() - 1);
            break;
    }

    dateRange.value.start = start.toISOString().split('T')[0];
    dateRange.value.end = end.toISOString().split('T')[0];
    applyFilters();
};
</script>

<template>
    <Head title="Analytics Dashboard" />

    <AppLayout layout="sidebar">
        <div class="flex h-full flex-1 flex-col space-y-8 p-6">
            <!-- Enhanced Header -->
            <div
                class="flex flex-col gap-6 lg:flex-row lg:items-center lg:justify-between"
            >
                <div>
                    <h1
                        class="text-display-2 mb-2 text-neutral-900 dark:text-white"
                    >
                        Analytics
                        <span class="text-gradient">Dashboard</span> 📊
                    </h1>
                    <p
                        class="text-body-large text-neutral-600 dark:text-neutral-400"
                    >
                        Track your social media performance and engagement
                        across all platforms
                    </p>
                </div>
                <div class="flex gap-3">
                    <Button
                        @click="showFilters = !showFilters"
                        variant="outline"
                        class="btn-secondary"
                    >
                        <Filter class="mr-2 h-4 w-4" />
                        {{ showFilters ? 'Hide' : 'Show' }} Filters
                    </Button>
                    <Button
                        @click="exportData"
                        variant="outline"
                        class="btn-secondary"
                    >
                        <Download class="mr-2 h-4 w-4" />
                        Export Data
                    </Button>
                </div>
            </div>

            <!-- Enhanced Date Filters -->
            <div
                v-if="showFilters"
                class="rounded-xl border bg-card text-card-foreground shadow-sm"
            >
                <div class="mb-6">
                    <h2
                        class="text-headline-3 mb-2 text-neutral-900 dark:text-white"
                    >
                        Date Range
                    </h2>
                    <p class="text-body text-neutral-600 dark:text-neutral-400">
                        Select the time period for your analytics data
                    </p>
                </div>
                <div class="grid grid-cols-1 gap-6 md:grid-cols-3">
                    <div>
                        <label
                            class="text-body mb-2 block font-medium text-neutral-700 dark:text-neutral-300"
                            >Start Date</label
                        >
                        <input
                            v-model="dateRange.start"
                            type="date"
                            class="input-field w-full"
                        />
                    </div>
                    <div>
                        <label
                            class="text-body mb-2 block font-medium text-neutral-700 dark:text-neutral-300"
                            >End Date</label
                        >
                        <input
                            v-model="dateRange.end"
                            type="date"
                            class="input-field w-full"
                        />
                    </div>
                    <div class="flex items-end">
                        <Button
                            @click="applyFilters"
                            :disabled="loading"
                            class="btn-primary w-full"
                        >
                            <Calendar class="mr-2 h-4 w-4" />
                            Apply Filters
                        </Button>
                    </div>
                </div>

                <!-- Quick Date Options -->
                <div
                    class="mt-6 border-t border-neutral-200 pt-6 dark:border-neutral-700"
                >
                    <p
                        class="text-body-small mb-3 text-neutral-600 dark:text-neutral-400"
                    >
                        Quick select:
                    </p>
                    <div class="flex flex-wrap gap-2">
                        <Button
                            variant="outline"
                            size="sm"
                            @click="setQuickDate('7d')"
                            class="text-xs"
                        >
                            Last 7 days
                        </Button>
                        <Button
                            variant="outline"
                            size="sm"
                            @click="setQuickDate('30d')"
                            class="text-xs"
                        >
                            Last 30 days
                        </Button>
                        <Button
                            variant="outline"
                            size="sm"
                            @click="setQuickDate('90d')"
                            class="text-xs"
                        >
                            Last 90 days
                        </Button>
                        <Button
                            variant="outline"
                            size="sm"
                            @click="setQuickDate('1y')"
                            class="text-xs"
                        >
                            Last year
                        </Button>
                    </div>
                </div>
            </div>

            <!-- Enhanced Overview Cards -->
            <div class="grid gap-6 md:grid-cols-2 lg:grid-cols-4">
                <!-- Total Posts Card -->
                <div
                    class="hover-lift group rounded-xl border bg-card text-card-foreground shadow-sm"
                >
                    <div class="mb-4 flex items-center justify-between">
                        <div
                            class="flex h-12 w-12 items-center justify-center rounded-xl bg-blue-100 transition-transform group-hover:scale-110 dark:bg-blue-900/30"
                        >
                            <FileText
                                class="h-6 w-6 text-blue-600 dark:text-blue-400"
                            />
                        </div>
                        <div
                            class="flex items-center gap-1 text-green-600 dark:text-green-400"
                        >
                            <TrendingUp class="h-4 w-4" />
                            <span class="text-xs font-medium">+15%</span>
                        </div>
                    </div>
                    <div>
                        <h3
                            class="text-2xl font-bold text-neutral-900 dark:text-white"
                        >
                            {{ analytics.total_posts || 0 }}
                        </h3>
                        <p
                            class="mt-1 text-sm text-neutral-600 dark:text-neutral-400"
                        >
                            Total Posts
                        </p>
                        <p
                            class="mt-1 text-xs text-neutral-500 dark:text-neutral-500"
                        >
                            {{ analytics.published_posts || 0 }} published,
                            {{ analytics.scheduled_posts || 0 }} scheduled
                        </p>
                    </div>
                </div>

                <!-- Total Engagement Card -->
                <div
                    class="hover-lift group rounded-xl border bg-card text-card-foreground shadow-sm"
                >
                    <div class="mb-4 flex items-center justify-between">
                        <div
                            class="flex h-12 w-12 items-center justify-center rounded-xl bg-pink-100 transition-transform group-hover:scale-110 dark:bg-pink-900/30"
                        >
                            <Heart
                                class="h-6 w-6 text-pink-600 dark:text-pink-400"
                            />
                        </div>
                        <div
                            class="flex items-center gap-1 text-green-600 dark:text-green-400"
                        >
                            <TrendingUp class="h-4 w-4" />
                            <span class="text-xs font-medium">+28%</span>
                        </div>
                    </div>
                    <div>
                        <h3
                            class="text-2xl font-bold text-neutral-900 dark:text-white"
                        >
                            {{ formatNumber(analytics.total_engagement || 0) }}
                        </h3>
                        <p
                            class="mt-1 text-sm text-neutral-600 dark:text-neutral-400"
                        >
                            Total Engagement
                        </p>
                        <p
                            class="mt-1 text-xs text-neutral-500 dark:text-neutral-500"
                        >
                            Likes, comments, and shares
                        </p>
                    </div>
                </div>

                <!-- Total Reach Card -->
                <div
                    class="hover-lift group rounded-xl border bg-card text-card-foreground shadow-sm"
                >
                    <div class="mb-4 flex items-center justify-between">
                        <div
                            class="flex h-12 w-12 items-center justify-center rounded-xl bg-purple-100 transition-transform group-hover:scale-110 dark:bg-purple-900/30"
                        >
                            <Eye
                                class="h-6 w-6 text-purple-600 dark:text-purple-400"
                            />
                        </div>
                        <div
                            class="flex items-center gap-1 text-green-600 dark:text-green-400"
                        >
                            <TrendingUp class="h-4 w-4" />
                            <span class="text-xs font-medium">+42%</span>
                        </div>
                    </div>
                    <div>
                        <h3
                            class="text-2xl font-bold text-neutral-900 dark:text-white"
                        >
                            {{ formatNumber(analytics.total_reach || 0) }}
                        </h3>
                        <p
                            class="mt-1 text-sm text-neutral-600 dark:text-neutral-400"
                        >
                            Total Reach
                        </p>
                        <p
                            class="mt-1 text-xs text-neutral-500 dark:text-neutral-500"
                        >
                            Unique users reached
                        </p>
                    </div>
                </div>

                <!-- Engagement Rate Card -->
                <div
                    class="hover-lift group rounded-xl border bg-card text-card-foreground shadow-sm"
                >
                    <div class="mb-4 flex items-center justify-between">
                        <div
                            class="flex h-12 w-12 items-center justify-center rounded-xl bg-green-100 transition-transform group-hover:scale-110 dark:bg-green-900/30"
                        >
                            <TrendingUp
                                class="h-6 w-6 text-green-600 dark:text-green-400"
                            />
                        </div>
                        <div
                            class="flex items-center gap-1 text-green-600 dark:text-green-400"
                        >
                            <TrendingUp class="h-4 w-4" />
                            <span class="text-xs font-medium">+8%</span>
                        </div>
                    </div>
                    <div>
                        <h3
                            class="text-2xl font-bold text-neutral-900 dark:text-white"
                        >
                            {{
                                analytics.total_reach > 0
                                    ? (
                                          (analytics.total_engagement /
                                              analytics.total_reach) *
                                          100
                                      ).toFixed(2)
                                    : '0'
                            }}%
                        </h3>
                        <p
                            class="mt-1 text-sm text-neutral-600 dark:text-neutral-400"
                        >
                            Engagement Rate
                        </p>
                        <p
                            class="mt-1 text-xs text-neutral-500 dark:text-neutral-500"
                        >
                            Average engagement rate
                        </p>
                    </div>
                </div>
            </div>

            <!-- Best Performing Post -->
            <Card v-if="analytics.best_performing_post">
                <CardHeader>
                    <CardTitle>Best Performing Post</CardTitle>
                    <CardDescription>
                        Your top performing post in the selected period
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <div class="flex items-center justify-between">
                        <div class="flex-1">
                            <p class="mb-2 text-sm font-medium">
                                {{ analytics.best_performing_post.content }}
                            </p>
                            <div
                                class="flex items-center gap-4 text-sm text-muted-foreground"
                            >
                                <span class="flex items-center gap-1">
                                    <Badge variant="secondary">{{
                                        analytics.best_performing_post.platform
                                    }}</Badge>
                                </span>
                                <span>{{
                                    new Date(
                                        analytics.best_performing_post.created_at,
                                    ).toLocaleDateString()
                                }}</span>
                                <span class="flex items-center gap-1">
                                    <Heart class="h-3 w-3" />
                                    {{
                                        formatNumber(
                                            analytics.best_performing_post
                                                .total_engagement,
                                        )
                                    }}
                                    engagement
                                </span>
                            </div>
                        </div>
                        <Button
                            @click="goToPost(analytics.best_performing_post.id)"
                            variant="outline"
                        >
                            View Post
                        </Button>
                    </div>
                </CardContent>
            </Card>

            <!-- Charts -->
            <div class="grid gap-6 lg:grid-cols-2">
                <!-- Engagement Over Time -->
                <Card>
                    <CardHeader>
                        <CardTitle>Engagement Over Time</CardTitle>
                        <CardDescription>
                            Track your engagement and post volume
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div style="width: 100%; height: 300px">
                            <Line :data="engagementChartData" />
                        </div>
                    </CardContent>
                </Card>

                <!-- Platform Performance -->
                <Card>
                    <CardHeader>
                        <CardTitle>Platform Performance</CardTitle>
                        <CardDescription>
                            Compare performance across platforms
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div style="width: 100%; height: 300px">
                            <Bar :data="platformChartData" />
                        </div>
                    </CardContent>
                </Card>

                <!-- Post Types Performance -->
                <Card>
                    <CardHeader>
                        <CardTitle>Post Types Performance</CardTitle>
                        <CardDescription>
                            Average engagement by post type
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div style="width: 100%; height: 300px">
                            <BarChart :data="postTypeChartData">
                                <CartesianGrid strokeDasharray="3 3" />
                                <XAxis dataKey="labels" />
                                <YAxis />
                                <ChartTooltip />
                                <Legend />
                                <Bar
                                    dataKey="average_engagement"
                                    fill="rgba(147, 51, 234, 0.8)"
                                />
                            </BarChart>
                        </div>
                    </CardContent>
                </Card>

                <!-- Posts Distribution -->
                <Card>
                    <CardHeader>
                        <CardTitle>Posts Distribution</CardTitle>
                        <CardDescription>
                            Posts distribution across platforms
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div style="width: 100%; height: 300px">
                            <Pie :data="pieChartData" />
                        </div>
                    </CardContent>
                </Card>
            </div>
        </div>
    </AppLayout>
</template>
