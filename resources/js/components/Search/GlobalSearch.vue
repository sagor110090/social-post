<script setup lang="ts">
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Search } from 'lucide-vue-next';
import { onMounted, onUnmounted, ref } from 'vue';

const isOpen = ref(false);
const searchQuery = ref('');

const handleOpenChange = (open: boolean) => {
    isOpen.value = open;
    if (open) {
        // Focus input when dialog opens
        setTimeout(() => {
            const input = document.querySelector(
                '#global-search-input',
            ) as HTMLInputElement;
            input?.focus();
        }, 100);
    }
};

const handleSearch = (event: KeyboardEvent) => {
    if ((event.metaKey || event.ctrlKey) && event.key === 'k') {
        event.preventDefault();
        isOpen.value = true;
    }
};

// Add keyboard listener
onMounted(() => {
    document.addEventListener('keydown', handleSearch);
});

onUnmounted(() => {
    document.removeEventListener('keydown', handleSearch);
});
</script>

<template>
    <Dialog :open="isOpen" @update:open="handleOpenChange">
        <DialogTrigger as-child>
            <Button
                variant="ghost"
                size="icon"
                class="group h-9 w-9 cursor-pointer"
            >
                <Search class="size-5 opacity-80 group-hover:opacity-100" />
            </Button>
        </DialogTrigger>
        <DialogContent class="sm:max-w-[425px]">
            <DialogHeader>
                <DialogTitle>Global Search</DialogTitle>
                <DialogDescription>
                    Search for posts, accounts, analytics, and more...
                </DialogDescription>
            </DialogHeader>
            <div class="flex items-center space-x-2 py-4">
                <Search class="h-4 w-4 text-muted-foreground" />
                <Input
                    id="global-search-input"
                    v-model="searchQuery"
                    placeholder="Type to search..."
                    class="flex-1"
                />
            </div>
            <div class="space-y-2">
                <div class="text-sm font-medium text-muted-foreground">
                    Quick Actions
                </div>
                <div class="grid gap-2">
                    <Button
                        variant="ghost"
                        class="justify-start"
                        @click="isOpen = false"
                    >
                        Create new post
                    </Button>
                    <Button
                        variant="ghost"
                        class="justify-start"
                        @click="isOpen = false"
                    >
                        View analytics
                    </Button>
                    <Button
                        variant="ghost"
                        class="justify-start"
                        @click="isOpen = false"
                    >
                        Manage social accounts
                    </Button>
                </div>
            </div>
        </DialogContent>
    </Dialog>
</template>
