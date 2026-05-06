/** Путь в каталоге: `/catalog` или `/catalog/a/b/c` */
export function buildCatalogPath(segments?: string[]): string {
  if (!segments?.length) {
    return '/catalog'
  }

  return `/catalog/${segments.map((segment) => encodeURIComponent(segment)).join('/')}`
}
