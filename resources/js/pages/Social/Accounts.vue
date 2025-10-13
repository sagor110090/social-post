<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
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
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Head, router } from '@inertiajs/vue3';
import {
    CheckCircleIcon,
    ExternalLinkIcon,
    FacebookIcon,
    InstagramIcon,
    LinkedinIcon,
    PlusIcon,
    RefreshCwIcon,
    TwitterIcon,
    XCircleIcon,
} from 'lucide-vue-next';
import { computed, onMounted, ref } from 'vue';

const props = defineProps({
    accounts: Array,
    errors: Object,
    flash: Object,
});

const loading = ref(false);
const disconnecting = ref(false);
const selectedAccount = ref(null);
const showDisconnectDialog = ref(false);

const platforms = [
    {
        name: 'Facebook',
        provider: 'facebook',
        icon: FacebookIcon,
        color: 'bg-blue-600',
        description: 'Connect Facebook pages for posting',
        features: ['Page posting', 'Analytics', 'Multiple pages'],
    },
    {
        name: 'Instagram',
        provider: 'instagram',
        icon: InstagramIcon,
        color: 'bg-pink-600',
        description: 'Connect Instagram business accounts',
        features: ['Image posting', 'Stories', 'Business insights'],
    },
    {
        name: 'LinkedIn',
        provider: 'linkedin',
        icon: LinkedinIcon,
        color: 'bg-blue-700',
        description: 'Connect LinkedIn profile',
        features: ['Profile posting', 'Network stats', 'Professional content'],
    },
    {
        name: 'X (Twitter)',
        provider: 'twitter',
        icon: TwitterIcon,
        color: 'bg-black',
        description: 'Connect X (Twitter) account',
        features: ['Tweet posting', 'Media upload', 'Thread support'],
    },
];

const connectedProviders = computed(() => {
    return props.accounts?.map((account) => account.provider) || [];
});

const isPlatformConnected = (provider) => {
    return connectedProviders.value.includes(provider);
};

const getConnectedAccount = (provider) => {
    return props.accounts?.find((account) => account.provider === provider);
};

const connectAccount = (provider) => {
    loading.value = true;
    window.location.href = `/oauth/${provider}`;
};

const confirmDisconnect = (account) => {
    selectedAccount.value = account;
    showDisconnectDialog.value = true;
};

const disconnectAccount = () => {
    if (!selectedAccount.value) return;

    disconnecting.value = true;

    router.delete(`/oauth/${selectedAccount.value.provider}/disconnect`, {
        onSuccess: () => {
            showDisconnectDialog.value = false;
            selectedAccount.value = null;
        },
        onFinish: () => {
            disconnecting.value = false;
        },
    });
};

const refreshAccount = (provider) => {
    loading.value = true;

    router.post(
        `/social/${provider}/refresh-tokens`,
        {},
        {
            onSuccess: () => {
                // Refresh the page to show updated data
                router.reload();
            },
            onFinish: () => {
                loading.value = false;
            },
        },
    );
};

const formatDate = (dateString) => {
    return new Date(dateString).toLocaleDateString();
};

onMounted(() => {
    // Check for flash messages and show appropriate alerts
    if (props.flash?.success || props.flash?.error) {
        setTimeout(() => {
            // Flash messages will be cleared automatically by Inertia
        }, 5000);
    }
});
</script>

<template>
    <Head title="Social Accounts" />

    <AppLayout>
        <div class="py-12">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
                <!-- Header -->
                <div class="mb-8">
                    <h1 class="text-3xl font-bold text-gray-900">
                        Social Media Accounts
                    </h1>
                    <p class="mt-2 text-gray-600">
                        Connect your social media accounts to start posting
                        content across platforms.
                    </p>
                </div>

                <!-- Flash Messages -->
                <Alert
                    v-if="flash?.success"
                    class="mb-6 border-green-200 bg-green-50"
                >
                    <CheckCircleIcon class="h-4 w-4 text-green-600" />
                    <AlertDescription class="text-green-800">
                        {{ flash.success }}
                    </AlertDescription>
                </Alert>

                <Alert
                    v-if="flash?.error"
                    class="mb-6 border-red-200 bg-red-50"
                >
                    <XCircleIcon class="h-4 w-4 text-red-600" />
                    <AlertDescription class="text-red-800">
                        {{ flash.error }}
                    </AlertDescription>
                </Alert>

                <!-- Connected Accounts Summary -->
                <Card class="mb-8">
                    <CardHeader>
                        <CardTitle>Connected Accounts</CardTitle>
                        <CardDescription>
                            You have {{ accounts?.length || 0 }} social media
                            account(s) connected
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div class="flex flex-wrap gap-2">
                            <Badge
                                v-for="account in accounts"
                                :key="account.id"
                                variant="secondary"
                                class="flex items-center gap-1"
                            >
                                <component
                                    :is="
                                        platforms.find(
                                            (p) =>
                                                p.provider === account.provider,
                                        )?.icon
                                    "
                                    class="h-3 w-3"
                                />
                                {{ account.display_name }}
                            </Badge>
                        </div>
                    </CardContent>
                </Card>

                <!-- Platform Cards -->
                <div class="grid gap-6 md:grid-cols-2">
                    <Card
                        v-for="platform in platforms"
                        :key="platform.provider"
                        class="relative overflow-hidden"
                    >
                        <CardHeader>
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-3">
                                    <div
                                        :class="[
                                            platform.color,
                                            'flex h-10 w-10 items-center justify-center rounded-lg',
                                        ]"
                                    >
                                        <component
                                            :is="platform.icon"
                                            class="h-5 w-5 text-white"
                                        />
                                    </div>
                                    <div>
                                        <CardTitle class="text-lg">{{
                                            platform.name
                                        }}</CardTitle>
                                        <CardDescription>{{
                                            platform.description
                                        }}</CardDescription>
                                    </div>
                                </div>

                                <!-- Connection Status -->
                                <div class="flex items-center gap-2">
                                    <Badge
                                        v-if="
                                            isPlatformConnected(
                                                platform.provider,
                                            )
                                        "
                                        variant="default"
                                        class="bg-green-100 text-green-800"
                                    >
                                        <CheckCircleIcon class="mr-1 h-3 w-3" />
                                        Connected
                                    </Badge>
                                    <Badge
                                        v-else
                                        variant="outline"
                                        class="text-gray-500"
                                    >
                                        Not Connected
                                    </Badge>
                                </div>
                            </div>
                        </CardHeader>

                        <CardContent>
                            <!-- Connected Account Info -->
                            <div
                                v-if="isPlatformConnected(platform.provider)"
                                class="space-y-4"
                            >
                                <div class="rounded-lg bg-gray-50 p-4">
                                    <div
                                        class="flex items-center justify-between"
                                    >
                                        <div>
                                            <p
                                                class="font-medium text-gray-900"
                                            >
                                                {{
                                                    getConnectedAccount(
                                                        platform.provider,
                                                    )?.display_name
                                                }}
                                            </p>
                                            <p class="text-sm text-gray-500">
                                                @{{
                                                    getConnectedAccount(
                                                        platform.provider,
                                                    )?.username
                                                }}
                                            </p>
                                            <p class="text-xs text-gray-400">
                                                Connected
                                                {{
                                                    formatDate(
                                                        getConnectedAccount(
                                                            platform.provider,
                                                        )?.connected_at,
                                                    )
                                                }}
                                            </p>
                                        </div>
                                        <img
                                            v-if="
                                                getConnectedAccount(
                                                    platform.provider,
                                                )?.avatar
                                            "
                                            :src="
                                                getConnectedAccount(
                                                    platform.provider,
                                                ).avatar
                                            "
                                            :alt="
                                                getConnectedAccount(
                                                    platform.provider,
                                                ).display_name
                                            "
                                            class="h-10 w-10 rounded-full"
                                        />
                                    </div>
                                </div>

                                <!-- Platform-specific actions -->
                                <div class="flex gap-2">
                                    <Button
                                        v-if="platform.provider === 'facebook'"
                                        variant="outline"
                                        size="sm"
                                        @click="
                                            refreshAccount(platform.provider)
                                        "
                                        :disabled="loading"
                                    >
                                        <RefreshCwIcon class="mr-2 h-4 w-4" />
                                        Refresh Pages
                                    </Button>

                                    <Button
                                        variant="outline"
                                        size="sm"
                                        @click="
                                            confirmDisconnect(
                                                getConnectedAccount(
                                                    platform.provider,
                                                ),
                                            )
                                        "
                                        :disabled="disconnecting"
                                    >
                                        Disconnect
                                    </Button>
                                </div>
                            </div>

                            <!-- Not Connected State -->
                            <div v-else class="space-y-4">
                                <div>
                                    <h4 class="mb-2 font-medium text-gray-900">
                                        Features:
                                    </h4>
                                    <ul class="space-y-1 text-sm text-gray-600">
                                        <li
                                            v-for="feature in platform.features"
                                            :key="feature"
                                            class="flex items-center gap-2"
                                        >
                                            <div
                                                class="h-1.5 w-1.5 rounded-full bg-gray-400"
                                            ></div>
                                            {{ feature }}
                                        </li>
                                    </ul>
                                </div>

                                <Button
                                    class="w-full"
                                    @click="connectAccount(platform.provider)"
                                    :disabled="loading"
                                >
                                    <PlusIcon class="mr-2 h-4 w-4" />
                                    Connect {{ platform.name }}
                                </Button>
                            </div>
                        </CardContent>
                    </Card>
                </div>

                <!-- Help Section -->
                <Card class="mt-8">
                    <CardHeader>
                        <CardTitle>Need Help?</CardTitle>
                        <CardDescription>
                            Learn more about connecting your social media
                            accounts
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div class="grid gap-4 md:grid-cols-2">
                            <div class="flex items-start gap-3">
                                <ExternalLinkIcon
                                    class="mt-0.5 h-5 w-5 text-gray-400"
                                />
                                <div>
                                    <h4 class="font-medium text-gray-900">
                                        Developer Accounts
                                    </h4>
                                    <p class="text-sm text-gray-600">
                                        You'll need developer accounts for each
                                        platform to connect them.
                                    </p>
                                </div>
                            </div>
                            <div class="flex items-start gap-3">
                                <ExternalLinkIcon
                                    class="mt-0.5 h-5 w-5 text-gray-400"
                                />
                                <div>
                                    <h4 class="font-medium text-gray-900">
                                        Permissions
                                    </h4>
                                    <p class="text-sm text-gray-600">
                                        We only request permissions needed for
                                        posting and analytics.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </div>

        <!-- Disconnect Confirmation Dialog -->
        <Dialog v-model:open="showDisconnectDialog">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Disconnect Social Account</DialogTitle>
                    <DialogDescription>
                        Are you sure you want to disconnect your
                        {{ selectedAccount?.provider_name }} account? This will
                        remove access to post content and you'll need to
                        reconnect it later.
                    </DialogDescription>
                </DialogHeader>

                <div class="mt-6 flex justify-end gap-3">
                    <Button
                        variant="outline"
                        @click="showDisconnectDialog = false"
                        :disabled="disconnecting"
                    >
                        Cancel
                    </Button>
                    <Button
                        variant="destructive"
                        @click="disconnectAccount"
                        :disabled="disconnecting"
                    >
                        <span v-if="disconnecting">Disconnecting...</span>
                        <span v-else>Disconnect Account</span>
                    </Button>
                </div>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
