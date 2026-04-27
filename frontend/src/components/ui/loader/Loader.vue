<script setup lang="ts">
import { Loader2 } from 'lucide-vue-next'

withDefaults(
  defineProps<{
    label?: string
    size?: number
    compact?: boolean
  }>(),
  {
    label: 'Загрузка',
    size: 18,
    compact: false,
  },
)
</script>

<template>
  <span class="ui-loader" :class="{ 'ui-loader--compact': compact }" role="status" aria-live="polite">
    <Loader2 class="ui-loader__icon" :size="size" />
    <span v-if="label" class="ui-loader__label">{{ label }}</span>
  </span>
</template>

<style scoped>
.ui-loader {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  color: #0f172a;
  font-size: 13px;
  font-weight: 600;
  letter-spacing: 0.01em;
}

.ui-loader--compact {
  gap: 0;
}

.ui-loader__icon {
  animation: ui-loader-spin 1s linear infinite;
}

@keyframes ui-loader-spin {
  to {
    transform: rotate(360deg);
  }
}
</style>
