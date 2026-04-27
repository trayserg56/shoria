<script setup lang="ts">
import type { HTMLAttributes } from 'vue'
import { cn } from '@/lib/utils'

defineOptions({
  inheritAttrs: false,
})

type TextareaProps = {
  class?: HTMLAttributes['class']
}

const props = defineProps<TextareaProps>()
const model = defineModel<string | null | undefined>({ default: '' })

function onInput(event: Event) {
  const target = event.target as HTMLTextAreaElement
  model.value = target.value
}
</script>

<template>
  <textarea
    v-bind="$attrs"
    :value="model ?? ''"
    @input="onInput"
    :class="
      cn(
        'flex min-h-24 w-full rounded-2xl border border-input bg-background px-4 py-3 text-sm text-foreground shadow-xs transition-colors placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring disabled:cursor-not-allowed disabled:opacity-50',
        props.class,
      )
    "
  />
</template>
