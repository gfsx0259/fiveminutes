import type { RouteLocationNormalizedLoaded, Router } from 'vue-router'
import { cabinetOrigin } from '@/host'
import {
  isTableViewShareQuery,
  queryWithoutShareAssist,
} from '@/tableViewShare'
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
  router?: Router,
): Promise<boolean> {
  if (session.loading || session.missing || !session.payload?.user) {
    return false
  }

  const intent = parseStockModalIntent(route.query)
  const assist = parseStockShareAssist(route.query)
  if (!assist) {
    return false
  }
  const tableShare = isTableViewShareQuery(route.query)
  if (!intent && !tableShare) {
    return false
  }

  const actorRole = session.payload.impersonation
    ? 'admin'
    : session.payload.user.role
  if (actorRole !== 'admin') {
    return false
  }

  if (session.payload.user.id === assist.asUserId && session.payload.user.role === assist.forRole) {
    const redirected = redirectToRoleCabinetIfNeeded(session, route, assist.forRole)
    if (!redirected && router && (route.query.as != null || route.query.for != null)) {
      const carry = intent
        ? stockModalQueryWithoutAssist(route.query)
        : queryWithoutShareAssist(route.query)
      await router.replace({ path: route.path, query: carry })
      return true
    }
    return redirected
  }

  await session.startImpersonation(assist.asUserId, assist.forRole, { redirect: false })
  const carry = intent
    ? stockModalQueryWithoutAssist(route.query)
    : queryWithoutShareAssist(route.query)
  const params = new URLSearchParams(carry)
  const path = route.path.startsWith('/') ? route.path : `/${route.path}`
  const qs = params.toString()
  const target = `${cabinetOrigin(window.location.hostname, assist.forRole)}${path}${qs ? `?${qs}` : ''}`
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

  const intent = parseStockModalIntent(route.query)
  const carry = intent
    ? stockModalQueryWithoutAssist(route.query)
    : queryWithoutShareAssist(route.query)
  const params = new URLSearchParams(carry)
  const path = route.path.startsWith('/') ? route.path : `/${route.path}`
  const qs = params.toString()
  window.location.href = `${expected}${path}${qs ? `?${qs}` : ''}`
  return true
}