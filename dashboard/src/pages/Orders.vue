<template>
  <div>
    <div class="page-head">
      <div>
        <h1>{{ isPartner ? 'My orders' : 'Orders' }}</h1>
        <p v-if="isPartner">Day and stock orders you opened. Create new day orders from Buy leads.</p>
        <p v-else>Day orders for today or tomorrow. Partner payout is at or above the preset floor; the platform fee is charged to the buyer.</p>
      </div>
      <UiButton v-if="isPartner" variant="outline" icon="mdi-warehouse" @click="goBuy">Buy leads</UiButton>
      <UiButton v-else variant="outline" icon="mdi-plus" @click="show">Create</UiButton>
    </div>

    <OrderFormDialog v-if="!isPartner" v-model="open" />

    <v-alert v-if="actionError || market.error" type="error" variant="tonal" class="mb-4">
      {{ actionError || market.error }}
    </v-alert>

    <UiTableCard title="Orders">
      <template #actions>
        <UiButton variant="outline" icon="mdi-refresh" :loading="market.loading" @click="refresh()">
          Refresh
        </UiButton>
      </template>
      <UiDataTable :headers="headers" :items="market.orders" :loading="market.loading" item-value="id" hover>
        <template #item.status="{ item }">
          <StatusChip :status="item.status" />
        </template>
        <template #item.geo="{ item }">
          <CountryPick :code="item.geo" />
        </template>
        <template #item.kind="{ item }">
          {{ item.stock ? 'Stock' : item.serviceOn }}
        </template>
        <template #item.opensAt="{ item }">
          {{ item.opensAt }}–{{ item.closesAt }}
        </template>
        <template #item.paymentModel="{ item }">
          {{ modelLabel(item.paymentModel) }}
        </template>
        <template #item.priceCents="{ item }">
          {{ dollars(item.priceCents / 100) }}
        </template>
        <template #item.partnerPriceCents="{ item }">
          {{ dollars(item.partnerPriceCents / 100) }}
        </template>
        <template #item.supplied="{ item }">
          {{ item.supplied }} / {{ item.volume }}
        </template>
        <template #item.actions="{ item }">
          <div class="order-actions">
            <UiButton v-if="item.status === 'in_progress'" variant="outline" :loading="acting === item.id" @click="act(item.id, 'pause')">
              Pause
            </UiButton>
            <UiButton v-if="item.status === 'paused'" variant="outline" :loading="acting === item.id" @click="act(item.id, 'resume')">
              Resume
            </UiButton>
            <UiButton v-if="item.status !== 'finished'" variant="ghost" :loading="acting === item.id" @click="act(item.id, 'finish')">
              Finish
            </UiButton>
          </div>
        </template>
        <template #no-data>
          <div class="py-8 text-medium-emphasis">Заказов пока нет</div>
        </template>
      </UiDataTable>
    </UiTableCard>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import CountryPick from '@/components/CountryPick.vue'
import OrderFormDialog from '@/components/OrderFormDialog.vue'
import StatusChip from '@/components/StatusChip.vue'
import UiButton from '@/components/ui/UiButton.vue'
import UiDataTable from '@/components/ui/UiDataTable.vue'
import UiTableCard from '@/components/ui/UiTableCard.vue'
import { modelLabel } from '@/marketOptions'
import { dollars } from '@/money'
import { useMarketStore } from '@/stores/market'
import { useSessionStore } from '@/stores/session'

const router = useRouter()
const session = useSessionStore()
const market = useMarketStore()
const isPartner = computed(() => session.payload?.user?.role === 'partner')
const open = ref(false)
const acting = ref('')
const actionError = ref('')

const headers = computed(() => {
  const base = [
    { title: 'Status', key: 'status' },
    { title: 'Traffic', key: 'trafficSource' },
    { title: 'Model', key: 'paymentModel' },
    { title: 'Geo', key: 'geo' },
    { title: 'When', key: 'kind' },
    { title: 'Hours', key: 'opensAt' },
    { title: 'Supplied', key: 'supplied' },
  ]
  if (isPartner.value) {
    return [...base, { title: 'You pay', key: 'partnerPriceCents' }, { title: '', key: 'actions', sortable: false }]
  }
  return [
    ...base,
    { title: 'Catalog price', key: 'priceCents' },
    { title: 'Partner pays', key: 'partnerPriceCents' },
    { title: '', key: 'actions', sortable: false },
  ]
})

async function refresh(): Promise<void> {
  await market.loadPresets()
  await market.loadOrders()
}

function show(): void {
  open.value = true
}

function goBuy(): void {
  void router.push('/stock')
}

async function act(id: string, action: 'pause' | 'resume' | 'finish'): Promise<void> {
  acting.value = id
  actionError.value = ''
  try {
    await market.actOrder(id, action)
  } catch (error) {
    actionError.value = error instanceof Error ? error.message : 'Статус не сменился'
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
