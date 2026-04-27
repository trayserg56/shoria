<script setup lang="ts">
import type { HTMLAttributes } from 'vue'
import { CheckboxIndicator, CheckboxRoot } from 'reka-ui'
import { cn } from '@/lib/utils'

const props = defineProps<{
  checked?: boolean
  disabled?: boolean
  class?: HTMLAttributes['class']
}>()
</script>

<template>
  <CheckboxRoot
    :checked="props.checked"
    :disabled="props.disabled"
    :class="
      cn(
        'peer h-4 w-4 shrink-0 rounded-sm border border-border bg-background shadow-xs focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50',
        props.checked ? 'border-primary bg-primary text-primary-foreground' : '',
        props.class,
      )
    "
  >
    <CheckboxIndicator v-if="props.checked" force-mount class="flex items-center justify-center text-current">
      <svg viewBox="0 0 24 24" class="h-3.5 w-3.5 fill-none stroke-current stroke-[3]">
        <path d="M20 6 9 17l-5-5" />
      </svg>
    </CheckboxIndicator>
  </CheckboxRoot>
</template>
