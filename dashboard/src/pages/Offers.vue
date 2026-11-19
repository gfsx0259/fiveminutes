<template>
  <div>
    <div class="page-head">
      <div>
        <h1>Offers</h1>
        <p>{{ isAdmin ? 'Approve a supplier before leads are accepted.' : 'Offer volume against a cap. Leads start after an admin approves it.' }}</p>
      </div>
      <UiButton v-if="isBuyer" variant="outline" icon="mdi-plus" @click="show">Create</UiButton>
    </div>

    <OfferFormDialog v-model="open" />

    <v-alert v-if="actionError || market.error" type="error" variant="tonal" class="mb-4">
      {{ actionError || market.error }}
    </v-alert>

    <UiTableCard title="Offers">
      <template #actions>
        <UiButton variant="outline" icon="mdi-refresh" :loading="market.loading" @click="refresh()">
          Refresh
        </UiButton>
      </template>
      <UiDataTable :headers="headers" :items="market.offers" :loading="market.loading" item-value="id" hover>
        <template #item.status="{ item }">
          <StatusChip :status="item.status" />
        </template>
        <template #item.geo="{ item }">
          <CountryPick :code="item.geo" />
        </template>
        <template #item.when="{ item }">
          {{ item.stock ? 'Stock' : item.serviceOn }}
        </template>
        <template #item.supplied="{ item }">
          {{ item.supplied }} / {{ item.volume }}
        </template>
        <template #item.actions="{ item }">
          <div v-if="isAdmin && item.status === 'pending'" class="order-actions">
            <UiButton :loading="acting === item.id" @click="moderate(item.id, 'approve')">Approve</UiButton>
            <UiButton variant="outline" :loading="acting === item.id" @click="moderate(item.id, 'reject')">Reject</UiButton>
          </div>
        </template>
        <template #no-data>
          <div class="py-8 text-medium-emphasis">Предложений пока нет</div>
        </template>
      </UiDataTable>
    </UiTableCard>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import CountryPick from '@/components/CountryPick.vue'
import OfferFormDialog from '@/components/OfferFormDialog.vue'
import StatusChip from '@/components/StatusChip.vue'
import UiButton from '@/components/ui/UiButton.vue'
import UiDataTable from '@/components/ui/UiDataTable.vue'
import UiTableCard from '@/components/ui/UiTableCard.vue'
import { useMarketStore } from '@/stores/market'
import { useSessionStore } from '@/stores/session'

const session = useSessionStore()
const market = useMarketStore()
const isAdmin = computed(() => session.payload?.user?.role === 'admin')
const isBuyer = computed(() => session.payload?.user?.role === 'buyer')
const open = ref(false)
const acting = ref('')
const actionError = ref('')

const headers = computed(() => [
  { title: 'Status', key: 'status' },
  { title: 'Traffic', key: 'trafficSource' },
  { title: 'Geo', key: 'geo' },
  { title: 'Date', key: 'when' },
  { title: 'Supplied', key: 'supplied' },
  ...(isAdmin.value ? [{ title: 'Supplier', key: 'buyerEmail' }] : []),
  { title: '', key: 'actions', sortable: false },
])

async function refresh(): Promise<void> {
  await market.loadPresets()
  await market.loadOffers()
}

function show(): void {
  open.value = true
}

async function moderate(id: string, action: 'approve' | 'reject'): Promise<void> {
  acting.value = id
  actionError.value = ''
  try {
    await market.moderateOffer(id, action)
  } catch (error) {
    actionError.value = error instanceof Error ? error.message : 'Модерация не прошла'
  } finally {
    acting.value = ''
  }
}

onMounted(() => {
  void refresh()
})
</script>

<style scoped>
.order-actions {
  display: flex;
  gap: 8px;
}
</style>
