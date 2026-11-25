import type { RouteLocationNormalizedLoaded } from 'vue-router'
import { cabinetOrigin } from '@/host'
import { parseStockShareAssist } from '@/stockModalIntent'
import type { useSessionStore } from '@/stores/session'

type SessionStore = ReturnType<typeof useSessionStore>

function hrefForRole(route: RouteLocationNormalizedLoaded, forRole: string): string {
  const params = new URLSearchParams()
  for (const [key, value] of Object.entries(route.query)) {
    if (typeof value === 'string' && value !== '') {
      params.set(key, value)
    }
  }
  const path = route.path.startsWith('/') ? route.path : `/${route.path}`
  const qs = params.toString()
  return `${cabinetOrigin(window.location.hostname, forRole)}${path}${qs ? `?${qs}` : ''}`
}

/** Admin opening a partner link (?as=&for=) enters that cabinet and keeps the rest of the URL. */
export async function maybeResolveAdminStockShare(
  session: SessionStore,
  route: RouteLocationNormalizedLoaded,
): Promise<boolean> {
  if (session.loading || session.missing || !session.payload?.user) {
    return false
  }

  const assist = parseStockShareAssist(route.query)
  if (!assist) {
    return false
  }

  const actorRole = session.payload.impersonation
    ? 'admin'
    : session.payload.user.role
  if (actorRole !== 'admin') {
    return false
  }

  const isTarget = session.payload.user.id === assist.asUserId
    && session.payload.user.role === assist.forRole
  if (!isTarget) {
    await session.startImpersonation(assist.asUserId, assist.forRole, { redirect: false })
  }

  const expected = cabinetOrigin(window.location.hostname, assist.forRole)
  if (window.location.origin === expected) {
    return false
  }

  window.location.href = hrefForRole(route, assist.forRole)
  return true
}
