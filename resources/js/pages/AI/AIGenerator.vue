<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select/index';
import { Textarea } from '@/components/ui/textarea';
import { useToast } from '@/composables/useToast';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import {
    AlertCircle,
    Copy,
    Edit,
    Lightbulb,
    RefreshCw,
    Wand2,
} from 'lucide-vue-next';
import { computed, onMounted, ref } from 'vue';

// Type definitions
interface GeneratedContent {
    content: string;
    hashtags: string[];
    image_prompt?: string;
    error?: string;
}

interface Template {
    id: number;
    name: string;
    prompt: string;
    category: string;
    tones: string[];
}

interface Form {
    prompt: string;
    platform: string;
    tone: string;
    include_hashtags: boolean;
    include_image: boolean;
}

const form = ref<Form>({
    prompt: '',
    platform: 'facebook',
    tone: 'professional',
    include_hashtags: true,
    include_image: false,
});

const generatedContent = ref<GeneratedContent | null>(null);
const isGenerating = ref(false);
const isImproving = ref(false);
const isPosting = ref(false);
const toast = useToast();
const templates = ref<Template[]>([]);
const showTemplates = ref(false);
const selectedTemplate = ref<Template | null>(null);

const platforms = [
    { value: 'facebook', label: 'Facebook', icon: '📘' },
    { value: 'instagram', label: 'Instagram', icon: '📷' },
    { value: 'linkedin', label: 'LinkedIn', icon: '💼' },
    { value: 'twitter', label: 'X (Twitter)', icon: '🐦' },
];

const tones = [
    {
        value: 'professional',
        label: 'Professional',
        description: 'Formal and business-like',
    },
    {
        value: 'casual',
        label: 'Casual',
        description: 'Relaxed and conversational',
    },
    {
        value: 'friendly',
        label: 'Friendly',
        description: 'Warm and approachable',
    },
    {
        value: 'humorous',
        label: 'Humorous',
        description: 'Witty and entertaining',
    },
];

const characterLimits: Record<string, number> = {
    facebook: 2000,
    instagram: 2200,
    linkedin: 3000,
    twitter: 280,
};

const currentCharacterLimit = computed(() => {
    return (
        characterLimits[form.value.platform as keyof typeof characterLimits] ||
        80000
    );
});

const contentLength = computed(() => {
    return generatedContent.value?.content?.length || 0;
});

const isOverLimit = computed(() => {
    return contentLength.value > currentCharacterLimit.value;
});

const formattedContent = computed(() => {
    if (!generatedContent.value?.content) return '';

    let content = generatedContent.value.content;

    // Use simplified formatting to match backend
    switch (form.value.platform) {
        case 'facebook':
            // Remove markdown bold formatting
            content = content.replace(/\*\*(.*?)\*\*/g, '$1');
            // Convert numbered lists to bullet points
            content = content.replace(/^\d+\.\s+(.+)$/gm, '• $1');
            break;
        case 'instagram':
            // Remove markdown formatting
            content = content.replace(/\*\*(.*?)\*\*/g, '$1');
            break;
        case 'linkedin':
            // Remove markdown formatting
            content = content.replace(/\*\*(.*?)\*\*/g, '$1');
            break;
        case 'twitter':
            // Remove all formatting for Twitter
            content = content.replace(/\*\*(.*?)\*\*/g, '$1');
            content = content.replace(/^\d+\.\s+(.+)$/gm, '$1');
            break;
    }

    return content;
});

onMounted(() => {
    loadTemplates();
});

const loadTemplates = async () => {
    try {
        const response = await fetch('/api/ai/templates');
        const data = await response.json();
        templates.value = data.templates;
    } catch (error) {
        console.error('Error loading templates:', error);
    }
};

const generateContent = async () => {
    if (!form.value.prompt.trim()) return;

    isGenerating.value = true;
    generatedContent.value = null;

    try {
        // Get CSRF token safely
        const csrfToken = document
            .querySelector('meta[name="csrf-token"]')
            ?.getAttribute('content');

        const response = await fetch('/api/ai/generate', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                ...(csrfToken && { 'X-CSRF-TOKEN': csrfToken }),
            },
            body: JSON.stringify(form.value),
        });

        const data = await response.json();

        if (response.ok) {
            generatedContent.value = data;
        } else {
            throw new Error(data.error || 'Failed to generate content');
        }
    } catch (error) {
        console.error('Error generating content:', error);
        generatedContent.value = {
            content:
                'Unable to generate content at the moment. Please try again.',
            hashtags: [],
            error: (error as Error).message,
        };
    } finally {
        isGenerating.value = false;
    }
};

const improveContent = async () => {
    if (!generatedContent.value?.content) return;

    isImproving.value = true;

    try {
        // Get CSRF token safely
        const csrfToken = document
            .querySelector('meta[name="csrf-token"]')
            ?.getAttribute('content');

        const response = await fetch('/api/ai/improve', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                ...(csrfToken && { 'X-CSRF-TOKEN': csrfToken }),
            },
            body: JSON.stringify({
                content: generatedContent.value.content,
                platform: form.value.platform,
                tone: form.value.tone,
            }),
        });

        const data = await response.json();

        if (response.ok) {
            generatedContent.value.content = data.improved_content;
            if (data.suggestions?.length > 0) {
                // You could show suggestions in a toast or modal
                console.log('AI Suggestions:', data.suggestions);
            }
        }
    } catch (error) {
        console.error('Error improving content:', error);
    } finally {
        isImproving.value = false;
    }
};

const useContent = async () => {
    if (!generatedContent.value) return;

    isPosting.value = true;

    const postData = {
        content: generatedContent.value.content,
        hashtags: generatedContent.value.hashtags,
        platforms: [form.value.platform],
    };

    try {
        // Get CSRF token safely
        const csrfToken = document
            .querySelector('meta[name="csrf-token"]')
            ?.getAttribute('content');

        const response = await fetch('/posts/publish', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                ...(csrfToken && { 'X-CSRF-TOKEN': csrfToken }),
            },
            body: JSON.stringify(postData),
        });

        const data = await response.json();

        if (response.ok && data.success) {
            // Show success message
            toast.success(data.message);

            // Redirect to posts history
            router.visit('/social/posts/history');
        } else {
            // Show error message
            toast.error(data.error || 'Failed to create post');
        }
    } catch (error) {
        console.error('Network error:', error);
        toast.error('Network error: Failed to create post');
    } finally {
        isPosting.value = false;
    }
};

const copyToClipboard = async (text: string) => {
    try {
        await navigator.clipboard.writeText(text);
        toast.success('Content copied to clipboard!');
    } catch (error) {
        console.error('Failed to copy:', error);
        toast.error('Failed to copy content to clipboard');
    }
};

const copyHashtags = () => {
    if (!generatedContent.value?.hashtags?.length) {
        toast.warning('No hashtags available to copy');
        return;
    }
    const hashtags = generatedContent.value.hashtags
        .map((tag: string) => `#${tag}`)
        .join(' ');
    copyToClipboard(hashtags);
};

const selectTemplate = (template: Template) => {
    selectedTemplate.value = template;
    form.value.prompt = template.prompt;
    showTemplates.value = false;
};

const resetForm = () => {
    form.value.prompt = '';
    form.value.platform = 'facebook';
    form.value.tone = 'professional';
    form.value.include_hashtags = true;
    form.value.include_image = false;
    generatedContent.value = null;
    selectedTemplate.value = null;
};

const regenerateContent = () => {
    generateContent();
};

const getToneEmoji = (tone: string) => {
    const emojis: Record<string, string> = {
        professional: '👔',
        casual: '😊',
        friendly: '🤗',
        humorous: '😄',
    };
    return emojis[tone] || '📝';
};
</script>

<template>
    <Head title="AI Content Generator" />

    <AppLayout>
        <div class="min-h-screen">
            <div class="p-6">
                <div class="mx-auto max-w-7xl">
                    <!-- Header -->
                    <div class="animate-fade-in mb-12">
                        <h1
                            class="text-display-1 mb-4 text-neutral-900 dark:text-white"
                        >
                            AI Content
                            <span class="text-gradient font-bold"
                                >Generator</span
                            >
                            ✨
                        </h1>
                        <p
                            class="text-body-large max-w-3xl leading-relaxed text-neutral-600 dark:text-neutral-400"
                        >
                            Transform your ideas into compelling social media
                            content with the power of artificial intelligence.
                            Create engaging posts in seconds, not hours.
                        </p>
                        <div class="mt-6 flex gap-4">
                            <Button
                                @click="showTemplates = !showTemplates"
                                class="btn-secondary hover-glow"
                            >
                                <Lightbulb class="mr-3 h-5 w-5" />
                                {{ showTemplates ? 'Hide' : 'Show' }} Templates
                            </Button>
                        </div>
                    </div>

                    <!-- Templates Panel -->
                    <div
                        v-if="showTemplates"
                        class="card-elevated animate-slide-up relative mb-12 overflow-hidden"
                    >
                        <div
                            class="absolute top-0 left-0 h-1 w-full bg-gradient-to-r from-amber-500 via-orange-500 to-pink-500"
                        ></div>
                        <div class="mb-6">
                            <h2
                                class="text-headline-1 mb-3 flex items-center gap-4 text-neutral-900 dark:text-white"
                            >
                                <div
                                    class="flex h-14 w-14 items-center justify-center rounded-2xl bg-gradient-to-br from-amber-400 to-orange-500 shadow-lg"
                                >
                                    <Lightbulb class="h-7 w-7 text-white" />
                                </div>
                                Content Templates
                            </h2>
                            <p
                                class="text-body-large text-neutral-600 dark:text-neutral-400"
                            >
                                Quick-start templates for common social media
                                scenarios
                            </p>
                        </div>
                        <div
                            class="grid grid-cols-1 gap-6 md:grid-cols-2 lg:grid-cols-3"
                        >
                            <div
                                v-for="template in templates"
                                :key="template.id"
                                class="group hover:border-brand-primary cursor-pointer rounded-2xl border-2 border-neutral-200/60 bg-white/50 p-6 transition-all duration-300 hover:scale-105 hover:bg-white hover:shadow-xl dark:border-neutral-700/60 dark:bg-neutral-800/50 dark:hover:bg-neutral-800/80"
                                @click="selectTemplate(template)"
                            >
                                <div
                                    class="mb-6 flex items-start justify-between"
                                >
                                    <div
                                        class="flex h-14 w-14 items-center justify-center rounded-2xl bg-gradient-to-br from-purple-100 to-pink-100 shadow-md transition-all group-hover:scale-110 group-hover:rotate-3 dark:from-purple-900/30 dark:to-pink-900/30"
                                    >
                                        <Lightbulb
                                            class="h-7 w-7 text-purple-600 dark:text-purple-400"
                                        />
                                    </div>
                                    <Badge
                                        variant="secondary"
                                        class="px-3 py-1 text-xs font-semibold"
                                    >
                                        {{ template.category }}
                                    </Badge>
                                </div>
                                <h3
                                    class="text-headline-3 group-hover:text-brand-primary mb-3 text-neutral-900 transition-colors dark:text-white"
                                >
                                    {{ template.name }}
                                </h3>
                                <p
                                    class="text-body-large mb-6 line-clamp-3 leading-relaxed text-neutral-600 dark:text-neutral-400"
                                >
                                    {{ template.prompt }}
                                </p>
                                <div class="flex items-center gap-3">
                                    <Badge
                                        variant="outline"
                                        class="text-xs font-semibold"
                                    >
                                        {{ template.tones.join(', ') }}
                                    </Badge>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 gap-8 lg:grid-cols-2">
                        <!-- Input Form -->
                        <div
                            class="card-elevated group relative overflow-hidden transition-all duration-300 hover:scale-[1.01]"
                        >
                            <div
                                class="absolute top-0 left-0 h-1 w-full bg-gradient-to-r from-purple-500 to-pink-500 opacity-0 transition-opacity duration-300 group-hover:opacity-100"
                            ></div>
                            <div class="mb-6">
                                <h2
                                    class="text-headline-1 mb-3 text-neutral-900 dark:text-white"
                                >
                                    Create Content
                                </h2>
                                <p
                                    class="text-body-large text-neutral-600 dark:text-neutral-400"
                                >
                                    Describe what you want to post about and
                                    customize the settings
                                </p>
                            </div>

                            <div class="space-y-6">
                                <!-- Platform Selection -->
                                <div class="space-y-3">
                                    <label
                                        class="text-body-large flex items-center gap-3 font-semibold text-neutral-700 dark:text-neutral-300"
                                    >
                                        Target Platform
                                    </label>
                                    <Select v-model="form.platform">
                                        <SelectTrigger class="h-12 text-base">
                                            <SelectValue
                                                placeholder="Select platform"
                                            />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem
                                                v-for="platform in platforms"
                                                :key="platform.value"
                                                :value="platform.value"
                                            >
                                                <span
                                                    class="flex items-center gap-4 py-2"
                                                >
                                                    <span class="text-2xl">{{
                                                        platform.icon
                                                    }}</span>
                                                    <div>
                                                        <div
                                                            class="text-base font-semibold"
                                                        >
                                                            {{ platform.label }}
                                                        </div>
                                                        <div
                                                            class="text-sm text-neutral-500 dark:text-neutral-400"
                                                        >
                                                            {{
                                                                characterLimits[
                                                                    platform
                                                                        .value
                                                                ].toLocaleString()
                                                            }}
                                                            chars
                                                        </div>
                                                    </div>
                                                </span>
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                    <div
                                        class="flex items-center gap-3 rounded-2xl border border-blue-200/60 bg-blue-50/80 p-4 backdrop-blur-sm dark:border-blue-800/60 dark:bg-blue-900/30"
                                    >
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
                                                    d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
                                                />
                                            </svg>
                                        </div>
                                        <p
                                            class="text-body font-medium text-blue-700 dark:text-blue-300"
                                        >
                                            Character limit:
                                            {{
                                                currentCharacterLimit.toLocaleString()
                                            }}
                                        </p>
                                    </div>
                                </div>

                                <!-- Tone Selection -->
                                <div class="space-y-3">
                                    <label
                                        class="text-body flex items-center gap-2 font-medium text-neutral-700 dark:text-neutral-300"
                                    >
                                        Content Tone
                                    </label>
                                    <Select v-model="form.tone">
                                        <SelectTrigger>
                                            <SelectValue
                                                placeholder="Select tone"
                                            />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem
                                                v-for="tone in tones"
                                                :key="tone.value"
                                                :value="tone.value"
                                            >
                                                <div
                                                    class="flex items-center gap-3 py-2"
                                                >
                                                    <div
                                                        class="flex h-8 w-8 items-center justify-center rounded-lg bg-neutral-100 dark:bg-neutral-800"
                                                    >
                                                        <span class="text-sm">{{
                                                            getToneEmoji(
                                                                tone.value,
                                                            )
                                                        }}</span>
                                                    </div>
                                                    <div>
                                                        <div
                                                            class="font-medium"
                                                        >
                                                            {{ tone.label }}
                                                        </div>
                                                        <div
                                                            class="text-xs text-neutral-500 dark:text-neutral-400"
                                                        >
                                                            {{
                                                                tone.description
                                                            }}
                                                        </div>
                                                    </div>
                                                </div>
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>

                                <!-- Prompt Input -->
                                <div class="space-y-3">
                                    <label
                                        class="text-body flex items-center gap-2 font-medium text-neutral-700 dark:text-neutral-300"
                                    >
                                        What do you want to post about?
                                    </label>
                                    <Textarea
                                        v-model="form.prompt"
                                        placeholder="e.g., Launching our new product line this week with special discounts for early customers..."
                                        rows="4"
                                        :disabled="isGenerating"
                                        class="resize-none"
                                    />
                                    <div
                                        class="flex items-center justify-between"
                                    >
                                        <span
                                            class="text-body-small text-neutral-600 dark:text-neutral-400"
                                        >
                                            {{ form.prompt.length }} / 1000
                                            characters
                                        </span>
                                        <div
                                            v-if="selectedTemplate"
                                            class="flex items-center gap-2 rounded-full border border-purple-200 bg-purple-100 px-3 py-1 dark:border-purple-800 dark:bg-purple-900/30"
                                        >
                                            <Lightbulb
                                                class="h-3 w-3 text-purple-600 dark:text-purple-400"
                                            />
                                            <span
                                                class="text-body-small font-medium text-purple-700 dark:text-purple-300"
                                            >
                                                {{ selectedTemplate.name }}
                                            </span>
                                        </div>
                                    </div>
                                </div>

                                <!-- Options -->
                                <div class="space-y-4">
                                    <label
                                        class="text-body font-medium text-neutral-700 dark:text-neutral-300"
                                        >Additional Options</label
                                    >
                                    <div class="space-y-3">
                                        <label
                                            class="flex cursor-pointer items-center gap-3 rounded-lg border border-neutral-200 p-3 transition-colors hover:bg-neutral-50 dark:border-neutral-700 dark:hover:bg-neutral-800"
                                        >
                                            <input
                                                type="checkbox"
                                                v-model="form.include_hashtags"
                                                :disabled="isGenerating"
                                                class="rounded border-neutral-300"
                                            />
                                            <div class="flex-1">
                                                <span
                                                    class="text-body font-medium text-neutral-900 dark:text-white"
                                                    >Include hashtags</span
                                                >
                                                <p
                                                    class="text-body-small text-neutral-600 dark:text-neutral-400"
                                                >
                                                    Add relevant hashtags to
                                                    increase reach
                                                </p>
                                            </div>
                                        </label>

                                        <label
                                            class="flex cursor-pointer items-center gap-3 rounded-lg border border-neutral-200 p-3 transition-colors hover:bg-neutral-50 dark:border-neutral-700 dark:hover:bg-neutral-800"
                                        >
                                            <input
                                                type="checkbox"
                                                v-model="form.include_image"
                                                :disabled="isGenerating"
                                                class="rounded border-neutral-300"
                                            />
                                            <div class="flex-1">
                                                <span
                                                    class="text-body font-medium text-neutral-900 dark:text-white"
                                                    >Generate image ideas</span
                                                >
                                                <p
                                                    class="text-body-small text-neutral-600 dark:text-neutral-400"
                                                >
                                                    Get AI-powered image
                                                    suggestions
                                                </p>
                                            </div>
                                        </label>
                                    </div>
                                </div>

                                <!-- Action Buttons -->
                                <div class="flex gap-3">
                                    <Button
                                        @click="generateContent"
                                        :disabled="
                                            !form.prompt.trim() || isGenerating
                                        "
                                        class="btn-primary hover-glow flex-1 py-3"
                                    >
                                        <div v-if="isGenerating" class="mr-3">
                                            <div class="spinner h-5 w-5"></div>
                                        </div>
                                        <Wand2 v-else class="mr-3 h-5 w-5" />
                                        <span class="font-medium">
                                            {{
                                                isGenerating
                                                    ? 'Generating Magic...'
                                                    : 'Generate Content'
                                            }}
                                        </span>
                                    </Button>
                                    <Button
                                        variant="outline"
                                        @click="resetForm"
                                        :disabled="isGenerating"
                                        class="hover-glow"
                                    >
                                        <RefreshCw class="h-5 w-5" />
                                    </Button>
                                </div>
                            </div>
                        </div>

                        <!-- Generated Content -->
                        <div
                            class="card-elevated group relative overflow-hidden transition-all duration-300 hover:scale-[1.01]"
                        >
                            <div
                                class="absolute top-0 left-0 h-1 w-full bg-gradient-to-r from-green-500 to-emerald-500 opacity-0 transition-opacity duration-300 group-hover:opacity-100"
                            ></div>
                            <div class="mb-6">
                                <h2
                                    class="text-headline-1 mb-3 text-neutral-900 dark:text-white"
                                >
                                    Generated Content
                                </h2>
                                <p
                                    class="text-body-large text-neutral-600 dark:text-neutral-400"
                                >
                                    Review and use your AI-generated content
                                </p>
                            </div>

                            <div class="space-y-6">
                                <div
                                    v-if="!generatedContent"
                                    class="py-16 text-center"
                                >
                                    <div
                                        class="mx-auto mb-6 flex h-16 w-16 items-center justify-center rounded-xl bg-gradient-to-br from-purple-100 to-pink-100 dark:from-purple-900/30 dark:to-pink-900/30"
                                    >
                                        <Wand2
                                            class="h-8 w-8 text-purple-600 dark:text-purple-400"
                                        />
                                    </div>
                                    <h3
                                        class="text-headline-4 mb-2 text-neutral-900 dark:text-white"
                                    >
                                        Ready to create magic?
                                    </h3>
                                    <p
                                        class="text-body text-neutral-600 dark:text-neutral-400"
                                    >
                                        Your AI-generated content will appear
                                        here once you describe what you want to
                                        post about.
                                    </p>
                                </div>

                                <div v-else class="space-y-6">
                                    <!-- Error Message -->
                                    <div
                                        v-if="generatedContent.error"
                                        class="rounded-xl border border-red-200 bg-red-50 p-4 dark:border-red-800 dark:bg-red-900/20"
                                    >
                                        <div class="flex items-start gap-3">
                                            <div
                                                class="flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-lg bg-red-500"
                                            >
                                                <AlertCircle
                                                    class="h-4 w-4 text-white"
                                                />
                                            </div>
                                            <div>
                                                <h4
                                                    class="text-body mb-1 font-medium text-red-800 dark:text-red-200"
                                                >
                                                    Generation Error
                                                </h4>
                                                <p
                                                    class="text-body-small text-red-700 dark:text-red-300"
                                                >
                                                    {{ generatedContent.error }}
                                                </p>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Content -->
                                    <div
                                        class="rounded-xl border border-neutral-200 bg-gradient-to-br from-neutral-50 to-neutral-100 p-6 dark:border-neutral-700 dark:from-neutral-800 dark:to-neutral-900"
                                    >
                                        <div
                                            class="mb-4 flex items-center justify-between"
                                        >
                                            <div
                                                class="flex items-center gap-2"
                                            >
                                                <div
                                                    class="bg-brand-primary flex h-8 w-8 items-center justify-center rounded-lg"
                                                >
                                                    <Edit
                                                        class="h-4 w-4 text-white"
                                                    />
                                                </div>
                                                <h3
                                                    class="text-headline-4 text-neutral-900 dark:text-white"
                                                >
                                                    Generated Content
                                                    <span
                                                        class="text-body-small font-normal text-neutral-500"
                                                    >
                                                        (Formatted for
                                                        {{
                                                            platforms.find(
                                                                (p) =>
                                                                    p.value ===
                                                                    form.platform,
                                                            )?.label
                                                        }})
                                                    </span>
                                                </h3>
                                            </div>
                                            <div
                                                class="flex items-center gap-3"
                                            >
                                                <div class="text-right">
                                                    <span
                                                        class="text-body-small font-medium"
                                                        :class="
                                                            isOverLimit
                                                                ? 'text-red-600 dark:text-red-400'
                                                                : 'text-neutral-600 dark:text-neutral-400'
                                                        "
                                                    >
                                                        {{ contentLength }} /
                                                        {{
                                                            currentCharacterLimit.toLocaleString()
                                                        }}
                                                    </span>
                                                    <div
                                                        v-if="!isOverLimit"
                                                        class="text-body-small text-green-600 dark:text-green-400"
                                                    >
                                                        Perfect length ✅
                                                    </div>
                                                </div>
                                                <Button
                                                    variant="ghost"
                                                    size="sm"
                                                    @click="
                                                        copyToClipboard(
                                                            generatedContent.content,
                                                        )
                                                    "
                                                    class="text-brand-primary hover:text-brand-primary-dark"
                                                >
                                                    <Copy class="h-4 w-4" />
                                                </Button>
                                            </div>
                                        </div>
                                        <div
                                            class="rounded-lg border border-neutral-200 bg-white p-4 dark:border-neutral-700 dark:bg-neutral-800"
                                        >
                                            <p
                                                class="text-body whitespace-pre-wrap text-neutral-700 dark:text-neutral-300"
                                            >
                                                {{ formattedContent }}
                                            </p>
                                        </div>
                                    </div>

                                    <!-- Hashtags -->
                                    <div
                                        v-if="generatedContent.hashtags?.length"
                                        class="rounded-xl border border-blue-200 bg-gradient-to-br from-blue-50 to-cyan-50 p-6 dark:border-blue-800 dark:from-blue-900/20 dark:to-cyan-900/20"
                                    >
                                        <div
                                            class="mb-4 flex items-center justify-between"
                                        >
                                            <div
                                                class="flex items-center gap-2"
                                            >
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
                                                            d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"
                                                        />
                                                    </svg>
                                                </div>
                                                <h3
                                                    class="text-headline-4 text-neutral-900 dark:text-white"
                                                >
                                                    Suggested Hashtags
                                                </h3>
                                            </div>
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                @click="copyHashtags"
                                                class="text-blue-600 hover:text-blue-700 dark:text-blue-400 dark:hover:text-blue-300"
                                            >
                                                <Copy class="h-4 w-4" />
                                            </Button>
                                        </div>
                                        <div class="flex flex-wrap gap-2">
                                            <Badge
                                                v-for="tag in generatedContent.hashtags"
                                                :key="tag"
                                                variant="secondary"
                                                class="cursor-pointer bg-blue-100 px-3 py-1 text-blue-700 transition-colors hover:bg-blue-200 dark:bg-blue-900/30 dark:text-blue-300 dark:hover:bg-blue-900/50"
                                            >
                                                #{{ tag }}
                                            </Badge>
                                        </div>
                                    </div>

                                    <!-- Image Ideas -->
                                    <div
                                        v-if="generatedContent.image_prompt"
                                        class="rounded-xl border border-green-200 bg-gradient-to-br from-green-50 to-emerald-50 p-6 dark:border-green-800 dark:from-green-900/20 dark:to-emerald-900/20"
                                    >
                                        <div
                                            class="mb-4 flex items-center gap-2"
                                        >
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
                                            <h3
                                                class="text-headline-4 text-neutral-900 dark:text-white"
                                            >
                                                Image Idea
                                            </h3>
                                        </div>
                                        <div
                                            class="rounded-lg border border-green-200 bg-white p-4 dark:border-green-700 dark:bg-neutral-800"
                                        >
                                            <p
                                                class="text-body text-neutral-700 dark:text-neutral-300"
                                            >
                                                {{
                                                    generatedContent.image_prompt
                                                }}
                                            </p>
                                        </div>
                                    </div>

                                    <!-- Action Buttons -->
                                    <div class="flex gap-3">
                                        <Button
                                            @click="useContent"
                                            class="btn-primary hover-glow flex-1 py-3"
                                            :disabled="isOverLimit || isPosting"
                                        >
                                            <div v-if="isPosting" class="mr-3">
                                                <div
                                                    class="spinner h-5 w-5"
                                                ></div>
                                            </div>
                                            <Edit v-else class="mr-3 h-5 w-5" />
                                            <span class="font-medium">
                                                {{
                                                    isPosting
                                                        ? 'Posting...'
                                                        : 'Use This Content'
                                                }}
                                            </span>
                                        </Button>
                                        <Button
                                            variant="outline"
                                            @click="improveContent"
                                            :disabled="isImproving"
                                            class="hover-glow"
                                        >
                                            <div
                                                v-if="isImproving"
                                                class="mr-2"
                                            >
                                                <div
                                                    class="spinner h-4 w-4"
                                                ></div>
                                            </div>
                                            <RefreshCw
                                                v-else
                                                class="mr-2 h-4 w-4"
                                            />
                                            Improve
                                        </Button>
                                        <Button
                                            variant="outline"
                                            @click="regenerateContent"
                                            :disabled="isGenerating"
                                            class="hover-glow"
                                        >
                                            <Wand2 class="h-4 w-4" />
                                        </Button>
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
