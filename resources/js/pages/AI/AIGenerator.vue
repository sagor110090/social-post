<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import Card from '@/components/ui/card/Card.vue';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select/index';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/AppLayout.vue';
import { router } from '@inertiajs/vue3';
import { Copy, Edit, Lightbulb, RefreshCw, Wand2 } from 'lucide-vue-next';
import { computed, onMounted, ref } from 'vue';

const form = ref({
    prompt: '',
    platform: 'facebook',
    tone: 'professional',
    include_hashtags: true,
    include_image: false,
});

const generatedContent = ref(null);
const isGenerating = ref(false);
const isImproving = ref(false);
const templates = ref([]);
const showTemplates = ref(false);
const selectedTemplate = ref(null);

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

const characterLimits = {
    facebook: 80000,
    instagram: 2200,
    linkedin: 3000,
    twitter: 280,
};

const currentCharacterLimit = computed(() => {
    return characterLimits[form.value.platform] || 80000;
});

const contentLength = computed(() => {
    return generatedContent.value?.content?.length || 0;
});

const isOverLimit = computed(() => {
    return contentLength.value > currentCharacterLimit.value;
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
        const response = await fetch('/api/ai/generate', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document
                    .querySelector('meta[name="csrf-token"]')
                    .getAttribute('content'),
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
            error: error.message,
        };
    } finally {
        isGenerating.value = false;
    }
};

const improveContent = async () => {
    if (!generatedContent.value?.content) return;

    isImproving.value = true;

    try {
        const response = await fetch('/api/ai/improve', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document
                    .querySelector('meta[name="csrf-token"]')
                    .getAttribute('content'),
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

const useContent = () => {
    if (!generatedContent.value) return;

    const postData = {
        content: generatedContent.value.content,
        hashtags: generatedContent.value.hashtags,
        platforms: [form.value.platform],
    };

    router.post('/posts/create', postData);
};

const copyToClipboard = async (text) => {
    try {
        await navigator.clipboard.writeText(text);
        // You could show a toast notification here
    } catch (error) {
        console.error('Failed to copy:', error);
    }
};

const copyHashtags = () => {
    if (!generatedContent.value?.hashtags?.length) return;
    const hashtags = generatedContent.value.hashtags
        .map((tag) => `#${tag}`)
        .join(' ');
    copyToClipboard(hashtags);
};

const selectTemplate = (template) => {
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

const getToneEmoji = (tone) => {
    const emojis = {
        professional: '👔',
        casual: '😊',
        friendly: '🤗',
        humorous: '😄',
    };
    return emojis[tone] || '📝';
};
</script>

<template>
    <AppLayout title="AI Content Generator">
        <div class="min-h-screen">
            <div class="mx-auto max-w-7xl space-y-8 p-6">
                <!-- Enhanced Header -->
                <div class="text-center animate-fade-in">
                    <div
                        class="mb-8 inline-flex items-center gap-3 rounded-full border border-purple-200/60 bg-gradient-to-r from-purple-100/80 to-pink-100/80 px-6 py-3 shadow-lg backdrop-blur-sm dark:border-purple-800/60 dark:from-purple-900/40 dark:to-pink-900/40"
                    >
                        <div class="relative">
                            <BrainCircuit
                                class="h-6 w-6 text-purple-600 dark:text-purple-400 animate-pulse"
                            />
                            <div class="absolute -inset-1 bg-purple-400 rounded-full opacity-30 blur animate-ping"></div>
                        </div>
                        <span
                            class="text-body-large font-semibold text-purple-700 dark:text-purple-300"
                            >Powered by Advanced AI</span
                        >
                    </div>
                    <h1
                        class="text-display-1 mb-6 text-neutral-900 dark:text-white"
                    >
                        AI Content <span class="text-gradient font-bold">Generator</span> ✨
                    </h1>
                    <p
                        class="text-body-large mx-auto mb-8 max-w-4xl text-neutral-600 dark:text-neutral-400 leading-relaxed animate-slide-up"
                    >
                        Transform your ideas into compelling social media content
                        with the power of artificial intelligence. Create engaging
                        posts in seconds, not hours.
                    </p>
                    <Button
                        @click="showTemplates = !showTemplates"
                        class="btn-secondary hover-glow animate-slide-up"
                    >
                        <Lightbulb class="mr-3 h-5 w-5" />
                        {{ showTemplates ? 'Hide' : 'Show' }} Templates
                    </Button>
                </div>

            <!-- Enhanced Templates Panel -->
            <div
                v-if="showTemplates"
                class="card-elevated relative overflow-hidden animate-slide-up"
            >
                <div class="absolute top-0 left-0 w-full h-1 bg-gradient-to-r from-amber-500 via-orange-500 to-pink-500"></div>
                <div class="mb-8">
                    <h2
                        class="text-headline-1 mb-4 flex items-center gap-4 text-neutral-900 dark:text-white"
                    >
                        <div
                            class="flex h-14 w-14 items-center justify-center rounded-2xl bg-gradient-to-br from-amber-400 to-orange-500 shadow-lg"
                        >
                            <Lightbulb class="h-7 w-7 text-white" />
                        </div>
                        Content Templates
                    </h2>
                    <p class="text-body-large text-neutral-600 dark:text-neutral-400">
                        Quick-start templates for common social media scenarios
                    </p>
                </div>
                <div
                    class="grid grid-cols-1 gap-6 md:grid-cols-2 lg:grid-cols-3"
                >
                    <div
                        v-for="template in templates"
                        :key="template.id"
                        class="group hover:border-brand-primary cursor-pointer rounded-2xl border-2 border-neutral-200/60 bg-white/50 p-6 transition-all duration-300 hover:shadow-xl hover:scale-105 hover:bg-white dark:border-neutral-700/60 dark:bg-neutral-800/50 dark:hover:bg-neutral-800/80"
                        @click="selectTemplate(template)"
                    >
                        <div class="mb-6 flex items-start justify-between">
                            <div
                                class="flex h-14 w-14 items-center justify-center rounded-2xl bg-gradient-to-br from-purple-100 to-pink-100 transition-all group-hover:scale-110 group-hover:rotate-3 shadow-md dark:from-purple-900/30 dark:to-pink-900/30"
                            >
                                <BrainCircuit
                                    class="h-7 w-7 text-purple-600 dark:text-purple-400"
                                />
                            </div>
                            <Badge variant="secondary" class="text-xs font-semibold px-3 py-1">
                                {{ template.category }}
                            </Badge>
                        </div>
                        <h3
                            class="text-headline-3 group-hover:text-brand-primary mb-3 text-neutral-900 transition-colors dark:text-white"
                        >
                            {{ template.name }}
                        </h3>
                        <p
                            class="text-body-large mb-6 line-clamp-3 text-neutral-600 dark:text-neutral-400 leading-relaxed"
                        >
                            {{ template.prompt }}
                        </p>
                        <div class="flex items-center gap-3">
                            <Badge variant="outline" class="text-xs font-semibold">
                                {{ template.tones.join(', ') }}
                            </Badge>
                        </div>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 gap-8 lg:grid-cols-2">
                <!-- Enhanced Input Form -->
                <div
                    class="card-elevated relative overflow-hidden"
                >
                    <div class="absolute top-0 left-0 w-full h-1 bg-gradient-to-r from-brand-primary to-brand-accent"></div>
                    <div class="mb-8">
                        <h2
                            class="text-headline-1 mb-4 text-neutral-900 dark:text-white"
                        >
                            Create Content
                        </h2>
                        <p
                            class="text-body-large text-neutral-600 dark:text-neutral-400"
                        >
                            Describe what you want to post about and customize
                            the settings
                        </p>
                    </div>

                    <div class="space-y-8">
                        <!-- Enhanced Platform Selection -->
                        <div class="space-y-4">
                            <label
                                class="text-body-large flex items-center gap-3 font-semibold text-neutral-700 dark:text-neutral-300"
                            >
                                <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-brand-primary/10">
                                    <svg
                                        class="h-5 w-5 text-brand-primary"
                                        fill="none"
                                        stroke="currentColor"
                                        viewBox="0 0 24 24"
                                    >
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            stroke-width="2"
                                            d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.367 2.684 3 3 0 00-5.367-2.684z"
                                        />
                                    </svg>
                                </div>
                                Target Platform
                            </label>
                            <Select v-model="form.platform">
                                <SelectTrigger class="input-field h-12 text-base">
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
                                        <span class="flex items-center gap-4 py-2">
                                            <span class="text-2xl">{{
                                                platform.icon
                                            }}</span>
                                            <div>
                                                <div class="font-semibold text-base">
                                                    {{ platform.label }}
                                                </div>
                                                <div
                                                    class="text-sm text-neutral-500 dark:text-neutral-400"
                                                >
                                                    {{
                                                        characterLimits[
                                                            platform.value
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
                                <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-blue-500">
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
                                    {{ currentCharacterLimit.toLocaleString() }}
                                </p>
                            </div>
                        </div>

                        <!-- Enhanced Tone Selection -->
                        <div class="space-y-3">
                            <label
                                class="text-body flex items-center gap-2 font-medium text-neutral-700 dark:text-neutral-300"
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
                                        d="M14.828 14.828a4 4 0 01-5.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
                                    />
                                </svg>
                                Content Tone
                            </label>
                            <Select v-model="form.tone">
                                <SelectTrigger class="input-field">
                                    <SelectValue placeholder="Select tone" />
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
                                                    getToneEmoji(tone.value)
                                                }}</span>
                                            </div>
                                            <div>
                                                <div class="font-medium">
                                                    {{ tone.label }}
                                                </div>
                                                <div
                                                    class="text-xs text-neutral-500 dark:text-neutral-400"
                                                >
                                                    {{ tone.description }}
                                                </div>
                                            </div>
                                        </div>
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </div>

                        <!-- Enhanced Prompt Input -->
                        <div class="space-y-3">
                            <label
                                class="text-body flex items-center gap-2 font-medium text-neutral-700 dark:text-neutral-300"
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
                                        d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"
                                    />
                                </svg>
                                What do you want to post about?
                            </label>
                            <Textarea
                                v-model="form.prompt"
                                placeholder="e.g., Launching our new product line this week with special discounts for early customers..."
                                rows="4"
                                :disabled="isGenerating"
                                class="input-field resize-none"
                            />
                            <div class="flex items-center justify-between">
                                <span
                                    class="text-body-small text-neutral-600 dark:text-neutral-400"
                                >
                                    {{ form.prompt.length }} / 1000 characters
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

                        <!-- Enhanced Options -->
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
                                        class="text-brand-primary focus:ring-brand-primary rounded border-neutral-300"
                                    />
                                    <div class="flex-1">
                                        <span
                                            class="text-body font-medium text-neutral-900 dark:text-white"
                                            >Include hashtags</span
                                        >
                                        <p
                                            class="text-body-small text-neutral-600 dark:text-neutral-400"
                                        >
                                            Add relevant hashtags to increase
                                            reach
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
                                        class="text-brand-primary focus:ring-brand-primary rounded border-neutral-300"
                                    />
                                    <div class="flex-1">
                                        <span
                                            class="text-body font-medium text-neutral-900 dark:text-white"
                                            >Generate image ideas</span
                                        >
                                        <p
                                            class="text-body-small text-neutral-600 dark:text-neutral-400"
                                        >
                                            Get AI-powered image suggestions
                                        </p>
                                    </div>
                                </label>
                            </div>
                        </div>

                        <!-- Enhanced Action Buttons -->
                        <div class="flex gap-3">
                            <Button
                                @click="generateContent"
                                :disabled="!form.prompt.trim() || isGenerating"
                                class="btn-primary flex-1 py-3"
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
                                class="px-4"
                            >
                                <RefreshCw class="h-5 w-5" />
                            </Button>
                        </div>
                    </div>
                </div>

                <!-- Enhanced Generated Content -->
                <Card
                    class="rounded-xl border bg-card text-card-foreground shadow-sm"
                >
                    <div class="mb-6">
                        <h2
                            class="text-headline-2 mb-2 text-neutral-900 dark:text-white"
                        >
                            Generated Content
                        </h2>
                        <p
                            class="text-body text-neutral-600 dark:text-neutral-400"
                        >
                            Review and use your AI-generated content
                        </p>
                    </div>

                    <div class="space-y-6">
                        <div v-if="!generatedContent" class="py-16 text-center">
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
                                Your AI-generated content will appear here once
                                you describe what you want to post about.
                            </p>
                        </div>

                        <div v-else class="space-y-6">
                            <!-- Enhanced Error Message -->
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

                            <!-- Enhanced Content -->
                            <div
                                class="rounded-xl border border-neutral-200 bg-gradient-to-br from-neutral-50 to-neutral-100 p-6 dark:border-neutral-700 dark:from-neutral-800 dark:to-neutral-900"
                            >
                                <div
                                    class="mb-4 flex items-center justify-between"
                                >
                                    <div class="flex items-center gap-2">
                                        <div
                                            class="bg-brand-primary flex h-8 w-8 items-center justify-center rounded-lg"
                                        >
                                            <Edit class="h-4 w-4 text-white" />
                                        </div>
                                        <h3
                                            class="text-headline-4 text-neutral-900 dark:text-white"
                                        >
                                            Generated Content
                                        </h3>
                                    </div>
                                    <div class="flex items-center gap-3">
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
                                        {{ generatedContent.content }}
                                    </p>
                                </div>
                            </div>

                            <!-- Enhanced Hashtags -->
                            <div
                                v-if="generatedContent.hashtags?.length"
                                class="rounded-xl border border-blue-200 bg-gradient-to-br from-blue-50 to-cyan-50 p-6 dark:border-blue-800 dark:from-blue-900/20 dark:to-cyan-900/20"
                            >
                                <div
                                    class="mb-4 flex items-center justify-between"
                                >
                                    <div class="flex items-center gap-2">
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

                            <!-- Enhanced Image Ideas -->
                            <div
                                v-if="generatedContent.image_prompt"
                                class="rounded-xl border border-green-200 bg-gradient-to-br from-green-50 to-emerald-50 p-6 dark:border-green-800 dark:from-green-900/20 dark:to-emerald-900/20"
                            >
                                <div class="mb-4 flex items-center gap-2">
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
                                        {{ generatedContent.image_prompt }}
                                    </p>
                                </div>
                            </div>

                            <!-- Enhanced Action Buttons -->
                            <div class="flex gap-3">
                                <Button
                                    @click="useContent"
                                    class="btn-primary flex-1 py-3"
                                    :disabled="isOverLimit"
                                >
                                    <Edit class="mr-3 h-5 w-5" />
                                    <span class="font-medium"
                                        >Use This Content</span
                                    >
                                </Button>
                                <Button
                                    variant="outline"
                                    @click="improveContent"
                                    :disabled="isImproving"
                                    class="px-4"
                                >
                                    <div v-if="isImproving" class="mr-2">
                                        <div class="spinner h-4 w-4"></div>
                                    </div>
                                    <RefreshCw v-else class="mr-2 h-4 w-4" />
                                    Improve
                                </Button>
                                <Button
                                    variant="outline"
                                    @click="regenerateContent"
                                    :disabled="isGenerating"
                                    class="px-4"
                                >
                                    <Wand2 class="h-4 w-4" />
                                </Button>
                            </div>
                        </div>
                    </div>
                </Card>
            </div>
        </div>
        </div>
    </AppLayout>
</template>
