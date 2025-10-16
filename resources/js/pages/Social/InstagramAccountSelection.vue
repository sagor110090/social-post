<script setup lang="ts">
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import {
    CheckCircleIcon,
    FileTextIcon,
    InstagramIcon,
    LoaderIcon,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';

interface InstagramAccount {
    id: string;
    username: string;
    account_type: string;
    media_count?: number;
    followers_count?: number;
    follows_count?: number;
    website?: string;
    biography?: string;
}

interface UserInfo {
    id: string;
    name: string;
    email?: string;
    avatar?: string;
}

const props = defineProps<{
    accounts: InstagramAccount[];
    userInfo: UserInfo;
    existingAccountIds?: string[];
    errors?: Record<string, string>;
}>();

const selectedAccountId = ref<string | null>(null);
const connecting = ref(false);

const selectedAccount = computed(() => {
    return props.accounts?.find(
        (account: InstagramAccount) => account.id === selectedAccountId.value,
    );
});

const isAccountAlreadyConnected = (accountId: string) => {
    return props.existingAccountIds?.includes(accountId) || false;
};

const selectAccount = (accountId: string) => {
    // Don't allow selection of already connected accounts
    if (!isAccountAlreadyConnected(accountId)) {
        selectedAccountId.value = accountId;
    }
};

const connectAccount = () => {
    if (!selectedAccountId.value || !selectedAccount.value) return;

    connecting.value = true;

    router.post(
        '/oauth/instagram/save-account',
        {
            account_id: selectedAccount.value.id,
            username: selectedAccount.value.username,
            account_type: selectedAccount.value.account_type,
        },
        {
            onSuccess: () => {
                // Redirect handled by controller
            },
            onError: (errors) => {
                connecting.value = false;
                console.error('Error connecting account:', errors);
            },
            onFinish: () => {
                connecting.value = false;
            },
        },
    );
};

const cancel = () => {
    router.visit('/dashboard');
};

const formatNumber = (num: number) => {
    if (num >= 1000000) {
        return (num / 1000000).toFixed(1) + 'M';
    } else if (num >= 1000) {
        return (num / 1000).toFixed(1) + 'K';
    }
    return num.toString();
};
</script>

<template>
    <Head title="Select Instagram Account" />

    <AppLayout>
        <div class="min-h-screen">
            <div class="p-6">
                <div class="mx-auto max-w-4xl">
                    <!-- Header -->
                    <div class="mb-8 text-center">
                        <div class="mb-6 flex justify-center">
                            <div
                                class="flex h-20 w-20 items-center justify-center rounded-2xl bg-gradient-to-br from-purple-600 via-pink-600 to-orange-600 shadow-lg"
                            >
                                <InstagramIcon class="h-10 w-10 text-white" />
                            </div>
                        </div>
                        <h1
                            class="text-display-2 mb-4 text-neutral-900 dark:text-white"
                        >
                            Select Instagram Account to Connect
                        </h1>
                        <p
                            class="text-body-large mx-auto max-w-2xl text-neutral-600 dark:text-neutral-400"
                        >
                            Choose an Instagram Business account to connect.
                            Accounts already connected are shown with a
                            "Connected" badge.
                        </p>
                    </div>

                    <!-- User Info -->
                    <div class="card-elevated mb-8 p-6">
                        <div class="flex items-center gap-4">
                            <img
                                v-if="userInfo?.avatar"
                                :src="userInfo.avatar"
                                :alt="userInfo.name"
                                class="h-12 w-12 rounded-full"
                            />
                            <div
                                class="flex h-12 w-12 items-center justify-center rounded-full bg-neutral-200 dark:bg-neutral-700"
                            >
                                <FileTextIcon
                                    class="h-6 w-6 text-neutral-600 dark:text-neutral-400"
                                />
                            </div>
                            <div>
                                <p
                                    class="text-body font-medium text-neutral-900 dark:text-white"
                                >
                                    Connected as {{ userInfo?.name }}
                                </p>
                                <p
                                    class="text-body-small text-neutral-600 dark:text-neutral-400"
                                >
                                    {{ userInfo?.email }}
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Accounts List -->
                    <div class="space-y-4">
                        <h2
                            class="text-headline-2 mb-4 text-neutral-900 dark:text-white"
                        >
                            Available Instagram Accounts
                        </h2>

                        <div
                            v-if="accounts?.length === 0"
                            class="card-elevated p-8 text-center"
                        >
                            <FileTextIcon
                                class="mx-auto mb-4 h-16 w-16 text-neutral-400"
                            />
                            <p
                                class="text-body-large text-neutral-600 dark:text-neutral-400"
                            >
                                No Instagram Business accounts found
                            </p>
                            <p
                                class="text-body mt-2 text-neutral-500 dark:text-neutral-500"
                            >
                                Make sure you have Instagram Business accounts
                                linked to your Facebook pages.
                            </p>
                        </div>

                        <div
                            v-for="account in accounts"
                            :key="account.id"
                            class="card-elevated transition-all duration-200"
                            :class="{
                                'cursor-pointer hover:scale-[1.02]':
                                    !isAccountAlreadyConnected(account.id),
                                'bg-purple-50 ring-2 ring-purple-500 dark:bg-purple-900/20':
                                    selectedAccountId === account.id,
                                'cursor-not-allowed bg-neutral-50 opacity-75 dark:bg-neutral-800/50':
                                    isAccountAlreadyConnected(account.id),
                            }"
                            @click="selectAccount(account.id)"
                        >
                            <div class="p-6">
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center gap-4">
                                        <div
                                            class="flex h-12 w-12 items-center justify-center rounded-xl bg-gradient-to-br from-purple-100 to-pink-100 dark:from-purple-900/50 dark:to-pink-900/50"
                                        >
                                            <InstagramIcon
                                                class="h-6 w-6 text-purple-600 dark:text-purple-400"
                                            />
                                        </div>
                                        <div>
                                            <h3
                                                class="text-headline-4 font-semibold text-neutral-900 dark:text-white"
                                            >
                                                @{{ account.username }}
                                            </h3>
                                            <p
                                                class="text-body-small text-neutral-600 dark:text-neutral-400"
                                            >
                                                {{
                                                    account.account_type
                                                        .charAt(0)
                                                        .toUpperCase() +
                                                    account.account_type.slice(
                                                        1,
                                                    )
                                                }}
                                                Account
                                            </p>
                                        </div>
                                    </div>

                                    <div class="flex items-center gap-3">
                                        <!-- Account Stats -->
                                        <div
                                            v-if="
                                                !isAccountAlreadyConnected(
                                                    account.id,
                                                )
                                            "
                                            class="flex flex-wrap gap-2"
                                        >
                                            <span
                                                v-if="account.followers_count"
                                                class="inline-block rounded-full bg-purple-100 px-2 py-1 text-xs font-medium text-purple-800 dark:bg-purple-900/50 dark:text-purple-200"
                                            >
                                                {{
                                                    formatNumber(
                                                        account.followers_count,
                                                    )
                                                }}
                                                followers
                                            </span>
                                            <span
                                                v-if="account.media_count"
                                                class="inline-block rounded-full bg-pink-100 px-2 py-1 text-xs font-medium text-pink-800 dark:bg-pink-900/50 dark:text-pink-200"
                                            >
                                                {{
                                                    formatNumber(
                                                        account.media_count,
                                                    )
                                                }}
                                                posts
                                            </span>
                                        </div>

                                        <!-- Already Connected Badge -->
                                        <div
                                            v-if="
                                                isAccountAlreadyConnected(
                                                    account.id,
                                                )
                                            "
                                            class="inline-flex items-center gap-2 rounded-full border border-emerald-200/60 bg-emerald-50/80 px-3 py-1 backdrop-blur-sm dark:border-emerald-800/60 dark:bg-emerald-900/30"
                                        >
                                            <div
                                                class="h-2 w-2 rounded-full bg-emerald-500"
                                            ></div>
                                            <span
                                                class="text-body-small font-medium text-emerald-700 dark:text-emerald-300"
                                            >
                                                Connected
                                            </span>
                                        </div>

                                        <!-- Selection Indicator -->
                                        <div
                                            v-if="
                                                selectedAccountId ===
                                                    account.id &&
                                                !isAccountAlreadyConnected(
                                                    account.id,
                                                )
                                            "
                                            class="flex h-6 w-6 items-center justify-center rounded-full bg-purple-500"
                                        >
                                            <CheckCircleIcon
                                                class="h-4 w-4 text-white"
                                            />
                                        </div>
                                        <div
                                            v-else-if="
                                                !isAccountAlreadyConnected(
                                                    account.id,
                                                )
                                            "
                                            class="flex h-6 w-6 items-center justify-center rounded-full border-2 border-neutral-300 dark:border-neutral-600"
                                        ></div>
                                    </div>
                                </div>

                                <!-- Biography -->
                                <div v-if="account.biography" class="mt-4">
                                    <p
                                        class="text-body-small line-clamp-2 text-neutral-600 dark:text-neutral-400"
                                    >
                                        {{ account.biography }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Actions -->
                    <div class="mt-8 flex justify-end gap-4">
                        <Button
                            variant="outline"
                            @click="cancel"
                            :disabled="connecting"
                            class="hover-glow"
                        >
                            Cancel
                        </Button>
                        <Button
                            class="btn-primary hover-glow bg-gradient-to-r from-purple-600 to-pink-600 hover:from-purple-700 hover:to-pink-700"
                            @click="connectAccount"
                            :disabled="!selectedAccountId || connecting"
                        >
                            <LoaderIcon
                                v-if="connecting"
                                class="mr-2 h-5 w-5 animate-spin"
                            />
                            {{
                                connecting ? 'Connecting...' : 'Connect Account'
                            }}
                        </Button>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
