<script setup lang="ts">
import AppLogoIcon from '@/components/AppLogoIcon.vue';
</script>

<template>
    <div class="flex items-center gap-3">
        <div
            class="from-brand-primary to-brand-accent flex aspect-square size-10 items-center justify-center rounded-xl bg-gradient-to-br shadow-lg"
        >
            <AppLogoIcon class="size-6 fill-current text-white" />
        </div>
        <div class="grid flex-1 text-left">
            <span class="text-gradient text-xl leading-tight font-bold"
                >AutoPost AI</span
            >
            <span class="text-xs text-neutral-500 dark:text-neutral-400"
                >Social Media Management</span
            >
        </div>
    </div>
</template>
