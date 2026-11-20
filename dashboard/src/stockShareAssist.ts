import type { RouteLocationNormalizedLoaded } from 'vue-router'
import { cabinetOrigin } from '@/host'
import {
  parseStockModalIntent,
  parseStockShareAssist,
  stockModalQueryWithoutAssist,
} from '@/stockModalIntent'
import type { useSessionStore } from '@/stores/session'

type SessionStore = ReturnType<typeof useSessionStore>

export async function maybeResolveAdminStockShare(
  session: SessionStore,
  route: RouteLocationNormalizedLoaded,
): Promise<boolean> {
  if (session.loading || session.missing || !session.payload?.user) {
    return false
  }

  const intent = parseStockModalIntent(route.query)
  const assist = parseStockShareAssist(route.query)
  if (!intent || !assist) {
    return false
  }

  const actorRole = session.payload.impersonation
    ? 'admin'
    : session.payload.user.role
  if (actorRole !== 'admin') {
    return false
  }

  if (session.payload.user.id === assist.asUserId && session.payload.user.role === assist.forRole) {
    return redirectToRoleCabinetIfNeeded(session, route, assist.forRole)
  }

  await session.startImpersonation(assist.asUserId, assist.forRole, { redirect: false })
  const params = new URLSearchParams(stockModalQueryWithoutAssist(route.query))
  const path = route.path.startsWith('/') ? route.path : `/${route.path}`
  const target = `${cabinetOrigin(window.location.hostname, assist.forRole)}${path}?${params.toString()}`
  window.location.href = target
  return true
}

function redirectToRoleCabinetIfNeeded(
  _session: SessionStore,
  route: RouteLocationNormalizedLoaded,
  forRole: string,
): boolean {
  const expected = cabinetOrigin(window.location.hostname, forRole)
  if (window.location.origin === expected) {
    return false
  }

  const params = new URLSearchParams(stockModalQueryWithoutAssist(route.query))
  const path = route.path.startsWith('/') ? route.path : `/${route.path}`
  window.location.href = `${expected}${path}?${params.toString()}`
  return true
}