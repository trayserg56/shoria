const attributionStorageKey = 'shoria_first_touch_attribution_v1'

export type FirstTouchAttribution = {
  source: string | null
  medium: string | null
  campaign: string | null
  content: string | null
  term: string | null
  landing_path: string | null
  referrer_host: string | null
}

function normalizeValue(value: string | null) {
  const trimmed = value?.trim()

  return trimmed ? trimmed : null
}

function parseReferrerHost(referrer: string | null) {
  if (!referrer) {
    return null
  }

  try {
    return new URL(referrer).hostname || null
  } catch {
    return null
  }
}

function readCurrentAttribution(): FirstTouchAttribution {
  const currentUrl = new URL(window.location.href)
  const referrerHost = parseReferrerHost(document.referrer || null)
  const hasUtmParams = ['utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term'].some((key) =>
    currentUrl.searchParams.has(key),
  )

  return {
    source: normalizeValue(currentUrl.searchParams.get('utm_source')) ?? (hasUtmParams ? null : referrerHost ?? 'direct'),
    medium: normalizeValue(currentUrl.searchParams.get('utm_medium')) ?? (hasUtmParams ? null : referrerHost ? 'referral' : 'direct'),
    campaign: normalizeValue(currentUrl.searchParams.get('utm_campaign')),
    content: normalizeValue(currentUrl.searchParams.get('utm_content')),
    term: normalizeValue(currentUrl.searchParams.get('utm_term')),
    landing_path: normalizeValue(`${currentUrl.pathname}${currentUrl.search}`),
    referrer_host: referrerHost,
  }
}

export function getFirstTouchAttribution(): FirstTouchAttribution | null {
  try {
    const raw = window.localStorage.getItem(attributionStorageKey)

    if (!raw) {
      return null
    }

    return JSON.parse(raw) as FirstTouchAttribution
  } catch {
    return null
  }
}

export function captureFirstTouchAttribution() {
  const existing = getFirstTouchAttribution()

  if (existing) {
    return existing
  }

  const captured = readCurrentAttribution()

  try {
    window.localStorage.setItem(attributionStorageKey, JSON.stringify(captured))
  } catch {
    return captured
  }

  return captured
}
