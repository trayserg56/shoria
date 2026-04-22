type SeoPayload = {
  title: string
  description: string
  robots?: string
  canonical?: string
}

type StructuredDataPayload = Record<string, unknown>

function upsertMeta(name: string, content: string, by: 'name' | 'property' = 'name') {
  const selector = `meta[${by}="${name}"]`
  let node = document.head.querySelector<HTMLMetaElement>(selector)

  if (!node) {
    node = document.createElement('meta')
    node.setAttribute(by, name)
    document.head.appendChild(node)
  }

  node.setAttribute('content', content)
}

function upsertCanonical(href: string) {
  let node = document.head.querySelector<HTMLLinkElement>('link[rel="canonical"]')

  if (!node) {
    node = document.createElement('link')
    node.setAttribute('rel', 'canonical')
    document.head.appendChild(node)
  }

  node.setAttribute('href', href)
}

const structuredDataSelector = 'script[data-shoria-jsonld="true"]'

export function setSeoMeta(payload: SeoPayload) {
  document.title = payload.title
  upsertMeta('description', payload.description)
  upsertMeta('robots', payload.robots ?? 'index,follow')
  upsertMeta('og:title', payload.title, 'property')
  upsertMeta('og:description', payload.description, 'property')

  if (payload.canonical) {
    upsertCanonical(payload.canonical)
    upsertMeta('og:url', payload.canonical, 'property')
  }
}

export function clearStructuredData() {
  document.head.querySelectorAll(structuredDataSelector).forEach((node) => node.remove())
}

export function setStructuredData(payloads: StructuredDataPayload[]) {
  clearStructuredData()

  for (const payload of payloads) {
    const node = document.createElement('script')
    node.type = 'application/ld+json'
    node.dataset.shoriaJsonld = 'true'
    node.text = JSON.stringify(payload)
    document.head.appendChild(node)
  }
}
