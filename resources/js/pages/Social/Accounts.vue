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
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import AppLayout from '@/layouts/AppLayout.vue';
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
        <div class="min-h-screen">
            <div class="p-6">
                <div class="mx-auto max-w-7xl">
                    <!-- Header -->
                    <div class="mb-12 animate-fade-in">
                        <h1 class="text-display-1 mb-4 text-neutral-900 dark:text-white">
                            Social Media <span class="text-gradient font-bold">Accounts</span> 🌐
                        </h1>
                        <p class="text-body-large max-w-3xl text-neutral-600 dark:text-neutral-400 leading-relaxed">
                            Connect your social media accounts to start posting
                            content across platforms.
                        </p>
                    </div>

                <!-- Flash Messages -->
                <div
                    v-if="flash?.success"
                    class="mb-8 rounded-2xl border border-emerald-200/60 bg-emerald-50/80 p-6 backdrop-blur-sm animate-slide-up dark:border-emerald-800/60 dark:bg-emerald-900/30"
                >
                    <div class="flex items-center gap-3">
                        <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-emerald-500">
                            <CheckCircleIcon class="h-5 w-5 text-white" />
                        </div>
                        <p class="text-body-large font-medium text-emerald-800 dark:text-emerald-200">
                            {{ flash.success }}
                        </p>
                    </div>
                </div>

                <div
                    v-if="flash?.error"
                    class="mb-8 rounded-2xl border border-red-200/60 bg-red-50/80 p-6 backdrop-blur-sm animate-slide-up dark:border-red-800/60 dark:bg-red-900/30"
                >
                    <div class="flex items-center gap-3">
                        <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-red-500">
                            <XCircleIcon class="h-5 w-5 text-white" />
                        </div>
                        <p class="text-body-large font-medium text-red-800 dark:text-red-200">
                            {{ flash.error }}
                        </p>
                    </div>
                </div>

                <!-- Connected Accounts Summary -->
                <div class="card-elevated relative overflow-hidden mb-12 animate-slide-up">
                    <div class="absolute top-0 left-0 w-full h-1 bg-gradient-to-r from-emerald-500 to-blue-500"></div>
                    <div class="mb-6">
                        <h2 class="text-headline-1 mb-3 text-neutral-900 dark:text-white">
                            Connected Accounts
                        </h2>
                        <p class="text-body-large text-neutral-600 dark:text-neutral-400">
                            You have {{ accounts?.length || 0 }} social media
                            account(s) connected
                        </p>
                    </div>
                    <div class="flex flex-wrap gap-3">
                        <div
                            v-for="account in accounts"
                            :key="account.id"
                            class="inline-flex items-center gap-2 rounded-2xl border border-neutral-200/60 bg-white/50 px-4 py-2 shadow-sm backdrop-blur-sm dark:border-neutral-700/60 dark:bg-neutral-800/50"
                        >
                            <component
                                :is="
                                    platforms.find(
                                        (p) =>
                                            p.provider === account.provider,
                                    )?.icon
                                "
                                class="h-5 w-5"
                            />
                            <span class="text-body font-medium text-neutral-900 dark:text-white">
                                {{ account.display_name }}
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Platform Cards -->
                <div class="grid gap-8 lg:grid-cols-2">
                    <div
                        v-for="platform in platforms"
                        :key="platform.provider"
                        class="card-elevated relative overflow-hidden group hover:scale-[1.02] transition-all duration-300"
                    >
                        <div class="absolute top-0 left-0 w-full h-1 opacity-0 group-hover:opacity-100 transition-opacity duration-300"
                             :class="platform.color"></div>
                        <div class="mb-6">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-4">
                                    <div
                                        :class="[
                                            platform.color,
                                            'flex h-14 w-14 items-center justify-center rounded-2xl shadow-lg group-hover:scale-110 transition-transform duration-300',
                                        ]"
                                    >
                                        <component
                                            :is="platform.icon"
                                            class="h-7 w-7 text-white"
                                        />
                                    </div>
                                    <div>
                                        <h3 class="text-headline-2 text-neutral-900 dark:text-white">{{
                                            platform.name
                                        }}</h3>
                                        <p class="text-body-large text-neutral-600 dark:text-neutral-400 mt-1">{{
                                            platform.description
                                        }}</p>
                                    </div>
                                </div>

                                <!-- Connection Status -->
                                <div class="flex items-center gap-3">
                                    <div
                                        v-if="
                                            isPlatformConnected(
                                                platform.provider,
                                            )
                                        "
                                        class="inline-flex items-center gap-2 rounded-full border border-emerald-200/60 bg-emerald-50/80 px-4 py-2 backdrop-blur-sm dark:border-emerald-800/60 dark:bg-emerald-900/30"
                                    >
                                        <div class="h-2 w-2 rounded-full bg-emerald-500 animate-pulse"></div>
                                        <span class="text-body font-medium text-emerald-700 dark:text-emerald-300">Connected</span>
                                    </div>
                                    <div
                                        v-else
                                        class="inline-flex items-center gap-2 rounded-full border border-neutral-200/60 bg-neutral-50/80 px-4 py-2 backdrop-blur-sm dark:border-neutral-700/60 dark:bg-neutral-800/50"
                                    >
                                        <div class="h-2 w-2 rounded-full bg-neutral-400"></div>
                                        <span class="text-body font-medium text-neutral-600 dark:text-neutral-400">Not Connected</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="space-y-6">
                            <!-- Connected Account Info -->
                            <div
                                v-if="isPlatformConnected(platform.provider)"
                                class="space-y-6"
                            >
                                <div class="rounded-2xl border border-neutral-200/60 bg-gradient-to-br from-neutral-50/80 to-white/80 p-6 backdrop-blur-sm dark:border-neutral-700/60 dark:from-neutral-800/80 dark:to-neutral-900/80">
                                    <div
                                        class="flex items-center justify-between"
                                    >
                                        <div class="flex-1">
                                            <p
                                                class="text-headline-3 font-semibold text-neutral-900 dark:text-white"
                                            >
                                                {{
                                                    getConnectedAccount(
                                                        platform.provider,
                                                    )?.display_name
                                                }}
                                            </p>
                                            <p class="text-body-large text-neutral-600 dark:text-neutral-400 mt-1">
                                                @{{
                                                    getConnectedAccount(
                                                        platform.provider,
                                                    )?.username
                                                }}
                                            </p>
                                            <div class="flex items-center gap-2 mt-3">
                                                <div class="h-2 w-2 rounded-full bg-emerald-500"></div>
                                                <p class="text-body-small text-neutral-500 dark:text-neutral-500">
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
                                            class="h-14 w-14 rounded-2xl shadow-lg ring-2 ring-white dark:ring-neutral-800"
                                        />
                                    </div>
                                </div>

                                <!-- Platform-specific actions -->
                                <div class="flex gap-3">
                                    <Button
                                        v-if="platform.provider === 'facebook'"
                                        variant="outline"
                                        @click="
                                            refreshAccount(platform.provider)
                                        "
                                        :disabled="loading"
                                        class="hover-glow"
                                    >
                                        <RefreshCwIcon class="mr-2 h-5 w-5" />
                                        Refresh Pages
                                    </Button>

                                    <Button
                                        variant="outline"
                                        @click="
                                            confirmDisconnect(
                                                getConnectedAccount(
                                                    platform.provider,
                                                ),
                                            )
                                        "
                                        :disabled="disconnecting"
                                        class="hover:border-red-500 hover:text-red-600 hover:bg-red-50 dark:hover:bg-red-900/20"
                                    >
                                        Disconnect
                                    </Button>
                                </div>
                            </div>

                            <!-- Not Connected State -->
                            <div v-else class="space-y-6">
                                <div>
                                    <h4 class="text-headline-4 mb-4 font-semibold text-neutral-900 dark:text-white">
                                        Features:
                                    </h4>
                                    <ul class="space-y-3">
                                        <li
                                            v-for="feature in platform.features"
                                            :key="feature"
                                            class="flex items-center gap-3 text-body-large text-neutral-600 dark:text-neutral-400"
                                        >
                                            <div
                                                class="h-2 w-2 rounded-full bg-brand-primary"
                                            ></div>
                                            {{ feature }}
                                        </li>
                                    </ul>
                                </div>

                                <Button
                                    class="btn-primary w-full py-4 text-base font-semibold hover-glow"
                                    @click="connectAccount(platform.provider)"
                                    :disabled="loading"
                                >
                                    <PlusIcon class="mr-3 h-5 w-5" />
                                    Connect {{ platform.name }}
                                </Button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Help Section -->
                <div class="card-elevated relative overflow-hidden mt-12 animate-slide-up group hover:scale-[1.01] transition-all duration-300">
                    <div class="absolute top-0 left-0 w-full h-1 opacity-0 group-hover:opacity-100 transition-opacity duration-300 bg-gradient-to-r from-amber-500 to-orange-500"></div>
                    <div class="mb-6">
                        <h2 class="text-headline-1 mb-3 text-neutral-900 dark:text-white">Need Help?</h2>
                        <p class="text-body-large text-neutral-600 dark:text-neutral-400">
                            Learn more about connecting your social media accounts
                        </p>
                    </div>
                    <div class="grid gap-6 md:grid-cols-2">
                        <div class="flex items-start gap-4">
                            <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-gradient-to-br from-amber-500 to-orange-500 shadow-lg">
                                <ExternalLinkIcon class="h-5 w-5 text-white" />
                            </div>
                            <div>
                                <h3 class="text-headline-4 font-semibold text-neutral-900 dark:text-white mb-2">
                                    Developer Accounts
                                </h3>
                                <p class="text-body text-neutral-600 dark:text-neutral-400">
                                    You'll need developer accounts for each platform to connect them.
                                </p>
                            </div>
                        </div>
                        <div class="flex items-start gap-4">
                            <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-gradient-to-br from-amber-500 to-orange-500 shadow-lg">
                                <ExternalLinkIcon class="h-5 w-5 text-white" />
                            </div>
                            <div>
                                <h3 class="text-headline-4 font-semibold text-neutral-900 dark:text-white mb-2">
                                    Permissions
                                </h3>
                                <p class="text-body text-neutral-600 dark:text-neutral-400">
                                    We only request permissions needed for posting and analytics.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
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
