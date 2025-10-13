<script setup lang="ts">
interface Props {
  placeholder?: string
}

defineProps<Props>()
</script>

<template>
  <span class="select-value">
    <slot>{{ placeholder }}</slot>
  </span>
</template>