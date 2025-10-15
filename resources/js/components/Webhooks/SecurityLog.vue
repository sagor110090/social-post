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
import { computed } from 'vue';

interface SecurityEvent {
    id: number;
    webhook_config_id: number;
    event_type:
        | 'signature_verification_failed'
        | 'rate_limit_exceeded'
        | 'ip_blocked'
        | 'unauthorized_access';
    ip_address: string;
    user_agent?: string;
    payload?: Record<string, any>;
    severity: 'low' | 'medium' | 'high' | 'critical';
    resolved: boolean;
    created_at: string;
    resolved_at?: string;
    webhook_config?: {
        social_account: {
            platform: string;
            platform_username: string;
        };
    };
}

interface Props {
    event: SecurityEvent;
    onResolve?: (eventId: number) => void;
    onViewDetails?: (event: SecurityEvent) => void;
}

const props = defineProps<Props>();

const severityVariant = computed(() => {
    switch (props.event.severity) {
        case 'critical':
            return 'destructive';
        case 'high':
            return 'destructive';
        case 'medium':
            return 'secondary';
        case 'low':
            return 'outline';
        default:
            return 'outline';
    }
});

const severityColor = computed(() => {
    switch (props.event.severity) {
        case 'critical':
            return 'text-red-600 bg-red-50';
        case 'high':
            return 'text-red-600 bg-red-50';
        case 'medium':
            return 'text-yellow-600 bg-yellow-50';
        case 'low':
            return 'text-blue-600 bg-blue-50';
        default:
            return 'text-gray-600 bg-gray-50';
    }
});

const eventTypeLabel = computed(() => {
    switch (props.event.event_type) {
        case 'signature_verification_failed':
            return 'Signature Verification Failed';
        case 'rate_limit_exceeded':
            return 'Rate Limit Exceeded';
        case 'ip_blocked':
            return 'IP Address Blocked';
        case 'unauthorized_access':
            return 'Unauthorized Access';
        default:
            return props.event.event_type;
    }
});

const eventTypeDescription = computed(() => {
    switch (props.event.event_type) {
        case 'signature_verification_failed':
            return 'Webhook signature could not be verified';
        case 'rate_limit_exceeded':
            return 'Too many requests received from this IP';
        case 'ip_blocked':
            return 'IP address is blocked from accessing webhooks';
        case 'unauthorized_access':
            return 'Attempted access without proper authorization';
        default:
            return 'Unknown security event';
    }
});

const handleResolve = () => {
    props.onResolve?.(props.event.id);
};

const handleViewDetails = () => {
    props.onViewDetails?.(props.event);
};

const formattedPayload = computed(() => {
    return props.event.payload
        ? JSON.stringify(props.event.payload, null, 2)
        : null;
});
</script>

<template>
    <Card :class="{ 'opacity-75': event.resolved }">
        <CardHeader class="pb-3">
            <div class="flex items-center justify-between">
                <div class="flex items-center space-x-3">
                    <div class="flex items-center space-x-2">
                        <Badge :variant="severityVariant">
                            {{ event.severity.toUpperCase() }}
                        </Badge>
                        <Badge v-if="event.resolved" variant="outline">
                            Resolved
                        </Badge>
                    </div>
                    <div>
                        <CardTitle class="text-base">
                            {{ eventTypeLabel }}
                        </CardTitle>
                        <CardDescription>
                            {{ eventTypeDescription }}
                        </CardDescription>
                    </div>
                </div>
            </div>
        </CardHeader>

        <CardContent class="space-y-3">
            <Alert :class="severityColor">
                <AlertDescription>
                    {{ eventTypeDescription }}
                </AlertDescription>
            </Alert>

            <div class="grid grid-cols-1 gap-4 text-sm md:grid-cols-2">
                <div>
                    <span class="font-medium">IP Address:</span>
                    <code class="ml-2 rounded bg-muted px-2 py-1">{{
                        event.ip_address
                    }}</code>
                </div>

                <div v-if="event.user_agent">
                    <span class="font-medium">User Agent:</span>
                    <div class="mt-1 text-xs break-all text-muted-foreground">
                        {{ event.user_agent }}
                    </div>
                </div>

                <div>
                    <span class="font-medium">Occurred:</span>
                    <div class="text-muted-foreground">
                        {{ new Date(event.created_at).toLocaleString() }}
                    </div>
                </div>

                <div v-if="event.resolved_at">
                    <span class="font-medium">Resolved:</span>
                    <div class="text-muted-foreground">
                        {{ new Date(event.resolved_at).toLocaleString() }}
                    </div>
                </div>

                <div v-if="event.webhook_config" class="md:col-span-2">
                    <span class="font-medium">Webhook:</span>
                    <div class="text-muted-foreground">
                        {{
                            event.webhook_config.social_account
                                .platform_username
                        }}
                        ({{ event.webhook_config.social_account.platform }})
                    </div>
                </div>
            </div>

            <div v-if="formattedPayload" class="space-y-2">
                <h4 class="text-sm font-medium">Event Details:</h4>
                <pre
                    class="max-h-40 overflow-auto rounded bg-muted p-2 text-xs"
                    >{{ formattedPayload }}</pre
                >
            </div>

            <div class="flex gap-2 pt-2">
                <Button variant="outline" size="sm" @click="handleViewDetails">
                    View Details
                </Button>

                <Button
                    v-if="!event.resolved"
                    variant="outline"
                    size="sm"
                    @click="handleResolve"
                >
                    Mark as Resolved
                </Button>
            </div>
        </CardContent>
    </Card>
</template>
