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

interface WebhookConfig {
    id: number;
    social_account_id: number;
    webhook_url: string;
    secret: string;
    events: string[];
    is_active: boolean;
    metadata: Record<string, any>;
    created_at: string;
    updated_at: string;
    social_account: {
        id: number;
        platform: string;
        platform_username: string;
    };
}

interface Props {
    config: WebhookConfig;
    onToggle?: (config: WebhookConfig) => void;
    onEdit?: (config: WebhookConfig) => void;
    onDelete?: (config: WebhookConfig) => void;
    onRegenerateSecret?: (config: WebhookConfig) => void;
    onTest?: (config: WebhookConfig) => void;
}

const props = defineProps<Props>();

const statusVariant = computed(() => {
    return props.config.is_active ? 'default' : 'secondary';
});

const statusText = computed(() => {
    return props.config.is_active ? 'Active' : 'Inactive';
});

const eventLabels: Record<string, string> = {
    feed: 'Feed Updates',
    messages: 'Messages',
    comments: 'Comments',
    likes: 'Likes',
    shares: 'Shares',
    mentions: 'Mentions',
    follows: 'Follows',
};

const formattedEvents = computed(() => {
    return props.config.events.map((event) => eventLabels[event] || event);
});

const handleToggle = () => {
    props.onToggle?.(props.config);
};

const handleEdit = () => {
    props.onEdit?.(props.config);
};

const handleDelete = () => {
    props.onDelete?.(props.config);
};

const handleRegenerateSecret = () => {
    props.onRegenerateSecret?.(props.config);
};

const handleTest = () => {
    props.onTest?.(props.config);
};
</script>

<template>
    <Card class="relative">
        <CardHeader class="pb-3">
            <div class="flex items-center justify-between">
                <div class="flex items-center space-x-3">
                    <PlatformIcon
                        :platform="config.social_account.platform as any"
                        size="lg"
                    />
                    <div>
                        <CardTitle class="text-lg">
                            {{ config.social_account.platform_username }}
                        </CardTitle>
                        <CardDescription>
                            {{
                                config.social_account.platform
                                    .charAt(0)
                                    .toUpperCase() +
                                config.social_account.platform.slice(1)
                            }}
                        </CardDescription>
                    </div>
                </div>
                <Badge :variant="statusVariant">
                    {{ statusText }}
                </Badge>
            </div>
        </CardHeader>

        <CardContent class="space-y-4">
            <div>
                <h4 class="mb-2 text-sm font-medium">Webhook URL</h4>
                <code
                    class="block rounded bg-muted px-2 py-1 text-xs break-all"
                >
                    {{ config.webhook_url }}
                </code>
            </div>

            <div>
                <h4 class="mb-2 text-sm font-medium">Subscribed Events</h4>
                <div class="flex flex-wrap gap-1">
                    <Badge
                        v-for="event in formattedEvents"
                        :key="event"
                        variant="outline"
                        class="text-xs"
                    >
                        {{ event }}
                    </Badge>
                </div>
            </div>

            <div class="text-xs text-muted-foreground">
                Created: {{ new Date(config.created_at).toLocaleDateString() }}
                <br />
                Last updated:
                {{ new Date(config.updated_at).toLocaleDateString() }}
            </div>

            <div class="flex flex-wrap gap-2 pt-2">
                <Button variant="outline" size="sm" @click="handleToggle">
                    {{ config.is_active ? 'Disable' : 'Enable' }}
                </Button>

                <Button variant="outline" size="sm" @click="handleEdit">
                    Edit
                </Button>

                <Button variant="outline" size="sm" @click="handleTest">
                    Test
                </Button>

                <Button
                    variant="outline"
                    size="sm"
                    @click="handleRegenerateSecret"
                >
                    Regenerate Secret
                </Button>

                <Button variant="destructive" size="sm" @click="handleDelete">
                    Delete
                </Button>
            </div>
        </CardContent>
    </Card>
</template>
