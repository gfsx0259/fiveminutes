<template>
  <div>
    <div class="mb-4">
      <h1>Slots</h1>
      <p>{{ isBuyer ? 'A volume you promise to mine' : 'Open buyer slots in this organization' }}</p>
    </div>

    <ListingForm
      v-if="isBuyer"
      kind="slot"
      title="Open a slot"
      hint="The price is what you receive. The partner pays 10% on top. One partner accepts the whole slot."
      submit-label="Open slot"
      :busy="busy"
      :error="formError"
      @submit="create"
    />

    <v-alert v-if="market.error" type="error" variant="tonal" class="mb-4">{{ market.error }}</v-alert>

    <UiTableCard title="Slots">
      <template #actions>
        <UiButton variant="outline" icon="mdi-refresh" :loading="market.loading" @click="market.loadSlots()">
          Refresh
        </UiButton>
      </template>
      <UiDataTable
        :headers="headers"
        :items="market.slots"
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
        <template #item.actions="{ item }">
          <UiButton
            v-if="!isBuyer && item.status === 'open'"
            :loading="accepting === item.id"
            @click="accept(item.id)"
          >
            Accept
          </UiButton>
        </template>
        <template #no-data>
          <div class="py-8 text-medium-emphasis">Слотов пока нет</div>
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

const headers = computed(() => [
  { title: 'Status', key: 'status' },
  { title: 'Geo', key: 'geo' },
  { title: 'Volume', key: 'volume' },
  { title: 'Buyer price', key: 'priceCents' },
  { title: 'Model', key: 'pricingModel' },
  { title: 'Deadline', key: 'deadline' },
  { title: 'Buyer', key: 'buyerEmail' },
  { title: '', key: 'actions', sortable: false },
])

async function create(body: Record<string, string | number>): Promise<void> {
  busy.value = true
  formError.value = ''
  try {
    await market.createSlot(body)
    await market.loadSlots()
  } catch (error) {
    formError.value = error instanceof Error ? error.message : 'Слот не открылся'
  } finally {
    busy.value = false
  }
}

async function accept(id: string): Promise<void> {
  accepting.value = id
  market.error = ''
  try {
    await market.acceptSlot(id)
    await market.loadSlots()
  } catch (error) {
    market.error = error instanceof Error ? error.message : 'Слот не принялся'
  } finally {
    accepting.value = ''
  }
}

onMounted(() => {
  void market.loadSlots()
})
</script>
