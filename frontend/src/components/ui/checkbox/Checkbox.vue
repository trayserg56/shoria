<script setup lang="ts">
import type { HTMLAttributes } from 'vue'
import { NCheckbox } from 'naive-ui'
import { cn } from '@/lib/utils'

defineOptions({
  inheritAttrs: false,
})

const props = defineProps<{
  checked?: boolean
  disabled?: boolean
  class?: HTMLAttributes['class']
}>()
</script>

<template>
  <NCheckbox
    :checked="!!props.checked"
    :disabled="!!props.disabled"
    :class="cn(props.class)"
    style="pointer-events: none"
    :tabindex="-1"
  />
</template>
