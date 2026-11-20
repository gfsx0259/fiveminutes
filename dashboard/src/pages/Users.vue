<template>
  <div class="users-page">
    <div class="page-head">
      <div>
        <h1>Users</h1>
        <p>Organization members. Sign in as a partner or buyer to see their cabinet.</p>
      </div>
    </div>

    <v-alert v-if="error" type="error" variant="tonal" class="mb-4" closable @click:close="error = ''">
      {{ error }}
    </v-alert>

    <UiTableCard title="Users">
      <UiDataTable
        :headers="headers"
        :items="items"
        :loading="loading"
        item-value="id"
        hover
        fixed-header
        height="calc(100vh - 280px)"
      >
        <template #item.role="{ item }">
          <span class="users-role">{{ item.role }}</span>
        </template>
        <template #item.actions="{ item }">
          <UiButton
            v-if="item.role !== 'admin'"
            variant="outline"
            size="small"
            :disabled="impersonatingId === item.id"
            @click="signInAs(item)"
          >
            Sign in as
          </UiButton>
          <span v-else class="text-medium-emphasis">—</span>
        </template>
      </UiDataTable>
    </UiTableCard>
  </div>
</template>

<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { api } from '@/api'
import UiButton from '@/components/ui/UiButton.vue'
import UiDataTable from '@/components/ui/UiDataTable.vue'
import UiTableCard from '@/components/ui/UiTableCard.vue'
import { useSessionStore } from '@/stores/session'

interface OrgUser {
  id: string
  email: string
  role: string
}

const session = useSessionStore()
const items = ref<OrgUser[]>([])
const loading = ref(false)
const error = ref('')
const impersonatingId = ref<string | null>(null)

const headers = [
  { title: 'Email', key: 'email' },
  { title: 'Role', key: 'role' },
  { title: '', key: 'actions', sortable: false, width: 140 },
]

async function load(): Promise<void> {
  loading.value = true
  error.value = ''
  try {
    const response = await api('/users')
    const data = await response.json() as { items?: OrgUser[]; error?: string }
    if (!response.ok) {
      throw new Error(data.error ?? `Request failed: ${response.status}`)
    }
    items.value = data.items ?? []
  } catch (err) {
    error.value = err instanceof Error ? err.message : 'Failed to load users'
  } finally {
    loading.value = false
  }
}

async function signInAs(item: OrgUser): Promise<void> {
  impersonatingId.value = item.id
  error.value = ''
  try {
    await session.startImpersonation(item.id, item.role)
  } catch (err) {
    error.value = err instanceof Error ? err.message : 'Failed to sign in as user'
    impersonatingId.value = null
  }
}

onMounted(() => {
  void load()
})
</script>

<style scoped>
.users-page {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.users-role {
  text-transform: capitalize;
}
</style>