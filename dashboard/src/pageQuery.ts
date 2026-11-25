import type { RouteLocationNormalizedLoaded, Router } from 'vue-router'

export function queryRecord(query: Record<string, unknown>): Record<string, string> {
  const out: Record<string, string> = {}
  for (const [key, value] of Object.entries(query)) {
    if (typeof value === 'string' && value !== '') {
      out[key] = value
    }
  }
  return out
}

export function sameQuery(current: Record<string, unknown>, next: Record<string, string>): boolean {
  const cur = queryRecord(current)
  const keys = new Set([...Object.keys(cur), ...Object.keys(next)])
  for (const key of keys) {
    if ((cur[key] ?? '') !== (next[key] ?? '')) {
      return false
    }
  }
  return true
}

export function replacePageQuery(
  router: Router,
  route: RouteLocationNormalizedLoaded,
  next: Record<string, string>,
): void {
  if (sameQuery(route.query, next)) {
    return
  }
  void router.replace({ path: route.path, query: next })
}
