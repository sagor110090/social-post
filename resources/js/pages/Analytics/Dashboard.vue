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
</script>

<template>
    <Head title="Analytics Dashboard" />

    <AppLayout layout="sidebar">
        <div class="flex h-full flex-1 flex-col space-y-6 p-6">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-3xl font-bold tracking-tight">
                        Analytics Dashboard
                    </h1>
                    <p class="text-muted-foreground">
                        Track your social media performance and engagement
                    </p>
                </div>
                <div class="flex gap-2">
                    <Button
                        @click="showFilters = !showFilters"
                        variant="outline"
                    >
                        <Filter class="mr-2 h-4 w-4" />
                        Filters
                    </Button>
                    <Button @click="exportData" variant="outline">
                        <Download class="mr-2 h-4 w-4" />
                        Export
                    </Button>
                </div>
            </div>

            <!-- Date Filters -->
            <Card v-if="showFilters" class="mb-6">
                <CardHeader>
                    <CardTitle class="text-lg">Date Range</CardTitle>
                </CardHeader>
                <CardContent>
                    <div class="flex items-end gap-4">
                        <div class="flex-1">
                            <label class="text-sm font-medium"
                                >Start Date</label
                            >
                            <input
                                v-model="dateRange.start"
                                type="date"
                                class="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 focus:ring-2 focus:ring-primary focus:outline-none"
                            />
                        </div>
                        <div class="flex-1">
                            <label class="text-sm font-medium">End Date</label>
                            <input
                                v-model="dateRange.end"
                                type="date"
                                class="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 focus:ring-2 focus:ring-primary focus:outline-none"
                            />
                        </div>
                        <Button @click="applyFilters" :disabled="loading">
                            <Calendar class="mr-2 h-4 w-4" />
                            Apply
                        </Button>
                    </div>
                </CardContent>
            </Card>

            <!-- Overview Cards -->
            <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                <Card>
                    <CardHeader
                        class="flex flex-row items-center justify-between space-y-0 pb-2"
                    >
                        <CardTitle class="text-sm font-medium"
                            >Total Posts</CardTitle
                        >
                        <TrendingUp class="h-4 w-4 text-muted-foreground" />
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold">
                            {{ analytics.total_posts }}
                        </div>
                        <p class="text-xs text-muted-foreground">
                            {{ analytics.published_posts }} published,
                            {{ analytics.scheduled_posts }} scheduled
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
                        <Heart class="h-4 w-4 text-muted-foreground" />
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold">
                            {{ formatNumber(analytics.total_engagement) }}
                        </div>
                        <p class="text-xs text-muted-foreground">
                            Likes, comments, and shares
                        </p>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader
                        class="flex flex-row items-center justify-between space-y-0 pb-2"
                    >
                        <CardTitle class="text-sm font-medium"
                            >Total Reach</CardTitle
                        >
                        <Eye class="h-4 w-4 text-muted-foreground" />
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold">
                            {{ formatNumber(analytics.total_reach) }}
                        </div>
                        <p class="text-xs text-muted-foreground">
                            Unique users reached
                        </p>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader
                        class="flex flex-row items-center justify-between space-y-0 pb-2"
                    >
                        <CardTitle class="text-sm font-medium"
                            >Engagement Rate</CardTitle
                        >
                        <TrendingUp class="h-4 w-4 text-muted-foreground" />
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold">
                            {{
                                analytics.total_reach > 0
                                    ? (
                                          (analytics.total_engagement /
                                              analytics.total_reach) *
                                          100
                                      ).toFixed(2)
                                    : '0'
                            }}%
                        </div>
                        <p class="text-xs text-muted-foreground">
                            Average engagement rate
                        </p>
                    </CardContent>
                </Card>
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
