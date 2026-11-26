export type TeamPermission =
  | 'leads.view'
  | 'leads.manage'
  | 'orders.view'
  | 'orders.manage'
  | 'reports.view'
  | 'reports.export'
  | 'wallet.view'
  | 'wallet.manage'
  | 'team.manage'

export type TeamRoleId = 'view' | 'edit' | 'finance' | 'full'

export interface TeamPermissionInfo {
  id: TeamPermission
  section: string
  label: string
  description: string
}

export interface TeamRoleInfo {
  id: TeamRoleId
  label: string
  description: string
  permissions: TeamPermission[]
}

export type BudgetPeriodId = 'total' | 'month' | 'week'

export interface TeamBudget {
  teamId: string
  teamName: string
  budgetCents: number | null
  period: BudgetPeriodId
  resetsAt: string | null
  spentCents: number
  reservedCents: number
  leftCents: number | null
  usedPercent: number | null
}

export interface TeamUnit {
  id: string
  parentId: string | null
  name: string
  budget: TeamBudget | null
  manageable: boolean
  canBuild: boolean
}

export interface TeamMember {
  id: string
  email: string
  teamId: string | null
  permissions: TeamPermission[]
  role: TeamRoleId | null
  status: 'invited' | 'expired' | 'active'
  invitedAt: string
  expiresAt: string | null
  signUpUrl: string | null
  manageable: boolean
}

export interface TeamViewer {
  teamId: string | null
  owner: boolean
  realOwner: boolean
  lead: boolean
  canBuild: boolean
  path: { id: string; name: string }[]
}

export interface TeamRoster {
  viewer: TeamViewer
  owner: { email: string }
  teams: TeamUnit[]
  members: TeamMember[]
  permissions: TeamPermissionInfo[]
  roles: TeamRoleInfo[]
  periods: { id: BudgetPeriodId; label: string }[]
}

export interface TeamEvent {
  id: string
  action: string
  label: string
  actor: string
  teamId: string | null
  details: Record<string, string | number | boolean | null>
  at: string
}

export function budgetTone(budget: TeamBudget | null | undefined): 'ok' | 'warn' | 'over' {
  const used = budget?.usedPercent ?? 0
  if (budget?.budgetCents === null || budget?.budgetCents === undefined) return 'ok'
  return used >= 100 ? 'over' : used >= 80 ? 'warn' : 'ok'
}

export interface TeamOption {
  title: string
  value: string
}

export const TEAM_PATH = '/team'
export const WALLET_PATH = '/transactions'
export const ACCOUNT_LEVEL = 'Whole account'

const PAGE_PERMISSIONS: Record<string, TeamPermission> = {
  '/dashboard': 'reports.view',
  '/reports': 'reports.view',
  '/stock': 'orders.view',
  '/orders': 'orders.view',
  '/transactions': 'wallet.view',
}

export function pagePermission(path: string): TeamPermission | null {
  return PAGE_PERMISSIONS[path] ?? null
}

export function roleOf(roles: TeamRoleInfo[], permissions: Iterable<TeamPermission>): TeamRoleInfo | null {
  const picked = [...new Set(permissions)].sort().join(',')
  return roles.find((role) => [...role.permissions].sort().join(',') === picked) ?? null
}

export function teamPath(teams: TeamUnit[], id: string | null | undefined): string {
  const byId = new Map(teams.map((team) => [team.id, team]))
  const names: string[] = []
  let current = id ? byId.get(id) : undefined
  while (current && names.length <= teams.length) {
    names.unshift(current.name)
    current = current.parentId ? byId.get(current.parentId) : undefined
  }
  return names.join(' › ')
}

export function teamOptions(teams: TeamUnit[], pick: (team: TeamUnit) => boolean, accountLevel: boolean): TeamOption[] {
  const options = teams
    .filter(pick)
    .map((team) => ({ title: teamPath(teams, team.id), value: team.id }))
    .sort((left, right) => left.title.localeCompare(right.title))
  return accountLevel ? [{ title: ACCOUNT_LEVEL, value: '' }, ...options] : options
}

export function subtreeOf(teams: TeamUnit[], rootId: string): Set<string> {
  const ids = new Set([rootId])
  let grew = true
  while (grew) {
    grew = false
    for (const team of teams) {
      if (team.parentId && ids.has(team.parentId) && !ids.has(team.id)) {
        ids.add(team.id)
        grew = true
      }
    }
  }
  return ids
}
