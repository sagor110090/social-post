<script setup lang="ts">
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import WebhookForm from '@/components/Webhooks/WebhookForm.vue';
import WebhookStatusCard from '@/components/Webhooks/WebhookStatusCard.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';
import { onMounted, ref } from 'vue';

interface SocialAccount {
    id: number;
    platform: string;
    platform_username: string;
}

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

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: 'Webhooks',
        href: '/settings/webhooks',
    },
    {
        title: 'Configurations',
        href: '/settings/webhooks/configs',
    },
];

const configs = ref<WebhookConfig[]>([]);
const socialAccounts = ref<SocialAccount[]>([]);
const loading = ref(true);
const showCreateDialog = ref(false);
const showEditDialog = ref(false);
const editingConfig = ref<WebhookConfig | null>(null);
const formLoading = ref(false);
const error = ref<string | null>(null);

const fetchConfigs = async () => {
    try {
        const response = await fetch('/webhooks/manage/configs');
        if (response.ok) {
            configs.value = await response.json();
        }
    } catch (err) {
        error.value = 'Failed to fetch webhook configurations';
        console.error('Failed to fetch configs:', err);
    }
};

const fetchSocialAccounts = async () => {
    try {
        const response = await fetch('/api/social-accounts');
        if (response.ok) {
            socialAccounts.value = await response.json();
        }
    } catch (err) {
        console.error('Failed to fetch social accounts:', err);
    }
};

const handleCreateConfig = async (configData: Partial<WebhookConfig>) => {
    formLoading.value = true;
    error.value = null;

    try {
        const response = await fetch('/webhooks/manage/configs', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN':
                    document
                        .querySelector('meta[name="csrf-token"]')
                        ?.getAttribute('content') || '',
            },
            body: JSON.stringify(configData),
        });

        if (response.ok) {
            await fetchConfigs();
            showCreateDialog.value = false;
            router.reload({ only: ['configs'] });
        } else {
            const data = await response.json();
            error.value =
                data.error || 'Failed to create webhook configuration';
        }
    } catch (err) {
        error.value = 'Failed to create webhook configuration';
        console.error('Failed to create config:', err);
    } finally {
        formLoading.value = false;
    }
};

const handleUpdateConfig = async (configData: Partial<WebhookConfig>) => {
    if (!editingConfig.value) return;

    formLoading.value = true;
    error.value = null;

    try {
        const response = await fetch(
            `/webhooks/manage/configs/${editingConfig.value.id}`,
            {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN':
                        document
                            .querySelector('meta[name="csrf-token"]')
                            ?.getAttribute('content') || '',
                },
                body: JSON.stringify(configData),
            },
        );

        if (response.ok) {
            await fetchConfigs();
            showEditDialog.value = false;
            editingConfig.value = null;
            router.reload({ only: ['configs'] });
        } else {
            const data = await response.json();
            error.value =
                data.error || 'Failed to update webhook configuration';
        }
    } catch (err) {
        error.value = 'Failed to update webhook configuration';
        console.error('Failed to update config:', err);
    } finally {
        formLoading.value = false;
    }
};

const handleToggleConfig = async (config: WebhookConfig) => {
    try {
        const response = await fetch(`/webhooks/manage/configs/${config.id}`, {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN':
                    document
                        .querySelector('meta[name="csrf-token"]')
                        ?.getAttribute('content') || '',
            },
            body: JSON.stringify({
                ...config,
                is_active: !config.is_active,
            }),
        });

        if (response.ok) {
            await fetchConfigs();
        } else {
            error.value = 'Failed to toggle webhook configuration';
        }
    } catch (err) {
        error.value = 'Failed to toggle webhook configuration';
        console.error('Failed to toggle config:', err);
    }
};

const handleDeleteConfig = async (config: WebhookConfig) => {
    if (
        !confirm(
            `Are you sure you want to delete the webhook configuration for ${config.social_account.platform_username}?`,
        )
    ) {
        return;
    }

    try {
        const response = await fetch(`/webhooks/manage/configs/${config.id}`, {
            method: 'DELETE',
            headers: {
                'X-CSRF-TOKEN':
                    document
                        .querySelector('meta[name="csrf-token"]')
                        ?.getAttribute('content') || '',
            },
        });

        if (response.ok) {
            await fetchConfigs();
        } else {
            error.value = 'Failed to delete webhook configuration';
        }
    } catch (err) {
        error.value = 'Failed to delete webhook configuration';
        console.error('Failed to delete config:', err);
    }
};

const handleRegenerateSecret = async (config: WebhookConfig) => {
    if (
        !confirm(
            'Are you sure you want to regenerate the webhook secret? This will invalidate any existing integrations.',
        )
    ) {
        return;
    }

    try {
        const response = await fetch(
            `/webhooks/manage/configs/${config.id}/regenerate-secret`,
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
            alert(`New webhook secret: ${data.secret}`);
            await fetchConfigs();
        } else {
            error.value = 'Failed to regenerate webhook secret';
        }
    } catch (err) {
        error.value = 'Failed to regenerate webhook secret';
        console.error('Failed to regenerate secret:', err);
    }
};

const handleTestWebhook = async (config: WebhookConfig) => {
    try {
        const response = await fetch('/webhooks/test', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN':
                    document
                        .querySelector('meta[name="csrf-token"]')
                        ?.getAttribute('content') || '',
            },
            body: JSON.stringify({
                test: true,
                config_id: config.id,
            }),
        });

        if (response.ok) {
            alert('Test webhook sent successfully!');
        } else {
            error.value = 'Failed to send test webhook';
        }
    } catch (err) {
        error.value = 'Failed to send test webhook';
        console.error('Failed to test webhook:', err);
    }
};

const openEditDialog = (config: WebhookConfig) => {
    editingConfig.value = config;
    showEditDialog.value = true;
};

onMounted(async () => {
    await Promise.all([fetchConfigs(), fetchSocialAccounts()]);
    loading.value = false;
});
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head title="Webhook Configurations" />

        <SettingsLayout>
            <div class="space-y-6">
                <!-- Header -->
                <div class="flex items-center justify-between">
                    <div>
                        <h1 class="text-2xl font-bold">
                            Webhook Configurations
                        </h1>
                        <p class="text-muted-foreground">
                            Manage webhook endpoints for your social media
                            accounts
                        </p>
                    </div>
                    <Dialog v-model:open="showCreateDialog">
                        <DialogTrigger asChild>
                            <Button :disabled="socialAccounts.length === 0">
                                Add Configuration
                            </Button>
                        </DialogTrigger>
                        <DialogContent
                            class="max-h-[90vh] max-w-4xl overflow-y-auto"
                        >
                            <DialogHeader>
                                <DialogTitle
                                    >Create Webhook Configuration</DialogTitle
                                >
                            </DialogHeader>
                            <WebhookForm
                                :social-accounts="socialAccounts"
                                :on-submit="handleCreateConfig"
                                :on-cancel="() => (showCreateDialog = false)"
                                :loading="formLoading"
                            />
                        </DialogContent>
                    </Dialog>
                </div>

                <!-- Error Alert -->
                <Alert v-if="error" class="border-red-200 bg-red-50">
                    <AlertDescription class="text-red-800">
                        {{ error }}
                    </AlertDescription>
                </Alert>

                <!-- No Social Accounts -->
                <Card v-if="socialAccounts.length === 0 && !loading">
                    <CardContent class="py-8 text-center">
                        <h3 class="mb-2 text-lg font-medium">
                            No Social Accounts Found
                        </h3>
                        <p class="mb-4 text-muted-foreground">
                            You need to connect at least one social media
                            account before configuring webhooks.
                        </p>
                        <Link href="/settings/social-accounts">
                            <Button>Connect Social Account</Button>
                        </Link>
                    </CardContent>
                </Card>

                <!-- Loading State -->
                <div v-if="loading" class="py-8 text-center">
                    <div
                        class="mx-auto h-8 w-8 animate-spin rounded-full border-b-2 border-primary"
                    ></div>
                    <p class="mt-2 text-muted-foreground">
                        Loading webhook configurations...
                    </p>
                </div>

                <!-- Configurations List -->
                <div v-else-if="configs.length > 0" class="space-y-4">
                    <WebhookStatusCard
                        v-for="config in configs"
                        :key="config.id"
                        :config="config"
                        @toggle="handleToggleConfig"
                        @edit="openEditDialog"
                        @delete="handleDeleteConfig"
                        @regenerate-secret="handleRegenerateSecret"
                        @test="handleTestWebhook"
                    />
                </div>

                <!-- Empty State -->
                <Card v-else-if="!loading">
                    <CardContent class="py-8 text-center">
                        <h3 class="mb-2 text-lg font-medium">
                            No Webhook Configurations
                        </h3>
                        <p class="mb-4 text-muted-foreground">
                            Create your first webhook configuration to start
                            receiving real-time events from your social media
                            accounts.
                        </p>
                        <Dialog v-model:open="showCreateDialog">
                            <DialogTrigger asChild>
                                <Button>Create Configuration</Button>
                            </DialogTrigger>
                            <DialogContent
                                class="max-h-[90vh] max-w-4xl overflow-y-auto"
                            >
                                <DialogHeader>
                                    <DialogTitle
                                        >Create Webhook
                                        Configuration</DialogTitle
                                    >
                                </DialogHeader>
                                <WebhookForm
                                    :social-accounts="socialAccounts"
                                    :on-submit="handleCreateConfig"
                                    :on-cancel="
                                        () => (showCreateDialog = false)
                                    "
                                    :loading="formLoading"
                                />
                            </DialogContent>
                        </Dialog>
                    </CardContent>
                </Card>

                <!-- Edit Dialog -->
                <Dialog v-model:open="showEditDialog">
                    <DialogContent
                        class="max-h-[90vh] max-w-4xl overflow-y-auto"
                    >
                        <DialogHeader>
                            <DialogTitle
                                >Edit Webhook Configuration</DialogTitle
                            >
                        </DialogHeader>
                        <WebhookForm
                            v-if="editingConfig"
                            :social-accounts="socialAccounts"
                            :config="editingConfig"
                            :is-editing="true"
                            :on-submit="handleUpdateConfig"
                            :on-cancel="
                                () => {
                                    showEditDialog = false;
                                    editingConfig = null;
                                }
                            "
                            :loading="formLoading"
                        />
                    </DialogContent>
                </Dialog>
            </div>
        </SettingsLayout>
    </AppLayout>
</template>
