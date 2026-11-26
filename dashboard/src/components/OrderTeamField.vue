<template>
  <UiField v-if="items.length > 1" label="Team" icon="mdi-folder-account-outline">
    <UiSelect v-model="model" :items="items" placeholder="Pick a team" />
  </UiField>
</template>

<script setup lang="ts">
import { computed, onMounted, watch } from 'vue'
import UiField from '@/components/ui/UiField.vue'
import UiSelect from '@/components/ui/UiSelect.vue'
import { useSessionStore } from '@/stores/session'
import { useTeamStore } from '@/stores/team'
import { teamOptions } from '@/team'

const model = defineModel<string | undefined>({ default: undefined })

const session = useSessionStore()
const team = useTeamStore()

const items = computed(() => team.roster
  ? teamOptions(team.roster.teams, () => true, team.roster.viewer.teamId === null)
  : [])

watch(
  () => [team.roster, model.value] as const,
  ([roster, value]) => {
    if (roster && value === undefined) {
      model.value = roster.viewer.teamId ?? ''
    }
  },
  { immediate: true },
)

onMounted(() => {
  if (session.isPartner && !team.roster) {
    void team.load()
  }
})
</script>
