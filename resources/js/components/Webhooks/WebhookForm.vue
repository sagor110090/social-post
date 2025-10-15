<script setup lang="ts">
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { computed, ref, watch } from 'vue';

interface SocialAccount {
    id: number;
    platform: string;
    platform_username: string;
}

interface WebhookConfig {
    id?: number;
    social_account_id: number;
    webhook_url?: string;
    secret?: string;
    events: string[];
    is_active: boolean;
    metadata?: Record<string, any>;
}

interface Props {
    socialAccounts: SocialAccount[];
    config?: WebhookConfig | null;
    isEditing?: boolean;
    onSubmit: (config: Partial<WebhookConfig>) => void;
    onCancel: () => void;
    loading?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    isEditing: false,
    loading: false,
});

const formData = ref<Partial<WebhookConfig>>({
    social_account_id: props.config?.social_account_id || 0,
    webhook_url: props.config?.webhook_url || '',
    secret: props.config?.secret || '',
    events: props.config?.events || [],
    is_active: props.config?.is_active ?? true,
    metadata: props.config?.metadata || {},
});

const selectedPlatform = computed(() => {
    const account = props.socialAccounts.find(
        (acc) => acc.id === formData.value.social_account_id,
    );
    return account?.platform || '';
});

const availableEvents = computed(() => {
    switch (selectedPlatform.value) {
        case 'facebook':
            return [
                {
                    id: 'feed',
                    label: 'Feed Updates',
                    description: 'Posts and feed changes',
                },
                {
                    id: 'messages',
                    label: 'Messages',
                    description: 'Direct messages',
                },
                {
                    id: 'comments',
                    label: 'Comments',
                    description: 'Comment activity',
                },
                { id: 'likes', label: 'Likes', description: 'Like reactions' },
                {
                    id: 'mentions',
                    label: 'Mentions',
                    description: 'When mentioned',
                },
            ];
        case 'instagram':
            return [
                {
                    id: 'feed',
                    label: 'Feed Updates',
                    description: 'Posts and media changes',
                },
                {
                    id: 'comments',
                    label: 'Comments',
                    description: 'Comment activity',
                },
                { id: 'likes', label: 'Likes', description: 'Like reactions' },
                {
                    id: 'mentions',
                    label: 'Mentions',
                    description: 'When mentioned',
                },
                {
                    id: 'follows',
                    label: 'Follows',
                    description: 'New followers',
                },
            ];
        case 'twitter':
            return [
                {
                    id: 'feed',
                    label: 'Feed Updates',
                    description: 'Tweet changes',
                },
                {
                    id: 'messages',
                    label: 'Messages',
                    description: 'Direct messages',
                },
                {
                    id: 'mentions',
                    label: 'Mentions',
                    description: 'Tweet mentions',
                },
                {
                    id: 'follows',
                    label: 'Follows',
                    description: 'New followers',
                },
                { id: 'likes', label: 'Likes', description: 'Tweet likes' },
                {
                    id: 'shares',
                    label: 'Retweets',
                    description: 'Tweet retweets',
                },
            ];
        case 'linkedin':
            return [
                {
                    id: 'feed',
                    label: 'Feed Updates',
                    description: 'Post changes',
                },
                {
                    id: 'messages',
                    label: 'Messages',
                    description: 'Direct messages',
                },
                {
                    id: 'comments',
                    label: 'Comments',
                    description: 'Comment activity',
                },
                {
                    id: 'likes',
                    label: 'Likes',
                    description: 'Reaction activity',
                },
                {
                    id: 'mentions',
                    label: 'Mentions',
                    description: 'When mentioned',
                },
            ];
        default:
            return [];
    }
});

const webhookUrl = computed(() => {
    if (!selectedPlatform.value) return '';
    const baseUrl = window.location.origin;
    return `${baseUrl}/webhooks/${selectedPlatform.value}`;
});

const generateSecret = () => {
    const array = new Uint8Array(32);
    crypto.getRandomValues(array);
    formData.value.secret = Array.from(array, (byte) =>
        byte.toString(16).padStart(2, '0'),
    ).join('');
};

const handleSubmit = () => {
    if (!formData.value.social_account_id) {
        return;
    }

    const submitData = {
        ...formData.value,
        webhook_url: webhookUrl.value,
    };

    props.onSubmit(submitData);
};

const handleEventToggle = (eventId: string, checked: boolean) => {
    if (checked) {
        if (!formData.value.events) {
            formData.value.events = [];
        }
        formData.value.events.push(eventId);
    } else {
        formData.value.events =
            formData.value.events?.filter((id) => id !== eventId) || [];
    }
};

// Auto-generate webhook URL when platform changes
watch(selectedPlatform, () => {
    if (selectedPlatform.value && !props.isEditing) {
        formData.value.webhook_url = webhookUrl.value;
    }
});

// Initialize form if config is provided
watch(
    () => props.config,
    (newConfig) => {
        if (newConfig) {
            formData.value = { ...newConfig };
        }
    },
    { immediate: true },
);
</script>

<template>
    <Card class="mx-auto max-w-2xl">
        <CardHeader>
            <CardTitle>
                {{
                    isEditing
                        ? 'Edit Webhook Configuration'
                        : 'Create Webhook Configuration'
                }}
            </CardTitle>
            <CardDescription>
                Configure webhook endpoints to receive real-time events from
                your social media accounts.
            </CardDescription>
        </CardHeader>

        <CardContent class="space-y-6">
            <form @submit.prevent="handleSubmit" class="space-y-6">
                <div class="space-y-2">
                    <Label for="social_account">Social Account</Label>
                    <Select
                        v-model="formData.social_account_id"
                        :disabled="isEditing"
                    >
                        <SelectTrigger>
                            <SelectValue
                                placeholder="Select a social account"
                            />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem
                                v-for="account in socialAccounts"
                                :key="account.id"
                                :value="account.id"
                            >
                                {{ account.platform_username }} ({{
                                    account.platform
                                }})
                            </SelectItem>
                        </SelectContent>
                    </Select>
                </div>

                <div class="space-y-2">
                    <Label for="webhook_url">Webhook URL</Label>
                    <Input
                        id="webhook_url"
                        v-model="formData.webhook_url"
                        readonly
                        class="bg-muted"
                    />
                    <p class="text-sm text-muted-foreground">
                        This URL is automatically generated based on the
                        selected platform.
                    </p>
                </div>

                <div class="space-y-2">
                    <Label for="secret">Webhook Secret</Label>
                    <div class="flex gap-2">
                        <Input
                            id="secret"
                            v-model="formData.secret"
                            type="password"
                            placeholder="Webhook secret for signature verification"
                            class="flex-1"
                        />
                        <Button
                            type="button"
                            variant="outline"
                            @click="generateSecret"
                        >
                            Generate
                        </Button>
                    </div>
                    <p class="text-sm text-muted-foreground">
                        Used to verify webhook signatures. Keep this secret
                        secure.
                    </p>
                </div>

                <div class="space-y-3">
                    <Label>Events to Subscribe</Label>
                    <div
                        v-if="availableEvents.length === 0"
                        class="text-sm text-muted-foreground"
                    >
                        Select a social account to see available events.
                    </div>
                    <div v-else class="space-y-3">
                        <div
                            v-for="event in availableEvents"
                            :key="event.id"
                            class="flex items-start space-x-3 rounded-lg border p-3"
                        >
                            <Checkbox
                                :id="event.id"
                                :checked="formData.events?.includes(event.id)"
                                @update:checked="
                                    (checked) =>
                                        handleEventToggle(
                                            event.id,
                                            checked as boolean,
                                        )
                                "
                            />
                            <div class="flex-1">
                                <Label
                                    :for="event.id"
                                    class="cursor-pointer font-medium"
                                >
                                    {{ event.label }}
                                </Label>
                                <p class="text-sm text-muted-foreground">
                                    {{ event.description }}
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="flex items-center space-x-2">
                    <Checkbox
                        id="is_active"
                        v-model:checked="formData.is_active"
                    />
                    <Label for="is_active">Enable webhook</Label>
                </div>

                <Alert v-if="!formData.secret">
                    <AlertDescription>
                        Please generate a webhook secret before creating the
                        configuration.
                    </AlertDescription>
                </Alert>

                <div class="flex gap-3 pt-4">
                    <Button
                        type="submit"
                        :disabled="
                            loading ||
                            !formData.secret ||
                            !formData.events?.length
                        "
                    >
                        {{
                            isEditing
                                ? 'Update Configuration'
                                : 'Create Configuration'
                        }}
                    </Button>
                    <Button type="button" variant="outline" @click="onCancel">
                        Cancel
                    </Button>
                </div>
            </form>
        </CardContent>
    </Card>
</template>
