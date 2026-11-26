<template>
  <FormDialog
    :model-value="modelValue"
    title="Move order to a team"
    hint="The order, its leads and its spend move to the chosen team and count against its budget"
    :error="error"
    submit-label="Move"
    submit-icon="mdi-folder-move-outline"
    :loading="busy"
    :max-width="460"
    @update:model-value="emit('update:modelValue', $event)"
    @submit="submit"
  >
    <UiField label="Team">
      <UiSelect v-model="teamId" :items="items" placeholder="Pick a team" />
    </UiField>
  </FormDialog>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { apiPut } from '@/api'
import FormDialog from '@/components/FormDialog.vue'
import UiField from '@/components/ui/UiField.vue'
import UiSelect from '@/components/ui/UiSelect.vue'
import { teamOptions, type TeamRoster } from '@/team'

const props = defineProps<{
  modelValue: boolean
  roster: TeamRoster
  orderId: string
  current: string | null
}>()

const emit = defineEmits<{
  'update:modelValue': [value: boolean]
  saved: []
}>()

const teamId = ref('')
const busy = ref(false)
const error = ref('')

const items = computed(() => teamOptions(props.roster.teams, () => true, props.roster.viewer.teamId === null))

async function submit(): Promise<void> {
  busy.value = true
  error.value = ''
  try {
    await apiPut(`/team/orders/${props.orderId}`, { team_id: teamId.value || null })
    emit('saved')
    emit('update:modelValue', false)
  } catch (err) {
    error.value = err instanceof Error ? err.message : 'Failed to move the order'
  } finally {
    busy.value = false
  }
}

watch(
  () => props.modelValue,
  (open) => {
    if (open) {
      error.value = ''
      teamId.value = props.current ?? ''
    }
  },
  { immediate: true },
)
</script>
