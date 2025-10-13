<script setup lang="ts">
import { computed } from 'vue';
import { ProgressRoot, type ProgressRootEmits, type ProgressRootProps } from 'radix-vue';
import { cn } from '@/lib/utils';

const props = defineProps<ProgressRootProps & { class?: string }>();
const emits = defineEmits<ProgressRootEmits>();

const delegatedProps = computed(() => {
    const { class: _, ...delegated } = props;

    return delegated;
});
</script>

<template>
    <ProgressRoot
        v-bind="delegatedProps"
        :class="
            cn(
                'relative h-4 w-full overflow-hidden rounded-full bg-secondary',
                props.class,
            )
        "
        @update:model-value="emits('update:modelValue', $event)"
    >
        <div
            class="h-full w-full flex-1 bg-primary transition-all"
            :style="`transform: translateX(-${100 - (props.modelValue ?? 0)}%)`"
        />
    </ProgressRoot>
</template>