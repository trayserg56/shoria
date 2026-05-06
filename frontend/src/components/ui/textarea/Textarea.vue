<script setup lang="ts">
import type { HTMLAttributes } from 'vue'
import { NInput } from 'naive-ui'
import { computed, useAttrs } from 'vue'
import { cn } from '@/lib/utils'

defineOptions({
  inheritAttrs: false,
})

type TextareaProps = {
  class?: HTMLAttributes['class']
}

const props = defineProps<TextareaProps>()
const model = defineModel<string | null | undefined>({ default: '' })
const attrs = useAttrs()

const mergedClass = computed(() => cn(props.class, attrs.class as string))

const plainAttrs = computed(() => {
  const raw = { ...attrs } as Record<string, unknown>
  delete raw.class
  return raw
})

const displayValue = computed(() => model.value ?? '')

const rowCount = computed(() => {
  const r = attrs.rows
  if (typeof r === 'number') {
    return r
  }
  if (typeof r === 'string') {
    const n = Number.parseInt(r, 10)
    return Number.isFinite(n) ? n : 3
  }
  return 3
})

const rootPassthrough = computed(() => {
  const a = plainAttrs.value as Record<string, unknown>
  const next = { ...a }
  delete next.rows
  return next
})

function handleUpdateValue(raw: string) {
  model.value = raw
}
</script>

<template>
  <NInput
    type="textarea"
    :value="displayValue"
    :rows="rowCount"
    :class="mergedClass"
    v-bind="rootPassthrough"
    @update:value="handleUpdateValue"
  />
</template>
