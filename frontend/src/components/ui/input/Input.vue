<script setup lang="ts">
import type { HTMLAttributes } from 'vue'
import { NInput } from 'naive-ui'
import { computed, useAttrs } from 'vue'
import { cn } from '@/lib/utils'

defineOptions({
  inheritAttrs: false,
})

type InputProps = {
  class?: HTMLAttributes['class']
  /** Для `type="number"`: вернуть число вместо строки (напр. `v-model.number`). */
  coerceNumber?: boolean
}

const props = withDefaults(defineProps<InputProps>(), {
  coerceNumber: false,
})

const model = defineModel<string | number | null | undefined>({ default: '' })
const attrs = useAttrs()

const mergedClass = computed(() => cn(props.class, attrs.class as string))

const plainAttrs = computed(() => {
  const raw = { ...attrs } as Record<string, unknown>
  delete raw.class
  return raw
})

const displayValue = computed(() => {
  const m = model.value
  if (m === null || m === undefined) {
    return ''
  }
  return String(m)
})

const inputTypeAttr = computed(() => (attrs.type as string | undefined) ?? 'text')

const nInputRootType = computed<'text' | 'password'>(() =>
  inputTypeAttr.value === 'password' ? 'password' : 'text',
)

const nativeInputProps = computed(() => {
  const t = inputTypeAttr.value
  const a = attrs as Record<string, unknown>
  const ip: Record<string, unknown> = {}

  if (t && t !== 'password') {
    ip.type = t
  }

  const passthroughKeys = ['min', 'max', 'step', 'required', 'autocomplete', 'name', 'id', 'inputmode', 'pattern'] as const
  for (const key of passthroughKeys) {
    if (a[key] !== undefined) {
      ip[key] = a[key]
    }
  }

  Object.keys(a).forEach((k) => {
    if (k.startsWith('aria-')) {
      ip[k] = a[k]
    }
  })

  return ip
})

const rootPassthrough = computed(() => {
  const a = plainAttrs.value as Record<string, unknown>
  const next = { ...a }
  delete next.type
  for (const key of ['min', 'max', 'step', 'required', 'autocomplete', 'name', 'id', 'inputmode', 'pattern']) {
    delete next[key]
  }
  Object.keys(next).forEach((k) => {
    if (k.startsWith('aria-')) {
      delete next[k]
    }
  })
  return next
})

function handleUpdateValue(raw: string) {
  const t = inputTypeAttr.value
  if (t === 'number' && props.coerceNumber) {
    model.value = raw === '' ? 0 : Number(raw)
    return
  }
  if (t === 'number') {
    model.value = raw
    return
  }
  model.value = raw
}
</script>

<template>
  <NInput
    :type="nInputRootType"
    :value="displayValue"
    :class="mergedClass"
    :input-props="nativeInputProps"
    v-bind="rootPassthrough"
    @update:value="handleUpdateValue"
  />
</template>
