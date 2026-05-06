<script setup lang="ts">
/**
 * Полноэкранный просмотр галереи: клик по фону или Esc — закрыть.
 * Если lockBodyScroll=true (страница товара) — временно блокируем прокрутку страницы.
 */
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue'
import { ChevronLeft, ChevronRight, X } from 'lucide-vue-next'
import { applyImageFallback, resolveImageSrc } from '@/lib/image-fallback'

export type ProductImageSlide = {
  url: string
  alt: string
}

const props = withDefaults(
  defineProps<{
    slides: ProductImageSlide[]
    /** При открытии показываем кадр с этим индексом */
    anchorIndex?: number
    /** Для модалки поверх уже залоченного body не трогаем overflow */
    lockBodyScroll?: boolean
  }>(),
  {
    anchorIndex: 0,
    lockBodyScroll: true,
  },
)

const open = defineModel<boolean>({ default: false })

const emit = defineEmits<{
  /** Синхронизация с превью (мииниатюрами) снаружи */
  syncIndex: [index: number]
}>()

const rootRef = ref<HTMLElement | null>(null)

const activeIndex = ref(0)

const count = computed(() => props.slides.length)

const currentSlide = computed((): ProductImageSlide | null => {
  const list = props.slides
  if (!list.length) {
    return null
  }

  const i = Math.min(Math.max(0, activeIndex.value), list.length - 1)

  return list[i] ?? null
})

let savedHtmlOverflow = ''
let savedBodyOverflow = ''
let savedBodyPaddingRight = ''

function lockScroll(): void {
  const html = document.documentElement
  const body = document.body
  const gap = Math.max(0, window.innerWidth - html.clientWidth)

  savedHtmlOverflow = html.style.overflow
  savedBodyOverflow = body.style.overflow
  savedBodyPaddingRight = body.style.paddingRight

  html.style.overflow = 'hidden'
  body.style.overflow = 'hidden'

  if (gap > 0) {
    body.style.paddingRight = `${gap}px`
  }
}

function unlockScroll(): void {
  const html = document.documentElement
  const body = document.body

  html.style.overflow = savedHtmlOverflow
  body.style.overflow = savedBodyOverflow
  body.style.paddingRight = savedBodyPaddingRight
}

watch(
  open,
  (isOpen) => {
    if (!isOpen) {
      window.removeEventListener('keydown', onWindowKeyDown, true)
      if (props.lockBodyScroll) {
        unlockScroll()
      }

      return
    }

    const maxIdx = Math.max(0, count.value - 1)
    activeIndex.value = Math.min(Math.max(0, props.anchorIndex ?? 0), maxIdx)

    window.addEventListener('keydown', onWindowKeyDown, true)

    if (props.lockBodyScroll) {
      lockScroll()
    }

    void nextTick(() => {
      rootRef.value?.focus()
      emit('syncIndex', activeIndex.value)
    })
  },
  { flush: 'post' },
)

watch(activeIndex, (i) => {
  if (!open.value) {
    return
  }

  emit('syncIndex', i)
})

onBeforeUnmount(() => {
  window.removeEventListener('keydown', onWindowKeyDown, true)
  if (open.value && props.lockBodyScroll) {
    unlockScroll()
  }
})

function close(): void {
  open.value = false
}

function onWindowKeyDown(e: KeyboardEvent): void {
  if (!open.value) {
    return
  }

  if (e.key === 'Escape') {
    e.preventDefault()
    e.stopPropagation()
    close()

    return
  }

  if (!props.slides.length) {
    return
  }

  if (e.key === 'ArrowRight') {
    e.preventDefault()
    goNext()

    return
  }

  if (e.key === 'ArrowLeft') {
    e.preventDefault()
    goPrev()
  }
}

function goPrev(): void {
  if (count.value < 2) {
    return
  }

  activeIndex.value = (activeIndex.value - 1 + count.value) % count.value
}

function goNext(): void {
  if (count.value < 2) {
    return
  }

  activeIndex.value = (activeIndex.value + 1) % count.value
}
</script>

<template>
  <Teleport to="body">
    <div
      v-if="open"
      ref="rootRef"
      class="pil"
      tabindex="-1"
      role="dialog"
      aria-modal="true"
      :aria-label="currentSlide?.alt || 'Изображение товара'"
    >
      <div class="pil__backdrop" @click="close" />

      <button type="button" class="pil__close" aria-label="Закрыть полноэкранный просмотр" @click="close">
        <X :size="26" :stroke-width="2" aria-hidden="true" />
      </button>

      <template v-if="count > 1">
        <button
          type="button"
          class="pil__nav pil__nav--prev"
          aria-label="Предыдущее фото"
          @click.stop.prevent="goPrev"
        >
          <ChevronLeft :size="40" :stroke-width="2" aria-hidden="true" />
        </button>
        <button
          type="button"
          class="pil__nav pil__nav--next"
          aria-label="Следующее фото"
          @click.stop.prevent="goNext"
        >
          <ChevronRight :size="40" :stroke-width="2" aria-hidden="true" />
        </button>
      </template>

      <div class="pil__frame">
        <img
          v-if="currentSlide"
          class="pil__img"
          :src="resolveImageSrc(currentSlide.url)"
          :alt="currentSlide.alt"
          @error="applyImageFallback"
        />
      </div>

      <p v-if="count > 1" class="pil__counter">{{ activeIndex + 1 }} / {{ count }}</p>
    </div>
  </Teleport>
</template>

<style scoped>
.pil {
  position: fixed;
  inset: 0;
  z-index: 4000;
  display: grid;
  place-items: center;
  outline: none;
}

.pil__backdrop {
  position: absolute;
  inset: 0;
  background: rgb(13 17 35 / 88%);
}

.pil__close {
  position: absolute;
  top: max(14px, env(safe-area-inset-top));
  right: max(14px, env(safe-area-inset-right));
  z-index: 2;
  display: flex;
  align-items: center;
  justify-content: center;
  width: 48px;
  height: 48px;
  border: none;
  border-radius: 999px;
  background: rgb(255 255 255 / 12%);
  color: #f4f7ff;
  cursor: pointer;
  transition: background 0.15s ease, transform 0.12s ease;
}

.pil__close:hover {
  background: rgb(255 255 255 / 20%);
}

.pil__close:focus-visible {
  outline: 2px solid rgb(255 255 255 / 80%);
  outline-offset: 2px;
}

.pil__nav {
  position: absolute;
  top: 50%;
  z-index: 2;
  translate: 0 -50%;
  width: 48px;
  height: 48px;
  display: grid;
  place-items: center;
  border: none;
  border-radius: 999px;
  background: rgb(255 255 255 / 10%);
  color: #f4f7ff;
  cursor: pointer;
  transition: background 0.15s ease;
}

.pil__nav:hover {
  background: rgb(255 255 255 / 18%);
}

.pil__nav:focus-visible {
  outline: 2px solid rgb(255 255 255 / 72%);
  outline-offset: 2px;
}

.pil__nav--prev {
  left: max(10px, env(safe-area-inset-left));
}

.pil__nav--next {
  right: max(10px, env(safe-area-inset-right));
}

.pil__frame {
  position: relative;
  z-index: 1;
  box-sizing: border-box;
  width: min(96vw, 1200px);
  height: min(88vh, 900px);
  padding: 12px max(54px, 4vw);
  pointer-events: none;
}

@media (max-width: 640px) {
  .pil__frame {
    width: min(94vw, 1200px);
    height: min(76vh, 900px);
    padding-inline: max(42px, 8vw);
  }
}

.pil__img {
  width: 100%;
  height: 100%;
  object-fit: contain;
  pointer-events: none;
  user-select: none;
}

.pil__counter {
  position: absolute;
  bottom: max(16px, env(safe-area-inset-bottom));
  left: 50%;
  translate: -50%;
  margin: 0;
  padding: 6px 12px;
  border-radius: 999px;
  font-size: 13px;
  font-weight: 600;
  color: rgb(246 247 251 / 86%);
  background: rgb(255 255 255 / 8%);
}
</style>
