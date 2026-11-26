<template>
  <v-dialog :model-value="modelValue" max-width="720" @update:model-value="emit('update:modelValue', $event)">
    <div class="partner-team">
      <header class="partner-team__head">
        <div>
          <h2>Team · {{ partner.email }}</h2>
          <p>Teams and people inside this partner account. Remove a person here when the partner asks support to.</p>
        </div>
        <button type="button" class="partner-team__close" aria-label="Close" @click="emit('update:modelValue', false)">
          <v-icon icon="mdi-close" size="20" />
        </button>
      </header>

      <v-alert v-if="error" type="error" variant="tonal" closable @click:close="error = ''">{{ error }}</v-alert>
      <div v-if="loading" class="partner-team__empty">Loading…</div>
      <div v-else-if="roster && rows.length === 0" class="partner-team__empty">This partner works alone, no teams or people yet</div>

      <ul v-else class="partner-team__list">
        <li v-for="row in rows" :key="row.key" class="partner-team__row" :style="{ paddingLeft: `${row.depth * 20 + 8}px` }">
          <v-icon :icon="row.member ? 'mdi-account-outline' : 'mdi-folder-outline'" size="18" class="partner-team__icon" />
          <span class="partner-team__label">
            <strong v-if="!row.member">{{ row.label }}</strong>
            <span v-else>{{ row.label }}</span>
            <small>{{ row.caption }}</small>
          </span>
          <UiButton
            v-if="row.member"
            variant="outline"
            size="small"
            :disabled="busy === row.member.id"
            @click="remove(row.member)"
          >
            Remove
          </UiButton>
        </li>
      </ul>
    </div>
  </v-dialog>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { api, apiDelete } from '@/api'
import UiButton from '@/components/ui/UiButton.vue'
import { confirmAction } from '@/composables/useConfirm'
import { showToast } from '@/composables/useToast'
import { roleOf, type TeamMember, type TeamRoster, type TeamUnit } from '@/team'

interface Row {
  key: string
  depth: number
  label: string
  caption: string
  member: TeamMember | null
}

const props = defineProps<{
  modelValue: boolean
  partner: { id: string; email: string }
}>()

const emit = defineEmits<{
  'update:modelValue': [value: boolean]
}>()

const roster = ref<TeamRoster | null>(null)
const loading = ref(false)
const error = ref('')
const busy = ref<string | null>(null)

function personRow(member: TeamMember, depth: number, all: TeamRoster): Row {
  const role = roleOf(all.roles, member.permissions)
  return {
    key: member.id,
    depth,
    label: member.email,
    caption: `${role?.label ?? 'Custom'} · ${member.status}`,
    member,
  }
}

function teamRows(unit: TeamUnit, depth: number, all: TeamRoster): Row[] {
  return [
    { key: unit.id, depth, label: unit.name, caption: '', member: null },
    ...all.members.filter((member) => member.teamId === unit.id).map((member) => personRow(member, depth + 1, all)),
    ...all.teams.filter((child) => child.parentId === unit.id).flatMap((child) => teamRows(child, depth + 1, all)),
  ]
}

const rows = computed((): Row[] => {
  const all = roster.value
  if (!all) return []
  return [
    ...all.members.filter((member) => member.teamId === null).map((member) => personRow(member, 0, all)),
    ...all.teams.filter((unit) => unit.parentId === null).flatMap((unit) => teamRows(unit, 0, all)),
  ]
})

async function load(): Promise<void> {
  loading.value = true
  error.value = ''
  try {
    const response = await api(`/users/${props.partner.id}/team`)
    const data = await response.json() as TeamRoster & { error?: string }
    if (!response.ok) throw new Error(data.error ?? `Request failed: ${response.status}`)
    roster.value = data
  } catch (err) {
    error.value = err instanceof Error ? err.message : 'Failed to load the team'
  } finally {
    loading.value = false
  }
}

async function remove(member: TeamMember): Promise<void> {
  const sure = await confirmAction({
    title: `Remove ${member.email}?`,
    text: `The person loses access to ${props.partner.email} right away. The partner sees this in the team activity.`,
    confirmLabel: 'Remove',
    danger: true,
  })
  if (!sure) return
  busy.value = member.id
  try {
    await apiDelete(`/users/${props.partner.id}/team/${member.id}`)
    showToast('Person removed')
    await load()
  } catch (err) {
    error.value = err instanceof Error ? err.message : 'Failed to remove the person'
  } finally {
    busy.value = null
  }
}

watch(
  () => props.modelValue,
  (open) => {
    if (open) void load()
  },
  { immediate: true },
)
</script>

<style scoped>
.partner-team {
  background: var(--lm-surface, #fff);
  border-radius: 16px;
  display: flex;
  flex-direction: column;
  gap: 12px;
  max-height: 80vh;
  overflow-y: auto;
  padding: 20px 24px;
}

.partner-team__head {
  align-items: flex-start;
  display: flex;
  gap: 12px;
  justify-content: space-between;
}

.partner-team__head h2 {
  font-size: 18px;
}

.partner-team__head p {
  color: var(--lm-muted);
  font-size: 13px;
}

.partner-team__close {
  background: none;
  border: 0;
  color: var(--lm-muted);
  cursor: pointer;
}

.partner-team__empty {
  color: var(--lm-muted);
  padding: 24px 0;
  text-align: center;
}

.partner-team__list {
  display: flex;
  flex-direction: column;
  list-style: none;
  margin: 0;
  padding: 0;
}

.partner-team__row {
  align-items: center;
  border-bottom: 1px solid var(--lm-border);
  display: flex;
  gap: 8px;
  min-height: 44px;
  padding-right: 8px;
}

.partner-team__icon {
  color: var(--lm-primary);
}

.partner-team__label {
  align-items: baseline;
  display: flex;
  flex: 1;
  flex-wrap: wrap;
  gap: 8px;
}

.partner-team__label small {
  color: var(--lm-muted);
  font-size: 12px;
}
</style>
