<script setup lang="ts">
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import EventFilter from '@/components/Webhooks/EventFilter.vue';
import EventListItem from '@/components/Webhooks/EventListItem.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/vue3';
import { computed, onMounted, onUnmounted, ref } from 'vue';

interface WebhookEvent {
    id: number;
    social_account_id: number;
    platform: string;
    event_type: string;
    event_id: string;
    payload: Record<string, any>;
    status: 'pending' | 'processing' | 'processed' | 'failed';
    error_message?: string;
    received_at: string;
    processed_at?: string;
    retry_count: number;
    social_account: {
        id: number;
        platform: string;
        platform_username: string;
    };
}

interface PaginatedResponse {
    data: WebhookEvent[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
}

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: 'Webhooks',
        href: '/settings/webhooks',
    },
    {
        title: 'Event Logs',
        href: '/settings/webhooks/events',
    },
];

const events = ref<WebhookEvent[]>([]);
const loading = ref(true);
const error = ref<string | null>(null);
const selectedEvent = ref<WebhookEvent | null>(null);
const showEventDetails = ref(false);
const currentPage = ref(1);
const lastPage = ref(1);
const total = ref(0);
const perPage = ref(20);

const filters = ref({
    platform: '',
    status: '',
    startDate: '',
    endDate: '',
    search: '',
});

const hasActiveFilters = computed(() => {
    return Object.values(filters.value).some((value) => value !== '');
});

const fetchEvents = async (page = 1) => {
    loading.value = true;
    error.value = null;

    try {
        const params = new URLSearchParams({
            page: page.toString(),
            per_page: perPage.value.toString(),
        });

        // Add filters to params
        Object.entries(filters.value).forEach(([key, value]) => {
            if (value) {
                params.append(key, value);
            }
        });

        const response = await fetch(`/webhooks/manage/events?${params}`);
        if (response.ok) {
            const data: PaginatedResponse = await response.json();
            events.value = data.data;
            currentPage.value = data.current_page;
            lastPage.value = data.last_page;
            total.value = data.total;
        } else {
            error.value = 'Failed to fetch webhook events';
        }
    } catch (err) {
        error.value = 'Failed to fetch webhook events';
        console.error('Failed to fetch events:', err);
    } finally {
        loading.value = false;
    }
};

const handleFiltersChange = (newFilters: typeof filters.value) => {
    filters.value = { ...newFilters };
    currentPage.value = 1;
    fetchEvents(1);
};

const handleRetryEvent = async (event: WebhookEvent) => {
    try {
        const response = await fetch(
            `/webhooks/manage/events/${event.id}/retry`,
            {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN':
                        document
                            .querySelector('meta[name="csrf-token"]')
                            ?.getAttribute('content') || '',
                },
            },
        );

        if (response.ok) {
            const data = await response.json();
            alert(data.message);
            await fetchEvents(currentPage.value);
        } else {
            const data = await response.json();
            alert(data.error || 'Failed to retry event');
        }
    } catch (err) {
        alert('Failed to retry event');
        console.error('Failed to retry event:', err);
    }
};

const handleViewEvent = (event: WebhookEvent) => {
    selectedEvent.value = event;
    showEventDetails.value = true;
};

const handlePageChange = (page: number) => {
    if (page >= 1 && page <= lastPage.value) {
        fetchEvents(page);
    }
};

const exportEvents = async () => {
    try {
        const params = new URLSearchParams();

        // Add filters to params
        Object.entries(filters.value).forEach(([key, value]) => {
            if (value) {
                params.append(key, value);
            }
        });

        const response = await fetch(
            `/webhooks/manage/events/export?${params}`,
        );
        if (response.ok) {
            const blob = await response.blob();
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `webhook-events-${new Date().toISOString().split('T')[0]}.csv`;
            a.click();
            URL.revokeObjectURL(url);
        } else {
            error.value = 'Failed to export events';
        }
    } catch (err) {
        error.value = 'Failed to export events';
        console.error('Failed to export events:', err);
    }
};

const clearFilters = () => {
    filters.value = {
        platform: '',
        status: '',
        startDate: '',
        endDate: '',
        search: '',
    };
    currentPage.value = 1;
    fetchEvents(1);
};

onMounted(() => {
    fetchEvents();
});

// Auto-refresh for real-time updates
let refreshInterval: NodeJS.Timeout;
onMounted(() => {
    refreshInterval = setInterval(() => {
        if (!showEventDetails.value) {
            fetchEvents(currentPage.value);
        }
    }, 30000); // Refresh every 30 seconds
});

onUnmounted(() => {
    if (refreshInterval) {
        clearInterval(refreshInterval);
    }
});
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head title="Webhook Event Logs" />

        <SettingsLayout>
            <div class="space-y-6">
                <!-- Header -->
                <div class="flex items-center justify-between">
                    <div>
                        <h1 class="text-2xl font-bold">Webhook Event Logs</h1>
                        <p class="text-muted-foreground">
                            Monitor and manage webhook events in real-time
                        </p>
                    </div>
                    <div class="flex gap-2">
                        <Button
                            v-if="hasActiveFilters"
                            variant="outline"
                            @click="clearFilters"
                        >
                            Clear Filters
                        </Button>
                        <Button variant="outline" @click="exportEvents">
                            Export Events
                        </Button>
                    </div>
                </div>

                <!-- Error Alert -->
                <Alert v-if="error" class="border-red-200 bg-red-50">
                    <AlertDescription class="text-red-800">
                        {{ error }}
                    </AlertDescription>
                </Alert>

                <!-- Filters -->
                <EventFilter
                    :filters="filters"
                    @filters-change="handleFiltersChange"
                />

                <!-- Stats -->
                <Card>
                    <CardContent class="pt-6">
                        <div class="grid grid-cols-2 gap-4 md:grid-cols-4">
                            <div class="text-center">
                                <div class="text-2xl font-bold">
                                    {{ total }}
                                </div>
                                <div class="text-sm text-muted-foreground">
                                    Total Events
                                </div>
                            </div>
                            <div class="text-center">
                                <div class="text-2xl font-bold text-green-600">
                                    {{
                                        events.filter(
                                            (e) => e.status === 'processed',
                                        ).length
                                    }}
                                </div>
                                <div class="text-sm text-muted-foreground">
                                    Processed
                                </div>
                            </div>
                            <div class="text-center">
                                <div class="text-2xl font-bold text-red-600">
                                    {{
                                        events.filter(
                                            (e) => e.status === 'failed',
                                        ).length
                                    }}
                                </div>
                                <div class="text-sm text-muted-foreground">
                                    Failed
                                </div>
                            </div>
                            <div class="text-center">
                                <div class="text-2xl font-bold text-blue-600">
                                    {{
                                        events.filter(
                                            (e) => e.status === 'pending',
                                        ).length
                                    }}
                                </div>
                                <div class="text-sm text-muted-foreground">
                                    Pending
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <!-- Loading State -->
                <div
                    v-if="loading && events.length === 0"
                    class="py-8 text-center"
                >
                    <div
                        class="mx-auto h-8 w-8 animate-spin rounded-full border-b-2 border-primary"
                    ></div>
                    <p class="mt-2 text-muted-foreground">
                        Loading webhook events...
                    </p>
                </div>

                <!-- Events List -->
                <div v-else-if="events.length > 0" class="space-y-4">
                    <EventListItem
                        v-for="event in events"
                        :key="event.id"
                        :event="event"
                        @view="handleViewEvent"
                        @retry="handleRetryEvent"
                    />

                    <!-- Pagination -->
                    <div class="flex items-center justify-between">
                        <div class="text-sm text-muted-foreground">
                            Showing {{ (currentPage - 1) * perPage + 1 }} to
                            {{ Math.min(currentPage * perPage, total) }} of
                            {{ total }} events
                        </div>
                        <div class="flex gap-2">
                            <Button
                                variant="outline"
                                size="sm"
                                :disabled="currentPage <= 1"
                                @click="handlePageChange(currentPage - 1)"
                            >
                                Previous
                            </Button>
                            <span class="flex items-center px-3 text-sm">
                                Page {{ currentPage }} of {{ lastPage }}
                            </span>
                            <Button
                                variant="outline"
                                size="sm"
                                :disabled="currentPage >= lastPage"
                                @click="handlePageChange(currentPage + 1)"
                            >
                                Next
                            </Button>
                        </div>
                    </div>
                </div>

                <!-- Empty State -->
                <Card v-else-if="!loading">
                    <CardContent class="py-8 text-center">
                        <h3 class="mb-2 text-lg font-medium">
                            No Events Found
                        </h3>
                        <p class="text-muted-foreground">
                            {{
                                hasActiveFilters
                                    ? 'No events match the current filters. Try adjusting your search criteria.'
                                    : 'No webhook events have been received yet.'
                            }}
                        </p>
                    </CardContent>
                </Card>

                <!-- Event Details Dialog -->
                <Dialog v-model:open="showEventDetails">
                    <DialogContent
                        class="max-h-[90vh] max-w-4xl overflow-y-auto"
                    >
                        <DialogHeader>
                            <DialogTitle>Event Details</DialogTitle>
                        </DialogHeader>
                        <div v-if="selectedEvent" class="space-y-4">
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <h4 class="mb-1 font-medium">Event ID</h4>
                                    <code
                                        class="rounded bg-muted px-2 py-1 text-sm"
                                        >{{ selectedEvent.event_id }}</code
                                    >
                                </div>
                                <div>
                                    <h4 class="mb-1 font-medium">Platform</h4>
                                    <div class="flex items-center gap-2">
                                        <span class="capitalize">{{
                                            selectedEvent.platform
                                        }}</span>
                                        <Badge
                                            :variant="
                                                selectedEvent.status ===
                                                'processed'
                                                    ? 'default'
                                                    : selectedEvent.status ===
                                                        'failed'
                                                      ? 'destructive'
                                                      : 'outline'
                                            "
                                        >
                                            {{ selectedEvent.status }}
                                        </Badge>
                                    </div>
                                </div>
                                <div>
                                    <h4 class="mb-1 font-medium">Event Type</h4>
                                    <p>{{ selectedEvent.event_type }}</p>
                                </div>
                                <div>
                                    <h4 class="mb-1 font-medium">Account</h4>
                                    <p>
                                        {{
                                            selectedEvent.social_account
                                                .platform_username
                                        }}
                                    </p>
                                </div>
                                <div>
                                    <h4 class="mb-1 font-medium">Received</h4>
                                    <p>
                                        {{
                                            new Date(
                                                selectedEvent.received_at,
                                            ).toLocaleString()
                                        }}
                                    </p>
                                </div>
                                <div v-if="selectedEvent.processed_at">
                                    <h4 class="mb-1 font-medium">Processed</h4>
                                    <p>
                                        {{
                                            new Date(
                                                selectedEvent.processed_at,
                                            ).toLocaleString()
                                        }}
                                    </p>
                                </div>
                                <div v-if="selectedEvent.retry_count > 0">
                                    <h4 class="mb-1 font-medium">
                                        Retry Count
                                    </h4>
                                    <p>{{ selectedEvent.retry_count }}/3</p>
                                </div>
                                <div v-if="selectedEvent.error_message">
                                    <h4 class="mb-1 font-medium">Error</h4>
                                    <p class="text-red-600">
                                        {{ selectedEvent.error_message }}
                                    </p>
                                </div>
                            </div>

                            <div>
                                <h4 class="mb-2 font-medium">Payload</h4>
                                <pre
                                    class="max-h-96 overflow-auto rounded bg-muted p-4 text-xs"
                                    >{{
                                        JSON.stringify(
                                            selectedEvent.payload,
                                            null,
                                            2,
                                        )
                                    }}</pre
                                >
                            </div>

                            <div class="flex gap-2">
                                <Button
                                    v-if="
                                        selectedEvent.status === 'failed' &&
                                        selectedEvent.retry_count < 3
                                    "
                                    @click="handleRetryEvent(selectedEvent)"
                                >
                                    Retry Event
                                </Button>
                                <Button
                                    variant="outline"
                                    @click="showEventDetails = false"
                                >
                                    Close
                                </Button>
                            </div>
                        </div>
                    </DialogContent>
                </Dialog>
            </div>
        </SettingsLayout>
    </AppLayout>
</template>
