<script setup lang="ts">
import type { HTMLAttributes } from 'vue'
import { cn } from '@/lib/utils'

defineOptions({
  inheritAttrs: false,
})

type SelectProps = {
  class?: HTMLAttributes['class']
}

const props = defineProps<SelectProps>()
const model = defineModel<string | number | null | undefined>({ default: '' })

function onChange(event: Event) {
  const target = event.target as HTMLSelectElement
  model.value = target.value
}
</script>

<template>
  <select
    v-bind="$attrs"
    :value="model ?? ''"
    @change="onChange"
    :class="
      cn(
        'flex h-12 w-full rounded-3xl border border-input bg-background px-4 text-base text-foreground transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring disabled:cursor-not-allowed disabled:opacity-50',
        props.class,
      )
    "
  >
    <slot />
  </select>
</template>
