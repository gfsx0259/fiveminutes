<template>
  <FormDialog
    :model-value="modelValue"
    :title="member ? `Access · ${member.email}` : 'Invite a person'"
    :hint="member ? 'Pick a role or set each right' : 'The person signs up with this email and works inside the chosen team'"
    :error="error"
    :submit-label="member ? 'Save access' : 'Send invite'"
    :submit-icon="member ? 'mdi-check' : 'mdi-account-plus-outline'"
    :loading="busy"
    :max-width="640"
    @update:model-value="emit('update:modelValue', $event)"
    @submit="submit"
  >
    <UiField v-if="!member" label="Email">
      <UiInput v-model="email" type="email" autocomplete="off" placeholder="name@company.com" />
    </UiField>

    <UiField label="Team">
      <UiSelect v-model="teamId" :items="teamItems" placeholder="Pick a team" />
    </UiField>

    <UiField label="Role">
      <div class="team-roles">
        <UiSegmented v-model="roleId" :items="roleItems" />
        <span class="team-roles__hint">{{ roleHint }}</span>
      </div>
    </UiField>

    <div class="team-rights" role="tree">
      <section v-for="section in sections" :key="section.title" class="team-rights__section" role="treeitem">
        <label class="team-rights__head">
          <input
            type="checkbox"
            :checked="sectionState(section) === 'all'"
            :indeterminate="sectionState(section) === 'some'"
            @change="toggleSection(section, ($event.target as HTMLInputElement).checked)"
          >
          <span>{{ section.title }}</span>
        </label>
        <label
          v-for="permission in section.permissions"
          :key="permission.id"
          class="team-rights__item"
          :class="{ 'team-rights__item--off': !grantable(permission.id) }"
        >
          <input
            type="checkbox"
            :checked="granted.has(permission.id)"
            :disabled="!grantable(permission.id)"
            @change="toggle(permission.id, ($event.target as HTMLInputElement).checked)"
          >
          <span class="team-rights__text">
            <strong>{{ permission.label }}</strong>
            <small>{{ permission.description }}</small>
          </span>
        </label>
      </section>
    </div>
  </FormDialog>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { apiPost, apiPut } from '@/api'
import FormDialog from '@/components/FormDialog.vue'
import UiField from '@/components/ui/UiField.vue'
import UiInput from '@/components/ui/UiInput.vue'
import UiSegmented from '@/components/ui/UiSegmented.vue'
import UiSelect from '@/components/ui/UiSelect.vue'
import { useSessionStore } from '@/stores/session'
import { roleOf, teamOptions, type TeamMember, type TeamPermission, type TeamPermissionInfo, type TeamRoster } from '@/team'

interface RightsSection {
  title: string
  permissions: TeamPermissionInfo[]
}

const CUSTOM = 'custom'

const props = defineProps<{
  modelValue: boolean
  roster: TeamRoster
  member?: TeamMember | null
  team?: string | null
}>()

const emit = defineEmits<{
  'update:modelValue': [value: boolean]
  saved: [member: TeamMember]
}>()

const session = useSessionStore()
const email = ref('')
const teamId = ref('')
const granted = ref(new Set<TeamPermission>())

const teamItems = computed(() => teamOptions(
  props.roster.teams,
  (team) => team.canBuild,
  props.roster.viewer.canBuild && props.roster.viewer.teamId === null,
))

function grantable(permission: TeamPermission): boolean {
  return props.roster.viewer.owner || session.can(permission)
}
const busy = ref(false)
const error = ref('')

const sections = computed((): RightsSection[] => {
  const grouped = new Map<string, TeamPermissionInfo[]>()
  for (const permission of props.roster.permissions) {
    grouped.set(permission.section, [...(grouped.get(permission.section) ?? []), permission])
  }
  return [...grouped].map(([title, permissions]) => ({ title, permissions }))
})

const matchedRole = computed(() => roleOf(props.roster.roles, granted.value))

const roleItems = computed(() => [
  ...props.roster.roles.map((role) => ({ title: role.label, value: role.id })),
  ...(matchedRole.value ? [] : [{ title: 'Custom', value: CUSTOM }]),
])

const roleId = computed({
  get: () => matchedRole.value?.id ?? CUSTOM,
  set: (id: string) => {
    const role = props.roster.roles.find((item) => item.id === id)
    if (role) {
      granted.value = new Set(role.permissions.filter(grantable))
    }
  },
})

const roleHint = computed(() => matchedRole.value?.description ?? `${granted.value.size} rights picked by hand`)

function sectionState(section: RightsSection): 'all' | 'some' | 'none' {
  const on = section.permissions.filter((permission) => granted.value.has(permission.id)).length
  if (on === 0) {
    return 'none'
  }
  return on === section.permissions.length ? 'all' : 'some'
}

function toggle(permission: TeamPermission, on: boolean): void {
  const next = new Set(granted.value)
  if (on) {
    next.add(permission)
  } else {
    next.delete(permission)
  }
  granted.value = next
}

function toggleSection(section: RightsSection, on: boolean): void {
  const next = new Set(granted.value)
  for (const permission of section.permissions) {
    if (on) {
      next.add(permission.id)
    } else {
      next.delete(permission.id)
    }
  }
  granted.value = next
}

async function submit(): Promise<void> {
  busy.value = true
  error.value = ''
  const permissions = [...granted.value]
  try {
    const body = props.member
      ? await apiPut<{ member: TeamMember }>(`/team/${props.member.id}`, { team_id: teamId.value || null, permissions })
      : await apiPost<{ member: TeamMember }>('/team', { email: email.value.trim(), team_id: teamId.value || null, permissions })
    emit('saved', body.member)
    emit('update:modelValue', false)
  } catch (err) {
    error.value = err instanceof Error ? err.message : 'Failed to save access'
  } finally {
    busy.value = false
  }
}

watch(
  () => props.modelValue,
  (open) => {
    if (!open) {
      return
    }
    error.value = ''
    email.value = ''
    teamId.value = (props.member ? props.member.teamId : props.team ?? props.roster.viewer.teamId) ?? ''
    granted.value = new Set(props.member?.permissions ?? props.roster.roles.find((role) => role.id === 'view')?.permissions ?? [])
  },
  { immediate: true },
)
</script>

<style scoped>
.team-roles {
  align-items: center;
  display: flex;
  flex-wrap: wrap;
  gap: 10px;
}

.team-roles__hint {
  color: var(--lm-muted);
  font-size: 12px;
}

.team-rights {
  border: 1px solid var(--lm-border);
  border-radius: 12px;
  display: flex;
  flex-direction: column;
  max-height: 46vh;
  overflow-y: auto;
}

.team-rights__section {
  border-bottom: 1px solid var(--lm-border);
  display: flex;
  flex-direction: column;
  gap: 2px;
  padding: 10px 14px;
}

.team-rights__section:last-child {
  border-bottom: 0;
}

.team-rights__head,
.team-rights__item {
  align-items: flex-start;
  cursor: pointer;
  display: flex;
  gap: 10px;
}

.team-rights__head {
  font-size: 13px;
  font-weight: 700;
  padding: 2px 0 4px;
}

.team-rights__item {
  padding: 4px 0 4px 24px;
}

.team-rights__item--off {
  cursor: not-allowed;
  opacity: 0.45;
}

.team-rights input {
  accent-color: var(--lm-primary);
  height: 16px;
  margin-top: 1px;
  width: 16px;
}

.team-rights__text {
  display: flex;
  flex-direction: column;
  gap: 1px;
  min-width: 0;
}

.team-rights__text strong {
  font-size: 13px;
  font-weight: 600;
}

.team-rights__text small {
  color: var(--lm-muted);
  font-size: 12px;
}
</style>
