<script setup lang="ts">
import { ref } from 'vue'
import { RouterLink } from 'vue-router'

// ─── Типы блоков ───────────────────────────────────────────────────────────

interface BlockText {
  type: 'text'
  data: { content: string }
}

interface BlockCallout {
  type: 'callout'
  data: { type: 'info' | 'tip' | 'warning' | 'danger'; title?: string; text: string }
}

interface ChecklistItem {
  text: string
  note?: string
}

interface BlockChecklist {
  type: 'checklist'
  data: { title?: string; items: ChecklistItem[] }
}

interface StepItem {
  title: string
  description?: string
}

interface BlockSteps {
  type: 'steps'
  data: { title?: string; items: StepItem[] }
}

interface BlockImage {
  type: 'image'
  data: { src: string; alt?: string; caption?: string; link?: string; size?: 'full' | 'wide' | 'medium' | 'small' }
}

interface CtaButton {
  label: string
  url: string
  variant?: 'primary' | 'outline' | 'ghost'
}

interface BlockCta {
  type: 'cta'
  data: { title?: string; text?: string; buttons: CtaButton[]; align?: 'left' | 'center' | 'right' }
}

interface ColumnItem {
  icon?: string
  title: string
  text?: string
  link?: string
}

interface BlockColumns {
  type: 'columns'
  data: { title?: string; cols?: '2' | '3' | '4'; items: ColumnItem[] }
}

interface FaqItem {
  question: string
  answer: string
}

interface BlockFaq {
  type: 'faq'
  data: { title?: string; items: FaqItem[] }
}

interface BlockSeparator {
  type: 'separator'
  data: { style?: 'line' | 'space' | 'dots'; size?: 'sm' | 'md' | 'lg' }
}

export type PageBlock =
  | BlockText
  | BlockCallout
  | BlockChecklist
  | BlockSteps
  | BlockImage
  | BlockCta
  | BlockColumns
  | BlockFaq
  | BlockSeparator

// Filament Builder сохраняет блоки в формате [{type, data}]
// Но внутри Builder может быть и {type, data} вместо массива — поддерживаем оба
export type FilamentBlock = { type: string; data: Record<string, unknown> }

const props = defineProps<{
  blocks: FilamentBlock[]
}>()

// ─── FAQ — открытые элементы ───────────────────────────────────────────────
const openFaqItems = ref<Record<string, boolean>>({})

function toggleFaq(key: string) {
  openFaqItems.value[key] = !openFaqItems.value[key]
}

// ─── Хелперы ───────────────────────────────────────────────────────────────
function imageSizeClass(size?: string): string {
  const map: Record<string, string> = {
    full: 'spb-image--full',
    wide: 'spb-image--wide',
    medium: 'spb-image--medium',
    small: 'spb-image--small',
  }
  return map[size ?? 'full'] ?? 'spb-image--full'
}

function isExternal(url: string): boolean {
  return /^https?:\/\//.test(url)
}
</script>

<template>
  <div class="spb">
    <template v-for="(block, idx) in blocks" :key="idx">

      <!-- ── Текст ─────────────────────────────────────────────────────── -->
      <div v-if="block.type === 'text'" class="spb-text" v-html="(block.data as BlockText['data']).content" />

      <!-- ── Выноска ────────────────────────────────────────────────────── -->
      <div
        v-else-if="block.type === 'callout'"
        class="spb-callout"
        :data-type="(block.data as BlockCallout['data']).type"
      >
        <span class="spb-callout__icon" aria-hidden="true">
          <template v-if="(block.data as BlockCallout['data']).type === 'info'">💡</template>
          <template v-else-if="(block.data as BlockCallout['data']).type === 'tip'">✅</template>
          <template v-else-if="(block.data as BlockCallout['data']).type === 'warning'">⚠️</template>
          <template v-else>🚫</template>
        </span>
        <div class="spb-callout__body">
          <p v-if="(block.data as BlockCallout['data']).title" class="spb-callout__title">
            {{ (block.data as BlockCallout['data']).title }}
          </p>
          <p class="spb-callout__text">{{ (block.data as BlockCallout['data']).text }}</p>
        </div>
      </div>

      <!-- ── Checklist ──────────────────────────────────────────────────── -->
      <div v-else-if="block.type === 'checklist'" class="spb-checklist">
        <p v-if="(block.data as BlockChecklist['data']).title" class="spb-section-title">
          {{ (block.data as BlockChecklist['data']).title }}
        </p>
        <ul class="spb-checklist__list">
          <li
            v-for="(item, i) in (block.data as BlockChecklist['data']).items"
            :key="i"
            class="spb-checklist__item"
          >
            <span class="spb-checklist__mark" aria-hidden="true">✓</span>
            <span class="spb-checklist__content">
              <span class="spb-checklist__text">{{ item.text }}</span>
              <span v-if="item.note" class="spb-checklist__note">{{ item.note }}</span>
            </span>
          </li>
        </ul>
      </div>

      <!-- ── Шаги ──────────────────────────────────────────────────────── -->
      <div v-else-if="block.type === 'steps'" class="spb-steps">
        <p v-if="(block.data as BlockSteps['data']).title" class="spb-section-title">
          {{ (block.data as BlockSteps['data']).title }}
        </p>
        <ol class="spb-steps__list">
          <li
            v-for="(step, i) in (block.data as BlockSteps['data']).items"
            :key="i"
            class="spb-steps__item"
          >
            <span class="spb-steps__num" aria-hidden="true">{{ i + 1 }}</span>
            <div class="spb-steps__body">
              <p class="spb-steps__title">{{ step.title }}</p>
              <p v-if="step.description" class="spb-steps__desc">{{ step.description }}</p>
            </div>
          </li>
        </ol>
      </div>

      <!-- ── Изображение ────────────────────────────────────────────────── -->
      <figure
        v-else-if="block.type === 'image'"
        class="spb-image"
        :class="imageSizeClass((block.data as BlockImage['data']).size)"
      >
        <component
          :is="(block.data as BlockImage['data']).link ? 'a' : 'span'"
          :href="(block.data as BlockImage['data']).link ?? undefined"
          :target="isExternal((block.data as BlockImage['data']).link ?? '') ? '_blank' : undefined"
          :rel="isExternal((block.data as BlockImage['data']).link ?? '') ? 'noopener noreferrer' : undefined"
          class="spb-image__wrap"
        >
          <img
            :src="(block.data as BlockImage['data']).src"
            :alt="(block.data as BlockImage['data']).alt ?? ''"
            class="spb-image__img"
            loading="lazy"
          />
        </component>
        <figcaption v-if="(block.data as BlockImage['data']).caption" class="spb-image__caption">
          {{ (block.data as BlockImage['data']).caption }}
        </figcaption>
      </figure>

      <!-- ── CTA ───────────────────────────────────────────────────────── -->
      <div
        v-else-if="block.type === 'cta'"
        class="spb-cta"
        :data-align="(block.data as BlockCta['data']).align ?? 'left'"
      >
        <p v-if="(block.data as BlockCta['data']).title" class="spb-cta__title">
          {{ (block.data as BlockCta['data']).title }}
        </p>
        <p v-if="(block.data as BlockCta['data']).text" class="spb-cta__text">
          {{ (block.data as BlockCta['data']).text }}
        </p>
        <div class="spb-cta__buttons">
          <template v-for="(btn, i) in (block.data as BlockCta['data']).buttons" :key="i">
            <a
              v-if="isExternal(btn.url)"
              :href="btn.url"
              target="_blank"
              rel="noopener noreferrer"
              class="spb-btn"
              :data-variant="btn.variant ?? 'primary'"
            >{{ btn.label }}</a>
            <RouterLink
              v-else
              :to="btn.url"
              class="spb-btn"
              :data-variant="btn.variant ?? 'primary'"
            >{{ btn.label }}</RouterLink>
          </template>
        </div>
      </div>

      <!-- ── Карточки в сетке ───────────────────────────────────────────── -->
      <div v-else-if="block.type === 'columns'" class="spb-columns">
        <p v-if="(block.data as BlockColumns['data']).title" class="spb-section-title">
          {{ (block.data as BlockColumns['data']).title }}
        </p>
        <div
          class="spb-columns__grid"
          :style="`--spb-cols: ${(block.data as BlockColumns['data']).cols ?? 3}`"
        >
          <component
            :is="item.link ? 'a' : 'div'"
            v-for="(item, i) in (block.data as BlockColumns['data']).items"
            :key="i"
            :href="item.link ?? undefined"
            :target="item.link && isExternal(item.link) ? '_blank' : undefined"
            :rel="item.link && isExternal(item.link) ? 'noopener noreferrer' : undefined"
            class="spb-card"
            :class="{ 'spb-card--link': !!item.link }"
          >
            <span v-if="item.icon" class="spb-card__icon" aria-hidden="true">{{ item.icon }}</span>
            <p class="spb-card__title">{{ item.title }}</p>
            <p v-if="item.text" class="spb-card__text">{{ item.text }}</p>
          </component>
        </div>
      </div>

      <!-- ── FAQ ───────────────────────────────────────────────────────── -->
      <div v-else-if="block.type === 'faq'" class="spb-faq">
        <p v-if="(block.data as BlockFaq['data']).title" class="spb-section-title">
          {{ (block.data as BlockFaq['data']).title }}
        </p>
        <div class="spb-faq__list">
          <div
            v-for="(item, i) in (block.data as BlockFaq['data']).items"
            :key="i"
            class="spb-faq__item"
            :class="{ 'spb-faq__item--open': openFaqItems[`${idx}-${i}`] }"
          >
            <button
              type="button"
              class="spb-faq__q"
              :aria-expanded="openFaqItems[`${idx}-${i}`] ?? false"
              @click="toggleFaq(`${idx}-${i}`)"
            >
              <span>{{ item.question }}</span>
              <span class="spb-faq__arrow" aria-hidden="true">▾</span>
            </button>
            <div v-show="openFaqItems[`${idx}-${i}`]" class="spb-faq__a">
              <p>{{ item.answer }}</p>
            </div>
          </div>
        </div>
      </div>

      <!-- ── Разделитель ────────────────────────────────────────────────── -->
      <div
        v-else-if="block.type === 'separator'"
        class="spb-separator"
        :data-style="(block.data as BlockSeparator['data']).style ?? 'line'"
        :data-size="(block.data as BlockSeparator['data']).size ?? 'md'"
        aria-hidden="true"
      >
        <span v-if="(block.data as BlockSeparator['data']).style === 'dots'" class="spb-separator__dots">· · ·</span>
      </div>

    </template>
  </div>
</template>

<style scoped>
/* ─── Обёртка ─────────────────────────────────────────────────────────────── */
.spb {
  display: flex;
  flex-direction: column;
  gap: 32px;
}

/* ─── Общий заголовок раздела ────────────────────────────────────────────── */
.spb-section-title {
  margin: 0 0 16px;
  font-size: 20px;
  font-weight: 700;
  line-height: 1.3;
}

/* ─── Текстовый блок ─────────────────────────────────────────────────────── */
.spb-text {
  color: var(--foreground);
  line-height: 1.7;
}

.spb-text :deep(h2) { margin: 24px 0 10px; font-size: 22px; font-weight: 700; }
.spb-text :deep(h3) { margin: 20px 0 8px; font-size: 18px; font-weight: 700; }
.spb-text :deep(h4) { margin: 16px 0 6px; font-size: 16px; font-weight: 700; }
.spb-text :deep(p)  { margin: 0 0 14px; }
.spb-text :deep(p:last-child) { margin-bottom: 0; }
.spb-text :deep(ul), .spb-text :deep(ol) { padding-left: 22px; margin: 0 0 14px; }
.spb-text :deep(li) { margin-bottom: 6px; }
.spb-text :deep(a)  { color: var(--primary); text-decoration: underline; }
.spb-text :deep(strong) { font-weight: 700; }
.spb-text :deep(code) {
  padding: 2px 6px;
  border-radius: 4px;
  background: var(--muted);
  font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
  font-size: 0.9em;
}
.spb-text :deep(blockquote) {
  margin: 0 0 14px;
  padding: 12px 16px;
  border-left: 4px solid var(--primary);
  background: color-mix(in srgb, var(--primary), transparent 92%);
  border-radius: 0 8px 8px 0;
  font-style: italic;
}

/* ─── Выноска ────────────────────────────────────────────────────────────── */
.spb-callout {
  display: flex;
  gap: 14px;
  padding: 16px 20px;
  border-radius: 14px;
  border: 1px solid transparent;
  line-height: 1.55;
}

.spb-callout[data-type='info'] {
  background: color-mix(in srgb, #3b82f6, transparent 90%);
  border-color: color-mix(in srgb, #3b82f6, transparent 70%);
}
.spb-callout[data-type='tip'] {
  background: color-mix(in srgb, #22c55e, transparent 90%);
  border-color: color-mix(in srgb, #22c55e, transparent 70%);
}
.spb-callout[data-type='warning'] {
  background: color-mix(in srgb, #f59e0b, transparent 88%);
  border-color: color-mix(in srgb, #f59e0b, transparent 65%);
}
.spb-callout[data-type='danger'] {
  background: color-mix(in srgb, #ef4444, transparent 90%);
  border-color: color-mix(in srgb, #ef4444, transparent 70%);
}

.spb-callout__icon { font-size: 22px; flex-shrink: 0; line-height: 1.4; }
.spb-callout__body { flex: 1; }
.spb-callout__title { margin: 0 0 4px; font-weight: 700; font-size: 15px; }
.spb-callout__text  { margin: 0; font-size: 14px; }

/* ─── Checklist ──────────────────────────────────────────────────────────── */
.spb-checklist__list { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: 10px; }

.spb-checklist__item {
  display: flex;
  gap: 12px;
  align-items: flex-start;
}

.spb-checklist__mark {
  flex-shrink: 0;
  width: 24px;
  height: 24px;
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: 50%;
  background: color-mix(in srgb, var(--primary), transparent 85%);
  color: var(--primary);
  font-size: 13px;
  font-weight: 700;
  margin-top: 1px;
}

.spb-checklist__content { display: flex; flex-direction: column; gap: 2px; }
.spb-checklist__text { font-size: 15px; line-height: 1.5; }
.spb-checklist__note { font-size: 13px; color: var(--muted-foreground); }

/* ─── Шаги ───────────────────────────────────────────────────────────────── */
.spb-steps__list { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: 16px; }

.spb-steps__item {
  display: flex;
  gap: 16px;
  align-items: flex-start;
}

.spb-steps__num {
  flex-shrink: 0;
  width: 36px;
  height: 36px;
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: 50%;
  background: var(--primary);
  color: var(--primary-foreground, #fff);
  font-size: 15px;
  font-weight: 800;
}

.spb-steps__body { padding-top: 6px; }
.spb-steps__title { margin: 0 0 4px; font-size: 16px; font-weight: 700; }
.spb-steps__desc  { margin: 0; font-size: 14px; color: var(--muted-foreground); line-height: 1.5; }

/* ─── Изображение ────────────────────────────────────────────────────────── */
.spb-image { margin: 0; display: flex; flex-direction: column; align-items: center; gap: 10px; }

.spb-image--full  { width: 100%; }
.spb-image--wide  { width: min(80%, 100%); }
.spb-image--medium { width: min(60%, 100%); }
.spb-image--small { width: min(40%, 100%); }

.spb-image__wrap { display: block; width: 100%; }
.spb-image__img  { display: block; width: 100%; height: auto; border-radius: 12px; object-fit: cover; }
.spb-image__caption { font-size: 13px; color: var(--muted-foreground); text-align: center; }

/* ─── CTA ────────────────────────────────────────────────────────────────── */
.spb-cta {
  padding: 32px 28px;
  border-radius: 20px;
  background: color-mix(in srgb, var(--primary), transparent 94%);
  border: 1px solid color-mix(in srgb, var(--primary), transparent 78%);
}

.spb-cta[data-align='center'] { text-align: center; }
.spb-cta[data-align='right']  { text-align: right; }

.spb-cta__title { margin: 0 0 8px; font-size: 22px; font-weight: 700; }
.spb-cta__text  { margin: 0 0 20px; font-size: 15px; color: var(--muted-foreground); line-height: 1.55; }

.spb-cta__buttons {
  display: flex;
  flex-wrap: wrap;
  gap: 10px;
}
.spb-cta[data-align='center'] .spb-cta__buttons { justify-content: center; }
.spb-cta[data-align='right']  .spb-cta__buttons { justify-content: flex-end; }

/* ─── Кнопки ─────────────────────────────────────────────────────────────── */
.spb-btn {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  min-height: 46px;
  padding: 0 22px;
  border-radius: 12px;
  font-size: 15px;
  font-weight: 700;
  text-decoration: none;
  cursor: pointer;
  transition: opacity 0.15s, box-shadow 0.15s;
}

.spb-btn[data-variant='primary'] {
  background: var(--primary);
  color: var(--primary-foreground, #fff);
}
.spb-btn[data-variant='primary']:hover { opacity: 0.88; }

.spb-btn[data-variant='outline'] {
  background: transparent;
  border: 2px solid var(--primary);
  color: var(--primary);
}
.spb-btn[data-variant='outline']:hover { background: color-mix(in srgb, var(--primary), transparent 90%); }

.spb-btn[data-variant='ghost'] {
  background: transparent;
  color: var(--primary);
}
.spb-btn[data-variant='ghost']:hover { background: color-mix(in srgb, var(--primary), transparent 90%); }

/* ─── Карточки в сетке ───────────────────────────────────────────────────── */
.spb-columns__grid {
  display: grid;
  gap: 16px;
  grid-template-columns: repeat(var(--spb-cols, 3), 1fr);
}

@media (max-width: 720px) {
  .spb-columns__grid { grid-template-columns: 1fr 1fr; }
}
@media (max-width: 480px) {
  .spb-columns__grid { grid-template-columns: 1fr; }
}

.spb-card {
  display: flex;
  flex-direction: column;
  gap: 8px;
  padding: 20px;
  border-radius: 16px;
  border: 1px solid var(--border);
  background: var(--background);
  text-decoration: none;
  color: inherit;
}

.spb-card--link { transition: box-shadow 0.15s, border-color 0.15s; cursor: pointer; }
.spb-card--link:hover { border-color: var(--primary); box-shadow: 0 4px 16px color-mix(in srgb, var(--primary), transparent 82%); }

.spb-card__icon  { font-size: 28px; line-height: 1; }
.spb-card__title { margin: 0; font-size: 15px; font-weight: 700; }
.spb-card__text  { margin: 0; font-size: 13px; color: var(--muted-foreground); line-height: 1.5; }

/* ─── FAQ ────────────────────────────────────────────────────────────────── */
.spb-faq__list { display: flex; flex-direction: column; gap: 0; }

.spb-faq__item {
  border-bottom: 1px solid var(--border);
}
.spb-faq__item:first-child { border-top: 1px solid var(--border); }

.spb-faq__q {
  width: 100%;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  padding: 16px 4px;
  background: none;
  border: none;
  font: inherit;
  font-size: 15px;
  font-weight: 600;
  text-align: left;
  cursor: pointer;
  color: var(--foreground);
}

.spb-faq__arrow {
  flex-shrink: 0;
  font-size: 18px;
  transition: transform 0.2s;
  color: var(--muted-foreground);
}
.spb-faq__item--open .spb-faq__arrow { transform: rotate(180deg); }

.spb-faq__a {
  padding: 0 4px 16px;
  font-size: 14px;
  color: var(--muted-foreground);
  line-height: 1.6;
}
.spb-faq__a p { margin: 0; }

/* ─── Разделитель ────────────────────────────────────────────────────────── */
.spb-separator { display: flex; align-items: center; justify-content: center; }

.spb-separator[data-size='sm'] { padding: 8px 0; }
.spb-separator[data-size='md'] { padding: 16px 0; }
.spb-separator[data-size='lg'] { padding: 32px 0; }

.spb-separator[data-style='line']::before {
  content: '';
  display: block;
  width: 100%;
  height: 1px;
  background: var(--border);
}

.spb-separator__dots {
  font-size: 22px;
  letter-spacing: 0.25em;
  color: var(--muted-foreground);
}
</style>
