<script setup lang="ts">
import { Button } from '@/components/ui/button';
import posts from '@/routes/social/posts';
import { Link } from '@inertiajs/vue3';
import { PlusCircle } from 'lucide-vue-next';
</script>

<template>
    <Button as-child size="sm" class="gap-x-2">
        <Link :href="posts.create()">
            <PlusCircle class="h-4 w-4" />
            Create Post
        </Link>
    </Button>
</template>
