<template>
  <div class="dashboard">
    <div class="page-head">
      <div>
        <h1>Dashboard</h1>
        <p>Your lead purchases, spend and partner outcomes at a glance.</p>
      </div>
      <div class="dashboard__actions">
        <UiSegmented v-model="periodKey" :items="periodChoices" />
        <UiButton variant="outline" icon="mdi-plus" @click="orderOpen = true">Create order</UiButton>
        <UiButton icon="mdi-cart-outline" @click="router.push('/stock')">Buy leads</UiButton>
      </div>
    </div>

    <v-alert v-if="problem" type="error" variant="tonal" class="mb-4">{{ problem }}</v-alert>

    <WalletHero :purchases="purchases" :orders="market.orders" :now="now" />

    <div class="dashboard__kpis">
      <KpiTile icon="mdi-account-multiple-outline" label="Leads bought" :value="current.leads.toLocaleString('en-US')" :trend="trend.map((item) => item.leads)" :foot="period.versus">
        <DeltaChip :current="current.leads" :previous="previous.leads" />
      </KpiTile>
      <KpiTile icon="mdi-cash-minus" label="Spent" :value="dollars(current.spent)" :trend="trend.map((item) => item.spent)" :foot="period.versus">
        <DeltaChip :current="current.spent" :previous="previous.spent" good="none" />
      </KpiTile>
      <KpiTile icon="mdi-check-decagram-outline" label="Approval rate" :value="percent(current.approvalRate)" :trend="trend.map((item) => item.approvalRate)" color="#2f9e5b" foot="approved ÷ CPA and pending leads">
        <DeltaChip :current="current.approvalRate" :previous="previous.approvalRate" points />
      </KpiTile>
      <KpiTile icon="mdi-tag-outline" label="Avg cost per lead" :value="dollars(current.averageCost)" :trend="trend.map((item) => item.averageCost)" color="#1fb58f" :foot="period.versus">
        <DeltaChip :current="current.averageCost" :previous="previous.averageCost" good="down" />
      </KpiTile>
    </div>

    <div class="dashboard__grid dashboard__grid--main">
      <UiCard title="Spend and leads" :subtitle="chartSubtitle">
        <template #actions>
          <ChartLegend :items="[{ label: 'Spent', color: '#5b45e8' }, { label: 'Leads', color: '#1fb58f', line: true }]" />
        </template>
        <ComboChart
          :labels="buckets.map((bucket) => bucket.label)"
          :ticks="buckets.map((bucket) => bucket.tick)"
          :bars="[{ label: 'Spent', color: '#5b45e8', values: trend.map((item) => item.spent) }]"
          :line="{ label: 'Leads', color: '#1fb58f', values: trend.map((item) => item.leads) }"
          :left-format="compactDollars"
          :tooltip="spendTooltip"
        />
      </UiCard>
      <UiCard title="Lead outcomes" subtitle="What partners decided on the leads you bought">
        <div class="dashboard__outcomes">
          <DonutChart
            :segments="outcomes.map((item) => ({ value: current[item.value], color: item.color }))"
            :value="percent(current.approvalRate)"
            caption="approval rate"
          />
          <div class="dashboard__outcome-list">
            <div v-for="item in outcomes" :key="item.value" class="dashboard__outcome">
              <i :style="{ background: item.color }" />
              <span>{{ item.label }}</span>
              <b>{{ current[item.value].toLocaleString('en-US') }} <span class="dashboard__muted">· {{ share(current[item.value]) }}</span></b>
              <small>{{ item.note }}</small>
            </div>
          </div>
        </div>
      </UiCard>
    </div>

    <div class="dashboard__grid dashboard__grid--half">
      <UiCard title="Active orders" :subtitle="ordersSubtitle">
        <template #actions>
          <router-link class="dashboard__link" to="/orders">All orders <v-icon icon="mdi-arrow-right" size="14" /></router-link>
        </template>
        <ActiveOrders :orders="market.orders" :now="now" />
      </UiCard>
      <UiCard title="Top countries" subtitle="By spend for the period">
        <template #actions>
          <router-link class="dashboard__link" to="/reports">Reports <v-icon icon="mdi-arrow-right" size="14" /></router-link>
        </template>
        <TopGeos :purchases="inPeriod" />
      </UiCard>
    </div>

    <div class="dashboard__grid dashboard__grid--main">
      <UiCard title="Recent leads" subtitle="Latest purchases across all orders">
        <template #actions>
          <router-link class="dashboard__link" to="/leads">All leads <v-icon icon="mdi-arrow-right" size="14" /></router-link>
        </template>
        <RecentLeads :purchases="purchases" :now="now" />
      </UiCard>
      <UiCard title="Wallet activity" subtitle="Charges and deposits">
        <WalletActivity :transactions="wallet.transactions" :purchases="purchases" :now="now" />
      </UiCard>
    </div>

    <OrderFormDialog v-model="orderOpen" @created="refresh" />
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import ChartLegend from '@/components/charts/ChartLegend.vue'
import ComboChart from '@/components/charts/ComboChart.vue'
import DonutChart from '@/components/charts/DonutChart.vue'
import ActiveOrders from '@/components/dashboard/ActiveOrders.vue'
import KpiTile from '@/components/dashboard/KpiTile.vue'
import RecentLeads from '@/components/dashboard/RecentLeads.vue'
import TopGeos from '@/components/dashboard/TopGeos.vue'
import WalletActivity from '@/components/dashboard/WalletActivity.vue'
import WalletHero from '@/components/dashboard/WalletHero.vue'
import DeltaChip from '@/components/DeltaChip.vue'
import OrderFormDialog from '@/components/OrderFormDialog.vue'
import UiButton from '@/components/ui/UiButton.vue'
import UiCard from '@/components/ui/UiCard.vue'
import UiSegmented from '@/components/ui/UiSegmented.vue'
import type { TooltipRow } from '@/charts'
import { compactDollars, dollars, wholeDollars } from '@/money'
import { openOrdersOf, reservedOf } from '@/orderBook'
import { between, bucketsOf, outcomes, percent, periodOf, purchasesOf, totalsOf, type PeriodKey } from '@/purchases'
import { useLeadStore } from '@/stores/leads'
import { useMarketStore } from '@/stores/market'
import { useWalletStore } from '@/stores/wallet'

const router = useRouter()
const leadStore = useLeadStore()
const market = useMarketStore()
const wallet = useWalletStore()

const periodChoices = [
  { title: 'Today', value: 'today' },
  { title: '7 days', value: '7' },
  { title: '30 days', value: '30' },
]

const periodKey = ref('7')
const orderOpen = ref(false)
const now = ref(Date.now())
let clock: ReturnType<typeof setInterval> | undefined

const purchases = computed(() => purchasesOf(leadStore.leads, market.orders, market.presets))
const period = computed(() => periodOf(periodKey.value as PeriodKey, now.value))
const inPeriod = computed(() => between(purchases.value, period.value.from, period.value.to))
const current = computed(() => totalsOf(inPeriod.value))
const previous = computed(() => totalsOf(between(purchases.value, period.value.previousFrom, period.value.previousTo)))
const buckets = computed(() => bucketsOf(period.value))
const trend = computed(() => buckets.value.map((bucket) => totalsOf(between(inPeriod.value, bucket.from, bucket.to))))

const chartSubtitle = computed(() => `${period.value.title}, ${period.value.unit === 'hour' ? 'by hour' : 'by day'} · ${dollars(current.value.spent)} for ${current.value.leads.toLocaleString('en-US')} leads`)

const ordersSubtitle = computed(() => {
  const running = openOrdersOf(market.orders).filter((order) => order.status === 'in_progress').length
  return `${running} running · ${wholeDollars(reservedOf(market.orders))} reserved`
})

const problem = computed(() => leadStore.error || market.error || wallet.error)

function share(count: number): string {
  return `${current.value.leads ? Math.round(count / current.value.leads * 100) : 0}%`
}

function spendTooltip(index: number): TooltipRow[] {
  const item = trend.value[index]
  if (!item) return []
  return [
    { label: 'Spent', value: dollars(item.spent), color: '#5b45e8' },
    { label: 'Leads', value: item.leads.toLocaleString('en-US'), color: '#1fb58f' },
    { label: 'Avg cost', value: dollars(item.averageCost) },
  ]
}

async function refresh(): Promise<void> {
  now.value = Date.now()
  await Promise.all([leadStore.load(), market.loadOrders(), wallet.load()])
}

onMounted(() => {
  void refresh()
  clock = setInterval(() => {
    now.value = Date.now()
  }, 60_000)
})

onUnmounted(() => {
  if (clock !== undefined) clearInterval(clock)
})
</script>

<style scoped>
.page-head h1,
.page-head p {
  margin-bottom: 0;
  margin-top: 0;
}

.page-head p {
  margin-top: 3px;
}

.dashboard__actions {
  align-items: center;
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}

.dashboard__kpis {
  display: grid;
  gap: 12px;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  margin-top: 12px;
}

.dashboard__grid {
  display: grid;
  gap: 12px;
  margin-top: 12px;
}

.dashboard__grid--main {
  grid-template-columns: minmax(0, 2fr) minmax(0, 1fr);
}

.dashboard__grid--half {
  grid-template-columns: repeat(2, minmax(0, 1fr));
}

.dashboard__link {
  align-items: center;
  color: var(--lm-primary-deep);
  display: inline-flex;
  font-size: 13px;
  font-weight: 600;
  gap: 2px;
  text-decoration: none;
  white-space: nowrap;
}

.dashboard__link:hover {
  text-decoration: underline;
}

.dashboard__outcomes {
  align-items: center;
  display: flex;
  gap: 22px;
  min-height: 270px;
}

.dashboard__outcome-list {
  display: flex;
  flex: 1;
  flex-direction: column;
  gap: 14px;
  min-width: 0;
}

.dashboard__outcome {
  align-items: center;
  column-gap: 8px;
  display: grid;
  font-size: 13px;
  grid-template-columns: 10px 1fr auto;
}

.dashboard__outcome i {
  border-radius: 3px;
  height: 10px;
  width: 10px;
}

.dashboard__outcome b {
  font-variant-numeric: tabular-nums;
}

.dashboard__outcome small {
  color: var(--lm-muted);
  font-size: 12px;
  grid-column: 2 / 4;
}

.dashboard__muted {
  color: var(--lm-muted);
  font-weight: 400;
}

@media (max-width: 1100px) {
  .dashboard__grid--main,
  .dashboard__grid--half {
    grid-template-columns: minmax(0, 1fr);
  }

  .dashboard__kpis {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}

@media (max-width: 767px) {
  .dashboard__kpis {
    grid-template-columns: minmax(0, 1fr);
  }

  .dashboard__outcomes {
    flex-direction: column;
  }
}
</style>
