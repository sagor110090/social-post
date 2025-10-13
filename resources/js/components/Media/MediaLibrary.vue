<script setup>
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    CheckIcon,
    DownloadIcon,
    ImageIcon,
    Loader2Icon,
    SearchIcon,
    TrashIcon,
    VideoIcon,
} from 'lucide-vue-next';
import { computed, onMounted, ref } from 'vue';

const props = defineProps({
    open: {
        type: Boolean,
        default: false,
    },
    multiple: {
        type: Boolean,
        default: false,
    },
    maxSelection: {
        type: Number,
        default: 10,
    },
    accept: {
        type: String,
        default: 'image/*,video/*',
    },
});

const emit = defineEmits(['update:open', 'select', 'close']);

const mediaItems = ref([]);
const loading = ref(false);
const searchQuery = ref('');
const selectedType = ref('');
const selectedPlatform = ref('');
const selectedItems = ref([]);
const pagination = ref({
    total: 0,
    limit: 20,
    offset: 0,
    has_more: false,
});

const typeOptions = [
    { value: '', label: 'All Types' },
    { value: 'image', label: 'Images' },
    { value: 'video', label: 'Videos' },
];

const platformOptions = [
    { value: '', label: 'All Platforms' },
    { value: 'facebook', label: 'Facebook' },
    { value: 'instagram', label: 'Instagram' },
    { value: 'linkedin', label: 'LinkedIn' },
    { value: 'twitter', label: 'X (Twitter)' },
];

const filteredMedia = computed(() => {
    let filtered = mediaItems.value;

    if (searchQuery.value) {
        filtered = filtered.filter((item) =>
            item.metadata.original_name
                .toLowerCase()
                .includes(searchQuery.value.toLowerCase()),
        );
    }

    if (selectedType.value) {
        filtered = filtered.filter((item) =>
            item.mime_type.startsWith(selectedType.value + '/'),
        );
    }

    if (selectedPlatform.value) {
        filtered = filtered.filter(
            (item) => item.metadata.platform === selectedPlatform.value,
        );
    }

    return filtered;
});

const isImage = (item) => {
    return item.mime_type.startsWith('image/');
};

const isVideo = (item) => {
    return item.mime_type.startsWith('video/');
};

const formatFileSize = (bytes) => {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
};

const formatDate = (dateString) => {
    return new Date(dateString).toLocaleDateString();
};

const loadMedia = async (reset = false) => {
    if (reset) {
        pagination.value.offset = 0;
        mediaItems.value = [];
    }

    loading.value = true;

    try {
        const params = new URLSearchParams({
            limit: pagination.value.limit,
            offset: pagination.value.offset,
        });

        if (selectedType.value) params.append('type', selectedType.value);
        if (selectedPlatform.value)
            params.append('platform', selectedPlatform.value);

        const response = await fetch(`/media/library?${params}`);
        const data = await response.json();

        if (response.ok) {
            if (reset) {
                mediaItems.value = data.media;
            } else {
                mediaItems.value.push(...data.media);
            }
            pagination.value = data.pagination;
        } else {
            console.error('Failed to load media:', data.error);
        }
    } catch (error) {
        console.error('Error loading media:', error);
    } finally {
        loading.value = false;
    }
};

const loadMore = () => {
    if (pagination.value.has_more && !loading.value) {
        pagination.value.offset += pagination.value.limit;
        loadMedia(false);
    }
};

const toggleSelection = (item) => {
    const index = selectedItems.value.findIndex(
        (selected) => selected.id === item.id,
    );

    if (index > -1) {
        selectedItems.value.splice(index, 1);
    } else {
        if (!props.multiple) {
            selectedItems.value = [];
        }
        if (selectedItems.value.length < props.maxSelection) {
            selectedItems.value.push(item);
        }
    }
};

const isSelected = (item) => {
    return selectedItems.value.some((selected) => selected.id === item.id);
};

const confirmSelection = () => {
    emit(
        'select',
        props.multiple ? selectedItems.value : selectedItems.value[0],
    );
    closeDialog();
};

const closeDialog = () => {
    emit('update:open', false);
    emit('close');
};

const deleteMedia = async (item) => {
    if (!confirm('Are you sure you want to delete this media file?')) {
        return;
    }

    try {
        const response = await fetch(`/media/${item.id}`, {
            method: 'DELETE',
            headers: {
                'X-CSRF-TOKEN': document
                    .querySelector('meta[name="csrf-token"]')
                    .getAttribute('content'),
            },
        });

        if (response.ok) {
            // Remove from local arrays
            mediaItems.value = mediaItems.value.filter(
                (media) => media.id !== item.id,
            );
            selectedItems.value = selectedItems.value.filter(
                (selected) => selected.id !== item.id,
            );
        } else {
            const error = await response.json();
            alert('Failed to delete media: ' + error.error);
        }
    } catch (error) {
        console.error('Error deleting media:', error);
        alert('Failed to delete media');
    }
};

const downloadMedia = (item) => {
    const link = document.createElement('a');
    link.href = item.url;
    link.download = item.metadata.original_name;
    link.target = '_blank';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
};

// Watch for open prop changes
watch(
    () => props.open,
    (newValue) => {
        if (newValue && mediaItems.value.length === 0) {
            loadMedia(true);
        }
    },
);

// Watch for filter changes
watch([selectedType, selectedPlatform], () => {
    loadMedia(true);
});

onMounted(() => {
    if (props.open) {
        loadMedia(true);
    }
});
</script>

<template>
    <Dialog :open="open" @update:open="closeDialog">
        <DialogContent class="max-h-[90vh] max-w-6xl overflow-hidden">
            <DialogHeader>
                <DialogTitle>Media Library</DialogTitle>
            </DialogHeader>

            <div class="flex h-[80vh] flex-col">
                <!-- Filters -->
                <div class="flex flex-wrap gap-4 border-b p-4">
                    <div class="min-w-[200px] flex-1">
                        <div class="relative">
                            <SearchIcon
                                class="absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 transform text-gray-400"
                            />
                            <Input
                                v-model="searchQuery"
                                placeholder="Search media..."
                                class="pl-10"
                            />
                        </div>
                    </div>

                    <Select v-model="selectedType">
                        <SelectTrigger class="w-[150px]">
                            <SelectValue placeholder="Type" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem
                                v-for="option in typeOptions"
                                :key="option.value"
                                :value="option.value"
                            >
                                {{ option.label }}
                            </SelectItem>
                        </SelectContent>
                    </Select>

                    <Select v-model="selectedPlatform">
                        <SelectTrigger class="w-[150px]">
                            <SelectValue placeholder="Platform" />
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

                <!-- Media Grid -->
                <div class="flex-1 overflow-y-auto p-4">
                    <div
                        v-if="loading && mediaItems.length === 0"
                        class="flex h-64 items-center justify-center"
                    >
                        <Loader2Icon
                            class="h-8 w-8 animate-spin text-gray-400"
                        />
                    </div>

                    <div
                        v-else-if="filteredMedia.length === 0"
                        class="py-12 text-center"
                    >
                        <ImageIcon
                            class="mx-auto mb-4 h-12 w-12 text-gray-400"
                        />
                        <h3 class="mb-2 text-lg font-medium text-gray-900">
                            No media found
                        </h3>
                        <p class="text-gray-500">
                            {{
                                searchQuery || selectedType || selectedPlatform
                                    ? 'Try adjusting your filters'
                                    : 'Upload some media to get started'
                            }}
                        </p>
                    </div>

                    <div
                        v-else
                        class="grid grid-cols-2 gap-4 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5"
                    >
                        <Card
                            v-for="item in filteredMedia"
                            :key="item.id"
                            class="cursor-pointer overflow-hidden transition-all hover:shadow-lg"
                            :class="{
                                'ring-2 ring-blue-500': isSelected(item),
                            }"
                            @click="toggleSelection(item)"
                        >
                            <div class="relative aspect-square bg-gray-100">
                                <!-- Selection Indicator -->
                                <div
                                    v-if="isSelected(item)"
                                    class="absolute top-2 right-2 z-10 flex h-6 w-6 items-center justify-center rounded-full bg-blue-500"
                                >
                                    <CheckIcon class="h-4 w-4 text-white" />
                                </div>

                                <!-- Media Preview -->
                                <img
                                    v-if="isImage(item)"
                                    :src="
                                        item.processed_images?.thumbnail ||
                                        item.url
                                    "
                                    :alt="item.metadata.original_name"
                                    class="h-full w-full object-cover"
                                />
                                <div
                                    v-else-if="isVideo(item)"
                                    class="flex h-full w-full items-center justify-center"
                                >
                                    <VideoIcon
                                        class="h-12 w-12 text-gray-400"
                                    />
                                </div>

                                <!-- Hover Actions -->
                                <div
                                    class="bg-opacity-0 hover:bg-opacity-50 absolute inset-0 flex items-center justify-center bg-black opacity-0 transition-all hover:opacity-100"
                                >
                                    <div class="flex gap-2">
                                        <Button
                                            variant="secondary"
                                            size="sm"
                                            @click.stop="downloadMedia(item)"
                                        >
                                            <DownloadIcon class="h-4 w-4" />
                                        </Button>
                                        <Button
                                            variant="destructive"
                                            size="sm"
                                            @click.stop="deleteMedia(item)"
                                        >
                                            <TrashIcon class="h-4 w-4" />
                                        </Button>
                                    </div>
                                </div>
                            </div>

                            <CardContent class="p-3">
                                <div class="space-y-1">
                                    <p
                                        class="truncate text-sm font-medium"
                                        :title="item.metadata.original_name"
                                    >
                                        {{ item.metadata.original_name }}
                                    </p>
                                    <div
                                        class="flex items-center justify-between text-xs text-gray-500"
                                    >
                                        <span>{{
                                            formatFileSize(item.size)
                                        }}</span>
                                        <span>{{
                                            formatDate(item.created_at)
                                        }}</span>
                                    </div>
                                    <div class="flex items-center gap-1">
                                        <Badge
                                            variant="outline"
                                            class="text-xs"
                                        >
                                            {{
                                                isImage(item)
                                                    ? 'Image'
                                                    : 'Video'
                                            }}
                                        </Badge>
                                        <Badge
                                            v-if="
                                                item.metadata.platform !==
                                                'general'
                                            "
                                            variant="outline"
                                            class="text-xs"
                                        >
                                            {{ item.metadata.platform }}
                                        </Badge>
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
                            <Loader2Icon
                                v-if="loading"
                                class="mr-2 h-4 w-4 animate-spin"
                            />
                            Load More
                        </Button>
                    </div>
                </div>

                <!-- Footer -->
                <div class="flex items-center justify-between border-t p-4">
                    <div class="text-sm text-gray-500">
                        {{ selectedItems.length }} selected
                        <span v-if="maxSelection > 1">
                            (max {{ maxSelection }})</span
                        >
                    </div>
                    <div class="flex gap-2">
                        <Button variant="outline" @click="closeDialog">
                            Cancel
                        </Button>
                        <Button
                            @click="confirmSelection"
                            :disabled="selectedItems.length === 0"
                        >
                            Select
                            {{
                                multiple
                                    ? `${selectedItems.length} Items`
                                    : 'Item'
                            }}
                        </Button>
                    </div>
                </div>
            </div>
        </DialogContent>
    </Dialog>
</template>
