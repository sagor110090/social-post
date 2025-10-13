<script setup>
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Progress } from '@/components/ui/progress';
import {
    AlertCircleIcon,
    EyeIcon,
    ImageIcon,
    Loader2Icon,
    TrashIcon,
    UploadIcon,
    VideoIcon,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';

const props = defineProps({
    accept: {
        type: String,
        default: 'image/*',
    },
    maxSize: {
        type: Number,
        default: 10 * 1024 * 1024, // 10MB
    },
    maxFiles: {
        type: Number,
        default: 1,
    },
    platform: {
        type: String,
        default: 'general',
    },
    multiple: {
        type: Boolean,
        default: false,
    },
    showLibrary: {
        type: Boolean,
        default: true,
    },
});

const emit = defineEmits([
    'upload-success',
    'upload-error',
    'file-removed',
    'file-selected',
]);

const isDragging = ref(false);
const isUploading = ref(false);
const uploadProgress = ref(0);
const uploadedFiles = ref([]);
const errors = ref([]);
const showLibrary = ref(false);

const fileInput = ref(null);

const isImageFile = (file) => {
    return file.type.startsWith('image/');
};

const isVideoFile = (file) => {
    return file.type.startsWith('video/');
};

const formatFileSize = (bytes) => {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
};

const validateFile = (file) => {
    const errors = [];

    // Check file size
    if (file.size > props.maxSize) {
        errors.push(
            `File size (${formatFileSize(file.size)}) exceeds maximum allowed size (${formatFileSize(props.maxSize)})`,
        );
    }

    // Check file type
    if (props.accept && !file.type.match(props.accept.replace('*', '.*'))) {
        errors.push(`File type (${file.type}) is not allowed`);
    }

    return errors;
};

const handleFileSelect = (files) => {
    const fileArray = Array.from(files);
    const validFiles = [];
    const newErrors = [];

    // Check if adding files would exceed maxFiles
    if (!props.multiple && fileArray.length > 1) {
        newErrors.push('Only one file is allowed');
        return;
    }

    if (uploadedFiles.value.length + fileArray.length > props.maxFiles) {
        newErrors.push(`Maximum ${props.maxFiles} files allowed`);
        return;
    }

    // Validate each file
    fileArray.forEach((file) => {
        const fileErrors = validateFile(file);
        if (fileErrors.length === 0) {
            validFiles.push(file);
        } else {
            newErrors.push(
                ...fileErrors.map((error) => `${file.name}: ${error}`),
            );
        }
    });

    if (newErrors.length > 0) {
        errors.value = newErrors;
        return;
    }

    errors.value = [];

    // Upload valid files
    validFiles.forEach((file) => {
        uploadFile(file);
    });
};

const uploadFile = async (file) => {
    isUploading.value = true;
    uploadProgress.value = 0;

    const formData = new FormData();
    formData.append('file', file);
    formData.append('platform', props.platform);

    const endpoint = isImageFile(file)
        ? '/media/upload-image'
        : '/media/upload-video';

    try {
        const xhr = new XMLHttpRequest();

        // Track upload progress
        xhr.upload.addEventListener('progress', (e) => {
            if (e.lengthComputable) {
                uploadProgress.value = Math.round((e.loaded / e.total) * 100);
            }
        });

        // Handle completion
        xhr.addEventListener('load', () => {
            if (xhr.status === 200) {
                const response = JSON.parse(xhr.responseText);
                if (response.success) {
                    uploadedFiles.value.push({
                        id: response.media_id,
                        file: file,
                        url: response.url,
                        processed_images: response.processed_images,
                        metadata: response.metadata,
                        preview: URL.createObjectURL(file),
                    });
                    emit('upload-success', response);
                } else {
                    errors.value = [response.error];
                    emit('upload-error', response.error);
                }
            } else {
                const error = JSON.parse(xhr.responseText);
                errors.value = [error.error || 'Upload failed'];
                emit('upload-error', error.error || 'Upload failed');
            }
            isUploading.value = false;
            uploadProgress.value = 0;
        });

        // Handle errors
        xhr.addEventListener('error', () => {
            errors.value = ['Upload failed due to network error'];
            emit('upload-error', 'Upload failed due to network error');
            isUploading.value = false;
            uploadProgress.value = 0;
        });

        xhr.open('POST', endpoint);
        xhr.setRequestHeader(
            'X-CSRF-TOKEN',
            document
                .querySelector('meta[name="csrf-token"]')
                .getAttribute('content'),
        );
        xhr.send(formData);
    } catch (error) {
        errors.value = [error.message];
        emit('upload-error', error.message);
        isUploading.value = false;
        uploadProgress.value = 0;
    }
};

const handleDrop = (e) => {
    e.preventDefault();
    isDragging.value = false;

    const files = e.dataTransfer.files;
    if (files.length > 0) {
        handleFileSelect(files);
    }
};

const handleDragOver = (e) => {
    e.preventDefault();
    isDragging.value = true;
};

const handleDragLeave = (e) => {
    e.preventDefault();
    isDragging.value = false;
};

const handleFileInput = (e) => {
    const files = e.target.files;
    if (files.length > 0) {
        handleFileSelect(files);
    }
    // Reset input value to allow selecting the same file again
    e.target.value = '';
};

const removeFile = (index) => {
    const file = uploadedFiles.value[index];
    uploadedFiles.value.splice(index, 1);

    // Revoke object URL to free memory
    if (file.preview) {
        URL.revokeObjectURL(file.preview);
    }

    emit('file-removed', file);
};

const openFileDialog = () => {
    fileInput.value?.click();
};

const clearErrors = () => {
    errors.value = [];
};

// Computed properties
const hasFiles = computed(() => uploadedFiles.value.length > 0);
const canAddMore = computed(() => uploadedFiles.value.length < props.maxFiles);
const uploadButtonText = computed(() => {
    if (isUploading.value) return 'Uploading...';
    if (hasFiles.value && !props.multiple) return 'Change File';
    if (hasFiles.value && props.multiple) return 'Add More Files';
    return 'Choose File';
});
</script>

<template>
    <div class="space-y-4">
        <!-- Upload Area -->
        <Card v-if="canAddMore" class="overflow-hidden">
            <CardContent class="p-6">
                <div
                    class="rounded-lg border-2 border-dashed p-8 text-center transition-colors"
                    :class="[
                        isDragging
                            ? 'border-blue-500 bg-blue-50'
                            : 'border-gray-300',
                        isUploading
                            ? 'pointer-events-none opacity-50'
                            : 'cursor-pointer hover:border-gray-400',
                    ]"
                    @drop="handleDrop"
                    @dragover="handleDragOver"
                    @dragleave="handleDragLeave"
                    @click="openFileDialog"
                >
                    <input
                        ref="fileInput"
                        type="file"
                        :accept="accept"
                        :multiple="multiple"
                        :disabled="isUploading"
                        class="hidden"
                        @change="handleFileInput"
                    />

                    <div class="flex flex-col items-center space-y-4">
                        <div
                            class="flex h-12 w-12 items-center justify-center rounded-full bg-gray-100"
                        >
                            <UploadIcon
                                v-if="!isUploading"
                                class="h-6 w-6 text-gray-600"
                            />
                            <Loader2Icon
                                v-else
                                class="h-6 w-6 animate-spin text-gray-600"
                            />
                        </div>

                        <div>
                            <h3 class="text-lg font-medium text-gray-900">
                                {{
                                    isUploading
                                        ? 'Uploading...'
                                        : 'Drop files here or click to browse'
                                }}
                            </h3>
                            <p class="mt-1 text-sm text-gray-500">
                                {{
                                    accept.includes('image')
                                        ? 'Images'
                                        : 'Files'
                                }}
                                up to {{ formatFileSize(maxSize) }}
                                <span v-if="maxFiles > 1">
                                    • Max {{ maxFiles }} files</span
                                >
                            </p>
                        </div>

                        <Button
                            type="button"
                            variant="outline"
                            :disabled="isUploading"
                        >
                            {{ uploadButtonText }}
                        </Button>
                    </div>
                </div>

                <!-- Upload Progress -->
                <div v-if="isUploading" class="mt-4">
                    <div
                        class="mb-2 flex items-center justify-between text-sm text-gray-600"
                    >
                        <span>Uploading...</span>
                        <span>{{ uploadProgress }}%</span>
                    </div>
                    <Progress :value="uploadProgress" class="w-full" />
                </div>
            </CardContent>
        </Card>

        <!-- Errors -->
        <Alert v-if="errors.length > 0" class="border-red-200 bg-red-50">
            <AlertCircleIcon class="h-4 w-4 text-red-600" />
            <AlertDescription class="text-red-800">
                <div class="space-y-1">
                    <div v-for="(error, index) in errors" :key="index">
                        {{ error }}
                    </div>
                </div>
            </AlertDescription>
        </Alert>

        <!-- Uploaded Files -->
        <div v-if="hasFiles" class="space-y-4">
            <h3 class="text-lg font-medium text-gray-900">Uploaded Files</h3>

            <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                <Card
                    v-for="(file, index) in uploadedFiles"
                    :key="file.id"
                    class="overflow-hidden"
                >
                    <CardContent class="p-4">
                        <div class="space-y-3">
                            <!-- Preview -->
                            <div
                                class="aspect-square overflow-hidden rounded-lg bg-gray-100"
                            >
                                <img
                                    v-if="isImageFile(file.file)"
                                    :src="file.preview || file.url"
                                    :alt="file.file.name"
                                    class="h-full w-full object-cover"
                                />
                                <div
                                    v-else-if="isVideoFile(file.file)"
                                    class="flex h-full w-full items-center justify-center"
                                >
                                    <VideoIcon
                                        class="h-12 w-12 text-gray-400"
                                    />
                                </div>
                                <div
                                    v-else
                                    class="flex h-full w-full items-center justify-center"
                                >
                                    <ImageIcon
                                        class="h-12 w-12 text-gray-400"
                                    />
                                </div>
                            </div>

                            <!-- File Info -->
                            <div class="space-y-2">
                                <div class="flex items-start justify-between">
                                    <div class="min-w-0 flex-1">
                                        <p
                                            class="truncate text-sm font-medium text-gray-900"
                                        >
                                            {{ file.file.name }}
                                        </p>
                                        <p class="text-xs text-gray-500">
                                            {{ formatFileSize(file.file.size) }}
                                        </p>
                                    </div>
                                    <Badge variant="secondary" class="ml-2">
                                        {{
                                            isImageFile(file.file)
                                                ? 'Image'
                                                : 'Video'
                                        }}
                                    </Badge>
                                </div>

                                <!-- Actions -->
                                <div class="flex gap-2">
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        class="flex-1"
                                    >
                                        <EyeIcon class="mr-1 h-4 w-4" />
                                        View
                                    </Button>
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        @click="removeFile(index)"
                                        :disabled="isUploading"
                                    >
                                        <TrashIcon class="h-4 w-4" />
                                    </Button>
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </div>

        <!-- Media Library Button -->
        <div v-if="showLibrary && !hasFiles" class="text-center">
            <Button variant="outline" @click="showLibrary = true">
                <ImageIcon class="mr-2 h-4 w-4" />
                Browse Media Library
            </Button>
        </div>
    </div>
</template>
