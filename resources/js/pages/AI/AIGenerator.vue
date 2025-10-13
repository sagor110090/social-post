<script setup>
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select/index';
import { Textarea } from '@/components/ui/textarea';
import { router, usePage } from '@inertiajs/vue3';
import {
    Copy,
    Edit,
    Lightbulb,
    Loader2,
    RefreshCw,
    Wand2,
} from 'lucide-vue-next';
import { computed, onMounted, ref } from 'vue';

const page = usePage();
const user = computed(() => page.props.auth.user);

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
</script>

<template>
    <AppLayout title="AI Content Generator">
        <div class="mx-auto max-w-6xl space-y-6">
            <!-- Header -->
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-3xl font-bold">AI Content Generator</h1>
                    <p class="mt-2 text-muted-foreground">
                        Create engaging social media content powered by AI
                    </p>
                </div>
                <Button
                    variant="outline"
                    @click="showTemplates = !showTemplates"
                >
                    <Lightbulb class="mr-2 h-4 w-4" />
                    Templates
                </Button>
            </div>

            <!-- Templates Panel -->
            <Card v-if="showTemplates" class="mb-6">
                <CardHeader>
                    <CardTitle class="flex items-center gap-2">
                        <Lightbulb class="h-5 w-5" />
                        Content Templates
                    </CardTitle>
                    <CardDescription>
                        Quick-start templates for common social media scenarios
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <div
                        class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3"
                    >
                        <div
                            v-for="template in templates"
                            :key="template.id"
                            class="cursor-pointer rounded-lg border p-4 transition-colors hover:bg-gray-50"
                            @click="selectTemplate(template)"
                        >
                            <h3 class="font-medium">{{ template.name }}</h3>
                            <p class="mt-1 text-sm text-muted-foreground">
                                {{ template.prompt }}
                            </p>
                            <div class="mt-2 flex items-center gap-2">
                                <Badge variant="secondary">{{
                                    template.category
                                }}</Badge>
                                <Badge variant="outline" class="text-xs">
                                    {{ template.tones.join(', ') }}
                                </Badge>
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
                <!-- Input Form -->
                <Card>
                    <CardHeader>
                        <CardTitle>Create Content</CardTitle>
                        <CardDescription>
                            Describe what you want to post about and customize
                            the settings
                        </CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-6">
                        <!-- Platform Selection -->
                        <div class="space-y-2">
                            <label class="text-sm font-medium">Platform</label>
                            <Select v-model="form.platform">
                                <SelectTrigger>
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
                                        <span class="flex items-center gap-2">
                                            <span>{{ platform.icon }}</span>
                                            {{ platform.label }}
                                        </span>
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <p class="text-xs text-muted-foreground">
                                Character limit:
                                {{ currentCharacterLimit.toLocaleString() }}
                            </p>
                        </div>

                        <!-- Tone Selection -->
                        <div class="space-y-2">
                            <label class="text-sm font-medium">Tone</label>
                            <Select v-model="form.tone">
                                <SelectTrigger>
                                    <SelectValue placeholder="Select tone" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem
                                        v-for="tone in tones"
                                        :key="tone.value"
                                        :value="tone.value"
                                    >
                                        <div>
                                            <div class="font-medium">
                                                {{ tone.label }}
                                            </div>
                                            <div
                                                class="text-xs text-muted-foreground"
                                            >
                                                {{ tone.description }}
                                            </div>
                                        </div>
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </div>

                        <!-- Prompt Input -->
                        <div class="space-y-2">
                            <label class="text-sm font-medium"
                                >What do you want to post about?</label
                            >
                            <Textarea
                                v-model="form.prompt"
                                placeholder="e.g., Launching our new product line this week with special discounts..."
                                rows="4"
                                :disabled="isGenerating"
                            />
                            <div
                                class="flex justify-between text-xs text-muted-foreground"
                            >
                                <span
                                    >{{ form.prompt.length }} / 1000
                                    characters</span
                                >
                                <span
                                    v-if="selectedTemplate"
                                    class="text-blue-600"
                                >
                                    Template: {{ selectedTemplate.name }}
                                </span>
                            </div>
                        </div>

                        <!-- Options -->
                        <div class="space-y-3">
                            <label class="flex items-center gap-2">
                                <input
                                    type="checkbox"
                                    v-model="form.include_hashtags"
                                    :disabled="isGenerating"
                                    class="rounded"
                                />
                                <span class="text-sm">Include hashtags</span>
                            </label>

                            <label class="flex items-center gap-2">
                                <input
                                    type="checkbox"
                                    v-model="form.include_image"
                                    :disabled="isGenerating"
                                    class="rounded"
                                />
                                <span class="text-sm"
                                    >Generate image ideas</span
                                >
                            </label>
                        </div>

                        <!-- Action Buttons -->
                        <div class="flex gap-2">
                            <Button
                                @click="generateContent"
                                :disabled="!form.prompt.trim() || isGenerating"
                                class="flex-1"
                            >
                                <Loader2
                                    v-if="isGenerating"
                                    class="mr-2 h-4 w-4 animate-spin"
                                />
                                <Wand2 v-else class="mr-2 h-4 w-4" />
                                {{
                                    isGenerating
                                        ? 'Generating...'
                                        : 'Generate Content'
                                }}
                            </Button>
                            <Button
                                variant="outline"
                                @click="resetForm"
                                :disabled="isGenerating"
                            >
                                <RefreshCw class="h-4 w-4" />
                            </Button>
                        </div>
                    </CardContent>
                </Card>

                <!-- Generated Content -->
                <Card>
                    <CardHeader>
                        <CardTitle>Generated Content</CardTitle>
                        <CardDescription>
                            Review and use your AI-generated content
                        </CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <div
                            v-if="!generatedContent"
                            class="py-12 text-center text-muted-foreground"
                        >
                            <Wand2 class="mx-auto mb-4 h-12 w-12 opacity-50" />
                            <p>Your generated content will appear here</p>
                        </div>

                        <div v-else class="space-y-4">
                            <!-- Error Message -->
                            <div
                                v-if="generatedContent.error"
                                class="rounded-lg border border-red-200 bg-red-50 p-4"
                            >
                                <p class="text-sm text-red-800">
                                    {{ generatedContent.error }}
                                </p>
                            </div>

                            <!-- Content -->
                            <div class="rounded-lg bg-gray-50 p-4">
                                <div
                                    class="mb-2 flex items-center justify-between"
                                >
                                    <span class="text-sm font-medium"
                                        >Content</span
                                    >
                                    <div class="flex items-center gap-2">
                                        <span
                                            class="text-xs text-muted-foreground"
                                        >
                                            {{ contentLength }} /
                                            {{
                                                currentCharacterLimit.toLocaleString()
                                            }}
                                        </span>
                                        <Badge
                                            v-if="isOverLimit"
                                            variant="destructive"
                                            class="text-xs"
                                        >
                                            Over limit
                                        </Badge>
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            @click="
                                                copyToClipboard(
                                                    generatedContent.content,
                                                )
                                            "
                                        >
                                            <Copy class="h-4 w-4" />
                                        </Button>
                                    </div>
                                </div>
                                <p class="text-sm whitespace-pre-wrap">
                                    {{ generatedContent.content }}
                                </p>
                            </div>

                            <!-- Hashtags -->
                            <div
                                v-if="generatedContent.hashtags?.length"
                                class="rounded-lg bg-gray-50 p-4"
                            >
                                <div
                                    class="mb-2 flex items-center justify-between"
                                >
                                    <span class="text-sm font-medium"
                                        >Hashtags</span
                                    >
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        @click="copyHashtags"
                                    >
                                        <Copy class="h-4 w-4" />
                                    </Button>
                                </div>
                                <div class="flex flex-wrap gap-2">
                                    <Badge
                                        v-for="tag in generatedContent.hashtags"
                                        :key="tag"
                                        variant="secondary"
                                    >
                                        #{{ tag }}
                                    </Badge>
                                </div>
                            </div>

                            <!-- Image Ideas -->
                            <div
                                v-if="generatedContent.image_prompt"
                                class="rounded-lg bg-gray-50 p-4"
                            >
                                <span class="text-sm font-medium"
                                    >Image Idea</span
                                >
                                <p class="mt-1 text-sm">
                                    {{ generatedContent.image_prompt }}
                                </p>
                            </div>

                            <!-- Action Buttons -->
                            <div class="flex gap-2">
                                <Button
                                    @click="useContent"
                                    class="flex-1"
                                    :disabled="isOverLimit"
                                >
                                    <Edit class="mr-2 h-4 w-4" />
                                    Use This Content
                                </Button>
                                <Button
                                    variant="outline"
                                    @click="improveContent"
                                    :disabled="isImproving"
                                >
                                    <Loader2
                                        v-if="isImproving"
                                        class="mr-2 h-4 w-4 animate-spin"
                                    />
                                    <RefreshCw v-else class="mr-2 h-4 w-4" />
                                    Improve
                                </Button>
                                <Button
                                    variant="outline"
                                    @click="regenerateContent"
                                    :disabled="isGenerating"
                                >
                                    <Wand2 class="h-4 w-4" />
                                </Button>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </div>
    </AppLayout>
</template>
