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
import PlatformIcon from '@/components/Webhooks/PlatformIcon.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/vue3';
import { onMounted, ref } from 'vue';

interface WebhookStats {
    total_configs: number;
    active_configs: number;
    total_events: number;
    pending_events: number;
    failed_events: number;
    processed_events: number;
    events_by_platform: Array<{
        platform: string;
        count: number;
    }>;
    recent_events: Array<{
        id: number;
        platform: string;
        event_type: string;
        status: string;
        received_at: string;
    }>;
}

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: 'Webhooks',
        href: '/settings/webhooks',
    },
];

const stats = ref<WebhookStats | null>(null);
const loading = ref(true);

const fetchStats = async () => {
    try {
        const response = await fetch('/webhooks/manage/stats');
        if (response.ok) {
            stats.value = await response.json();
        }
    } catch (error) {
        console.error('Failed to fetch webhook stats:', error);
    } finally {
        loading.value = false;
    }
};

onMounted(() => {
    fetchStats();
});

const getHealthStatus = () => {
    if (!stats.value) return 'unknown';

    const failureRate =
        stats.value.total_events > 0
            ? (stats.value.failed_events / stats.value.total_events) * 100
            : 0;

    if (failureRate > 10) return 'critical';
    if (failureRate > 5) return 'warning';
    if (stats.value.active_configs === 0) return 'info';
    return 'healthy';
};

const getHealthColor = () => {
    const status = getHealthStatus();
    switch (status) {
        case 'healthy':
            return 'text-green-600';
        case 'warning':
            return 'text-yellow-600';
        case 'critical':
            return 'text-red-600';
        case 'info':
            return 'text-blue-600';
        default:
            return 'text-gray-600';
    }
};

const getHealthText = () => {
    const status = getHealthStatus();
    switch (status) {
        case 'healthy':
            return 'All systems operational';
        case 'warning':
            return 'Some issues detected';
        case 'critical':
            return 'Critical issues require attention';
        case 'info':
            return 'No active webhooks configured';
        default:
            return 'Status unknown';
    }
};
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head title="Webhook Settings" />

        <SettingsLayout>
            <div class="space-y-6">
                <!-- Header -->
                <div class="flex items-center justify-between">
                    <div>
                        <h1 class="text-2xl font-bold">Webhook Management</h1>
                        <p class="text-muted-foreground">
                            Configure and monitor webhook integrations with your
                            social media accounts
                        </p>
                    </div>
                    <Link href="/settings/webhooks/configs">
                        <Button> Configure Webhooks </Button>
                    </Link>
                </div>

                <!-- Health Status -->
                <Card>
                    <CardHeader>
                        <CardTitle class="flex items-center gap-2">
                            System Health
                            <Badge :class="getHealthColor()" variant="outline">
                                {{ getHealthText() }}
                            </Badge>
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div v-if="loading" class="py-4 text-center">
                            Loading webhook statistics...
                        </div>
                        <div
                            v-else-if="stats"
                            class="grid grid-cols-2 gap-4 md:grid-cols-4"
                        >
                            <div class="text-center">
                                <div class="text-2xl font-bold">
                                    {{ stats.total_configs }}
                                </div>
                                <div class="text-sm text-muted-foreground">
                                    Total Configs
                                </div>
                            </div>
                            <div class="text-center">
                                <div class="text-2xl font-bold text-green-600">
                                    {{ stats.active_configs }}
                                </div>
                                <div class="text-sm text-muted-foreground">
                                    Active Configs
                                </div>
                            </div>
                            <div class="text-center">
                                <div class="text-2xl font-bold">
                                    {{ stats.total_events }}
                                </div>
                                <div class="text-sm text-muted-foreground">
                                    Total Events
                                </div>
                            </div>
                            <div class="text-center">
                                <div class="text-2xl font-bold text-red-600">
                                    {{ stats.failed_events }}
                                </div>
                                <div class="text-sm text-muted-foreground">
                                    Failed Events
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <!-- Quick Actions -->
                <div
                    class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-4"
                >
                    <Link href="/settings/webhooks/configs">
                        <Card
                            class="cursor-pointer transition-shadow hover:shadow-md"
                        >
                            <CardContent class="p-6 text-center">
                                <div class="mb-2 text-2xl">⚙️</div>
                                <h3 class="font-medium">Configurations</h3>
                                <p class="text-sm text-muted-foreground">
                                    Manage webhook endpoints
                                </p>
                            </CardContent>
                        </Card>
                    </Link>

                    <Link href="/settings/webhooks/events">
                        <Card
                            class="cursor-pointer transition-shadow hover:shadow-md"
                        >
                            <CardContent class="p-6 text-center">
                                <div class="mb-2 text-2xl">📋</div>
                                <h3 class="font-medium">Event Logs</h3>
                                <p class="text-sm text-muted-foreground">
                                    View webhook events
                                </p>
                            </CardContent>
                        </Card>
                    </Link>

                    <Link href="/settings/webhooks/analytics">
                        <Card
                            class="cursor-pointer transition-shadow hover:shadow-md"
                        >
                            <CardContent class="p-6 text-center">
                                <div class="mb-2 text-2xl">📊</div>
                                <h3 class="font-medium">Analytics</h3>
                                <p class="text-sm text-muted-foreground">
                                    Performance metrics
                                </p>
                            </CardContent>
                        </Card>
                    </Link>

                    <Link href="/settings/webhooks/security">
                        <Card
                            class="cursor-pointer transition-shadow hover:shadow-md"
                        >
                            <CardContent class="p-6 text-center">
                                <div class="mb-2 text-2xl">🔒</div>
                                <h3 class="font-medium">Security</h3>
                                <p class="text-sm text-muted-foreground">
                                    Security settings
                                </p>
                            </CardContent>
                        </Card>
                    </Link>
                </div>

                <!-- Platform Statistics -->
                <Card v-if="stats && stats.events_by_platform.length > 0">
                    <CardHeader>
                        <CardTitle>Events by Platform</CardTitle>
                        <CardDescription
                            >Distribution of webhook events across
                            platforms</CardDescription
                        >
                    </CardHeader>
                    <CardContent>
                        <div class="grid grid-cols-2 gap-4 md:grid-cols-4">
                            <div
                                v-for="platform in stats.events_by_platform"
                                :key="platform.platform"
                                class="flex items-center space-x-3 rounded-lg border p-3"
                            >
                                <PlatformIcon
                                    :platform="platform.platform as any"
                                    size="lg"
                                />
                                <div>
                                    <div class="font-medium">
                                        {{ platform.count }}
                                    </div>
                                    <div
                                        class="text-sm text-muted-foreground capitalize"
                                    >
                                        {{ platform.platform }}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <!-- Recent Events -->
                <Card v-if="stats && stats.recent_events.length > 0">
                    <CardHeader>
                        <CardTitle>Recent Events</CardTitle>
                        <CardDescription
                            >Latest webhook activity</CardDescription
                        >
                    </CardHeader>
                    <CardContent>
                        <div class="space-y-3">
                            <div
                                v-for="event in stats.recent_events.slice(0, 5)"
                                :key="event.id"
                                class="flex items-center justify-between rounded-lg border p-3"
                            >
                                <div class="flex items-center space-x-3">
                                    <PlatformIcon
                                        :platform="event.platform as any"
                                        size="md"
                                    />
                                    <div>
                                        <div class="font-medium">
                                            {{ event.event_type }}
                                        </div>
                                        <div
                                            class="text-sm text-muted-foreground"
                                        >
                                            {{
                                                new Date(
                                                    event.received_at,
                                                ).toLocaleString()
                                            }}
                                        </div>
                                    </div>
                                </div>
                                <Badge
                                    :variant="
                                        event.status === 'processed'
                                            ? 'default'
                                            : event.status === 'failed'
                                              ? 'destructive'
                                              : 'outline'
                                    "
                                >
                                    {{ event.status }}
                                </Badge>
                            </div>
                        </div>
                        <div class="mt-4 text-center">
                            <Link href="/settings/webhooks/events">
                                <Button variant="outline"
                                    >View All Events</Button
                                >
                            </Link>
                        </div>
                    </CardContent>
                </Card>

                <!-- Getting Started -->
                <Card v-if="stats && stats.total_configs === 0">
                    <CardHeader>
                        <CardTitle>Getting Started with Webhooks</CardTitle>
                        <CardDescription>
                            Set up your first webhook configuration to start
                            receiving real-time events
                        </CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                            <div class="rounded-lg border p-4 text-center">
                                <div class="mb-2 text-2xl">1️⃣</div>
                                <h3 class="mb-1 font-medium">Configure</h3>
                                <p class="text-sm text-muted-foreground">
                                    Set up webhook endpoints for your social
                                    accounts
                                </p>
                            </div>
                            <div class="rounded-lg border p-4 text-center">
                                <div class="mb-2 text-2xl">2️⃣</div>
                                <h3 class="mb-1 font-medium">Subscribe</h3>
                                <p class="text-sm text-muted-foreground">
                                    Choose which events you want to receive
                                </p>
                            </div>
                            <div class="rounded-lg border p-4 text-center">
                                <div class="mb-2 text-2xl">3️⃣</div>
                                <h3 class="mb-1 font-medium">Monitor</h3>
                                <p class="text-sm text-muted-foreground">
                                    Track delivery and performance metrics
                                </p>
                            </div>
                        </div>
                        <div class="text-center">
                            <Link href="/settings/webhooks/configs">
                                <Button>Create Your First Webhook</Button>
                            </Link>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </SettingsLayout>
    </AppLayout>
</template>
