<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';

import MediaUpload from '@/components/Media/MediaUpload.vue';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { useToast } from '@/composables/useToast';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { AlertCircle, CheckCircle, PlusCircle } from 'lucide-vue-next';
import { computed, onMounted, ref } from 'vue';

// Type definitions
interface Platform {
    name: string;
    provider: string;
    icon: any;
    color: string;
    description: string;
    features: string[];
}

interface CharacterLimits {
    [key: string]: number;
}

interface CharacterCount {
    character_count: number;
    valid: boolean;
}

interface ValidationErrors {
    [key: string]: string[];
}

interface Flash {
    success?: string;
    error?: string;
}

interface MediaFile {
    url: string;
    mime_type: string;
}

const props = defineProps<{
    availablePlatforms?: string[];
    characterLimits?: CharacterLimits;
    errors?: ValidationErrors;
    flash?: Flash;
}>();

interface FormData {
    content: string;
    platforms: string[];
    link: string;
    image_url: string;
    media_urls: string[];
    schedule_at: string;
}

const form = ref<FormData>({
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
const toast = useToast();

const selectedPlatforms = computed(() => {
    return (
        props.availablePlatforms?.filter((platform) =>
            form.value.platforms.includes(platform),
        ) || []
    );
});

const maxCharacterLimit = computed(() => {
    if (selectedPlatforms.value.length === 0) return 280;
    return Math.min(
        ...selectedPlatforms.value.map(
            (platform) => props.characterLimits?.[platform] || 280,
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

const platformIcons: Record<string, string> = {
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

    // Use fetch instead of Inertia for validation
    fetch('/posts/validate', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            'X-CSRF-TOKEN':
                document
                    .querySelector('meta[name="csrf-token"]')
                    ?.getAttribute('content') || '',
        },
        body: JSON.stringify({
            content: form.value.content,
            platforms: form.value.platforms,
        }),
    })
        .then((response) => response.json())
        .then((data) => {
            validationErrors.value = {};
            characterCounts.value = data;
        })
        .catch((error) => {
            console.error('Validation error:', error);
            toast.error('Failed to validate content');
        });
};

const handleMediaUploadSuccess = (response: MediaFile) => {
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

const handleMediaUploadError = (error: any) => {
    console.error('Media upload error:', error);
    // You could show a toast notification here
};

const handleMediaRemoved = (file: MediaFile) => {
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

    const postData: any = {
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

    router.post('/posts/publish', postData, {
        onSuccess: (page) => {
            // Show success message
            if (page.props.flash?.success) {
                toast.success(page.props.flash.success);
            }

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
        onError: (errors) => {
            // Show error message
            if (errors.error) {
                toast.error(errors.error);
            }
        },
        onFinish: () => {
            isPublishing.value = false;
        },
    });
};

const togglePlatform = (platform: string) => {
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

const getCharacterCountText = (platform: string) => {
    const count = characterCounts.value[platform] as CharacterCount | undefined;
    if (!count) return '0/' + (props.characterLimits?.[platform] || 280);

    const limit = props.characterLimits?.[platform] || 280;
    return `${count.character_count}/${limit}`;
};

const getCharacterCountColor = (platform: string) => {
    const count = characterCounts.value[platform] as CharacterCount | undefined;
    if (!count) return 'text-neutral-500 dark:text-neutral-400';

    const limit = props.characterLimits?.[platform] || 280;
    const percentage = (count.character_count / limit) * 100;

    if (percentage > 90) return 'text-red-600 dark:text-red-400';
    else if (percentage > 75) return 'text-yellow-600 dark:text-yellow-400';
    else return 'text-green-600 dark:text-green-400';
};

const getPlatformDisplayName = (platform: string) => {
    const names: Record<string, string> = {
        facebook: 'Facebook Page',
        instagram: 'Instagram Profile',
        linkedin: 'LinkedIn Profile',
        twitter: 'X (Twitter) Account',
    };
    return names[platform] || platform;
};

const setScheduleTime = (time: string) => {
    const date = form.value.schedule_at
        ? new Date(form.value.schedule_at)
        : new Date();
    const [hours, minutes] = time.split(':');
    date.setHours(parseInt(hours), parseInt(minutes), 0, 0);
    form.value.schedule_at = date.toISOString().slice(0, 16);
};

onMounted(() => {
    // Platforms are now provided by the controller in the initial page load
    // No need to fetch them separately
});
</script>

<template>
    <Head title="Create Post" />

    <AppLayout>
        <div class="min-h-screen">
            <div class="p-6">
                <div class="mx-auto max-w-7xl">
                    <!-- Header -->
                    <div class="animate-fade-in mb-12">
                        <h1
                            class="text-display-1 mb-4 text-neutral-900 dark:text-white"
                        >
                            Create
                            <span class="text-gradient font-bold"
                                >New Post</span
                            >
                            ✍️
                        </h1>
                        <p
                            class="text-body-large max-w-3xl leading-relaxed text-neutral-600 dark:text-neutral-400"
                        >
                            Craft compelling posts and publish them across all
                            your connected social media platforms with ease.
                        </p>
                    </div>

                    <!-- Flash Messages -->
                    <div
                        v-if="flash?.success"
                        class="animate-slide-up mb-8 rounded-2xl border border-emerald-200/60 bg-emerald-50/80 p-6 backdrop-blur-sm dark:border-emerald-800/60 dark:bg-emerald-900/30"
                    >
                        <div class="flex items-center gap-3">
                            <div
                                class="flex h-10 w-10 items-center justify-center rounded-xl bg-emerald-500"
                            >
                                <CheckCircle class="h-5 w-5 text-white" />
                            </div>
                            <p
                                class="text-body-large font-medium text-emerald-800 dark:text-emerald-200"
                            >
                                {{ flash.success }}
                            </p>
                        </div>
                    </div>

                    <div
                        v-if="flash?.error"
                        class="animate-slide-up mb-8 rounded-2xl border border-red-200/60 bg-red-50/80 p-6 backdrop-blur-sm dark:border-red-800/60 dark:bg-red-900/30"
                    >
                        <div class="flex items-center gap-3">
                            <div
                                class="flex h-10 w-10 items-center justify-center rounded-xl bg-red-500"
                            >
                                <AlertCircle class="h-5 w-5 text-white" />
                            </div>
                            <p
                                class="text-body-large font-medium text-red-800 dark:text-red-200"
                            >
                                {{ flash.error }}
                            </p>
                        </div>
                    </div>

                    <div class="grid gap-8 lg:grid-cols-3">
                        <!-- Main Content -->
                        <div class="space-y-8 lg:col-span-2">
                            <!-- Content Input -->
                            <div
                                class="card-elevated animate-slide-up group relative overflow-hidden transition-all duration-300 hover:scale-[1.01]"
                            >
                                <div
                                    class="absolute top-0 left-0 h-1 w-full bg-gradient-to-r from-purple-500 to-pink-500 opacity-0 transition-opacity duration-300 group-hover:opacity-100"
                                ></div>
                                <div class="mb-6">
                                    <h2
                                        class="text-headline-1 mb-3 text-neutral-900 dark:text-white"
                                    >
                                        Content Creation
                                    </h2>
                                    <p
                                        class="text-body-large text-neutral-600 dark:text-neutral-400"
                                    >
                                        Write your post content. Character
                                        limits vary by platform.
                                    </p>
                                </div>

                                <div class="space-y-6">
                                    <div>
                                        <Label
                                            for="content"
                                            class="text-body mb-3 block font-medium text-neutral-700 dark:text-neutral-300"
                                        >
                                            What would you like to share? 📝
                                        </Label>
                                        <Textarea
                                            id="content"
                                            v-model="form.content"
                                            placeholder="Share your thoughts, ideas, or updates with your audience..."
                                            class="input-field text-body min-h-[150px] resize-none"
                                            @input="validateContent"
                                        />
                                        <div
                                            class="mt-3 flex items-center justify-between"
                                        >
                                            <div
                                                class="flex items-center gap-4"
                                            >
                                                <span
                                                    :class="[
                                                        'text-body-small font-medium',
                                                        isOverLimit
                                                            ? 'text-red-600 dark:text-red-400'
                                                            : 'text-neutral-600 dark:text-neutral-400',
                                                    ]"
                                                >
                                                    {{ currentCharacterCount }}
                                                    /
                                                    {{ maxCharacterLimit }}
                                                    characters
                                                </span>
                                                <div
                                                    v-if="
                                                        !isOverLimit &&
                                                        currentCharacterCount >
                                                            0
                                                    "
                                                    class="flex items-center gap-1 text-green-600 dark:text-green-400"
                                                >
                                                    <CheckCircleIcon
                                                        class="h-4 w-4"
                                                    />
                                                    <span
                                                        class="text-body-small"
                                                        >Good length</span
                                                    >
                                                </div>
                                            </div>
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                @click="
                                                    showPreview = !showPreview
                                                "
                                                class="text-brand-primary hover:text-brand-primary-dark"
                                            >
                                                <EyeIcon class="mr-2 h-4 w-4" />
                                                {{
                                                    showPreview
                                                        ? 'Hide'
                                                        : 'Show'
                                                }}
                                                Preview
                                            </Button>
                                        </div>
                                    </div>

                                    <!-- Enhanced Preview -->
                                    <div
                                        v-if="showPreview && form.content"
                                        class="rounded-xl border border-neutral-200 bg-gradient-to-br from-neutral-50 to-neutral-100 p-6 dark:border-neutral-700 dark:from-neutral-800 dark:to-neutral-900"
                                    >
                                        <div
                                            class="mb-4 flex items-center gap-2"
                                        >
                                            <div
                                                class="bg-brand-primary flex h-8 w-8 items-center justify-center rounded-lg"
                                            >
                                                <EyeIcon
                                                    class="h-4 w-4 text-white"
                                                />
                                            </div>
                                            <h3
                                                class="text-headline-4 text-neutral-900 dark:text-white"
                                            >
                                                Live Preview
                                            </h3>
                                        </div>
                                        <div class="space-y-3">
                                            <p
                                                class="text-body whitespace-pre-wrap text-neutral-700 dark:text-neutral-300"
                                            >
                                                {{ form.content }}
                                            </p>
                                            <div
                                                v-if="form.link"
                                                class="mt-3 rounded-lg border border-neutral-200 bg-white p-3 dark:border-neutral-700 dark:bg-neutral-800"
                                            >
                                                <div
                                                    class="flex items-center gap-2"
                                                >
                                                    <svg
                                                        class="h-4 w-4 text-neutral-400"
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
                                                    <a
                                                        :href="form.link"
                                                        target="_blank"
                                                        class="text-body-small text-brand-primary hover:text-brand-primary-dark truncate"
                                                    >
                                                        {{ form.link }}
                                                    </a>
                                                </div>
                                            </div>
                                            <div
                                                class="flex items-center gap-2 border-t border-neutral-200 pt-2 dark:border-neutral-700"
                                            >
                                                <div class="flex -space-x-2">
                                                    <div
                                                        v-for="platform in selectedPlatforms"
                                                        :key="platform"
                                                        class="flex h-6 w-6 items-center justify-center rounded-full border border-neutral-200 bg-white text-xs dark:border-neutral-700 dark:bg-neutral-800"
                                                    >
                                                        {{
                                                            platformIcons[
                                                                platform
                                                            ]
                                                        }}
                                                    </div>
                                                </div>
                                                <span
                                                    class="text-body-small text-neutral-500 dark:text-neutral-500"
                                                >
                                                    Will be posted to
                                                    {{
                                                        selectedPlatforms.length
                                                    }}
                                                    platform{{
                                                        selectedPlatforms.length >
                                                        1
                                                            ? 's'
                                                            : ''
                                                    }}
                                                </span>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Enhanced Validation Errors -->
                                    <div
                                        v-if="
                                            Object.keys(validationErrors)
                                                .length > 0
                                        "
                                        class="rounded-xl border border-red-200 bg-red-50 p-4 dark:border-red-800 dark:bg-red-900/20"
                                    >
                                        <div class="flex items-start gap-3">
                                            <div
                                                class="mt-0.5 flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-lg bg-red-500"
                                            >
                                                <AlertCircle
                                                    class="h-4 w-4 text-white"
                                                />
                                            </div>
                                            <div class="flex-1">
                                                <h4
                                                    class="text-body mb-2 font-medium text-red-800 dark:text-red-200"
                                                >
                                                    Content Issues
                                                </h4>
                                                <div class="space-y-3">
                                                    <div
                                                        v-for="(
                                                            errors, platform
                                                        ) in validationErrors"
                                                        :key="platform"
                                                        class="space-y-2"
                                                    >
                                                        <div
                                                            class="flex items-center gap-2"
                                                        >
                                                            <span
                                                                class="text-lg"
                                                                >{{
                                                                    platformIcons[
                                                                        platform
                                                                    ]
                                                                }}</span
                                                            >
                                                            <span
                                                                class="text-body font-medium text-red-700 capitalize dark:text-red-300"
                                                                >{{
                                                                    platform
                                                                }}</span
                                                            >
                                                        </div>
                                                        <ul
                                                            class="ml-6 list-inside list-disc space-y-1"
                                                        >
                                                            <li
                                                                v-for="error in errors"
                                                                :key="error"
                                                                class="text-body-small text-red-600 dark:text-red-400"
                                                            >
                                                                {{ error }}
                                                            </li>
                                                        </ul>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Media & Links -->
                            <div
                                class="card-elevated animate-slide-up group relative overflow-hidden transition-all duration-300 hover:scale-[1.01]"
                            >
                                <div
                                    class="absolute top-0 left-0 h-1 w-full bg-gradient-to-r from-blue-500 to-cyan-500 opacity-0 transition-opacity duration-300 group-hover:opacity-100"
                                ></div>
                                <div class="mb-6">
                                    <h2
                                        class="text-headline-1 mb-3 text-neutral-900 dark:text-white"
                                    >
                                        Media & Links
                                    </h2>
                                    <p
                                        class="text-body-large text-neutral-600 dark:text-neutral-400"
                                    >
                                        Add images, videos, or links to make
                                        your post more engaging (optional).
                                    </p>
                                </div>

                                <div class="space-y-6">
                                    <!-- Enhanced Media Upload -->
                                    <div>
                                        <Label
                                            class="text-body mb-3 flex items-center gap-2 font-medium text-neutral-700 dark:text-neutral-300"
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
                                                    d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"
                                                />
                                            </svg>
                                            Media Files
                                        </Label>
                                        <div
                                            class="hover:border-brand-primary rounded-xl border-2 border-dashed border-neutral-300 p-8 text-center transition-colors dark:border-neutral-600"
                                        >
                                            <div
                                                class="flex flex-col items-center space-y-3"
                                            >
                                                <div
                                                    class="flex h-12 w-12 items-center justify-center rounded-xl bg-neutral-100 dark:bg-neutral-800"
                                                >
                                                    <svg
                                                        class="h-6 w-6 text-neutral-400"
                                                        fill="none"
                                                        stroke="currentColor"
                                                        viewBox="0 0 24 24"
                                                    >
                                                        <path
                                                            stroke-linecap="round"
                                                            stroke-linejoin="round"
                                                            stroke-width="2"
                                                            d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"
                                                        />
                                                    </svg>
                                                </div>
                                                <div>
                                                    <p
                                                        class="text-body font-medium text-neutral-900 dark:text-white"
                                                    >
                                                        Drop media files here
                                                    </p>
                                                    <p
                                                        class="text-body-small mt-1 text-neutral-600 dark:text-neutral-400"
                                                    >
                                                        or click to browse
                                                    </p>
                                                </div>
                                                <p
                                                    class="text-body-small text-neutral-500 dark:text-neutral-500"
                                                >
                                                    Supports: Images (JPG, PNG,
                                                    GIF) and Videos (MP4, MOV) •
                                                    Max 50MB • Up to 5 files
                                                </p>
                                            </div>
                                            <!-- MediaUpload component would go here -->
                                            <MediaUpload
                                                :accept="'image/*,video/*'"
                                                :max-size="50 * 1024 * 1024"
                                                :max-files="5"
                                                :platform="
                                                    selectedPlatforms[0] ||
                                                    'general'
                                                "
                                                :multiple="true"
                                                @upload-success="
                                                    handleMediaUploadSuccess
                                                "
                                                @upload-error="
                                                    handleMediaUploadError
                                                "
                                                @file-removed="
                                                    handleMediaRemoved
                                                "
                                            />
                                        </div>
                                    </div>

                                    <!-- Enhanced Link -->
                                    <div>
                                        <Label
                                            for="link"
                                            class="text-body mb-3 flex items-center gap-2 font-medium text-neutral-700 dark:text-neutral-300"
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
                                                    d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"
                                                />
                                            </svg>
                                            Link Preview
                                        </Label>
                                        <Input
                                            id="link"
                                            v-model="form.link"
                                            type="url"
                                            placeholder="https://example.com"
                                            class="input-field"
                                        />
                                        <p
                                            class="text-body-small mt-2 text-neutral-500 dark:text-neutral-500"
                                        >
                                            Add a link to generate an automatic
                                            preview on supported platforms
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <!-- Scheduling -->
                            <div
                                class="card-elevated animate-slide-up group relative overflow-hidden transition-all duration-300 hover:scale-[1.01]"
                            >
                                <div
                                    class="absolute top-0 left-0 h-1 w-full bg-gradient-to-r from-green-500 to-emerald-500 opacity-0 transition-opacity duration-300 group-hover:opacity-100"
                                ></div>
                                <div
                                    class="mb-6 flex items-start justify-between"
                                >
                                    <div>
                                        <h2
                                            class="text-headline-1 mb-3 text-neutral-900 dark:text-white"
                                        >
                                            Schedule Post
                                        </h2>
                                        <p
                                            class="text-body-large text-neutral-600 dark:text-neutral-400"
                                        >
                                            Schedule your post to be published
                                            at the perfect time for maximum
                                            engagement.
                                        </p>
                                    </div>
                                    <div class="flex items-center gap-3">
                                        <Checkbox
                                            :checked="isScheduling"
                                            @update:checked="
                                                isScheduling = $event
                                            "
                                            class="text-brand-primary focus:ring-brand-primary rounded border-neutral-300"
                                        />
                                        <Label
                                            class="text-body cursor-pointer font-medium text-neutral-700 dark:text-neutral-300"
                                        >
                                            Enable Scheduling
                                        </Label>
                                    </div>
                                </div>

                                <div
                                    v-if="isScheduling"
                                    class="space-y-6 rounded-xl border border-blue-200 bg-gradient-to-br from-blue-50 to-indigo-50 p-6 dark:border-blue-800 dark:from-blue-900/20 dark:to-indigo-900/20"
                                >
                                    <div class="mb-4 flex items-center gap-3">
                                        <div
                                            class="flex h-10 w-10 items-center justify-center rounded-xl bg-blue-500"
                                        >
                                            <ClockIcon
                                                class="h-5 w-5 text-white"
                                            />
                                        </div>
                                        <div>
                                            <h3
                                                class="text-headline-4 text-neutral-900 dark:text-white"
                                            >
                                                Publish Date & Time
                                            </h3>
                                            <p
                                                class="text-body-small text-neutral-600 dark:text-neutral-400"
                                            >
                                                Choose when your post should go
                                                live
                                            </p>
                                        </div>
                                    </div>

                                    <div
                                        class="grid grid-cols-1 gap-4 md:grid-cols-2"
                                    >
                                        <div>
                                            <Label
                                                for="schedule_date"
                                                class="text-body mb-2 block font-medium text-neutral-700 dark:text-neutral-300"
                                                >Date</Label
                                            >
                                            <Input
                                                id="schedule_date"
                                                v-model="form.schedule_at"
                                                type="date"
                                                :min="
                                                    new Date()
                                                        .toISOString()
                                                        .split('T')[0]
                                                "
                                                class="input-field"
                                            />
                                        </div>
                                        <div>
                                            <Label
                                                for="schedule_time"
                                                class="text-body mb-2 block font-medium text-neutral-700 dark:text-neutral-300"
                                                >Time</Label
                                            >
                                            <Input
                                                id="schedule_time"
                                                v-model="form.schedule_at"
                                                type="time"
                                                class="input-field"
                                            />
                                        </div>
                                    </div>

                                    <!-- Quick Time Suggestions -->
                                    <div
                                        class="border-t border-blue-200 pt-4 dark:border-blue-800"
                                    >
                                        <p
                                            class="text-body-small mb-3 text-neutral-600 dark:text-neutral-400"
                                        >
                                            Quick suggestions:
                                        </p>
                                        <div class="flex flex-wrap gap-2">
                                            <Button
                                                variant="outline"
                                                size="sm"
                                                @click="
                                                    setScheduleTime('09:00')
                                                "
                                                class="text-xs"
                                            >
                                                9:00 AM
                                            </Button>
                                            <Button
                                                variant="outline"
                                                size="sm"
                                                @click="
                                                    setScheduleTime('12:00')
                                                "
                                                class="text-xs"
                                            >
                                                12:00 PM
                                            </Button>
                                            <Button
                                                variant="outline"
                                                size="sm"
                                                @click="
                                                    setScheduleTime('18:00')
                                                "
                                                class="text-xs"
                                            >
                                                6:00 PM
                                            </Button>
                                            <Button
                                                variant="outline"
                                                size="sm"
                                                @click="
                                                    setScheduleTime('21:00')
                                                "
                                                class="text-xs"
                                            >
                                                9:00 PM
                                            </Button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Sidebar -->
                        <div class="space-y-8">
                            <!-- Platform Selection -->
                            <div
                                class="card-elevated animate-slide-up group relative overflow-hidden transition-all duration-300 hover:scale-[1.01]"
                            >
                                <div
                                    class="absolute top-0 left-0 h-1 w-full bg-gradient-to-r from-amber-500 to-orange-500 opacity-0 transition-opacity duration-300 group-hover:opacity-100"
                                ></div>
                                <div class="mb-6">
                                    <h2
                                        class="text-headline-1 mb-3 text-neutral-900 dark:text-white"
                                    >
                                        Select Platforms
                                    </h2>
                                    <p
                                        class="text-body-large text-neutral-600 dark:text-neutral-400"
                                    >
                                        Choose where to publish your post.
                                    </p>
                                </div>

                                <div
                                    v-if="availablePlatforms?.length > 0"
                                    class="space-y-4"
                                >
                                    <div
                                        v-for="platform in availablePlatforms"
                                        :key="platform"
                                        class="group relative"
                                    >
                                        <div
                                            class="cursor-pointer rounded-xl border-2 p-4 transition-all duration-200"
                                            :class="[
                                                form.platforms.includes(
                                                    platform,
                                                )
                                                    ? 'border-brand-primary bg-brand-primary/5 dark:bg-brand-primary/10'
                                                    : 'border-neutral-200 hover:border-neutral-300 dark:border-neutral-700 dark:hover:border-neutral-600',
                                            ]"
                                            @click="togglePlatform(platform)"
                                        >
                                            <div
                                                class="flex items-center justify-between"
                                            >
                                                <div
                                                    class="flex items-center gap-3"
                                                >
                                                    <div
                                                        class="flex h-10 w-10 items-center justify-center rounded-xl transition-all duration-200"
                                                        :class="[
                                                            form.platforms.includes(
                                                                platform,
                                                            )
                                                                ? 'bg-brand-primary text-white'
                                                                : 'bg-neutral-100 text-neutral-600 group-hover:bg-neutral-200 dark:bg-neutral-800 dark:text-neutral-400 dark:group-hover:bg-neutral-700',
                                                        ]"
                                                    >
                                                        <span class="text-xl">{{
                                                            platformIcons[
                                                                platform
                                                            ]
                                                        }}</span>
                                                    </div>
                                                    <div>
                                                        <Label
                                                            :for="platform"
                                                            class="text-body cursor-pointer font-medium text-neutral-900 capitalize dark:text-white"
                                                        >
                                                            {{ platform }}
                                                        </Label>
                                                        <p
                                                            class="text-body-small text-neutral-600 dark:text-neutral-400"
                                                        >
                                                            {{
                                                                getPlatformDisplayName(
                                                                    platform,
                                                                )
                                                            }}
                                                        </p>
                                                    </div>
                                                </div>
                                                <div class="text-right">
                                                    <div
                                                        class="text-body-small font-medium"
                                                        :class="
                                                            getCharacterCountColor(
                                                                platform,
                                                            )
                                                        "
                                                    >
                                                        {{
                                                            getCharacterCountText(
                                                                platform,
                                                            )
                                                        }}
                                                    </div>
                                                    <Badge
                                                        v-if="
                                                            characterCounts[
                                                                platform
                                                            ]?.valid === false
                                                        "
                                                        variant="destructive"
                                                        class="mt-1 text-xs"
                                                    >
                                                        Invalid
                                                    </Badge>
                                                    <div
                                                        v-else-if="
                                                            form.platforms.includes(
                                                                platform,
                                                            )
                                                        "
                                                        class="bg-brand-primary mt-1 flex h-5 w-5 items-center justify-center rounded-full text-xs text-white"
                                                    >
                                                        ✓
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div v-else class="py-8 text-center">
                                    <div
                                        class="mx-auto mb-4 flex h-12 w-12 items-center justify-center rounded-xl bg-neutral-100 dark:bg-neutral-800"
                                    >
                                        <AlertCircleIcon
                                            class="h-6 w-6 text-neutral-400"
                                        />
                                    </div>
                                    <h3
                                        class="text-headline-4 mb-2 text-neutral-900 dark:text-white"
                                    >
                                        No Accounts Connected
                                    </h3>
                                    <p
                                        class="text-body mb-4 text-neutral-600 dark:text-neutral-400"
                                    >
                                        Connect your social media accounts to
                                        start publishing
                                    </p>
                                    <Link href="/social/accounts">
                                        <Button class="btn-primary">
                                            <PlusCircle class="mr-2 h-4 w-4" />
                                            Connect Accounts
                                        </Button>
                                    </Link>
                                </div>
                            </div>

                            <!-- Character Limits Reference -->
                            <div
                                class="card-elevated animate-slide-up group relative overflow-hidden transition-all duration-300 hover:scale-[1.01]"
                            >
                                <div
                                    class="absolute top-0 left-0 h-1 w-full bg-gradient-to-r from-indigo-500 to-purple-500 opacity-0 transition-opacity duration-300 group-hover:opacity-100"
                                ></div>
                                <div class="mb-4">
                                    <h3
                                        class="text-headline-1 mb-3 text-neutral-900 dark:text-white"
                                    >
                                        Platform Limits
                                    </h3>
                                    <p
                                        class="text-body-large text-neutral-600 dark:text-neutral-400"
                                    >
                                        Character limits for each platform
                                    </p>
                                </div>
                                <div class="space-y-3">
                                    <div
                                        v-for="(
                                            limit, platform
                                        ) in characterLimits"
                                        :key="platform"
                                        class="flex items-center justify-between rounded-lg bg-neutral-50 p-3 dark:bg-neutral-800"
                                    >
                                        <div class="flex items-center gap-2">
                                            <span class="text-lg">{{
                                                platformIcons[platform]
                                            }}</span>
                                            <span
                                                class="text-body-small font-medium text-neutral-700 capitalize dark:text-neutral-300"
                                            >
                                                {{ platform }}
                                            </span>
                                        </div>
                                        <span
                                            class="text-body-small text-neutral-600 dark:text-neutral-400"
                                        >
                                            {{ limit.toLocaleString() }} chars
                                        </span>
                                    </div>
                                </div>
                            </div>

                            <!-- Publish Button -->
                            <div
                                class="card-elevated animate-slide-up group relative overflow-hidden transition-all duration-300 hover:scale-[1.01]"
                            >
                                <div
                                    class="absolute top-0 left-0 h-1 w-full bg-gradient-to-r from-emerald-500 to-teal-500 opacity-0 transition-opacity duration-300 group-hover:opacity-100"
                                ></div>
                                <Button
                                    class="btn-primary w-full py-4 text-base font-semibold"
                                    size="lg"
                                    :disabled="!canPublish || isPublishing"
                                    @click="publishPost"
                                >
                                    <SendIcon
                                        v-if="!isPublishing"
                                        class="mr-3 h-5 w-5"
                                    />
                                    <div v-else class="mr-3">
                                        <div class="spinner h-5 w-5"></div>
                                    </div>
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

                                <div
                                    v-if="
                                        !canPublish && form.content.length > 0
                                    "
                                    class="mt-4 rounded-lg border border-red-200 bg-red-50 p-3 dark:border-red-800 dark:bg-red-900/20"
                                >
                                    <div class="flex items-center gap-2">
                                        <AlertCircleIcon
                                            class="h-4 w-4 text-red-600 dark:text-red-400"
                                        />
                                        <p
                                            class="text-body-small text-red-700 dark:text-red-300"
                                        >
                                            {{
                                                isOverLimit
                                                    ? 'Content exceeds character limit for selected platform(s)'
                                                    : 'Please select at least one platform to publish'
                                            }}
                                        </p>
                                    </div>
                                </div>

                                <!-- Publishing Stats -->
                                <div
                                    v-if="canPublish"
                                    class="mt-4 border-t border-neutral-200 pt-4 dark:border-neutral-700"
                                >
                                    <div
                                        class="text-body-small flex items-center justify-between text-neutral-600 dark:text-neutral-400"
                                    >
                                        <span>Ready to publish to</span>
                                        <span
                                            class="font-medium text-neutral-900 dark:text-white"
                                        >
                                            {{ selectedPlatforms.length }}
                                            platform{{
                                                selectedPlatforms.length > 1
                                                    ? 's'
                                                    : ''
                                            }}
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
