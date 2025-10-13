<script setup lang="ts">
import { cn } from '@/lib/utils'
import { ChevronDownIcon } from 'lucide-vue-next'
import { computed, type HTMLAttributes } from 'vue'

const props = defineProps<{
  placeholder?: string
  disabled?: boolean
  class?: HTMLAttributes['class']
  modelValue?: string
}>()

const emits = defineEmits<{
  'update:modelValue': [value: string]
}>()
</script>

<template>
  <div class="relative">
    <select
      :class="cn(
        'flex h-9 w-full items-center justify-between whitespace-nowrap rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-sm ring-offset-background placeholder:text-muted-foreground focus:outline-none focus:ring-1 focus:ring-ring disabled:cursor-not-allowed disabled:opacity-50 appearance-none',
        props.class,
      )"
      :disabled="disabled"
      :value="modelValue"
      @change="emits('update:modelValue', ($event.target as HTMLSelectElement).value)"
    >
      <option value="" disabled selected v-if="placeholder">{{ placeholder }}</option>
      <slot />
    </select>
    <ChevronDownIcon class="absolute right-3 top-1/2 transform -translate-y-1/2 w-4 h-4 opacity-50 pointer-events-none" />
  </div>
</template>