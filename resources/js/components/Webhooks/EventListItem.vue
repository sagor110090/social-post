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
import { computed } from 'vue';
import PlatformIcon from './PlatformIcon.vue';

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

interface Props {
    event: WebhookEvent;
    onView?: (event: WebhookEvent) => void;
    onRetry?: (event: WebhookEvent) => void;
    showDetails?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    showDetails: false,
});

const statusVariant = computed(() => {
    switch (props.event.status) {
        case 'processed':
            return 'default';
        case 'failed':
            return 'destructive';
        case 'processing':
            return 'secondary';
        case 'pending':
            return 'outline';
        default:
            return 'outline';
    }
});

const statusColor = computed(() => {
    switch (props.event.status) {
        case 'processed':
            return 'text-green-600';
        case 'failed':
            return 'text-red-600';
        case 'processing':
            return 'text-blue-600';
        case 'pending':
            return 'text-yellow-600';
        default:
            return 'text-gray-600';
    }
});

const canRetry = computed(() => {
    return props.event.status === 'failed' && props.event.retry_count < 3;
});

const formattedPayload = computed(() => {
    return JSON.stringify(props.event.payload, null, 2);
});

const handleView = () => {
    props.onView?.(props.event);
};

const handleRetry = () => {
    props.onRetry?.(props.event);
};
</script>

<template>
    <Card class="transition-shadow hover:shadow-md">
        <CardHeader class="pb-3">
            <div class="flex items-center justify-between">
                <div class="flex items-center space-x-3">
                    <PlatformIcon :platform="event.platform as any" size="md" />
                    <div>
                        <CardTitle class="text-base">
                            {{ event.event_type }}
                        </CardTitle>
                        <CardDescription>
                            {{ event.social_account.platform_username }} • Event
                            ID: {{ event.event_id }}
                        </CardDescription>
                    </div>
                </div>
                <div class="flex items-center space-x-2">
                    <Badge :variant="statusVariant">
                        {{ event.status }}
                    </Badge>
                    <div
                        v-if="event.retry_count > 0"
                        class="text-xs text-muted-foreground"
                    >
                        Retry: {{ event.retry_count }}/3
                    </div>
                </div>
            </div>
        </CardHeader>

        <CardContent class="space-y-3">
            <div class="flex items-center justify-between text-sm">
                <div>
                    <span class="text-muted-foreground">Received:</span>
                    {{ new Date(event.received_at).toLocaleString() }}
                </div>
                <div v-if="event.processed_at">
                    <span class="text-muted-foreground">Processed:</span>
                    {{ new Date(event.processed_at).toLocaleString() }}
                </div>
            </div>

            <div
                v-if="event.error_message"
                class="rounded bg-red-50 p-2 text-sm text-red-600"
            >
                <strong>Error:</strong> {{ event.error_message }}
            </div>

            <div v-if="showDetails" class="space-y-2">
                <div>
                    <h4 class="mb-1 text-sm font-medium">Payload Preview:</h4>
                    <pre
                        class="max-h-40 overflow-auto rounded bg-muted p-2 text-xs"
                        >{{ formattedPayload }}</pre
                    >
                </div>
            </div>

            <div class="flex gap-2 pt-2">
                <Button variant="outline" size="sm" @click="handleView">
                    View Details
                </Button>

                <Button
                    v-if="canRetry"
                    variant="outline"
                    size="sm"
                    @click="handleRetry"
                >
                    Retry
                </Button>
            </div>
        </CardContent>
    </Card>
</template>
