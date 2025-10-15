<script setup lang="ts">
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import MetricsChart from '@/components/Webhooks/MetricsChart.vue';
import PlatformIcon from '@/components/Webhooks/PlatformIcon.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/vue3';
import { computed, onMounted, ref } from 'vue';

interface MetricData {
    date: string;
    delivered: number;
    failed: number;
    pending: number;
    total: number;
}

interface DeliveryMetric {
    id: number;
    webhook_config_id: number;
    delivered_at: string;
    response_time_ms: number;
    status_code: number;
    success: boolean;
    webhook_config: {
        social_account: {
            platform: string;
            platform_username: string;
        };
    };
}

interface AnalyticsData {
    metrics: MetricData[];
    delivery_metrics: DeliveryMetric[];
    summary: {
        total_deliveries: number;
        success_rate: number;
        average_response_time: number;
        total_errors: number;
        platform_stats: Array<{
            platform: string;
            deliveries: number;
            success_rate: number;
            average_response_time: number;
        }>;
    };
}

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: 'Webhooks',
        href: '/settings/webhooks',
    },
    {
        title: 'Analytics',
        href: '/settings/webhooks/analytics',
    },
];

const analyticsData = ref<AnalyticsData | null>(null);
const loading = ref(true);
const error = ref<string | null>(null);
const timeRange = ref<'7d' | '30d' | '90d'>('30d');
const selectedPlatform = ref<string>('all');

const platforms = computed(() => {
    if (!analyticsData.value) return [];
    const uniquePlatforms = [
        ...new Set(
            analyticsData.value.summary.platform_stats.map(
                (stat) => stat.platform,
            ),
        ),
    ];
    return uniquePlatforms;
});

const filteredMetrics = computed(() => {
    if (!analyticsData.value) return [];

    let metrics = analyticsData.value.metrics;

    // Filter by time range
    const now = new Date();
    const daysBack =
        timeRange.value === '7d' ? 7 : timeRange.value === '30d' ? 30 : 90;
    const cutoffDate = new Date(now.getTime() - daysBack * 24 * 60 * 60 * 1000);

    metrics = metrics.filter((metric) => new Date(metric.date) >= cutoffDate);

    return metrics;
});

const filteredPlatformStats = computed(() => {
    if (!analyticsData.value) return [];

    let stats = analyticsData.value.summary.platform_stats;

    if (selectedPlatform.value !== 'all') {
        stats = stats.filter(
            (stat) => stat.platform === selectedPlatform.value,
        );
    }

    return stats;
});

const fetchAnalytics = async () => {
    loading.value = true;
    error.value = null;

    try {
        const params = new URLSearchParams({
            time_range: timeRange.value,
        });

        if (selectedPlatform.value !== 'all') {
            params.append('platform', selectedPlatform.value);
        }

        const response = await fetch(`/webhooks/manage/analytics?${params}`);
        if (response.ok) {
            analyticsData.value = await response.json();
        } else {
            error.value = 'Failed to fetch analytics data';
        }
    } catch (err) {
        error.value = 'Failed to fetch analytics data';
        console.error('Failed to fetch analytics:', err);
    } finally {
        loading.value = false;
    }
};

const exportAnalytics = async () => {
    try {
        const params = new URLSearchParams({
            time_range: timeRange.value,
        });

        if (selectedPlatform.value !== 'all') {
            params.append('platform', selectedPlatform.value);
        }

        const response = await fetch(
            `/webhooks/manage/analytics/export?${params}`,
        );
        if (response.ok) {
            const blob = await response.blob();
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `webhook-analytics-${new Date().toISOString().split('T')[0]}.csv`;
            a.click();
            URL.revokeObjectURL(url);
        } else {
            error.value = 'Failed to export analytics';
        }
    } catch (err) {
        error.value = 'Failed to export analytics';
        console.error('Failed to export analytics:', err);
    }
};

const formatResponseTime = (ms: number) => {
    if (ms < 1000) return `${ms}ms`;
    return `${(ms / 1000).toFixed(2)}s`;
};

const getStatusCodeColor = (code: number) => {
    if (code >= 200 && code < 300) return 'text-green-600';
    if (code >= 400 && code < 500) return 'text-yellow-600';
    if (code >= 500) return 'text-red-600';
    return 'text-gray-600';
};

onMounted(() => {
    fetchAnalytics();
});
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head title="Webhook Analytics" />

        <SettingsLayout>
            <div class="space-y-6">
                <!-- Header -->
                <div class="flex items-center justify-between">
                    <div>
                        <h1 class="text-2xl font-bold">Webhook Analytics</h1>
                        <p class="text-muted-foreground">
                            Monitor webhook performance and delivery metrics
                        </p>
                    </div>
                    <div class="flex gap-2">
                        <Select
                            v-model="timeRange"
                            @update:model-value="fetchAnalytics"
                        >
                            <SelectTrigger class="w-32">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="7d">Last 7 days</SelectItem>
                                <SelectItem value="30d"
                                    >Last 30 days</SelectItem
                                >
                                <SelectItem value="90d"
                                    >Last 90 days</SelectItem
                                >
                            </SelectContent>
                        </Select>
                        <Select
                            v-model="selectedPlatform"
                            @update:model-value="fetchAnalytics"
                        >
                            <SelectTrigger class="w-40">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all"
                                    >All Platforms</SelectItem
                                >
                                <SelectItem
                                    v-for="platform in platforms"
                                    :key="platform"
                                    :value="platform"
                                >
                                    {{
                                        platform.charAt(0).toUpperCase() +
                                        platform.slice(1)
                                    }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <Button variant="outline" @click="exportAnalytics">
                            Export Data
                        </Button>
                    </div>
                </div>

                <!-- Error Alert -->
                <Alert v-if="error" class="border-red-200 bg-red-50">
                    <AlertDescription class="text-red-800">
                        {{ error }}
                    </AlertDescription>
                </Alert>

                <!-- Loading State -->
                <div v-if="loading" class="py-8 text-center">
                    <div
                        class="mx-auto h-8 w-8 animate-spin rounded-full border-b-2 border-primary"
                    ></div>
                    <p class="mt-2 text-muted-foreground">
                        Loading analytics data...
                    </p>
                </div>

                <!-- Analytics Content -->
                <div v-else-if="analyticsData" class="space-y-6">
                    <!-- Summary Cards -->
                    <div class="grid grid-cols-1 gap-4 md:grid-cols-4">
                        <Card>
                            <CardContent class="pt-6">
                                <div class="text-center">
                                    <div class="text-2xl font-bold">
                                        {{
                                            analyticsData.summary
                                                .total_deliveries
                                        }}
                                    </div>
                                    <div class="text-sm text-muted-foreground">
                                        Total Deliveries
                                    </div>
                                </div>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardContent class="pt-6">
                                <div class="text-center">
                                    <div
                                        class="text-2xl font-bold text-green-600"
                                    >
                                        {{
                                            analyticsData.summary.success_rate.toFixed(
                                                1,
                                            )
                                        }}%
                                    </div>
                                    <div class="text-sm text-muted-foreground">
                                        Success Rate
                                    </div>
                                </div>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardContent class="pt-6">
                                <div class="text-center">
                                    <div
                                        class="text-2xl font-bold text-blue-600"
                                    >
                                        {{
                                            formatResponseTime(
                                                analyticsData.summary
                                                    .average_response_time,
                                            )
                                        }}
                                    </div>
                                    <div class="text-sm text-muted-foreground">
                                        Avg Response Time
                                    </div>
                                </div>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardContent class="pt-6">
                                <div class="text-center">
                                    <div
                                        class="text-2xl font-bold text-red-600"
                                    >
                                        {{ analyticsData.summary.total_errors }}
                                    </div>
                                    <div class="text-sm text-muted-foreground">
                                        Total Errors
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                    </div>

                    <!-- Metrics Chart -->
                    <MetricsChart
                        :data="filteredMetrics"
                        title="Delivery Metrics Over Time"
                        description="Webhook delivery performance trends"
                    />

                    <!-- Platform Statistics -->
                    <Card>
                        <CardHeader>
                            <CardTitle>Platform Performance</CardTitle>
                            <CardDescription
                                >Delivery metrics by social media
                                platform</CardDescription
                            >
                        </CardHeader>
                        <CardContent>
                            <div class="space-y-4">
                                <div
                                    v-for="stat in filteredPlatformStats"
                                    :key="stat.platform"
                                    class="flex items-center justify-between rounded-lg border p-4"
                                >
                                    <div class="flex items-center space-x-3">
                                        <PlatformIcon
                                            :platform="stat.platform as any"
                                            size="lg"
                                        />
                                        <div>
                                            <div class="font-medium capitalize">
                                                {{ stat.platform }}
                                            </div>
                                            <div
                                                class="text-sm text-muted-foreground"
                                            >
                                                {{ stat.deliveries }} deliveries
                                            </div>
                                        </div>
                                    </div>
                                    <div
                                        class="flex items-center space-x-6 text-sm"
                                    >
                                        <div class="text-center">
                                            <div
                                                class="font-medium text-green-600"
                                            >
                                                {{
                                                    stat.success_rate.toFixed(
                                                        1,
                                                    )
                                                }}%
                                            </div>
                                            <div class="text-muted-foreground">
                                                Success Rate
                                            </div>
                                        </div>
                                        <div class="text-center">
                                            <div
                                                class="font-medium text-blue-600"
                                            >
                                                {{
                                                    formatResponseTime(
                                                        stat.average_response_time,
                                                    )
                                                }}
                                            </div>
                                            <div class="text-muted-foreground">
                                                Avg Response
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Recent Deliveries -->
                    <Card>
                        <CardHeader>
                            <CardTitle>Recent Deliveries</CardTitle>
                            <CardDescription
                                >Latest webhook delivery
                                attempts</CardDescription
                            >
                        </CardHeader>
                        <CardContent>
                            <div class="space-y-3">
                                <div
                                    v-for="delivery in analyticsData.delivery_metrics.slice(
                                        0,
                                        10,
                                    )"
                                    :key="delivery.id"
                                    class="flex items-center justify-between rounded-lg border p-3"
                                >
                                    <div class="flex items-center space-x-3">
                                        <PlatformIcon
                                            :platform="
                                                delivery.webhook_config
                                                    .social_account
                                                    .platform as any
                                            "
                                            size="md"
                                        />
                                        <div>
                                            <div class="font-medium">
                                                {{
                                                    delivery.webhook_config
                                                        .social_account
                                                        .platform_username
                                                }}
                                            </div>
                                            <div
                                                class="text-sm text-muted-foreground"
                                            >
                                                {{
                                                    new Date(
                                                        delivery.delivered_at,
                                                    ).toLocaleString()
                                                }}
                                            </div>
                                        </div>
                                    </div>
                                    <div class="flex items-center space-x-4">
                                        <Badge
                                            :variant="
                                                delivery.success
                                                    ? 'default'
                                                    : 'destructive'
                                            "
                                        >
                                            {{
                                                delivery.success
                                                    ? 'Success'
                                                    : 'Failed'
                                            }}
                                        </Badge>
                                        <code
                                            :class="
                                                getStatusCodeColor(
                                                    delivery.status_code,
                                                )
                                            "
                                        >
                                            {{ delivery.status_code }}
                                        </code>
                                        <span
                                            class="text-sm text-muted-foreground"
                                        >
                                            {{
                                                formatResponseTime(
                                                    delivery.response_time_ms,
                                                )
                                            }}
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </div>

                <!-- Empty State -->
                <Card v-else-if="!loading">
                    <CardContent class="py-8 text-center">
                        <h3 class="mb-2 text-lg font-medium">
                            No Analytics Data
                        </h3>
                        <p class="text-muted-foreground">
                            No webhook delivery data is available for the
                            selected time period.
                        </p>
                    </CardContent>
                </Card>
            </div>
        </SettingsLayout>
    </AppLayout>
</template>
