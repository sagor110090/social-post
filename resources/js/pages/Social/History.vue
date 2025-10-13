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
    draft: 'bg-gray-100 text-gray-800',
    scheduled: 'bg-blue-100 text-blue-800',
    published: 'bg-green-100 text-green-800',
    partially_published: 'bg-yellow-100 text-yellow-800',
    failed: 'bg-red-100 text-red-800',
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
        <div class="py-12">
            <div class="mx-auto max-w-6xl sm:px-6 lg:px-8">
                <!-- Header -->
                <div class="mb-8 flex items-center justify-between">
                    <div>
                        <h1 class="text-3xl font-bold text-gray-900">
                            Post History
                        </h1>
                        <p class="mt-2 text-gray-600">
                            View and manage your published and scheduled posts.
                        </p>
                    </div>
                    <div class="flex gap-2">
                        <Button
                            variant="outline"
                            @click="refreshPosts"
                            :disabled="loading"
                        >
                            <RefreshCwIcon
                                class="mr-2 h-4 w-4"
                                :class="{ 'animate-spin': loading }"
                            />
                            Refresh
                        </Button>
                        <Button as-child>
                            <a href="/social/posts/create">Create New Post</a>
                        </Button>
                    </div>
                </div>

                <!-- Filters -->
                <Card class="mb-6">
                    <CardHeader>
                        <CardTitle class="flex items-center gap-2">
                            <FilterIcon class="h-5 w-5" />
                            Filters
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div class="grid gap-4 md:grid-cols-4">
                            <div>
                                <Label for="status-filter">Status</Label>
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

                            <div>
                                <Label for="platform-filter">Platform</Label>
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

                            <div>
                                <Label for="limit">Posts per page</Label>
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
                    </CardContent>
                </Card>

                <!-- Posts List -->
                <div class="space-y-4">
                    <div
                        v-if="filteredPosts.length === 0"
                        class="py-12 text-center"
                    >
                        <div class="mb-4 text-gray-400">
                            <CalendarIcon class="mx-auto h-12 w-12" />
                        </div>
                        <h3 class="mb-2 text-lg font-medium text-gray-900">
                            No posts found
                        </h3>
                        <p class="mb-4 text-gray-500">
                            {{
                                filters.status || filters.platform
                                    ? 'Try adjusting your filters'
                                    : 'Get started by creating your first post'
                            }}
                        </p>
                        <Button
                            as-child
                            v-if="!filters.status && !filters.platform"
                        >
                            <a href="/social/posts/create">Create Post</a>
                        </Button>
                    </div>

                    <Card
                        v-for="post in filteredPosts"
                        :key="post.id"
                        class="transition-shadow hover:shadow-md"
                    >
                        <CardContent class="p-6">
                            <div class="mb-4 flex items-start justify-between">
                                <div class="flex-1">
                                    <div class="mb-2 flex items-center gap-2">
                                        <Badge
                                            :class="statusColors[post.status]"
                                            class="flex items-center gap-1"
                                        >
                                            <component
                                                :is="statusIcons[post.status]"
                                                class="h-3 w-3"
                                            />
                                            {{ post.status.replace('_', ' ') }}
                                        </Badge>

                                        <div class="flex gap-1">
                                            <span
                                                v-for="platform in post.platforms"
                                                :key="platform"
                                                class="text-lg"
                                            >
                                                {{ platformIcons[platform] }}
                                            </span>
                                        </div>
                                    </div>

                                    <p
                                        class="mb-2 whitespace-pre-wrap text-gray-900"
                                    >
                                        {{ truncateText(post.content, 200) }}
                                    </p>

                                    <div
                                        v-if="post.link || post.image_url"
                                        class="mb-2 flex gap-4 text-sm text-gray-500"
                                    >
                                        <span
                                            v-if="post.link"
                                            class="flex items-center gap-1"
                                        >
                                            <ExternalLinkIcon class="h-3 w-3" />
                                            Link included
                                        </span>
                                        <span
                                            v-if="post.image_url"
                                            class="flex items-center gap-1"
                                        >
                                            <EyeIcon class="h-3 w-3" />
                                            Image included
                                        </span>
                                    </div>

                                    <div class="text-sm text-gray-500">
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

                                <div class="ml-4 flex gap-2">
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        as-child
                                    >
                                        <a :href="`/social/posts/${post.id}`">
                                            <EyeIcon class="h-4 w-4" />
                                        </a>
                                    </Button>
                                    <Button
                                        variant="outline"
                                        size="sm"
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
                                class="border-t pt-4"
                            >
                                <h4
                                    class="mb-2 text-sm font-medium text-gray-900"
                                >
                                    Published Links:
                                </h4>
                                <div class="flex flex-wrap gap-2">
                                    <a
                                        v-for="(
                                            result, platform
                                        ) in getPlatformResults(post)"
                                        :key="platform"
                                        v-if="result.url"
                                        :href="result.url"
                                        target="_blank"
                                        class="inline-flex items-center gap-1 text-sm text-blue-600 hover:underline"
                                    >
                                        <span>{{
                                            platformIcons[platform]
                                        }}</span>
                                        View on
                                        {{
                                            platform.charAt(0).toUpperCase() +
                                            platform.slice(1)
                                        }}
                                        <ExternalLinkIcon class="h-3 w-3" />
                                    </a>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </div>

                <!-- Load More -->
                <div v-if="pagination.has_more" class="mt-6 text-center">
                    <Button
                        variant="outline"
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
                    class="mt-6 text-center text-sm text-gray-500"
                >
                    Showing
                    {{ Math.min(pagination.offset + 1, pagination.total) }}-{{
                        Math.min(
                            pagination.offset + pagination.limit,
                            pagination.total,
                        )
                    }}
                    of {{ pagination.total }} posts
                </div>
            </div>
        </div>

        <!-- Delete Confirmation Dialog -->
        <div
            v-if="showDeleteDialog"
            class="bg-opacity-50 fixed inset-0 z-50 flex items-center justify-center bg-black"
        >
            <div class="mx-4 w-full max-w-md rounded-lg bg-white p-6">
                <h3 class="mb-4 text-lg font-medium text-gray-900">
                    Delete Post
                </h3>
                <p class="mb-6 text-gray-600">
                    Are you sure you want to delete this post? This action
                    cannot be undone.
                </p>

                <div class="flex justify-end gap-3">
                    <Button
                        variant="outline"
                        @click="showDeleteDialog = false"
                        :disabled="deleting"
                    >
                        Cancel
                    </Button>
                    <Button
                        variant="destructive"
                        @click="deletePost"
                        :disabled="deleting"
                    >
                        <span v-if="deleting">Deleting...</span>
                        <span v-else>Delete Post</span>
                    </Button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
