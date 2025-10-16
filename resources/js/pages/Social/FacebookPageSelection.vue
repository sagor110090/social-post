<script setup lang="ts">
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import {
    CheckCircleIcon,
    FacebookIcon,
    FileTextIcon,
    LoaderIcon,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';

interface FacebookPage {
    id: string;
    name: string;
    category?: string;
    access_token: string;
    tasks?: string[];
}

interface UserInfo {
    id: string;
    name: string;
    email?: string;
    avatar?: string;
}

const props = defineProps<{
    pages: FacebookPage[];
    userInfo: UserInfo;
    existingPageIds?: string[];
    errors?: Record<string, string>;
}>();

const selectedPageId = ref<string | null>(null);
const connecting = ref(false);

const selectedPage = computed(() => {
    return props.pages?.find(
        (page: FacebookPage) => page.id === selectedPageId.value,
    );
});

const isPageAlreadyConnected = (pageId: string) => {
    return props.existingPageIds?.includes(pageId) || false;
};

const selectPage = (pageId: string) => {
    // Don't allow selection of already connected pages
    if (!isPageAlreadyConnected(pageId)) {
        selectedPageId.value = pageId;
    }
};

const connectPage = () => {
    if (!selectedPageId.value || !selectedPage.value) return;

    connecting.value = true;

    router.post(
        '/oauth/facebook/save-page',
        {
            page_id: selectedPage.value.id,
            page_name: selectedPage.value.name,
            page_access_token: selectedPage.value.access_token,
        },
        {
            onSuccess: () => {
                // Redirect handled by controller
            },
            onError: (errors) => {
                connecting.value = false;
                console.error('Error connecting page:', errors);
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
</script>

<template>
    <Head title="Select Facebook Page" />

    <AppLayout>
        <div class="min-h-screen">
            <div class="p-6">
                <div class="mx-auto max-w-4xl">
                    <!-- Header -->
                    <div class="mb-8 text-center">
                        <div class="mb-6 flex justify-center">
                            <div
                                class="flex h-20 w-20 items-center justify-center rounded-2xl bg-blue-600 shadow-lg"
                            >
                                <FacebookIcon class="h-10 w-10 text-white" />
                            </div>
                        </div>
                        <h1
                            class="text-display-2 mb-4 text-neutral-900 dark:text-white"
                        >
                            Select Facebook Page to Connect
                        </h1>
                        <p
                            class="text-body-large mx-auto max-w-2xl text-neutral-600 dark:text-neutral-400"
                        >
                            Choose a Facebook page to connect. Pages already
                            connected are shown with a "Connected" badge.
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

                    <!-- Pages List -->
                    <div class="space-y-4">
                        <h2
                            class="text-headline-2 mb-4 text-neutral-900 dark:text-white"
                        >
                            Available Pages
                        </h2>

                        <div
                            v-if="pages?.length === 0"
                            class="card-elevated p-8 text-center"
                        >
                            <FileTextIcon
                                class="mx-auto mb-4 h-16 w-16 text-neutral-400"
                            />
                            <p
                                class="text-body-large text-neutral-600 dark:text-neutral-400"
                            >
                                No Facebook pages found
                            </p>
                            <p
                                class="text-body mt-2 text-neutral-500 dark:text-neutral-500"
                            >
                                Make sure you have admin access to at least one
                                Facebook page.
                            </p>
                        </div>

                        <div
                            v-for="page in pages"
                            :key="page.id"
                            class="card-elevated transition-all duration-200"
                            :class="{
                                'cursor-pointer hover:scale-[1.02]':
                                    !isPageAlreadyConnected(page.id),
                                'bg-blue-50 ring-2 ring-blue-500 dark:bg-blue-900/20':
                                    selectedPageId === page.id,
                                'cursor-not-allowed bg-neutral-50 opacity-75 dark:bg-neutral-800/50':
                                    isPageAlreadyConnected(page.id),
                            }"
                            @click="selectPage(page.id)"
                        >
                            <div class="p-6">
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center gap-4">
                                        <div
                                            class="flex h-12 w-12 items-center justify-center rounded-xl bg-blue-100 dark:bg-blue-900/50"
                                        >
                                            <FileTextIcon
                                                class="h-6 w-6 text-blue-600 dark:text-blue-400"
                                            />
                                        </div>
                                        <div>
                                            <h3
                                                class="text-headline-4 font-semibold text-neutral-900 dark:text-white"
                                            >
                                                {{ page.name }}
                                            </h3>
                                            <p
                                                class="text-body-small text-neutral-600 dark:text-neutral-400"
                                            >
                                                {{
                                                    page.category ||
                                                    'Facebook Page'
                                                }}
                                            </p>
                                        </div>
                                    </div>

                                    <div class="flex items-center gap-3">
                                        <!-- Already Connected Badge -->
                                        <div
                                            v-if="
                                                isPageAlreadyConnected(page.id)
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

                                        <!-- Page Tasks -->
                                        <div
                                            v-else-if="page.tasks?.length"
                                            class="flex flex-wrap gap-1"
                                        >
                                            <span
                                                v-for="task in page.tasks.slice(
                                                    0,
                                                    2,
                                                )"
                                                :key="task"
                                                class="inline-block rounded-full bg-blue-100 px-2 py-1 text-xs font-medium text-blue-800 dark:bg-blue-900/50 dark:text-blue-200"
                                            >
                                                {{ task.replace('_', ' ') }}
                                            </span>
                                        </div>

                                        <!-- Selection Indicator -->
                                        <div
                                            v-if="
                                                selectedPageId === page.id &&
                                                !isPageAlreadyConnected(page.id)
                                            "
                                            class="flex h-6 w-6 items-center justify-center rounded-full bg-blue-500"
                                        >
                                            <CheckCircleIcon
                                                class="h-4 w-4 text-white"
                                            />
                                        </div>
                                        <div
                                            v-else-if="
                                                !isPageAlreadyConnected(page.id)
                                            "
                                            class="flex h-6 w-6 items-center justify-center rounded-full border-2 border-neutral-300 dark:border-neutral-600"
                                        ></div>
                                    </div>
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
                            class="btn-primary hover-glow"
                            @click="connectPage"
                            :disabled="!selectedPageId || connecting"
                        >
                            <LoaderIcon
                                v-if="connecting"
                                class="mr-2 h-5 w-5 animate-spin"
                            />
                            {{ connecting ? 'Connecting...' : 'Connect Page' }}
                        </Button>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
