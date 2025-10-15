<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { ref, watch } from 'vue';

interface FilterOptions {
    platform?: string;
    status?: string;
    startDate?: string;
    endDate?: string;
    search?: string;
}

interface Props {
    filters: FilterOptions;
    onFiltersChange: (filters: FilterOptions) => void;
}

const props = defineProps<Props>();

const localFilters = ref<FilterOptions>({ ...props.filters });

const platforms = [
    { value: '', label: 'All Platforms' },
    { value: 'facebook', label: 'Facebook' },
    { value: 'instagram', label: 'Instagram' },
    { value: 'twitter', label: 'Twitter' },
    { value: 'linkedin', label: 'LinkedIn' },
];

const statuses = [
    { value: '', label: 'All Statuses' },
    { value: 'pending', label: 'Pending' },
    { value: 'processing', label: 'Processing' },
    { value: 'processed', label: 'Processed' },
    { value: 'failed', label: 'Failed' },
];

const emitFilters = () => {
    props.onFiltersChange({ ...localFilters.value });
};

const resetFilters = () => {
    localFilters.value = {
        platform: '',
        status: '',
        startDate: '',
        endDate: '',
        search: '',
    };
    emitFilters();
};

// Watch for changes and emit
watch(localFilters, emitFilters, { deep: true });

// Watch for props changes and update local
watch(
    () => props.filters,
    (newFilters) => {
        localFilters.value = { ...newFilters };
    },
    { deep: true },
);
</script>

<template>
    <Card>
        <CardHeader>
            <CardTitle class="text-lg">Filter Events</CardTitle>
        </CardHeader>
        <CardContent class="space-y-4">
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3">
                <div class="space-y-2">
                    <Label for="search">Search</Label>
                    <Input
                        id="search"
                        v-model="localFilters.search"
                        placeholder="Search events..."
                    />
                </div>

                <div class="space-y-2">
                    <Label for="platform">Platform</Label>
                    <Select v-model="localFilters.platform">
                        <SelectTrigger>
                            <SelectValue placeholder="Select platform" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem
                                v-for="platform in platforms"
                                :key="platform.value"
                                :value="platform.value"
                            >
                                {{ platform.label }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                </div>

                <div class="space-y-2">
                    <Label for="status">Status</Label>
                    <Select v-model="localFilters.status">
                        <SelectTrigger>
                            <SelectValue placeholder="Select status" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem
                                v-for="status in statuses"
                                :key="status.value"
                                :value="status.value"
                            >
                                {{ status.label }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                </div>

                <div class="space-y-2">
                    <Label for="startDate">Start Date</Label>
                    <Input
                        id="startDate"
                        v-model="localFilters.startDate"
                        type="date"
                    />
                </div>

                <div class="space-y-2">
                    <Label for="endDate">End Date</Label>
                    <Input
                        id="endDate"
                        v-model="localFilters.endDate"
                        type="date"
                    />
                </div>

                <div class="flex items-end">
                    <Button
                        variant="outline"
                        @click="resetFilters"
                        class="w-full"
                    >
                        Reset Filters
                    </Button>
                </div>
            </div>
        </CardContent>
    </Card>
</template>
