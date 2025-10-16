<script setup>
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useToast } from '@/composables/useToast';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import {
    AlertCircleIcon,
    CalendarIcon,
    CheckCircleIcon,
    ClockIcon,
    ExternalLinkIcon,
    EyeIcon,
    FilterIcon,
    RefreshCwIcon,
    TrashIcon,
    XCircleIcon,
} from 'lucide-vue-next';
import { computed, onMounted, ref, watch } from 'vue';

const props = defineProps({
    posts: Object,
    filters: Object,
    flash: Object,
});

const loading = ref(false);
const deleting = ref(false);
const selectedPost = ref(null);
const showDeleteDialog = ref(false);
const showDetailsModal = ref(false);

const filters = ref({
    status: props.filters?.status || '',
    platform: props.filters?.platform || '',
    limit: 20,
    offset: 0,
});

const posts = ref(props.posts?.posts || []);
const pagination = ref(props.posts?.pagination || {});

const toast = useToast();
const page = usePage();

const statusOptions = [
    { value: '', label: 'All Status' },
    { value: 'draft', label: 'Draft' },
    { value: 'scheduled', label: 'Scheduled' },
    { value: 'published', label: 'Published' },
    { value: 'partially_published', label: 'Partially Published' },
    { value: 'failed', label: 'Failed' },
];

const platformOptions = [
    { value: '', label: 'All Platforms' },
    { value: 'facebook', label: 'Facebook' },
    { value: 'instagram', label: 'Instagram' },
    { value: 'linkedin', label: 'LinkedIn' },
    { value: 'twitter', label: 'X (Twitter)' },
];

const platformIcons = {
    facebook: '📘',
    instagram: '📷',
    linkedin: '💼',
    twitter: '🐦',
};

const statusColors = {
    draft: 'bg-neutral-100 text-neutral-800 dark:bg-neutral-800 dark:text-neutral-200',
    scheduled:
        'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300',
    published:
        'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300',
    partially_published:
        'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-300',
    failed: 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-300',
};

const statusIcons = {
    draft: ClockIcon,
    scheduled: ClockIcon,
    published: CheckCircleIcon,
    partially_published: AlertCircleIcon,
    failed: XCircleIcon,
};

const filteredPosts = computed(() => {
    return posts.value;
});

const loadPosts = () => {
    loading.value = true;

    router.get('/social/posts/history', filters.value, {
        preserveState: true,
        onSuccess: (response) => {
            posts.value = response.props.posts.posts;
            pagination.value = response.props.posts.pagination;
        },
        onFinish: () => {
            loading.value = false;
        },
    });
};

const applyFilters = () => {
    filters.value.offset = 0;
    loadPosts();
};

const loadMore = () => {
    if (!pagination.value.has_more) return;

    filters.value.offset += filters.value.limit;
    loadPosts();
};

const refreshPosts = () => {
    filters.value.offset = 0;
    loadPosts();
};

const confirmDelete = (post) => {
    selectedPost.value = post;
    showDeleteDialog.value = true;
};

const showPostDetails = (post) => {
    selectedPost.value = post;
    showDetailsModal.value = true;
};

const deletePost = () => {
    if (!selectedPost.value) return;

    deleting.value = true;

    router.delete(`/posts/${selectedPost.value.id}`, {
        onSuccess: () => {
            posts.value = posts.value.filter(
                (p) => p.id !== selectedPost.value.id,
            );
            showDeleteDialog.value = false;
            selectedPost.value = null;
        },
        onFinish: () => {
            deleting.value = false;
        },
    });
};

const formatDate = (dateString) => {
    return new Date(dateString).toLocaleDateString();
};

const formatDateTime = (dateString) => {
    return new Date(dateString).toLocaleString();
};

const truncateText = (text, maxLength = 100) => {
    if (text.length <= maxLength) return text;
    return text.substring(0, maxLength) + '...';
};

const getPlatformResults = (post) => {
    const results = post.platform_results;
    if (!results || typeof results !== 'object' || Array.isArray(results)) {
        return {};
    }
    return results;
};

const hasSuccessfulPost = (post) => {
    return Object.values(getPlatformResults(post)).some(
        (result) => result && result.url,
    );
};

onMounted(() => {
    // Show flash messages as toast notifications
    if (props.flash?.success) {
        toast.success(props.flash.success);
    }
    if (props.flash?.error) {
        toast.error(props.flash.error);
    }

    if (!posts.value.length) {
        loadPosts();
    }
});

// Watch for flash message changes (for redirects)
watch(
    () => page.props.flash,
    (flash) => {
        if (flash?.success) {
            toast.success(flash.success);
        }
        if (flash?.error) {
            toast.error(flash.error);
        }
    },
    { immediate: true },
);
</script>

<template>
    <Head title="Post History" />

    <AppLayout>
        <div class="min-h-screen">
            <div class="p-6">
                <div class="mx-auto max-w-7xl">
                    <!-- Header -->
                    <div class="animate-fade-in mb-12">
                        <h1
                            class="text-display-1 mb-4 text-neutral-900 dark:text-white"
                        >
                            Post
                            <span class="text-gradient font-bold">History</span>
                            📝
                        </h1>
                        <p
                            class="text-body-large max-w-3xl leading-relaxed text-neutral-600 dark:text-neutral-400"
                        >
                            View and manage your published and scheduled posts
                            across all platforms.
                        </p>
                        <div class="mt-6 flex gap-4">
                            <Button
                                variant="outline"
                                class="hover-glow"
                                @click="refreshPosts"
                                :disabled="loading"
                            >
                                <RefreshCwIcon
                                    class="mr-3 h-5 w-5"
                                    :class="{ 'animate-spin': loading }"
                                />
                                Refresh
                            </Button>
                            <Button as-child class="btn-primary hover-glow">
                                <a href="/social/posts/create"
                                    >Create New Post</a
                                >
                            </Button>
                        </div>
                    </div>

                    <!-- Filters -->
                    <div
                        class="card-elevated animate-slide-up relative mb-12 overflow-hidden"
                    >
                        <div
                            class="absolute top-0 left-0 h-1 w-full bg-gradient-to-r from-purple-500 to-pink-500"
                        ></div>
                        <div class="mb-6">
                            <h2
                                class="text-headline-1 mb-3 flex items-center gap-3 text-neutral-900 dark:text-white"
                            >
                                <FilterIcon class="h-6 w-6" />
                                Filters
                            </h2>
                            <p
                                class="text-body-large text-neutral-600 dark:text-neutral-400"
                            >
                                Filter posts by status, platform, or adjust
                                pagination
                            </p>
                        </div>
                        <div class="grid gap-6 md:grid-cols-4">
                            <div class="space-y-2">
                                <Label
                                    for="status-filter"
                                    class="text-body font-medium"
                                    >Status</Label
                                >
                                <Select
                                    v-model="filters.status"
                                    @update:modelValue="applyFilters"
                                >
                                    <SelectTrigger>
                                        <SelectValue
                                            placeholder="Select status"
                                        />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem
                                            v-for="option in statusOptions"
                                            :key="option.value"
                                            :value="option.value"
                                        >
                                            {{ option.label }}
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>

                            <div class="space-y-2">
                                <Label
                                    for="platform-filter"
                                    class="text-body font-medium"
                                    >Platform</Label
                                >
                                <Select
                                    v-model="filters.platform"
                                    @update:modelValue="applyFilters"
                                >
                                    <SelectTrigger>
                                        <SelectValue
                                            placeholder="Select platform"
                                        />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem
                                            v-for="option in platformOptions"
                                            :key="option.value"
                                            :value="option.value"
                                        >
                                            {{ option.label }}
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>

                            <div class="space-y-2">
                                <Label for="limit" class="text-body font-medium"
                                    >Posts per page</Label
                                >
                                <Select
                                    v-model="filters.limit"
                                    @update:modelValue="applyFilters"
                                >
                                    <SelectTrigger>
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem :value="10">10</SelectItem>
                                        <SelectItem :value="20">20</SelectItem>
                                        <SelectItem :value="50">50</SelectItem>
                                        <SelectItem :value="100"
                                            >100</SelectItem
                                        >
                                    </SelectContent>
                                </Select>
                            </div>

                            <div class="flex items-end">
                                <Button
                                    variant="outline"
                                    class="hover-glow"
                                    @click="
                                        filters = {
                                            status: '',
                                            platform: '',
                                            limit: 20,
                                            offset: 0,
                                        };
                                        loadPosts();
                                    "
                                >
                                    Clear Filters
                                </Button>
                            </div>
                        </div>
                    </div>

                    <!-- Posts List -->
                    <div class="space-y-6">
                        <div
                            v-if="filteredPosts.length === 0"
                            class="card-elevated animate-slide-up relative overflow-hidden py-12 text-center"
                        >
                            <div
                                class="mb-6 text-neutral-400 dark:text-neutral-500"
                            >
                                <CalendarIcon class="mx-auto h-16 w-16" />
                            </div>
                            <h3
                                class="text-headline-2 mb-3 text-neutral-900 dark:text-white"
                            >
                                No posts found
                            </h3>
                            <p
                                class="text-body-large mb-6 text-neutral-600 dark:text-neutral-400"
                            >
                                {{
                                    filters.status || filters.platform
                                        ? 'Try adjusting your filters'
                                        : 'Get started by creating your first post'
                                }}
                            </p>
                            <Button
                                as-child
                                class="btn-primary hover-glow"
                                v-if="!filters.status && !filters.platform"
                            >
                                <a href="/social/posts/create">Create Post</a>
                            </Button>
                        </div>

                        <div
                            v-for="post in filteredPosts"
                            :key="post.id"
                            class="card-elevated group animate-slide-up relative overflow-hidden transition-all duration-300 hover:scale-[1.01]"
                        >
                            <div
                                class="absolute top-0 left-0 h-1 w-full opacity-0 transition-opacity duration-300 group-hover:opacity-100"
                                :class="
                                    post.status === 'published'
                                        ? 'bg-gradient-to-r from-green-500 to-emerald-500'
                                        : post.status === 'scheduled'
                                          ? 'bg-gradient-to-r from-blue-500 to-indigo-500'
                                          : post.status === 'failed'
                                            ? 'bg-gradient-to-r from-red-500 to-pink-500'
                                            : 'bg-gradient-to-r from-gray-500 to-gray-600'
                                "
                            ></div>
                            <div class="p-6">
                                <div
                                    class="mb-4 flex items-start justify-between"
                                >
                                    <div class="flex-1">
                                        <div
                                            class="mb-3 flex items-center gap-3"
                                        >
                                            <Badge
                                                :class="
                                                    statusColors[post.status]
                                                "
                                                class="flex items-center gap-2 px-3 py-1"
                                            >
                                                <component
                                                    :is="
                                                        statusIcons[post.status]
                                                    "
                                                    class="h-4 w-4"
                                                />
                                                {{
                                                    post.status.replace(
                                                        '_',
                                                        ' ',
                                                    )
                                                }}
                                            </Badge>

                                            <div class="flex gap-2">
                                                <span
                                                    v-for="platform in post.platforms"
                                                    :key="platform"
                                                    class="text-xl"
                                                >
                                                    {{
                                                        platformIcons[platform]
                                                    }}
                                                </span>
                                            </div>
                                        </div>

                                        <div
                                            class="text-body-large mb-3 leading-relaxed whitespace-pre-wrap text-neutral-900 dark:text-neutral-100"
                                        >
                                            {{
                                                truncateText(post.content, 200)
                                            }}
                                        </div>

                                        <div
                                            v-if="post.link || post.image_url"
                                            class="text-body mb-3 flex gap-6 text-neutral-600 dark:text-neutral-400"
                                        >
                                            <span
                                                v-if="post.link"
                                                class="flex items-center gap-2"
                                            >
                                                <ExternalLinkIcon
                                                    class="h-4 w-4"
                                                />
                                                Link included
                                            </span>
                                            <span
                                                v-if="post.image_url"
                                                class="flex items-center gap-2"
                                            >
                                                <EyeIcon class="h-4 w-4" />
                                                Image included
                                            </span>
                                        </div>

                                        <div
                                            class="text-body text-neutral-500 dark:text-neutral-400"
                                        >
                                            Created
                                            {{ formatDate(post.created_at) }}
                                            <span
                                                v-if="post.scheduled_at"
                                                class="ml-2"
                                            >
                                                • Scheduled for
                                                {{
                                                    formatDateTime(
                                                        post.scheduled_at,
                                                    )
                                                }}
                                            </span>
                                        </div>
                                    </div>

                                    <div class="ml-6 flex gap-3">
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            class="hover-glow"
                                            @click="showPostDetails(post)"
                                        >
                                            <EyeIcon class="h-4 w-4" />
                                        </Button>
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            class="hover:border-red-500 hover:bg-red-50 hover:text-red-600 dark:hover:bg-red-900/20"
                                            @click="confirmDelete(post)"
                                            :disabled="deleting"
                                        >
                                            <TrashIcon class="h-4 w-4" />
                                        </Button>
                                    </div>
                                </div>

                                <!-- Platform Results -->
                                <div
                                    v-if="hasSuccessfulPost(post)"
                                    class="border-t border-neutral-200/60 pt-4 dark:border-neutral-700/60"
                                >
                                    <h4
                                        class="text-headline-4 mb-3 font-medium text-neutral-900 dark:text-white"
                                    >
                                        Published Links:
                                    </h4>
                                    <div class="flex flex-wrap gap-3">
                                        <template
                                            v-for="(
                                                result, platform
                                            ) in getPlatformResults(post)"
                                            :key="platform"
                                        >
                                            <a
                                                v-if="result && result.url"
                                                :href="result.url"
                                                target="_blank"
                                                class="text-body inline-flex items-center gap-2 rounded-full border border-blue-200/60 bg-blue-50/80 px-3 py-1 text-blue-600 hover:underline dark:border-blue-800/60 dark:bg-blue-900/30 dark:text-blue-400"
                                            >
                                                <span class="text-lg">{{
                                                    platformIcons[platform]
                                                }}</span>
                                                View on
                                                {{
                                                    platform
                                                        .charAt(0)
                                                        .toUpperCase() +
                                                    platform.slice(1)
                                                }}
                                                <ExternalLinkIcon
                                                    class="h-3 w-3"
                                                />
                                            </a>
                                        </template>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Load More -->
                    <div v-if="pagination.has_more" class="mt-8 text-center">
                        <Button
                            variant="outline"
                            class="hover-glow"
                            @click="loadMore"
                            :disabled="loading"
                        >
                            <span v-if="loading">Loading...</span>
                            <span v-else>Load More</span>
                        </Button>
                    </div>

                    <!-- Pagination Info -->
                    <div
                        v-if="pagination.total > 0"
                        class="text-body mt-8 text-center text-neutral-600 dark:text-neutral-400"
                    >
                        Showing
                        {{
                            Math.min(pagination.offset + 1, pagination.total)
                        }}-{{
                            Math.min(
                                pagination.offset + pagination.limit,
                                pagination.total,
                            )
                        }}
                        of {{ pagination.total }} posts
                    </div>
                </div>
            </div>
        </div>

        <!-- Delete Confirmation Dialog -->
        <div
            v-if="showDeleteDialog"
            class="bg-opacity-50 fixed inset-0 z-[60] flex items-center justify-center bg-black backdrop-blur-sm"
        >
            <div class="card-elevated mx-4 w-full max-w-md overflow-hidden">
                <div
                    class="absolute top-0 left-0 h-1 w-full bg-gradient-to-r from-red-500 to-pink-500"
                ></div>
                <div class="p-6">
                    <h3
                        class="text-headline-2 mb-4 text-neutral-900 dark:text-white"
                    >
                        Delete Post
                    </h3>
                    <p
                        class="text-body-large mb-6 text-neutral-600 dark:text-neutral-400"
                    >
                        Are you sure you want to delete this post? This action
                        cannot be undone.
                    </p>

                    <div class="flex justify-end gap-3">
                        <Button
                            variant="outline"
                            class="hover-glow"
                            @click="showDeleteDialog = false"
                            :disabled="deleting"
                        >
                            Cancel
                        </Button>
                        <Button
                            variant="destructive"
                            class="hover-glow"
                            @click="deletePost"
                            :disabled="deleting"
                        >
                            <span v-if="deleting">Deleting...</span>
                            <span v-else>Delete Post</span>
                        </Button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Post Details Modal -->
        <div
            v-if="showDetailsModal && selectedPost"
            class="bg-opacity-50 fixed inset-0 z-50 flex items-center justify-center bg-black p-4 backdrop-blur-sm"
            @click.self="showDetailsModal = false"
        >
            <div
                class="card-elevated animate-slide-up relative mx-auto flex h-[85vh] w-full max-w-4xl flex-col overflow-hidden"
            >
                <!-- Modal Header -->
                <div
                    class="absolute top-0 left-0 h-1 w-full bg-gradient-to-r from-purple-500 to-pink-500"
                ></div>

                <div
                    class="flex-shrink-0 border-b border-neutral-200/60 bg-white dark:border-neutral-700/60 dark:bg-neutral-900"
                >
                    <div class="flex items-center justify-between p-4">
                        <div class="flex items-center gap-3">
                            <div
                                class="bg-brand-primary flex h-8 w-8 items-center justify-center rounded-lg"
                            >
                                <EyeIcon class="h-4 w-4 text-white" />
                            </div>
                            <div>
                                <h3
                                    class="text-headline-3 text-neutral-900 dark:text-white"
                                >
                                    Post Details
                                </h3>
                                <p
                                    class="text-body-small text-neutral-600 dark:text-neutral-400"
                                >
                                    ID: {{ selectedPost.id }}
                                </p>
                            </div>
                        </div>

                        <Button
                            variant="ghost"
                            size="sm"
                            @click="showDetailsModal = false"
                            class="text-neutral-500 hover:text-neutral-700 dark:text-neutral-400 dark:hover:text-neutral-200"
                        >
                            <svg
                                class="h-5 w-5"
                                fill="none"
                                stroke="currentColor"
                                viewBox="0 0 24 24"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M6 18L18 6M6 6l12 12"
                                />
                            </svg>
                        </Button>
                    </div>
                </div>

                <!-- Modal Content -->
                <div class="flex-1 overflow-y-auto">
                    <div class="space-y-6 p-4">
                        <!-- Status and Platforms -->
                        <div class="flex flex-wrap items-center gap-4">
                            <Badge
                                :class="statusColors[selectedPost.status]"
                                class="flex items-center gap-2 px-3 py-2"
                            >
                                <component
                                    :is="statusIcons[selectedPost.status]"
                                    class="h-4 w-4"
                                />
                                {{ selectedPost.status.replace('_', ' ') }}
                            </Badge>

                            <div class="flex items-center gap-2">
                                <span
                                    class="text-body-small font-medium text-neutral-600 dark:text-neutral-400"
                                    >Platforms:</span
                                >
                                <div class="flex gap-2">
                                    <span
                                        v-for="platform in selectedPost.platforms"
                                        :key="platform"
                                        class="text-xl"
                                        :title="
                                            platform.charAt(0).toUpperCase() +
                                            platform.slice(1)
                                        "
                                    >
                                        {{ platformIcons[platform] }}
                                    </span>
                                </div>
                            </div>
                        </div>

                        <!-- Content -->
                        <div>
                            <h4
                                class="text-headline-4 mb-3 flex items-center gap-2 text-neutral-900 dark:text-white"
                            >
                                <svg
                                    class="h-4 w-4"
                                    fill="none"
                                    stroke="currentColor"
                                    viewBox="0 0 24 24"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"
                                    />
                                </svg>
                                Content
                            </h4>
                            <div
                                class="rounded-lg border border-neutral-200 bg-gradient-to-br from-neutral-50 to-neutral-100 p-4 dark:border-neutral-700 dark:from-neutral-800 dark:to-neutral-900"
                            >
                                <p
                                    class="text-body leading-relaxed whitespace-pre-wrap text-neutral-700 dark:text-neutral-300"
                                >
                                    {{ selectedPost.content }}
                                </p>
                            </div>
                        </div>

                        <!-- Media and Links -->
                        <div v-if="selectedPost.link || selectedPost.image_url">
                            <h4
                                class="text-headline-4 mb-3 flex items-center gap-2 text-neutral-900 dark:text-white"
                            >
                                <svg
                                    class="h-4 w-4"
                                    fill="none"
                                    stroke="currentColor"
                                    viewBox="0 0 24 24"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"
                                    />
                                </svg>
                                Media & Links
                            </h4>

                            <div class="space-y-3">
                                <!-- Link Preview -->
                                <div
                                    v-if="selectedPost.link"
                                    class="rounded-lg border border-blue-200 bg-gradient-to-br from-blue-50 to-cyan-50 p-3 dark:border-blue-800 dark:from-blue-900/20 dark:to-cyan-900/20"
                                >
                                    <div class="flex items-center gap-3">
                                        <div
                                            class="flex h-8 w-8 items-center justify-center rounded-lg bg-blue-500"
                                        >
                                            <svg
                                                class="h-4 w-4 text-white"
                                                fill="none"
                                                stroke="currentColor"
                                                viewBox="0 0 24 24"
                                            >
                                                <path
                                                    stroke-linecap="round"
                                                    stroke-linejoin="round"
                                                    stroke-width="2"
                                                    d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"
                                                />
                                            </svg>
                                        </div>
                                        <div class="flex-1">
                                            <p
                                                class="text-body-small font-medium text-blue-800 dark:text-blue-200"
                                            >
                                                Link
                                            </p>
                                            <a
                                                :href="selectedPost.link"
                                                target="_blank"
                                                class="text-body block truncate text-blue-600 hover:text-blue-700 hover:underline dark:text-blue-400 dark:hover:text-blue-300"
                                            >
                                                {{ selectedPost.link }}
                                            </a>
                                        </div>
                                        <ExternalLinkIcon
                                            class="h-4 w-4 text-blue-600 dark:text-blue-400"
                                        />
                                    </div>
                                </div>

                                <!-- Image Preview -->
                                <div
                                    v-if="selectedPost.image_url"
                                    class="rounded-lg border border-green-200 bg-gradient-to-br from-green-50 to-emerald-50 p-3 dark:border-green-800 dark:from-green-900/20 dark:to-emerald-900/20"
                                >
                                    <div class="flex items-center gap-3">
                                        <div
                                            class="flex h-8 w-8 items-center justify-center rounded-lg bg-green-500"
                                        >
                                            <svg
                                                class="h-4 w-4 text-white"
                                                fill="none"
                                                stroke="currentColor"
                                                viewBox="0 0 24 24"
                                            >
                                                <path
                                                    stroke-linecap="round"
                                                    stroke-linejoin="round"
                                                    stroke-width="2"
                                                    d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"
                                                />
                                            </svg>
                                        </div>
                                        <div class="flex-1">
                                            <p
                                                class="text-body-small font-medium text-green-800 dark:text-green-200"
                                            >
                                                Image
                                            </p>
                                            <img
                                                :src="selectedPost.image_url"
                                                alt="Post image"
                                                class="mt-2 h-auto max-h-64 max-w-full rounded-lg object-cover"
                                                @error="
                                                    $event.target.style.display =
                                                        'none'
                                                "
                                            />
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Platform Results -->
                        <div v-if="hasSuccessfulPost(selectedPost)">
                            <h4
                                class="text-headline-4 mb-3 flex items-center gap-2 text-neutral-900 dark:text-white"
                            >
                                <svg
                                    class="h-4 w-4"
                                    fill="none"
                                    stroke="currentColor"
                                    viewBox="0 0 24 24"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"
                                    />
                                </svg>
                                Published Links
                            </h4>
                            <div
                                class="grid gap-2 sm:grid-cols-2 lg:grid-cols-3"
                            >
                                <template
                                    v-for="(
                                        result, platform
                                    ) in getPlatformResults(selectedPost)"
                                    :key="platform"
                                >
                                    <a
                                        v-if="result && result.url"
                                        :href="result.url"
                                        target="_blank"
                                        class="group flex items-center gap-2 rounded-lg border border-blue-200/60 bg-blue-50/80 p-3 transition-all hover:bg-blue-100 hover:shadow-md dark:border-blue-800/60 dark:bg-blue-900/30 dark:hover:bg-blue-900/50"
                                    >
                                        <div
                                            class="flex h-8 w-8 items-center justify-center rounded-lg bg-blue-500 text-white"
                                        >
                                            <span class="text-lg">{{
                                                platformIcons[platform]
                                            }}</span>
                                        </div>
                                        <div class="flex-1">
                                            <p
                                                class="text-body font-medium text-blue-800 dark:text-blue-200"
                                            >
                                                {{
                                                    platform
                                                        .charAt(0)
                                                        .toUpperCase() +
                                                    platform.slice(1)
                                                }}
                                            </p>
                                            <p
                                                class="text-body-small text-blue-600 dark:text-blue-400"
                                            >
                                                View post
                                            </p>
                                        </div>
                                        <ExternalLinkIcon
                                            class="h-4 w-4 text-blue-600 transition-transform group-hover:translate-x-0.5 dark:text-blue-400"
                                        />
                                    </a>
                                </template>
                            </div>
                        </div>

                        <!-- Timestamps -->
                        <div>
                            <h4
                                class="text-headline-4 mb-3 flex items-center gap-2 text-neutral-900 dark:text-white"
                            >
                                <svg
                                    class="h-4 w-4"
                                    fill="none"
                                    stroke="currentColor"
                                    viewBox="0 0 24 24"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"
                                    />
                                </svg>
                                Timestamps
                            </h4>
                            <div
                                class="rounded-lg border border-neutral-200 bg-gradient-to-br from-neutral-50 to-neutral-100 p-3 dark:border-neutral-700 dark:from-neutral-800 dark:to-neutral-900"
                            >
                                <div class="grid gap-3 sm:grid-cols-2">
                                    <div>
                                        <p
                                            class="text-body-small font-medium text-neutral-600 dark:text-neutral-400"
                                        >
                                            Created
                                        </p>
                                        <p
                                            class="text-body text-neutral-900 dark:text-white"
                                        >
                                            {{
                                                formatDateTime(
                                                    selectedPost.created_at,
                                                )
                                            }}
                                        </p>
                                    </div>
                                    <div v-if="selectedPost.scheduled_at">
                                        <p
                                            class="text-body-small font-medium text-neutral-600 dark:text-neutral-400"
                                        >
                                            Scheduled For
                                        </p>
                                        <p
                                            class="text-body text-neutral-900 dark:text-white"
                                        >
                                            {{
                                                formatDateTime(
                                                    selectedPost.scheduled_at,
                                                )
                                            }}
                                        </p>
                                    </div>
                                    <div v-if="selectedPost.updated_at">
                                        <p
                                            class="text-body-small font-medium text-neutral-600 dark:text-neutral-400"
                                        >
                                            Last Updated
                                        </p>
                                        <p
                                            class="text-body text-neutral-900 dark:text-white"
                                        >
                                            {{
                                                formatDateTime(
                                                    selectedPost.updated_at,
                                                )
                                            }}
                                        </p>
                                    </div>
                                    <div v-if="selectedPost.published_at">
                                        <p
                                            class="text-body-small font-medium text-neutral-600 dark:text-neutral-400"
                                        >
                                            Published
                                        </p>
                                        <p
                                            class="text-body text-neutral-900 dark:text-white"
                                        >
                                            {{
                                                formatDateTime(
                                                    selectedPost.published_at,
                                                )
                                            }}
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Action Buttons -->
                        <div
                            class="flex flex-col gap-2 sm:flex-row sm:justify-between"
                        >
                            <div class="flex gap-2">
                                <Button
                                    variant="outline"
                                    size="sm"
                                    @click="confirmDelete(selectedPost)"
                                    class="hover:border-red-500 hover:bg-red-50 hover:text-red-600 dark:hover:bg-red-900/20"
                                    :disabled="deleting"
                                >
                                    <TrashIcon class="mr-2 h-4 w-4" />
                                    Delete
                                </Button>
                            </div>

                            <Button
                                variant="outline"
                                size="sm"
                                @click="showDetailsModal = false"
                                class="hover-glow"
                            >
                                Close
                            </Button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
