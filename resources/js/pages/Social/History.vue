<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Head, router } from '@inertiajs/vue3';
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
import { computed, onMounted, ref } from 'vue';

const props = defineProps({
    posts: Object,
    filters: Object,
});

const loading = ref(false);
const deleting = ref(false);
const selectedPost = ref(null);
const showDeleteDialog = ref(false);

const filters = ref({
    status: props.filters?.status || '',
    platform: props.filters?.platform || '',
    limit: 20,
    offset: 0,
});

const posts = ref(props.posts?.posts || []);
const pagination = ref(props.posts?.pagination || {});

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
    scheduled: 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300',
    published: 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300',
    partially_published: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-300',
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

const deletePost = () => {
    if (!selectedPost.value) return;

    deleting.value = true;

    router.delete(`/social/posts/${selectedPost.value.id}`, {
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
    return post.platform_results || {};
};

const hasSuccessfulPost = (post) => {
    return Object.values(getPlatformResults(post)).some((result) => result.url);
};

onMounted(() => {
    if (!posts.value.length) {
        loadPosts();
    }
});
</script>

<template>
    <Head title="Post History" />

    <AppLayout>
        <div class="min-h-screen">
            <div class="p-6">
                <div class="mx-auto max-w-7xl">
                    <!-- Header -->
                    <div class="mb-12 animate-fade-in">
                        <h1 class="text-display-1 mb-4 text-neutral-900 dark:text-white">
                            Post <span class="text-gradient font-bold">History</span> 📝
                        </h1>
                        <p class="text-body-large max-w-3xl text-neutral-600 dark:text-neutral-400 leading-relaxed">
                            View and manage your published and scheduled posts across all platforms.
                        </p>
                        <div class="flex gap-4 mt-6">
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
                            <Button
                                as-child
                                class="btn-primary hover-glow"
                            >
                                <a href="/social/posts/create">Create New Post</a>
                            </Button>
                        </div>
                    </div>

                    <!-- Filters -->
                    <div class="card-elevated relative overflow-hidden mb-12 animate-slide-up">
                        <div class="absolute top-0 left-0 w-full h-1 bg-gradient-to-r from-purple-500 to-pink-500"></div>
                        <div class="mb-6">
                            <h2 class="text-headline-1 mb-3 text-neutral-900 dark:text-white flex items-center gap-3">
                                <FilterIcon class="h-6 w-6" />
                                Filters
                            </h2>
                            <p class="text-body-large text-neutral-600 dark:text-neutral-400">
                                Filter posts by status, platform, or adjust pagination
                            </p>
                        </div>
                        <div class="grid gap-6 md:grid-cols-4">
                            <div class="space-y-2">
                                <Label for="status-filter" class="text-body font-medium">Status</Label>
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
                                <Label for="platform-filter" class="text-body font-medium">Platform</Label>
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
                                <Label for="limit" class="text-body font-medium">Posts per page</Label>
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
                            class="card-elevated relative overflow-hidden py-12 text-center animate-slide-up"
                        >
                            <div class="mb-6 text-neutral-400 dark:text-neutral-500">
                                <CalendarIcon class="mx-auto h-16 w-16" />
                            </div>
                            <h3 class="mb-3 text-headline-2 text-neutral-900 dark:text-white">
                                No posts found
                            </h3>
                            <p class="mb-6 text-body-large text-neutral-600 dark:text-neutral-400">
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
                            class="card-elevated relative overflow-hidden group hover:scale-[1.01] transition-all duration-300 animate-slide-up"
                        >
                            <div class="absolute top-0 left-0 w-full h-1 opacity-0 group-hover:opacity-100 transition-opacity duration-300"
                                 :class="post.status === 'published' ? 'bg-gradient-to-r from-green-500 to-emerald-500' :
                                        post.status === 'scheduled' ? 'bg-gradient-to-r from-blue-500 to-indigo-500' :
                                        post.status === 'failed' ? 'bg-gradient-to-r from-red-500 to-pink-500' :
                                        'bg-gradient-to-r from-gray-500 to-gray-600'"></div>
                            <div class="p-6">
                                <div class="mb-4 flex items-start justify-between">
                                    <div class="flex-1">
                                        <div class="mb-3 flex items-center gap-3">
                                            <Badge
                                                :class="statusColors[post.status]"
                                                class="flex items-center gap-2 px-3 py-1"
                                            >
                                                <component
                                                    :is="statusIcons[post.status]"
                                                    class="h-4 w-4"
                                                />
                                                {{ post.status.replace('_', ' ') }}
                                            </Badge>

                                            <div class="flex gap-2">
                                                <span
                                                    v-for="platform in post.platforms"
                                                    :key="platform"
                                                    class="text-xl"
                                                >
                                                    {{ platformIcons[platform] }}
                                                </span>
                                            </div>
                                        </div>

                                        <p
                                            class="mb-3 whitespace-pre-wrap text-body-large text-neutral-900 dark:text-neutral-100 leading-relaxed"
                                        >
                                            {{ truncateText(post.content, 200) }}
                                        </p>

                                        <div
                                            v-if="post.link || post.image_url"
                                            class="mb-3 flex gap-6 text-body text-neutral-600 dark:text-neutral-400"
                                        >
                                            <span
                                                v-if="post.link"
                                                class="flex items-center gap-2"
                                            >
                                                <ExternalLinkIcon class="h-4 w-4" />
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

                                        <div class="text-body text-neutral-500 dark:text-neutral-400">
                                            Created {{ formatDate(post.created_at) }}
                                            <span
                                                v-if="post.scheduled_at"
                                                class="ml-2"
                                            >
                                                • Scheduled for {{ formatDateTime(post.scheduled_at) }}
                                            </span>
                                        </div>
                                    </div>

                                    <div class="ml-6 flex gap-3">
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            class="hover-glow"
                                            as-child
                                        >
                                            <a :href="`/social/posts/${post.id}`">
                                                <EyeIcon class="h-4 w-4" />
                                            </a>
                                        </Button>
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            class="hover:border-red-500 hover:text-red-600 hover:bg-red-50 dark:hover:bg-red-900/20"
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
                                    class="border-t border-neutral-200/60 dark:border-neutral-700/60 pt-4"
                                >
                                    <h4
                                        class="mb-3 text-headline-4 font-medium text-neutral-900 dark:text-white"
                                    >
                                        Published Links:
                                    </h4>
                                    <div class="flex flex-wrap gap-3">
                                        <a
                                            v-for="(
                                                result, platform
                                            ) in getPlatformResults(post)"
                                            :key="platform"
                                            v-if="result.url"
                                            :href="result.url"
                                            target="_blank"
                                            class="inline-flex items-center gap-2 text-body text-blue-600 dark:text-blue-400 hover:underline px-3 py-1 rounded-full border border-blue-200/60 bg-blue-50/80 dark:border-blue-800/60 dark:bg-blue-900/30"
                                        >
                                            <span class="text-lg">{{
                                                platformIcons[platform]
                                            }}</span>
                                            View on {{ platform.charAt(0).toUpperCase() + platform.slice(1) }}
                                            <ExternalLinkIcon class="h-3 w-3" />
                                        </a>
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
                        class="mt-8 text-center text-body text-neutral-600 dark:text-neutral-400"
                    >
                        Showing {{ Math.min(pagination.offset + 1, pagination.total) }}-{{
                            Math.min(pagination.offset + pagination.limit, pagination.total)
                        }} of {{ pagination.total }} posts
                    </div>
                </div>
            </div>
        </div>

        <!-- Delete Confirmation Dialog -->
        <div
            v-if="showDeleteDialog"
            class="bg-opacity-50 fixed inset-0 z-50 flex items-center justify-center bg-black backdrop-blur-sm"
        >
            <div class="card-elevated mx-4 w-full max-w-md overflow-hidden">
                <div class="absolute top-0 left-0 w-full h-1 bg-gradient-to-r from-red-500 to-pink-500"></div>
                <div class="p-6">
                    <h3 class="mb-4 text-headline-2 text-neutral-900 dark:text-white">
                        Delete Post
                    </h3>
                    <p class="mb-6 text-body-large text-neutral-600 dark:text-neutral-400">
                        Are you sure you want to delete this post? This action cannot be undone.
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
    </AppLayout>
</template>
