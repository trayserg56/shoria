import DOMPurify, { type Config } from 'dompurify'

// Разрешённые теги для rich-text контента из Filament (статьи, страницы, условия)
const RICH_TEXT_CONFIG: Config = {
  ALLOWED_TAGS: [
    'p', 'br', 'b', 'i', 'em', 'strong', 'u', 's', 'del',
    'h1', 'h2', 'h3', 'h4', 'h5', 'h6',
    'ul', 'ol', 'li',
    'blockquote', 'pre', 'code',
    'a', 'img',
    'table', 'thead', 'tbody', 'tr', 'th', 'td',
    'div', 'span', 'hr',
  ],
  ALLOWED_ATTR: ['href', 'target', 'rel', 'src', 'alt', 'class', 'id', 'width', 'height'],
  ALLOW_DATA_ATTR: false,
  FORCE_BODY: false,
}

export function sanitizeHtml(dirty: string | null | undefined): string {
  if (!dirty) return ''
  return String(DOMPurify.sanitize(dirty, RICH_TEXT_CONFIG))
}
