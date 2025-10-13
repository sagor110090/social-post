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
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Head, router } from '@inertiajs/vue3';
import {
    AlertCircleIcon,
    CheckCircleIcon,
    ClockIcon,
    EyeIcon,
    SendIcon,
} from 'lucide-vue-next';
import { computed, onMounted, ref } from 'vue';

const props = defineProps({
    availablePlatforms: Array,
    characterLimits: Object,
    errors: Object,
    flash: Object,
});

const form = ref({
    content: '',
    platforms: [],
    link: '',
    image_url: '',
    media_urls: [],
    schedule_at: '',
});

const isScheduling = ref(false);
const isPublishing = ref(false);
const validationErrors = ref({});
const characterCounts = ref({});
const showPreview = ref(false);
const imagePreview = ref(null);

const selectedPlatforms = computed(() => {
    return (
        props.availablePlatforms?.filter((platform) =>
            form.value.platforms.includes(platform),
        ) || []
    );
});

const minCharacterLimit = computed(() => {
    if (selectedPlatforms.value.length === 0) return 280;
    return Math.min(
        ...selectedPlatforms.value.map(
            (platform) => props.characterLimits[platform] || 280,
        ),
    );
});

const maxCharacterLimit = computed(() => {
    if (selectedPlatforms.value.length === 0) return 280;
    return Math.min(
        ...selectedPlatforms.value.map(
            (platform) => props.characterLimits[platform] || 280,
        ),
    );
});

const currentCharacterCount = computed(() => {
    return form.value.content.length;
});

const isOverLimit = computed(() => {
    return currentCharacterCount.value > maxCharacterLimit.value;
});

const canPublish = computed(() => {
    return (
        form.value.content.trim().length > 0 &&
        form.value.platforms.length > 0 &&
        !isOverLimit.value
    );
});

const platformIcons = {
    facebook: '📘',
    instagram: '📷',
    linkedin: '💼',
    twitter: '🐦',
};

const validateContent = () => {
    if (
        form.value.content.trim().length === 0 ||
        form.value.platforms.length === 0
    ) {
        return;
    }

    router.post(
        '/social/posts/validate',
        {
            content: form.value.content,
            platforms: form.value.platforms,
        },
        {
            onSuccess: (response) => {
                validationErrors.value = {};
                characterCounts.value = response.props.validation_results || {};
            },
            onError: (errors) => {
                validationErrors.value = errors;
            },
        },
    );
};

const handleMediaUploadSuccess = (response) => {
    // Store uploaded media URLs
    if (!form.value.media_urls) {
        form.value.media_urls = [];
    }
    form.value.media_urls.push(response.url);

    // Set primary image URL if not set
    if (!form.value.image_url && response.mime_type.startsWith('image/')) {
        form.value.image_url = response.url;
    }
};

const handleMediaUploadError = (error) => {
    console.error('Media upload error:', error);
    // You could show a toast notification here
};

const handleMediaRemoved = (file) => {
    // Remove from media URLs
    if (form.value.media_urls) {
        const index = form.value.media_urls.indexOf(file.url);
        if (index > -1) {
            form.value.media_urls.splice(index, 1);
        }
    }

    // Update primary image URL if needed
    if (form.value.image_url === file.url) {
        form.value.image_url =
            form.value.media_urls?.find((url) => url.includes('image')) || '';
    }
};

const publishPost = () => {
    if (!canPublish.value) return;

    isPublishing.value = true;

    const postData = {
        content: form.value.content,
        platforms: form.value.platforms,
        link: form.value.link || undefined,
        image_url: form.value.image_url || undefined,
        media_urls: form.value.media_urls || undefined,
    };

    // Add scheduling if enabled
    if (isScheduling.value && form.value.schedule_at) {
        postData.schedule_at = form.value.schedule_at;
    }

    router.post('/social/posts/publish', postData, {
        onSuccess: () => {
            // Reset form
            form.value = {
                content: '',
                platforms: [],
                link: '',
                image_url: '',
                media_urls: [],
                schedule_at: '',
            };
            isScheduling.value = false;
            validationErrors.value = {};
            characterCounts.value = {};
        },
        onFinish: () => {
            isPublishing.value = false;
        },
    });
};

const togglePlatform = (platform) => {
    const index = form.value.platforms.indexOf(platform);
    if (index > -1) {
        form.value.platforms.splice(index, 1);
    } else {
        form.value.platforms.push(platform);
    }

    // Validate content when platforms change
    if (form.value.content.trim()) {
        validateContent();
    }
};

const getCharacterCountText = (platform) => {
    const count = characterCounts.value[platform];
    if (!count) return '';

    const limit = props.characterLimits[platform] || 280;
    const percentage = (count.character_count / limit) * 100;

    let colorClass = 'text-green-600';
    if (percentage > 90) colorClass = 'text-red-600';
    else if (percentage > 75) colorClass = 'text-yellow-600';

    return `${count.character_count}/${limit}`;
};

onMounted(() => {
    // Load available platforms if not provided
    if (!props.availablePlatforms?.length) {
        router.get(
            '/social/posts/platforms',
            {},
            {
                preserveState: false,
                onSuccess: (response) => {
                    // Platforms will be available in props
                },
            },
        );
    }
});
</script>

<template>
    <Head title="Create Post" />

    <AppLayout>
        <div class="py-12">
            <div class="mx-auto max-w-4xl sm:px-6 lg:px-8">
                <!-- Header -->
                <div class="mb-8">
                    <h1 class="text-3xl font-bold text-gray-900">
                        Create Post
                    </h1>
                    <p class="mt-2 text-gray-600">
                        Create and publish content across your connected social
                        media accounts.
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
                    <AlertCircleIcon class="h-4 w-4 text-red-600" />
                    <AlertDescription class="text-red-800">
                        {{ flash.error }}
                    </AlertDescription>
                </Alert>

                <div class="grid gap-6 lg:grid-cols-3">
                    <!-- Main Content -->
                    <div class="space-y-6 lg:col-span-2">
                        <!-- Content Input -->
                        <Card>
                            <CardHeader>
                                <CardTitle>Content</CardTitle>
                                <CardDescription>
                                    Write your post content. Character limits
                                    vary by platform.
                                </CardDescription>
                            </CardHeader>
                            <CardContent class="space-y-4">
                                <div>
                                    <Label for="content">Post Content</Label>
                                    <Textarea
                                        id="content"
                                        v-model="form.content"
                                        placeholder="What would you like to share?"
                                        class="min-h-[120px] resize-none"
                                        @input="validateContent"
                                    />
                                    <div
                                        class="mt-2 flex items-center justify-between text-sm"
                                    >
                                        <span
                                            :class="[
                                                isOverLimit
                                                    ? 'text-red-600'
                                                    : 'text-gray-500',
                                            ]"
                                        >
                                            {{ currentCharacterCount }} /
                                            {{ maxCharacterLimit }} characters
                                        </span>
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            @click="showPreview = !showPreview"
                                        >
                                            <EyeIcon class="mr-2 h-4 w-4" />
                                            {{ showPreview ? 'Hide' : 'Show' }}
                                            Preview
                                        </Button>
                                    </div>
                                </div>

                                <!-- Preview -->
                                <div
                                    v-if="showPreview && form.content"
                                    class="rounded-lg border bg-gray-50 p-4"
                                >
                                    <h4 class="mb-2 font-medium text-gray-900">
                                        Preview
                                    </h4>
                                    <p
                                        class="whitespace-pre-wrap text-gray-700"
                                    >
                                        {{ form.content }}
                                    </p>
                                    <div v-if="form.link" class="mt-2">
                                        <a
                                            :href="form.link"
                                            target="_blank"
                                            class="text-sm text-blue-600 hover:underline"
                                        >
                                            {{ form.link }}
                                        </a>
                                    </div>
                                </div>

                                <!-- Validation Errors -->
                                <div
                                    v-if="
                                        Object.keys(validationErrors).length > 0
                                    "
                                    class="space-y-2"
                                >
                                    <Alert class="border-red-200 bg-red-50">
                                        <AlertCircleIcon
                                            class="h-4 w-4 text-red-600"
                                        />
                                        <AlertDescription class="text-red-800">
                                            <div class="space-y-1">
                                                <div
                                                    v-for="(
                                                        errors, platform
                                                    ) in validationErrors"
                                                    :key="platform"
                                                >
                                                    <strong
                                                        >{{ platform }}:</strong
                                                    >
                                                    <ul
                                                        class="list-inside list-disc text-sm"
                                                    >
                                                        <li
                                                            v-for="error in errors"
                                                            :key="error"
                                                        >
                                                            {{ error }}
                                                        </li>
                                                    </ul>
                                                </div>
                                            </div>
                                        </AlertDescription>
                                    </Alert>
                                </div>
                            </CardContent>
                        </Card>

                        <!-- Media & Links -->
                        <Card>
                            <CardHeader>
                                <CardTitle>Media & Links</CardTitle>
                                <CardDescription>
                                    Add images, videos, or links to your post
                                    (optional).
                                </CardDescription>
                            </CardHeader>
                            <CardContent class="space-y-4">
                                <!-- Media Upload -->
                                <div>
                                    <Label>Media</Label>
                                    <MediaUpload
                                        :accept="'image/*,video/*'"
                                        :max-size="50 * 1024 * 1024"
                                        :max-files="5"
                                        :platform="
                                            selectedPlatforms[0] || 'general'
                                        "
                                        :multiple="true"
                                        @upload-success="
                                            handleMediaUploadSuccess
                                        "
                                        @upload-error="handleMediaUploadError"
                                        @file-removed="handleMediaRemoved"
                                    />
                                </div>

                                <!-- Link -->
                                <div>
                                    <Label for="link">Link</Label>
                                    <Input
                                        id="link"
                                        v-model="form.link"
                                        type="url"
                                        placeholder="https://example.com"
                                    />
                                </div>
                            </CardContent>
                        </Card>

                        <!-- Scheduling -->
                        <Card>
                            <CardHeader>
                                <CardTitle class="flex items-center gap-2">
                                    <Checkbox
                                        :checked="isScheduling"
                                        @update:checked="isScheduling = $event"
                                    />
                                    Schedule Post
                                </CardTitle>
                                <CardDescription>
                                    Schedule your post to be published at a
                                    later time.
                                </CardDescription>
                            </CardHeader>
                            <CardContent v-if="isScheduling">
                                <div>
                                    <Label for="schedule_at"
                                        >Publish Date & Time</Label
                                    >
                                    <Input
                                        id="schedule_at"
                                        v-model="form.schedule_at"
                                        type="datetime-local"
                                        :min="
                                            new Date()
                                                .toISOString()
                                                .slice(0, 16)
                                        "
                                    />
                                </div>
                            </CardContent>
                        </Card>
                    </div>

                    <!-- Sidebar -->
                    <div class="space-y-6">
                        <!-- Platform Selection -->
                        <Card>
                            <CardHeader>
                                <CardTitle>Platforms</CardTitle>
                                <CardDescription>
                                    Select where to publish your post.
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                <div
                                    v-if="availablePlatforms?.length > 0"
                                    class="space-y-3"
                                >
                                    <div
                                        v-for="platform in availablePlatforms"
                                        :key="platform"
                                        class="flex items-center justify-between"
                                    >
                                        <div class="flex items-center gap-3">
                                            <Checkbox
                                                :id="platform"
                                                :checked="
                                                    form.platforms.includes(
                                                        platform,
                                                    )
                                                "
                                                @update:checked="
                                                    togglePlatform(platform)
                                                "
                                            />
                                            <Label
                                                :for="platform"
                                                class="flex cursor-pointer items-center gap-2"
                                            >
                                                <span class="text-lg">{{
                                                    platformIcons[platform]
                                                }}</span>
                                                <span class="capitalize">{{
                                                    platform
                                                }}</span>
                                            </Label>
                                        </div>
                                        <div class="text-right">
                                            <div class="text-sm text-gray-500">
                                                {{
                                                    getCharacterCountText(
                                                        platform,
                                                    )
                                                }}
                                            </div>
                                            <Badge
                                                v-if="
                                                    characterCounts[platform]
                                                        ?.valid === false
                                                "
                                                variant="destructive"
                                                class="text-xs"
                                            >
                                                Invalid
                                            </Badge>
                                        </div>
                                    </div>
                                </div>
                                <div v-else>
                                    <Alert>
                                        <AlertCircleIcon class="h-4 w-4" />
                                        <AlertDescription>
                                            No social media accounts connected.
                                            <Link
                                                href="/social/accounts"
                                                class="text-blue-600 hover:underline"
                                            >
                                                Connect accounts
                                            </Link>
                                        </AlertDescription>
                                    </Alert>
                                </div>
                            </CardContent>
                        </Card>

                        <!-- Character Limits Reference -->
                        <Card>
                            <CardHeader>
                                <CardTitle>Character Limits</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <div class="space-y-2 text-sm">
                                    <div
                                        v-for="(
                                            limit, platform
                                        ) in characterLimits"
                                        :key="platform"
                                        class="flex justify-between"
                                    >
                                        <span class="capitalize">{{
                                            platform
                                        }}</span>
                                        <span class="text-gray-500"
                                            >{{ limit }} chars</span
                                        >
                                    </div>
                                </div>
                            </CardContent>
                        </Card>

                        <!-- Publish Button -->
                        <Card>
                            <CardContent class="pt-6">
                                <Button
                                    class="w-full"
                                    size="lg"
                                    :disabled="!canPublish || isPublishing"
                                    @click="publishPost"
                                >
                                    <SendIcon
                                        v-if="!isPublishing"
                                        class="mr-2 h-4 w-4"
                                    />
                                    <ClockIcon
                                        v-else
                                        class="mr-2 h-4 w-4 animate-spin"
                                    />
                                    <span v-if="isPublishing">
                                        {{
                                            isScheduling
                                                ? 'Scheduling...'
                                                : 'Publishing...'
                                        }}
                                    </span>
                                    <span v-else>
                                        {{
                                            isScheduling
                                                ? 'Schedule Post'
                                                : 'Publish Now'
                                        }}
                                    </span>
                                </Button>

                                <p
                                    v-if="
                                        !canPublish && form.content.length > 0
                                    "
                                    class="mt-2 text-sm text-gray-500"
                                >
                                    {{
                                        isOverLimit
                                            ? 'Content exceeds character limit'
                                            : 'Select at least one platform'
                                    }}
                                </p>
                            </CardContent>
                        </Card>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
