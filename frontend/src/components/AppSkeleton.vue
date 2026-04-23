<script setup lang="ts">
const props = withDefaults(defineProps<{
  width?: string
  height?: string
  radius?: string
  inline?: boolean
}>(), {
  width: '100%',
  height: '16px',
  radius: '14px',
  inline: false,
})
</script>

<template>
  <span
    class="app-skeleton"
    :class="{ 'app-skeleton--inline': inline }"
    :style="{
      width,
      height,
      borderRadius: radius,
    }"
    aria-hidden="true"
  />
</template>

<style scoped>
.app-skeleton {
  position: relative;
  display: block;
  overflow: hidden;
  background: linear-gradient(90deg, rgb(240 233 223 / 96%), rgb(252 247 240 / 98%), rgb(240 233 223 / 96%));
}

.app-skeleton--inline {
  display: inline-block;
  vertical-align: middle;
}

.app-skeleton::after {
  content: '';
  position: absolute;
  inset: 0;
  transform: translateX(-100%);
  background: linear-gradient(90deg, transparent, rgb(255 255 255 / 62%), transparent);
  animation: skeleton-shimmer 1.4s ease-in-out infinite;
}

@keyframes skeleton-shimmer {
  100% {
    transform: translateX(100%);
  }
}
</style>
