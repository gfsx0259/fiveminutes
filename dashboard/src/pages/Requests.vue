<template>
  <div>
    <div class="mb-4">
      <h1>Partner requests</h1>
      <p>{{ isBuyer ? 'Demands a partner posted. You take the whole one.' : 'A demand you post for buyers in this organization' }}</p>
    </div>

    <ListingForm
      v-if="!isBuyer"
      kind="demand"
      title="Open a request"
      hint="The price is what the buyer receives. You pay 10% on top. One buyer takes the whole request. Hours are the call center clock, timezone is not set yet."
      submit-label="Open request"
      :busy="busy"
      :error="formError"
      @submit="create"
    />

    <v-alert v-if="market.error" type="error" variant="tonal" class="mb-4">{{ market.error }}</v-alert>

    <UiTableCard title="Requests">
      <template #actions>
        <UiButton variant="outline" icon="mdi-refresh" :loading="market.loading" @click="market.loadDemands()">
          Refresh
        </UiButton>
      </template>
      <UiDataTable
        :headers="headers"
        :items="market.demands"
        :loading="market.loading"
        :items-per-page="25"
        item-value="id"
        hover
      >
        <template #item.status="{ item }">
          <StatusChip :status="item.status" />
        </template>
        <template #item.geo="{ item }">
          {{ item.geo ? `${flag(item.geo)} ${item.geo}` : '—' }}
        </template>
        <template #item.priceCents="{ item }">
          {{ dollars(item.priceCents / 100) }}
        </template>
        <template #item.pricingModel="{ item }">
          {{ item.pricingModel.toUpperCase() }}
        </template>
        <template #item.hours="{ item }">
          {{ item.serviceOn }} {{ item.opensAt }}–{{ item.closesAt }}
        </template>
        <template #item.actions="{ item }">
          <UiButton
            v-if="isBuyer && item.status === 'open'"
            :loading="accepting === item.id"
            @click="accept(item.id)"
          >
            Accept
          </UiButton>
        </template>
        <template #no-data>
          <div class="py-8 text-medium-emphasis">Заявок пока нет</div>
        </template>
      </UiDataTable>
    </UiTableCard>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import ListingForm from '@/components/ListingForm.vue'
import StatusChip from '@/components/StatusChip.vue'
import UiButton from '@/components/ui/UiButton.vue'
import UiDataTable from '@/components/ui/UiDataTable.vue'
import UiTableCard from '@/components/ui/UiTableCard.vue'
import { dollars, flag } from '@/money'
import { useMarketStore } from '@/stores/market'
import { useSessionStore } from '@/stores/session'

const session = useSessionStore()
const market = useMarketStore()
const isBuyer = computed(() => session.payload?.user?.role === 'buyer')
const busy = ref(false)
const formError = ref('')
const accepting = ref('')

const headers = [
  { title: 'Status', key: 'status' },
  { title: 'Geo', key: 'geo' },
  { title: 'Volume', key: 'volume' },
  { title: 'Buyer price', key: 'priceCents' },
  { title: 'Model', key: 'pricingModel' },
  { title: 'Call center', key: 'hours', sortable: false },
  { title: 'Partner', key: 'partnerEmail' },
  { title: '', key: 'actions', sortable: false },
]

async function create(body: Record<string, string | number>): Promise<void> {
  busy.value = true
  formError.value = ''
  try {
    await market.createDemand(body)
    await market.loadDemands()
  } catch (error) {
    formError.value = error instanceof Error ? error.message : 'Заявка не открылась'
  } finally {
    busy.value = false
  }
}

async function accept(id: string): Promise<void> {
  accepting.value = id
  market.error = ''
  try {
    await market.acceptDemand(id)
    await market.loadDemands()
  } catch (error) {
    market.error = error instanceof Error ? error.message : 'Заявка не взялась'
  } finally {
    accepting.value = ''
  }
}

onMounted(() => {
  void market.loadDemands()
})
</script>
