import type { InjectionKey, Ref } from 'vue'

export const siteSettingsInjectionKey: InjectionKey<Ref<SiteSettingsPayload>> =
  Symbol('siteSettings')

export type SiteFeatureFlags = {
  loyalty: boolean
  gift_certificates: boolean
  wishlist: boolean
  product_compare: boolean
}

export type StoreThemeHomeSectionState = {
  enabled: boolean
}

export type StoreTheme = {
  general: {
    container_width_px: number
    body_font: 'manrope' | 'rubik' | 'inter' | 'system'
    display_font: 'bebas' | 'manrope' | 'oswald' | 'system'
    base_font_size_px: number
    use_display_for_headings: boolean
    button_radius_px: number
    heading_weight: '400' | '500' | '600' | '700'
    primary_hex: string
    primary_foreground_hex: string
  }
  header: {
    sticky: boolean
    variant: 'classic' | 'centered' | 'wide_search'
  }
  hero: {
    variant: 'overlay' | 'split'
  }
  marketing: {
    variant: 'grid' | 'mosaic' | 'list'
  }
  footer: {
    tone: 'light' | 'muted' | 'dark'
    variant: 'columns' | 'minimal' | 'centered'
  }
  catalog: {
    grid_density: 'comfortable' | 'compact'
  }
  home: {
    sections: Record<string, StoreThemeHomeSectionState>
  }
}

export type SiteSettingsPayload = {
  logo_text: string
  logo_image_url: string | null
  phone_display: string
  phone_tel: string
  work_hours_short: string
  support_email: string | null
  footer_legal_line: string | null
  feature_flags: SiteFeatureFlags
  theme: StoreTheme
}

export const defaultSiteFeatureFlags: SiteFeatureFlags = {
  loyalty: true,
  gift_certificates: true,
  wishlist: true,
  product_compare: true,
}

export function defaultStoreTheme(): StoreTheme {
  return {
    general: {
      container_width_px: 1464,
      body_font: 'manrope',
      display_font: 'bebas',
      base_font_size_px: 16,
      use_display_for_headings: true,
      button_radius_px: 12,
      heading_weight: '700',
      primary_hex: '#09090b',
      primary_foreground_hex: '#fafafa',
    },
    header: { sticky: true, variant: 'classic' },
    hero: { variant: 'overlay' },
    marketing: { variant: 'grid' },
    footer: { tone: 'muted', variant: 'columns' },
    catalog: { grid_density: 'comfortable' },
    home: {
      sections: {
        hero: { enabled: true },
        trust: { enabled: true },
        marketing: { enabled: true },
        categories: { enabled: true },
        brands: { enabled: true },
        featured: { enabled: true },
        recent: { enabled: true },
        why: { enabled: true },
        newsletter: { enabled: true },
        news: { enabled: true },
      },
    },
  }
}

export function mergeStoreTheme(raw: StoreTheme | undefined | null): StoreTheme {
  const d = defaultStoreTheme()
  if (!raw || typeof raw !== 'object') {
    return d
  }
  const g = (raw.general && typeof raw.general === 'object' ? raw.general : {}) as Record<string, unknown>
  const h = (raw.header && typeof raw.header === 'object' ? raw.header : {}) as Record<string, unknown>
  const hr = (raw.hero && typeof raw.hero === 'object' ? raw.hero : {}) as Record<string, unknown>
  const mk = (raw.marketing && typeof raw.marketing === 'object' ? raw.marketing : {}) as Record<string, unknown>
  const f = (raw.footer && typeof raw.footer === 'object' ? raw.footer : {}) as Record<string, unknown>
  const c = (raw.catalog && typeof raw.catalog === 'object' ? raw.catalog : {}) as Record<string, unknown>
  const ho = (raw.home && typeof raw.home === 'object' ? raw.home : {}) as Record<string, unknown>
  const secIn = (ho.sections && typeof ho.sections === 'object' ? ho.sections : {}) as Record<string, unknown>

  const sections: Record<string, StoreThemeHomeSectionState> = { ...d.home.sections }
  for (const key of Object.keys(sections)) {
    const row = secIn[key]
    if (row && typeof row === 'object' && 'enabled' in row) {
      sections[key] = { enabled: Boolean((row as { enabled?: boolean }).enabled) }
    }
  }

  const bodyFontRaw = g.body_font
  const bodyFont =
    bodyFontRaw === 'rubik' ||
    bodyFontRaw === 'inter' ||
    bodyFontRaw === 'system' ||
    bodyFontRaw === 'manrope'
      ? bodyFontRaw
      : d.general.body_font
  const displayFontRaw = g.display_font
  const displayFont =
    displayFontRaw === 'bebas' ||
    displayFontRaw === 'manrope' ||
    displayFontRaw === 'oswald' ||
    displayFontRaw === 'system'
      ? displayFontRaw
      : d.general.display_font

  const hw = g.heading_weight
  const headingWeight =
    hw === '400' || hw === '500' || hw === '600' || hw === '700'
      ? hw
      : typeof hw === 'number' && [400, 500, 600, 700].includes(hw)
        ? (String(hw) as StoreTheme['general']['heading_weight'])
        : d.general.heading_weight

  return {
    general: {
      container_width_px: Number(g.container_width_px) || d.general.container_width_px,
      body_font: bodyFont,
      display_font: displayFont,
      base_font_size_px: Number(g.base_font_size_px) || d.general.base_font_size_px,
      use_display_for_headings:
        typeof g.use_display_for_headings === 'boolean'
          ? g.use_display_for_headings
          : d.general.use_display_for_headings,
      button_radius_px: Number.isFinite(Number(g.button_radius_px))
        ? Number(g.button_radius_px)
        : d.general.button_radius_px,
      heading_weight: headingWeight,
      primary_hex: typeof g.primary_hex === 'string' && g.primary_hex ? g.primary_hex : d.general.primary_hex,
      primary_foreground_hex:
        typeof g.primary_foreground_hex === 'string' && g.primary_foreground_hex
          ? g.primary_foreground_hex
          : d.general.primary_foreground_hex,
    },
    header: {
      sticky: typeof h.sticky === 'boolean' ? h.sticky : d.header.sticky,
      variant:
        h.variant === 'centered' || h.variant === 'wide_search' || h.variant === 'classic'
          ? h.variant
          : d.header.variant,
    },
    hero: {
      variant: hr.variant === 'split' ? 'split' : 'overlay',
    },
    marketing: {
      variant: mk.variant === 'mosaic' || mk.variant === 'list' ? mk.variant : 'grid',
    },
    footer: {
      tone: f.tone === 'light' || f.tone === 'dark' || f.tone === 'muted' ? f.tone : d.footer.tone,
      variant: f.variant === 'minimal' || f.variant === 'centered' ? f.variant : 'columns',
    },
    catalog: {
      grid_density: c.grid_density === 'compact' ? 'compact' : 'comfortable',
    },
    home: { sections },
  }
}

export function defaultSiteSettingsPayload(): SiteSettingsPayload {
  return {
    logo_text: 'Shoria',
    logo_image_url: null,
    phone_display: '+7 (900) 000-00-00',
    phone_tel: '+79000000000',
    work_hours_short: 'Пн–Вс: 10:00–20:00',
    support_email: null,
    footer_legal_line: null,
    feature_flags: { ...defaultSiteFeatureFlags },
    theme: defaultStoreTheme(),
  }
}

/** Сливает ответ API с дефолтами (новые флаги = включены). */
export function normalizeSiteSettingsPayload(raw: unknown): SiteSettingsPayload {
  const base = defaultSiteSettingsPayload()
  if (!raw || typeof raw !== 'object') {
    return base
  }
  const r = raw as Partial<SiteSettingsPayload>
  const flags = {
    ...defaultSiteFeatureFlags,
    ...(typeof r.feature_flags === 'object' && r.feature_flags !== null ? r.feature_flags : {}),
  }
  const theme = mergeStoreTheme(r.theme as StoreTheme | undefined)

  return {
    ...base,
    ...r,
    feature_flags: flags,
    theme,
  }
}

function navPathAllowed(path: string, flags: SiteFeatureFlags): boolean {
  if (!flags.loyalty && (path === '/loyalty-program' || path === '/account/loyalty')) {
    return false
  }
  if (!flags.gift_certificates && path === '/account/gift-certificates') {
    return false
  }
  if (!flags.wishlist && path === '/wishlist') {
    return false
  }
  if (!flags.product_compare && path === '/compare') {
    return false
  }
  if (!flags.wishlist && !flags.product_compare && path === '/account/saved') {
    return false
  }
  return true
}

export function filterNavItemsByFeatureFlags<T extends { path: string }>(
  items: T[],
  flags: SiteFeatureFlags,
): T[] {
  return items.filter((i) => navPathAllowed(i.path, flags))
}
