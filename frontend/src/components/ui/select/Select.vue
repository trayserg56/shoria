<script setup lang="ts">
import type { HTMLAttributes } from 'vue'
import { NSelect } from 'naive-ui'
import type { SelectOption } from 'naive-ui'
import { computed, useAttrs } from 'vue'
import { cn } from '@/lib/utils'

defineOptions({
  inheritAttrs: false,
})

type SelectProps = {
  options: SelectOption[]
  class?: HTMLAttributes['class']
}

const props = defineProps<SelectProps>()
const model = defineModel<string | number | null>({ default: '' })
const attrs = useAttrs()

const mergedClass = computed(() => cn(props.class, attrs.class as string))

const restAttrs = computed(() => {
  const a = { ...attrs } as Record<string, unknown>
  delete a.class
  return a
})
</script>

<template>
  <NSelect
    v-model:value="model"
    :options="props.options"
    :class="mergedClass"
    v-bind="restAttrs"
  />
</template>
