<template>
  <FormDialog
    :model-value="modelValue"
    :title="team ? `Edit · ${team.name}` : 'New team'"
    :hint="team ? 'Moving a team takes its people, sub-teams and orders along' : 'A team sees only its own orders, leads and spend, and everything below it'"
    :error="error"
    :submit-label="team ? 'Save' : 'Create team'"
    :submit-icon="team ? 'mdi-check' : 'mdi-folder-plus-outline'"
    :loading="busy"
    :max-width="480"
    @update:model-value="emit('update:modelValue', $event)"
    @submit="submit"
  >
    <UiField label="Name">
      <UiInput v-model="name" autocomplete="off" placeholder="Crypto, Gambling, Crypto EU" />
    </UiField>
    <UiField label="Inside">
      <UiSelect v-model="parentId" :items="parentItems" placeholder="Pick where it lives" />
    </UiField>
  </FormDialog>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { api, apiPost, apiPut } from '@/api'
import FormDialog from '@/components/FormDialog.vue'
import UiField from '@/components/ui/UiField.vue'
import UiInput from '@/components/ui/UiInput.vue'
import UiSelect from '@/components/ui/UiSelect.vue'
import { subtreeOf, teamOptions, type TeamRoster, type TeamUnit } from '@/team'

const props = defineProps<{
  modelValue: boolean
  roster: TeamRoster
  team?: TeamUnit | null
  parent?: string | null
}>()

const emit = defineEmits<{
  'update:modelValue': [value: boolean]
  saved: []
}>()

const name = ref('')
const parentId = ref('')
const busy = ref(false)
const error = ref('')

const parentItems = computed(() => {
  const own = props.team ? subtreeOf(props.roster.teams, props.team.id) : new Set<string>()
  return teamOptions(
    props.roster.teams,
    (team) => team.canBuild && !own.has(team.id),
    props.roster.viewer.canBuild && props.roster.viewer.teamId === null,
  )
})

async function send(path: string, method: string, body: unknown): Promise<void> {
  const response = await api(path, { method, body: JSON.stringify(body) })
  const data = await response.json() as { error?: string; errors?: Record<string, string> }
  if (!response.ok) {
    throw new Error(data.error ?? Object.values(data.errors ?? {})[0] ?? `Request failed: ${response.status}`)
  }
}

async function submit(): Promise<void> {
  busy.value = true
  error.value = ''
  try {
    if (props.team) {
      if (name.value.trim() !== props.team.name) {
        await send(`/team/units/${props.team.id}`, 'PATCH', { name: name.value })
      }
      if ((parentId.value || null) !== props.team.parentId) {
        await apiPut(`/team/units/${props.team.id}/parent`, { parent_id: parentId.value || null })
      }
    } else {
      await apiPost('/team/units', { name: name.value, parent_id: parentId.value || null })
    }
    emit('saved')
    emit('update:modelValue', false)
  } catch (err) {
    error.value = err instanceof Error ? err.message : 'Failed to save the team'
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
    name.value = props.team?.name ?? ''
    parentId.value = (props.team ? props.team.parentId : props.parent ?? props.roster.viewer.teamId) ?? ''
  },
  { immediate: true },
)
</script>
