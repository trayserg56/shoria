import { mergeStoreTheme, type StoreTheme } from '@/lib/site-settings'

const BODY_FONT_STACK: Record<string, string> = {
  manrope: `'Manrope', 'Segoe UI', sans-serif`,
  rubik: `'Rubik', 'Segoe UI', sans-serif`,
  inter: `'Inter', 'Segoe UI', sans-serif`,
  system: `system-ui, -apple-system, 'Segoe UI', sans-serif`,
}

const DISPLAY_FONT_STACK: Record<string, string> = {
  bebas: `'Bebas Neue', 'Arial Narrow', sans-serif`,
  manrope: `'Manrope', 'Segoe UI', sans-serif`,
  oswald: `'Oswald', 'Arial Narrow', sans-serif`,
  system: `system-ui, -apple-system, sans-serif`,
}

export function isHomeSectionEnabled(theme: StoreTheme | undefined, sectionKey: string): boolean {
  if (!theme) {
    return true
  }
  const merged = mergeStoreTheme(theme)
  const row = merged.home.sections[sectionKey]
  if (!row) {
    return true
  }
  return row.enabled !== false
}

export function applyStoreTheme(theme: StoreTheme | undefined, root: HTMLElement = document.documentElement): void {
  const t = mergeStoreTheme(theme)

  const bodyStack = BODY_FONT_STACK[t.general.body_font] ?? BODY_FONT_STACK.manrope!
  const displayStack = DISPLAY_FONT_STACK[t.general.display_font] ?? DISPLAY_FONT_STACK.bebas!

  root.style.setProperty('--layout-max-width', `${t.general.container_width_px}px`)
  const accountW = Math.min(1280, Math.round(t.general.container_width_px * 0.85))
  root.style.setProperty('--layout-account-max-width', `${accountW}px`)

  root.style.setProperty('--font-body', bodyStack)
  root.style.setProperty('--font-display', displayStack)
  root.style.setProperty('--site-base-font-size', `${t.general.base_font_size_px}px`)

  const headingFamily = t.general.use_display_for_headings ? displayStack : bodyStack
  root.style.setProperty('--heading-font-family', headingFamily)
  root.style.setProperty('--heading-font-weight', String(t.general.heading_weight))

  root.style.setProperty('--store-button-radius', `${t.general.button_radius_px}px`)

  root.style.setProperty('--primary', t.general.primary_hex)
  root.style.setProperty('--primary-foreground', t.general.primary_foreground_hex)
  root.style.setProperty('--ring', t.general.primary_hex)

  if (t.catalog.grid_density === 'compact') {
    root.setAttribute('data-catalog-density', 'compact')
  } else {
    root.removeAttribute('data-catalog-density')
  }
}
