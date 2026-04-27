<script setup lang="ts">
import type { HTMLAttributes } from 'vue'
import { cn } from '@/lib/utils'

defineOptions({
  inheritAttrs: false,
})

type InputProps = {
  class?: HTMLAttributes['class']
}

const props = defineProps<InputProps>()
const model = defineModel<string | number | null | undefined>({ default: '' })

function onInput(event: Event) {
  const target = event.target as HTMLInputElement
  model.value = target.value
}
</script>

<template>
  <input
    v-bind="$attrs"
    :value="model ?? ''"
    @input="onInput"
    :class="
      cn(
        'flex h-11 w-full rounded-2xl border border-input bg-background px-4 py-2 text-base text-foreground shadow-xs transition-colors file:border-0 file:bg-transparent file:text-sm file:font-medium file:text-foreground placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring disabled:cursor-not-allowed disabled:opacity-50',
        props.class,
      )
    "
  />
</template>
