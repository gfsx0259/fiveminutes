<template>
  <div class="team-page">
    <div class="page-head">
      <div>
        <h1>Team</h1>
        <p>Teams, people and who sees what. Every team sees only its own orders, leads and spend, and everything below it.</p>
      </div>
      <div class="team-page__actions">
        <PeriodPicker v-if="showStats && tab === 'structure'" v-model="choice" :now="now" />
        <UiButton v-if="roster?.viewer.canBuild" variant="outline" icon="mdi-folder-plus-outline" @click="openUnit(null, viewerTeam)">
          New team
        </UiButton>
        <UiButton v-if="roster?.viewer.canBuild" icon="mdi-account-plus-outline" @click="openInvite(viewerTeam)">
          Invite person
        </UiButton>
      </div>
    </div>

    <v-alert v-if="error || team.error" type="error" variant="tonal" closable @click:close="error = ''">
      {{ error || team.error }}
    </v-alert>

    <div v-if="roster && roster.viewer.path.length" class="team-place">
      <v-icon icon="mdi-map-marker-outline" size="16" />
      <span>Your place: <strong>{{ roster.viewer.path.map((item) => item.name).join(' › ') }}</strong></span>
      <span class="team-place__hint">You see this team and everything below it</span>
    </div>

    <div v-if="roster?.viewer.lead" class="team-tabs">
      <UiSegmented v-model="tab" :items="tabItems" />
    </div>

    <UiCard v-if="tab === 'structure'" title="Structure" :subtitle="structureSubtitle">
      <template #actions>
        <div class="team-search">
          <v-icon icon="mdi-magnify" size="18" />
          <input v-model="query" type="search" placeholder="Find a team or a person" aria-label="Find a team or a person">
        </div>
      </template>

      <div v-if="isEmpty" class="team-empty">
        <v-icon icon="mdi-folder-account-outline" size="40" />
        <strong>Build your first team</strong>
        <p>Split the account into teams like Crypto or Gambling, invite people into them and give each team its own budget. Teams do not see each other's orders, leads or spend.</p>
        <div class="team-empty__actions">
          <UiButton icon="mdi-folder-plus-outline" @click="openUnit(null, viewerTeam)">New team</UiButton>
          <UiButton variant="outline" icon="mdi-account-plus-outline" @click="openInvite(viewerTeam)">Invite person</UiButton>
        </div>
      </div>

      <div v-else class="team-tree" role="treegrid">
        <div class="team-tree__row team-tree__row--head" :class="{ 'team-tree__row--stats': showStats }">
          <span>Name</span>
          <span>Role</span>
          <template v-if="showStats">
            <span class="team-tree__num">Leads</span>
            <span class="team-tree__num">Spent</span>
            <span class="team-tree__num">Approved</span>
            <span class="team-tree__num">CR</span>
          </template>
          <span>Budget</span>
          <span />
        </div>

        <div
          v-for="row in visibleRows"
          :key="row.key"
          class="team-tree__row"
          :class="[`team-tree__row--${row.kind}`, { 'team-tree__row--stats': showStats, 'team-tree__row--match': matches(row) && query }]"
          role="row"
        >
          <span class="team-tree__name" :style="{ paddingLeft: `${row.depth * 22}px` }">
            <button
              v-if="row.expandable"
              type="button"
              class="team-tree__toggle"
              :aria-label="collapsed.has(row.key) ? 'Expand' : 'Collapse'"
              @click="toggle(row.key)"
            >
              <v-icon :icon="collapsed.has(row.key) ? 'mdi-chevron-right' : 'mdi-chevron-down'" size="18" />
            </button>
            <span v-else class="team-tree__toggle-space" />
            <v-icon :icon="iconOf(row)" size="18" class="team-tree__icon" />
            <span class="team-tree__label">
              <strong v-if="row.kind !== 'person'">{{ row.label }}</strong>
              <span v-else>{{ row.label }}</span>
              <span v-if="row.self" class="team-you">you</span>
              <StatusChip v-if="row.member?.status === 'invited'" status="pending" :label="inviteLabel(row.member)" />
              <StatusChip v-if="row.member?.status === 'expired'" status="rejected" label="Invite expired" />
              <small v-if="row.kind !== 'person'">{{ row.caption }}</small>
            </span>
          </span>
          <span class="team-tree__role" :title="row.rights">{{ row.role }}</span>
          <template v-if="showStats">
            <span class="team-tree__num" data-label="Leads">{{ row.stats ? row.stats.leads.toLocaleString('en-US') : '—' }}</span>
            <span class="team-tree__num" data-label="Spent">{{ row.stats ? dollars(row.stats.spent) : '—' }}</span>
            <span class="team-tree__num" data-label="Approved">{{ row.stats ? row.stats.approved.toLocaleString('en-US') : '—' }}</span>
            <span class="team-tree__num" data-label="CR">{{ row.stats && row.stats.leads ? percent(row.stats.approvalRate) : '—' }}</span>
          </template>
          <span class="team-tree__budget">
            <template v-if="row.team?.budget">
              <template v-if="row.team.budget.budgetCents !== null">
                <span class="team-budget-bar">
                  <span class="team-budget-bar__fill" :class="`team-budget-bar__fill--${budgetTone(row.team.budget)}`" :style="{ width: `${Math.min(100, row.team.budget.usedPercent ?? 0)}%` }" />
                </span>
                <small :class="`team-tree__tone--${budgetTone(row.team.budget)}`">
                  {{ cents(row.team.budget.leftCents) }} left of {{ cents(row.team.budget.budgetCents) }}{{ periodSuffix(row.team.budget.period) }}
                </small>
              </template>
              <small v-else>No limit · {{ cents(row.team.budget.spentCents + row.team.budget.reservedCents) }} used</small>
            </template>
            <small v-else-if="row.kind === 'account' && balance !== null">Balance {{ cents(balance) }}</small>
          </span>
          <span class="team-tree__actions">
            <UiActionMenu v-if="actionsOf(row).length" :items="actionsOf(row)" :menu-min-width="220" @select="act(row, $event)">
              <template #activator="{ menuProps }">
                <button type="button" class="team-tree__more" v-bind="menuProps" aria-label="Actions">
                  <v-icon icon="mdi-dots-horizontal" size="18" />
                </button>
              </template>
            </UiActionMenu>
          </span>
        </div>

        <div v-if="!team.loading && visibleRows.length === 0" class="team-tree__nothing">Nothing matches “{{ query }}”</div>
      </div>
    </UiCard>

    <UiCard v-else title="Activity" subtitle="Who changed what in the teams you lead, newest first">
      <div v-if="activityLoading" class="team-tree__nothing">Loading…</div>
      <div v-else-if="events.length === 0" class="team-tree__nothing">Nothing happened yet</div>
      <ul v-else class="team-activity">
        <li v-for="event in events" :key="event.id" class="team-activity__item">
          <v-icon :icon="eventIcon(event.action)" size="18" class="team-tree__icon" />
          <div class="team-activity__text">
            <span><strong>{{ event.label }}</strong> · {{ eventSummary(event) }}</span>
            <small>{{ event.actor }} · {{ when(event.at) }}</small>
          </div>
        </li>
      </ul>
    </UiCard>

    <TeamMemberDialog v-if="roster" v-model="memberOpen" :roster="roster" :member="editingMember" :team="targetTeam" @saved="onMemberSaved" />
    <TeamUnitDialog v-if="roster" v-model="unitOpen" :roster="roster" :team="editingUnit" :parent="targetTeam" @saved="reload" />
    <TeamBudgetDialog v-if="budgetUnit" v-model="budgetOpen" :team="budgetUnit" @saved="reload" />
  </div>
</template>

<script setup lang="ts">
import { computed, onBeforeUnmount, ref, watch } from 'vue'
import { api, apiDelete, apiPost } from '@/api'
import PeriodPicker from '@/components/PeriodPicker.vue'
import StatusChip from '@/components/StatusChip.vue'
import TeamBudgetDialog from '@/components/TeamBudgetDialog.vue'
import TeamMemberDialog from '@/components/TeamMemberDialog.vue'
import TeamUnitDialog from '@/components/TeamUnitDialog.vue'
import UiActionMenu, { type UiActionMenuItem } from '@/components/ui/UiActionMenu.vue'
import UiButton from '@/components/ui/UiButton.vue'
import UiCard from '@/components/ui/UiCard.vue'
import UiSegmented from '@/components/ui/UiSegmented.vue'
import { askConfirm, confirmAction } from '@/composables/useConfirm'
import { showToast } from '@/composables/useToast'
import { copyText } from '@/copyText'
import { dollars } from '@/money'
import { between, choicePeriodOf, percent, purchasesOf, totalsOf, type PeriodChoice, type Purchase, type PurchaseTotals } from '@/purchases'
import { useLeadStore } from '@/stores/leads'
import { useMarketStore } from '@/stores/market'
import { useSessionStore } from '@/stores/session'
import { useTeamStore } from '@/stores/team'
import { budgetTone, roleOf, subtreeOf, type BudgetPeriodId, type TeamEvent, type TeamMember, type TeamUnit } from '@/team'

interface TreeRow {
  key: string
  kind: 'account' | 'team' | 'person'
  depth: number
  label: string
  caption: string
  role: string
  rights: string
  self: boolean
  team: TeamUnit | null
  member: TeamMember | null
  stats: PurchaseTotals | null
  expandable: boolean
  parents: string[]
}

const session = useSessionStore()
const team = useTeamStore()
const leadStore = useLeadStore()
const market = useMarketStore()

const tabItems = [
  { title: 'Structure', value: 'structure' },
  { title: 'Activity', value: 'activity' },
]

const error = ref('')
const tab = ref<'structure' | 'activity'>('structure')
const query = ref('')
const choice = ref<PeriodChoice>({ key: '30', from: '', to: '' })
const now = ref(Date.now())
const collapsed = ref(new Set<string>())
const memberOpen = ref(false)
const unitOpen = ref(false)
const budgetOpen = ref(false)
const editingMember = ref<TeamMember | null>(null)
const editingUnit = ref<TeamUnit | null>(null)
const budgetUnit = ref<TeamUnit | null>(null)
const targetTeam = ref<string | null>(null)
const events = ref<TeamEvent[]>([])
const activityLoading = ref(false)
let clock: ReturnType<typeof setInterval> | undefined

const roster = computed(() => team.roster)
const viewerTeam = computed(() => roster.value?.viewer.teamId ?? null)
const showStats = computed(() => session.can('reports.view'))
const balance = computed(() => session.payload?.user?.balanceCents ?? null)
const myEmail = computed(() => session.payload?.member?.email ?? session.payload?.user?.email ?? '')
const isEmpty = computed(() => Boolean(roster.value && roster.value.viewer.teamId === null && roster.value.teams.length === 0 && roster.value.members.length === 0))

const period = computed(() => choicePeriodOf(choice.value, now.value))
const purchases = computed((): Purchase[] => between(
  purchasesOf(leadStore.leads, market.orders, market.presets),
  period.value.from,
  period.value.to,
))
const orderTeam = computed(() => new Map(market.orders.map((order) => [order.id, order.teamId ?? null])))
const orderOpener = computed(() => new Map(market.orders.map((order) => [order.id, order.openedBy ?? null])))

const structureSubtitle = computed(() => showStats.value
  ? `${period.value.title} · sums include every team below`
  : 'Teams and people you can see')

function teamStats(unit: TeamUnit): PurchaseTotals {
  const ids = subtreeOf(roster.value?.teams ?? [], unit.id)
  return totalsOf(purchases.value.filter((purchase) => ids.has(orderTeam.value.get(purchase.lead.orderId ?? '') ?? '')))
}

function personStats(email: string): PurchaseTotals {
  return totalsOf(purchases.value.filter((purchase) => orderOpener.value.get(purchase.lead.orderId ?? '') === email))
}

function roleLabel(member: TeamMember): string {
  const role = roster.value ? roleOf(roster.value.roles, member.permissions) : null
  return role?.label ?? `Custom · ${member.permissions.length} rights`
}

function rightsOf(member: TeamMember): string {
  const labels = new Map(roster.value?.permissions.map((permission) => [permission.id, permission.label]) ?? [])
  return member.permissions.map((permission) => labels.get(permission) ?? permission).join(', ')
}

function personRow(member: TeamMember, depth: number, parents: string[]): TreeRow {
  return {
    key: `person:${member.id}`,
    kind: 'person',
    depth,
    label: member.email,
    caption: '',
    role: roleLabel(member),
    rights: rightsOf(member),
    self: member.email === myEmail.value,
    team: null,
    member,
    stats: showStats.value ? personStats(member.email) : null,
    expandable: false,
    parents,
  }
}

function teamRows(unit: TeamUnit, depth: number, parents: string[]): TreeRow[] {
  const all = roster.value
  if (!all) return []
  const key = `team:${unit.id}`
  const people = all.members.filter((member) => member.teamId === unit.id)
  const children = all.teams.filter((child) => child.parentId === unit.id)
  const inside = [...parents, key]
  const total = subtreeOf(all.teams, unit.id)
  const headcount = all.members.filter((member) => member.teamId !== null && total.has(member.teamId)).length
  return [
    {
      key,
      kind: 'team',
      depth,
      label: unit.name,
      caption: `${headcount} ${headcount === 1 ? 'person' : 'people'}${children.length ? ` · ${children.length} sub-team${children.length === 1 ? '' : 's'}` : ''}`,
      role: '',
      rights: '',
      self: false,
      team: unit,
      member: null,
      stats: showStats.value ? teamStats(unit) : null,
      expandable: people.length + children.length > 0,
      parents,
    },
    ...people.map((member) => personRow(member, depth + 1, inside)),
    ...children.flatMap((child) => teamRows(child, depth + 1, inside)),
  ]
}

const rows = computed((): TreeRow[] => {
  const all = roster.value
  if (!all) return []
  const own = all.viewer.teamId ? all.teams.find((unit) => unit.id === all.viewer.teamId) : null
  if (own) {
    return teamRows(own, 0, [])
  }
  const key = 'account'
  return [
    {
      key,
      kind: 'account',
      depth: 0,
      label: 'Whole account',
      caption: all.owner.email,
      role: '',
      rights: '',
      self: false,
      team: null,
      member: null,
      stats: showStats.value ? totalsOf(purchases.value) : null,
      expandable: true,
      parents: [],
    },
    {
      key: 'owner',
      kind: 'person',
      depth: 1,
      label: all.owner.email,
      caption: '',
      role: 'Owner',
      rights: 'Every action and the whole team',
      self: all.viewer.realOwner,
      team: null,
      member: null,
      stats: showStats.value ? personStats(all.owner.email) : null,
      expandable: false,
      parents: [key],
    },
    ...all.members.filter((member) => member.teamId === null).map((member) => personRow(member, 1, [key])),
    ...all.teams.filter((unit) => unit.parentId === null).flatMap((unit) => teamRows(unit, 1, [key])),
  ]
})

function matches(row: TreeRow): boolean {
  const needle = query.value.trim().toLowerCase()
  return needle === '' || row.label.toLowerCase().includes(needle)
}

const visibleRows = computed(() => {
  const needle = query.value.trim().toLowerCase()
  if (needle !== '') {
    const keep = new Set<string>()
    for (const row of rows.value) {
      if (matches(row)) {
        keep.add(row.key)
        row.parents.forEach((parent) => keep.add(parent))
      }
    }
    return rows.value.filter((row) => keep.has(row.key))
  }
  return rows.value.filter((row) => row.parents.every((parent) => !collapsed.value.has(parent)))
})

function toggle(key: string): void {
  const next = new Set(collapsed.value)
  if (next.has(key)) {
    next.delete(key)
  } else {
    next.add(key)
  }
  collapsed.value = next
}

function iconOf(row: TreeRow): string {
  if (row.kind === 'account') return 'mdi-domain'
  if (row.kind === 'team') return collapsed.value.has(row.key) ? 'mdi-folder-outline' : 'mdi-folder-open-outline'
  return row.role === 'Owner' ? 'mdi-crown-outline' : 'mdi-account-outline'
}

function cents(value: number | null | undefined): string {
  return dollars((value ?? 0) / 100)
}

function periodSuffix(value: BudgetPeriodId): string {
  return value === 'month' ? ' this month' : value === 'week' ? ' this week' : ''
}

function day(iso: string | null): string {
  return iso ? new Date(iso).toLocaleDateString('en-GB', { day: 'numeric', month: 'short' }) : ''
}

function when(iso: string): string {
  return new Date(iso).toLocaleString('en-GB', { day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' })
}

function inviteLabel(member: TeamMember): string {
  return member.expiresAt ? `Invited · until ${day(member.expiresAt)}` : 'Invited'
}

function actionsOf(row: TreeRow): UiActionMenuItem[] {
  if (row.kind === 'team' && row.team) {
    return [
      ...(row.team.canBuild ? [
        { id: 'invite', title: 'Invite person here', icon: 'mdi-account-plus-outline' },
        { id: 'subteam', title: 'Add sub-team', icon: 'mdi-folder-plus-outline' },
      ] : []),
      ...(row.team.manageable ? [
        { id: 'budget', title: 'Budget', icon: 'mdi-cash-multiple' },
        { id: 'edit', title: 'Rename or move', icon: 'mdi-pencil-outline' },
        { id: 'delete', title: 'Delete team', icon: 'mdi-delete-outline' },
      ] : []),
    ]
  }
  if (row.kind === 'account' && roster.value?.viewer.canBuild) {
    return [
      { id: 'invite', title: 'Invite person here', icon: 'mdi-account-plus-outline' },
      { id: 'subteam', title: 'Add team', icon: 'mdi-folder-plus-outline' },
    ]
  }
  if (row.kind === 'person' && row.member) {
    const member = row.member
    return [
      ...(member.signUpUrl && member.status === 'invited' ? [{ id: 'link', title: 'Copy sign-up link', icon: 'mdi-link-variant' }] : []),
      ...(member.manageable && member.status !== 'active' ? [{ id: 'renew', title: 'Send the invite again', icon: 'mdi-email-sync-outline' }] : []),
      ...(member.manageable ? [
        { id: 'access', title: 'Access and team', icon: 'mdi-shield-account-outline' },
        { id: 'remove', title: member.status === 'active' ? 'Remove from team' : 'Revoke invite', icon: 'mdi-account-remove-outline' },
      ] : []),
      ...(roster.value?.viewer.realOwner && member.status === 'active' ? [{ id: 'owner', title: 'Make account owner', icon: 'mdi-crown-outline' }] : []),
    ]
  }
  return []
}

function openInvite(teamId: string | null): void {
  editingMember.value = null
  targetTeam.value = teamId
  memberOpen.value = true
}

function openUnit(unit: TeamUnit | null, parentId: string | null): void {
  editingUnit.value = unit
  targetTeam.value = parentId
  unitOpen.value = true
}

async function act(row: TreeRow, action: string): Promise<void> {
  const unit = row.team
  const member = row.member
  if (action === 'invite') openInvite(unit?.id ?? null)
  if (action === 'subteam') openUnit(null, unit?.id ?? null)
  if (action === 'edit' && unit) openUnit(unit, unit.parentId)
  if (action === 'budget' && unit) {
    budgetUnit.value = unit
    budgetOpen.value = true
  }
  if (action === 'delete' && unit) await removeUnit(unit)
  if (action === 'access' && member) {
    editingMember.value = member
    memberOpen.value = true
  }
  if (action === 'link' && member?.signUpUrl && await copyText(member.signUpUrl)) showToast('Sign-up link copied')
  if (action === 'renew' && member) await renew(member)
  if (action === 'remove' && member) await removeMember(member)
  if (action === 'owner' && member) await handOver(member)
}

async function guarded(action: () => Promise<unknown>, done: string, failed: string): Promise<void> {
  try {
    await action()
    showToast(done)
    await reload()
  } catch (err) {
    error.value = err instanceof Error ? err.message : failed
  }
}

async function removeUnit(unit: TeamUnit): Promise<void> {
  const sure = await confirmAction({
    title: `Delete ${unit.name}?`,
    text: 'Its orders move one level up. A team with people or sub-teams cannot be deleted.',
    confirmLabel: 'Delete team',
    danger: true,
  })
  if (sure) await guarded(() => apiDelete(`/team/units/${unit.id}`), 'Team deleted', 'Failed to delete the team')
}

async function removeMember(member: TeamMember): Promise<void> {
  const active = member.status === 'active'
  const sure = await confirmAction({
    title: active ? `Remove ${member.email}?` : `Revoke the invite for ${member.email}?`,
    text: active ? 'The person loses access to this account right away. Their orders stay with the team.' : 'The sign-up link stops working.',
    confirmLabel: active ? 'Remove' : 'Revoke',
    danger: true,
  })
  if (sure) await guarded(() => apiDelete(`/team/${member.id}`), active ? 'Person removed' : 'Invite revoked', 'Failed to remove the person')
}

async function renew(member: TeamMember): Promise<void> {
  await guarded(() => apiPost(`/team/${member.id}/renew`, {}), `Invite sent again to ${member.email}`, 'Failed to send the invite')
}

async function handOver(member: TeamMember): Promise<void> {
  const answer = await askConfirm({
    title: `Make ${member.email} the owner?`,
    text: 'They get the whole account: every team, the wallet and the right to hand it over again. You cannot undo this yourself.',
    confirmLabel: 'Hand over',
    danger: true,
    choices: [
      { value: 'keep', label: 'Stay as a lead of the whole account', hint: 'You keep every right except ownership' },
      { value: 'leave', label: 'Leave the account', hint: 'Your login stops opening this account' },
    ],
  })
  if (answer === null) return
  try {
    await apiPost('/team/owner', { member_id: member.id, keep_former_owner: answer === 'keep' })
    showToast(`${member.email} is the owner now`)
    await session.load()
    await reload()
  } catch (err) {
    error.value = err instanceof Error ? err.message : 'Failed to hand over the account'
  }
}

async function onMemberSaved(member: TeamMember): Promise<void> {
  if (editingMember.value) {
    showToast('Access saved')
  } else if (member.signUpUrl && await copyText(member.signUpUrl)) {
    showToast(`Invite emailed to ${member.email}, link copied`)
  } else {
    showToast(`Invite emailed to ${member.email}`)
  }
  await reload()
}

function detail(event: TeamEvent, key: string): string {
  const value = event.details[key]
  return value === null || value === undefined ? '' : String(value)
}

function centsDetail(event: TeamEvent, key: string): string {
  const value = event.details[key]
  return typeof value === 'number' ? cents(value) : 'no limit'
}

function eventSummary(event: TeamEvent): string {
  switch (event.action) {
    case 'team.created': return `${detail(event, 'team')} in ${detail(event, 'inside')}`
    case 'team.renamed': return `${detail(event, 'from')} → ${detail(event, 'to')}`
    case 'team.moved': return `${detail(event, 'team')}: ${detail(event, 'from')} → ${detail(event, 'to')}`
    case 'team.deleted': return `${detail(event, 'team')}, orders moved to ${detail(event, 'ordersMovedTo')}`
    case 'person.invited': return `${detail(event, 'email')} to ${detail(event, 'team')}`
    case 'person.changed': return `${detail(event, 'email')}: ${detail(event, 'from')} → ${detail(event, 'to')}`
    case 'person.removed': return `${detail(event, 'email')} from ${detail(event, 'team')}`
    case 'owner.changed': return `${detail(event, 'from')} → ${detail(event, 'to')}, former owner ${detail(event, 'formerOwner')}`
    case 'budget.changed': return `${detail(event, 'team')}: ${centsDetail(event, 'budgetCents')}${periodSuffix(detail(event, 'period') as BudgetPeriodId)}`
    case 'budget.alert': return `${detail(event, 'team')} used ${detail(event, 'threshold')}%: ${centsDetail(event, 'usedCents')} of ${centsDetail(event, 'budgetCents')}`
    case 'order.opened': return `order ${detail(event, 'order').slice(0, 8)}, ${detail(event, 'volume')} leads`
    case 'order.moved': return `order ${detail(event, 'order').slice(0, 8)}: ${detail(event, 'from') || 'Whole account'} → ${detail(event, 'to') || 'Whole account'}`
    case 'data.exported': return `${detail(event, 'rows')} rows of ${detail(event, 'kind')}`
    default: return detail(event, 'email')
  }
}

function eventIcon(action: string): string {
  if (action.startsWith('team.')) return 'mdi-folder-outline'
  if (action.startsWith('budget.')) return 'mdi-cash-multiple'
  if (action.startsWith('order.')) return 'mdi-clipboard-text-outline'
  if (action.startsWith('owner.')) return 'mdi-crown-outline'
  if (action.startsWith('data.')) return 'mdi-download'
  return 'mdi-account-outline'
}

async function loadEvents(): Promise<void> {
  activityLoading.value = true
  try {
    const response = await api('/team/events')
    events.value = response.ok ? (await response.json() as { events: TeamEvent[] }).events : []
  } finally {
    activityLoading.value = false
  }
}

async function reload(): Promise<void> {
  now.value = Date.now()
  await Promise.all([
    team.load(),
    ...(showStats.value ? [leadStore.load(), market.loadOrders()] : []),
    ...(tab.value === 'activity' ? [loadEvents()] : []),
  ])
}

watch(tab, (value) => {
  if (value === 'activity') void loadEvents()
})

watch(
  () => session.isPartner,
  (partner) => {
    if (partner) {
      void reload()
    }
  },
  { immediate: true },
)

clock = setInterval(() => { now.value = Date.now() }, 60_000)
onBeforeUnmount(() => {
  if (clock) clearInterval(clock)
})
</script>

<style scoped>
.team-page {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.team-page__actions {
  align-items: center;
  display: flex;
  flex-wrap: wrap;
  gap: 10px;
}

.team-place {
  align-items: center;
  background: var(--lm-lavender);
  border-radius: 12px;
  color: var(--lm-primary-deep);
  display: flex;
  flex-wrap: wrap;
  font-size: 13px;
  gap: 8px;
  padding: 10px 14px;
}

.team-place__hint {
  color: var(--lm-muted);
}

.team-tabs {
  display: flex;
}

.team-search {
  align-items: center;
  border: 1px solid var(--lm-border);
  border-radius: 10px;
  color: var(--lm-muted);
  display: flex;
  gap: 6px;
  padding: 6px 10px;
}

.team-search input {
  border: 0;
  font-size: 13px;
  min-width: 200px;
  outline: none;
}

.team-empty {
  align-items: center;
  color: var(--lm-muted);
  display: flex;
  flex-direction: column;
  gap: 8px;
  padding: 40px 16px;
  text-align: center;
}

.team-empty strong {
  color: var(--lm-ink, inherit);
  font-size: 16px;
}

.team-empty p {
  max-width: 520px;
}

.team-empty__actions {
  display: flex;
  flex-wrap: wrap;
  gap: 10px;
  justify-content: center;
}

.team-tree {
  display: flex;
  flex-direction: column;
}

.team-tree__row {
  align-items: center;
  border-bottom: 1px solid var(--lm-border);
  display: grid;
  gap: 12px;
  grid-template-columns: minmax(260px, 2.4fr) minmax(110px, 1fr) minmax(170px, 1.3fr) 44px;
  min-height: 48px;
  padding: 6px 8px;
}

.team-tree__row--stats {
  grid-template-columns: minmax(260px, 2.4fr) minmax(110px, 1fr) 70px 100px 80px 64px minmax(190px, 1.3fr) 44px;
}

.team-tree__row--head {
  background: var(--lm-lavender);
  border-bottom: 0;
  border-radius: 10px;
  font-size: 12px;
  font-weight: 700;
  min-height: 36px;
}

.team-tree__row--account {
  background: color-mix(in srgb, var(--lm-lavender) 45%, transparent);
}

.team-tree__row--match .team-tree__label {
  background: color-mix(in srgb, var(--lm-primary) 10%, transparent);
  border-radius: 6px;
}

.team-tree__name {
  align-items: center;
  display: flex;
  gap: 6px;
  min-width: 0;
}

.team-tree__toggle,
.team-tree__more {
  align-items: center;
  background: none;
  border: 0;
  border-radius: 6px;
  color: var(--lm-muted);
  cursor: pointer;
  display: inline-flex;
  height: 24px;
  justify-content: center;
  padding: 0;
  width: 24px;
}

.team-tree__toggle:hover,
.team-tree__more:hover {
  background: var(--lm-lavender);
  color: var(--lm-primary);
}

.team-tree__toggle-space {
  display: inline-block;
  flex: 0 0 24px;
}

.team-tree__icon {
  color: var(--lm-primary);
  flex: 0 0 auto;
}

.team-tree__label {
  align-items: center;
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
  min-width: 0;
  padding: 1px 4px;
}

.team-tree__label small {
  color: var(--lm-muted);
  font-size: 12px;
}

.team-tree__role {
  font-size: 13px;
  font-weight: 600;
}

.team-tree__num {
  font-variant-numeric: tabular-nums;
  text-align: right;
}

.team-tree__budget {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.team-tree__budget small {
  color: var(--lm-muted);
  font-size: 12px;
}

.team-tree__budget small.team-tree__tone--warn {
  color: #b7791f;
}

.team-tree__budget small.team-tree__tone--over {
  color: #c2405f;
}

.team-tree__actions {
  display: flex;
  justify-content: flex-end;
}

.team-tree__nothing {
  color: var(--lm-muted);
  padding: 24px 8px;
  text-align: center;
}

.team-budget-bar {
  background: var(--lm-border);
  border-radius: 999px;
  display: block;
  height: 6px;
  overflow: hidden;
}

.team-budget-bar__fill {
  background: var(--lm-primary);
  border-radius: 999px;
  display: block;
  height: 100%;
}

.team-budget-bar__fill--warn {
  background: #e0a526;
}

.team-budget-bar__fill--over {
  background: #c2405f;
}

.team-you {
  background: var(--lm-lavender);
  border-radius: 6px;
  color: var(--lm-primary-deep);
  font-size: 11px;
  font-weight: 600;
  padding: 1px 6px;
}

.team-activity {
  display: flex;
  flex-direction: column;
  list-style: none;
  margin: 0;
  padding: 0;
}

.team-activity__item {
  align-items: flex-start;
  border-bottom: 1px solid var(--lm-border);
  display: flex;
  gap: 10px;
  padding: 10px 4px;
}

.team-activity__text {
  display: flex;
  flex-direction: column;
  gap: 2px;
  font-size: 13px;
}

.team-activity__text small {
  color: var(--lm-muted);
  font-size: 12px;
}

@media (max-width: 760px) {
  .team-search input {
    min-width: 0;
    width: 100%;
  }

  .team-tree__row,
  .team-tree__row--stats {
    gap: 4px 12px;
    grid-template-columns: 1fr auto;
  }

  .team-tree__row--head {
    display: none;
  }

  .team-tree__name {
    grid-column: 1 / 2;
  }

  .team-tree__actions {
    grid-column: 2 / 3;
    grid-row: 1 / 2;
  }

  .team-tree__role,
  .team-tree__budget {
    grid-column: 1 / -1;
    padding-left: 30px;
  }

  .team-tree__num {
    display: none;
  }
}
</style>
