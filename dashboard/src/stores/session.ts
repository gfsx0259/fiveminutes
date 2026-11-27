import { defineStore } from 'pinia'
import { computed, ref } from 'vue'
import { apiDelete, apiPost } from '@/api'
import { apiOrigin, cabinetOrigin } from '@/host'
import type { ShelfAgePolicy } from '@/stores/market'
import { pagePermission, TEAM_PATH, WALLET_PATH, type TeamBudget, type TeamPermission } from '@/team'

export interface Organization {
  id: string
  name: string
  apex: string
  markupPercent?: number
  balanceCents?: number | null
  shelfAgePolicy?: ShelfAgePolicy
  rotationAxes?: string[]
}

export interface AppUser {
  id: string
  email: string
  role: string
  permissions: TeamPermission[]
  balanceCents?: number | null
  organization: Organization | null
}

export interface ImpersonationState {
  active: boolean
  actor: { id: string; email: string }
}

export interface SessionPayload {
  session: Record<string, unknown>
  user: AppUser | null
  member?: { id: string; email: string; teamId: string | null; teamPath: string[] } | null
  budget?: TeamBudget | null
  impersonation?: ImpersonationState | null
}

export const useSessionStore = defineStore('session', () => {
  const payload = ref<SessionPayload | null>(null)
  const missing = ref(false)
  const loading = ref(true)
  const error = ref('')
  const permissions = computed(() => new Set<string>(payload.value?.user?.permissions ?? []))
  const isPartner = computed(() => payload.value?.user?.role === 'partner')
  const isTeamScoped = computed(() => Boolean(payload.value?.member?.teamId))

  const spend = computed((): { label: string; cents: number; team: boolean } | null => {
    const budget = payload.value?.budget
    if (budget && budget.leftCents !== null) {
      return { label: `${budget.teamName} budget left`, cents: budget.leftCents, team: true }
    }
    const balance = payload.value?.user?.balanceCents
    return typeof balance === 'number' ? { label: 'Your balance', cents: balance, team: false } : null
  })

  function can(permission: TeamPermission): boolean {
    return permissions.value.has(permission)
  }

  function canOpen(path: string): boolean {
    if (path === TEAM_PATH) {
      return isPartner.value
    }
    if (path === WALLET_PATH && isTeamScoped.value) {
      return false
    }
    const permission = pagePermission(path)
    return permission === null || can(permission)
  }

  async function load(): Promise<void> {
    loading.value = true
    missing.value = false
    error.value = ''
    try {
      const response = await fetch(`${apiOrigin(window.location.hostname)}/session`, {
        credentials: 'include',
      })
      if (response.status === 401) {
        missing.value = true
        payload.value = null
        return
      }
      if (!response.ok) {
        error.value = `Failed to load session: ${response.status}`
        return
      }
      payload.value = await response.json() as SessionPayload
    } catch {
      error.value = 'Failed to load session'
    } finally {
      loading.value = false
    }
  }

  let refreshedAt = 0

  async function refresh(): Promise<void> {
    if (!payload.value || loading.value || Date.now() - refreshedAt < 15_000) {
      return
    }
    refreshedAt = Date.now()
    try {
      const response = await fetch(`${apiOrigin(window.location.hostname)}/session`, { credentials: 'include' })
      if (response.status === 401) {
        missing.value = true
        payload.value = null
        return
      }
      if (response.ok) {
        payload.value = await response.json() as SessionPayload
      }
    } catch {
      refreshedAt = 0
    }
  }

  async function startImpersonation(
    userId: string,
    role?: string,
    options?: { redirect?: boolean },
  ): Promise<void> {
    await apiPost('/impersonate', { userId })
    await load()
    if (options?.redirect === false) {
      return
    }
    const nextRole = role ?? payload.value?.user?.role ?? 'partner'
    window.location.href = cabinetOrigin(window.location.hostname, nextRole)
  }

  async function stopImpersonation(): Promise<void> {
    await apiDelete('/impersonate')
    await load()
    window.location.href = `${cabinetOrigin(window.location.hostname, 'admin')}/users`
  }

  return { payload, missing, loading, error, isPartner, isTeamScoped, spend, can, canOpen, load, refresh, startImpersonation, stopImpersonation }
})
