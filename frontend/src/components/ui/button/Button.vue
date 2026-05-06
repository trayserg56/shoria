<script setup lang="ts">
import type { HTMLAttributes } from 'vue'
import { NButton } from 'naive-ui'
import { computed, useAttrs } from 'vue'
import { cn } from '@/lib/utils'
import type { ButtonVariants } from './buttonVariants'

defineOptions({
  inheritAttrs: false,
})

type ButtonProps = {
  variant?: ButtonVariants['variant']
  size?: ButtonVariants['size']
  class?: HTMLAttributes['class']
}

const props = withDefaults(defineProps<ButtonProps>(), {
  variant: 'default',
  size: 'default',
})

const attrs = useAttrs()

const forwarded = computed(() => {
  const raw = { ...attrs } as Record<string, unknown>
  delete raw.class
  delete raw.type

  return raw
})

const attrType = computed(() => {
  const t = attrs.type

  if (t === 'submit' || t === 'reset' || t === 'button') {
    return t
  }

  return 'button'
})

const mergedClass = computed(() => cn(props.class, attrs.class as string))

const naiveType = computed(() => {
  if (props.variant === 'destructive') {
    return 'error'
  }

  if (props.variant === 'default') {
    return 'primary'
  }

  return 'default'
})

const tertiary = computed(() => props.variant === 'outline')
const quaternary = computed(() => props.variant === 'ghost')

const naiveSize = computed(() => {
  switch (props.size) {
    case 'sm':
      return 'small'
    case 'lg':
      return 'large'
    default:
      return 'medium'
  }
})

const circle = computed(() => props.size === 'icon')
</script>

<template>
  <NButton
    :type="naiveType"
    :size="naiveSize"
    :circle="circle"
    :tertiary="tertiary"
    :quaternary="quaternary"
    :attr-type="attrType"
    :class="mergedClass"
    v-bind="forwarded"
  >
    <slot />
  </NButton>
</template>
