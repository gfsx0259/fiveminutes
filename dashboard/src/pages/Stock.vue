<template>
  <div>
    <div class="page-head">
      <div>
        <h1>Stock</h1>
        <p>Available warehouse leads by preset. Buy a batch into your cabinet.</p>
      </div>
      <UiButton variant="outline" icon="mdi-refresh" :loading="market.loading" @click="refresh">
        Refresh
      </UiButton>
    </div>

    <v-alert v-if="market.error" type="error" variant="tonal" class="mb-4">{{ market.error }}</v-alert>

    <div v-if="market.loading" class="stock-state">
      <v-progress-circular indeterminate color="primary" size="36" width="3" />
    </div>
    <p v-else-if="market.stock.length === 0" class="stock-state">На складе лидов нет</p>

    <template v-else>
      <div class="stock-summary">
        <span class="stock-summary__total">{{ totalLeads }} leads in warehouse</span>
        <span class="stock-summary__sep">·</span>
        <span>{{ tree.length }} {{ tree.length === 1 ? 'country' : 'countries' }}</span>
      </div>

      <div class="stock-board">
        <section v-for="country in tree" :key="country.key" class="stock-country">
          <header
            class="stock-country__head"
            :class="{ 'stock-country__head--collapsed': !isCountryExpanded(country.key) }"
          >
            <div
              class="stock-country__top"
              role="button"
              tabindex="0"
              :aria-expanded="isCountryExpanded(country.key)"
              @click="toggleCountry(country.key)"
              @keydown.enter.prevent="toggleCountry(country.key)"
              @keydown.space.prevent="toggleCountry(country.key)"
            >
              <button
                type="button"
                class="stock-country__chev"
                :aria-label="isCountryExpanded(country.key) ? 'Collapse country' : 'Expand country'"
                @click.stop="toggleCountry(country.key)"
              >
                <v-icon size="22">
                  {{ isCountryExpanded(country.key) ? 'mdi-chevron-down' : 'mdi-chevron-right' }}
                </v-icon>
              </button>
              <CountryFlag :code="country.label" :size="20" class="stock-country__flag" />
              <div class="stock-country__titles">
                <h2 class="stock-country__title">{{ country.label }}</h2>
                <p class="stock-country__meta">
                  {{ countryMeta(country) }}
                </p>
              </div>
              <div
                class="stock-country__meter-wrap"
                title="Share of all warehouse stock"
                @click.stop
              >
                <StockVolumeBar
                  :value="country.count"
                  :max="totalLeads"
                  :parent="totalLeads"
                  class="stock-country__meter"
                />
              </div>
              <UiButton
                variant="outline"
                class="stock-country__buy"
                @click.stop="openBundle(countryLeaves(country), countryBuySummary(country))"
              >
                Buy all
              </UiButton>
            </div>
          </header>

          <div v-show="isCountryExpanded(country.key)" class="stock-table">
            <div class="stock-table__head">
              <span>Source</span>
              <span class="stock-table__num">Available</span>
              <span class="stock-table__num">Pricing</span>
              <span class="stock-table__actions-head"> </span>
            </div>

            <template v-for="traffic in country.children" :key="traffic.key">
              <div
                  class="stock-table__row stock-table__row--traffic"
                  role="button"
                  tabindex="0"
                  @click="toggleExpand(traffic.key)"
                  @keydown.enter.prevent="toggleExpand(traffic.key)"
                  @keydown.space.prevent="toggleExpand(traffic.key)"
                >
                  <div class="stock-table__criteria" data-depth="0">
                    <button
                      type="button"
                      class="stock-table__chev"
                      :aria-expanded="isExpanded(traffic.key)"
                      :aria-label="isExpanded(traffic.key) ? 'Collapse' : 'Expand'"
                      @click.stop="toggleExpand(traffic.key)"
                    >
                      <v-icon size="20">
                        {{ isExpanded(traffic.key) ? 'mdi-chevron-down' : 'mdi-chevron-right' }}
                      </v-icon>
                    </button>
                    <span class="stock-table__label">{{ trafficLabel(traffic.label) }}</span>
                    <span class="stock-table__hint">{{ trafficMeta(traffic) }}</span>
                  </div>
                  <div class="stock-table__share">
                    <StockVolumeBar
                      :value="traffic.count"
                      :max="country.count"
                      :parent="country.count"
                      :show-bar="false"
                    />
                  </div>
                  <span class="stock-table__pricing stock-table__muted">—</span>
                  <div class="stock-table__actions">
                    <UiButton
                      variant="ghost"
                      class="stock-table__buy-branch"
                      @click.stop="
                        openBundle(trafficLeaves(traffic), rowBuySummary(country, traffic, 'traffic'))
                      "
                    >
                      Buy all
                    </UiButton>
                  </div>
                </div>

              <template v-if="isExpanded(traffic.key)">
                <template v-for="gender in traffic.children" :key="gender.key">
                  <div
                    class="stock-table__row stock-table__row--gender"
                    role="button"
                    tabindex="0"
                    @click="toggleExpand(gender.key)"
                    @keydown.enter.prevent="toggleExpand(gender.key)"
                    @keydown.space.prevent="toggleExpand(gender.key)"
                  >
                    <div class="stock-table__criteria" data-depth="1">
                      <button
                        type="button"
                        class="stock-table__chev"
                        :aria-expanded="isExpanded(gender.key)"
                        :aria-label="isExpanded(gender.key) ? 'Collapse' : 'Expand'"
                        @click.stop="toggleExpand(gender.key)"
                      >
                        <v-icon size="18">
                          {{ isExpanded(gender.key) ? 'mdi-chevron-down' : 'mdi-chevron-right' }}
                        </v-icon>
                      </button>
                      <span class="stock-table__label stock-table__label--secondary">
                        {{ genderLabel(gender.label) }}
                      </span>
                      <span class="stock-table__hint">
                        {{ gender.leaves.length }} {{ gender.leaves.length === 1 ? 'age' : 'ages' }}
                      </span>
                    </div>
                    <div class="stock-table__share">
                      <StockVolumeBar
                        :value="gender.count"
                        :max="traffic.count"
                        :parent="traffic.count"
                        :show-bar="false"
                      />
                    </div>
                    <span class="stock-table__pricing stock-table__muted">—</span>
                    <div class="stock-table__actions">
                      <UiButton
                        variant="ghost"
                        class="stock-table__buy-branch"
                        @click.stop="openBundle(gender.leaves, rowBuySummary(country, gender, 'gender', traffic))"
                      >
                        Buy all
                      </UiButton>
                    </div>
                  </div>

                  <template v-if="isExpanded(gender.key)">
                    <div
                      v-for="(leaf, leafIndex) in gender.leaves"
                      :key="`${leaf.presetId}|${leaf.gender}|${leaf.age}`"
                      class="stock-table__row stock-table__row--leaf"
                      :class="{ 'stock-table__row--leaf-alt': leafIndex % 2 === 1 }"
                    >
                      <div class="stock-table__criteria" data-depth="2">
                        <span class="stock-table__chev stock-table__chev--spacer" aria-hidden="true" />
                        <span class="stock-table__age">{{ leaf.age }}</span>
                      </div>
                      <div class="stock-table__share stock-table__share--bar">
                        <StockVolumeBar
                          :value="leaf.available"
                          :max="gender.count"
                          :parent="gender.count"
                        />
                      </div>
                      <div class="stock-table__pricing">
                        <span>CPL {{ dollars(leaf.cplPriceCents / 100) }}</span>
                        <span class="stock-table__cpa">CPA {{ dollars(leaf.cpaPriceCents / 100) }}</span>
                      </div>
                      <div class="stock-table__actions">
                        <UiButton @click="openBundle([leaf], leafBuySummary(leaf))">Buy</UiButton>
                      </div>
                    </div>
                  </template>
                </template>
              </template>
            </template>
          </div>
        </section>
      </div>
    </template>

    <StockBuyDialog v-model="buyOpen" :target="buyTarget" @bought="onBought" />
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import CountryFlag from '@/components/CountryFlag.vue'
import StockBuyDialog from '@/components/StockBuyDialog.vue'
import StockVolumeBar from '@/components/StockVolumeBar.vue'
import UiButton from '@/components/ui/UiButton.vue'
import { genderLabel, trafficLabel } from '@/marketOptions'
import { dollars } from '@/money'
import { type StockBuyTarget } from '@/stockBuy'
import { useMarketStore, type StockLeaf } from '@/stores/market'

const EXPAND_STORAGE = 'rider-stock-expand-v2'

function countryExpandKey(countryKey: string): string {
  return `country:${countryKey}`
}

const market = useMarketStore()
const buyOpen = ref(false)
const buyTarget = ref<StockBuyTarget | null>(null)

type GenderNode = {
  key: string
  label: string
  count: number
  leaves: StockLeaf[]
}

type TrafficNode = {
  key: string
  label: string
  count: number
  children: GenderNode[]
}

type CountryNode = {
  key: string
  label: string
  count: number
  children: TrafficNode[]
}

function loadExpandState(): Set<string> {
  try {
    const raw = sessionStorage.getItem(EXPAND_STORAGE)
    if (raw) {
      const keys = JSON.parse(raw) as string[]
      return new Set(keys)
    }
  } catch {
    /* ignore */
  }
  return new Set()
}

const expandedKeys = ref<Set<string>>(loadExpandState())
const expandSeeded = ref(expandedKeys.value.size > 0)

const tree = computed<CountryNode[]>(() => {
  const countries = new Map<string, CountryNode>()
  for (const leaf of market.stock) {
    let country = countries.get(leaf.country)
    if (!country) {
      country = { key: leaf.country, label: leaf.country, count: 0, children: [] }
      countries.set(leaf.country, country)
    }
    country.count += leaf.available

    let traffic = country.children.find((node) => node.label === leaf.trafficSource)
    if (!traffic) {
      traffic = { key: `${leaf.country}|${leaf.trafficSource}`, label: leaf.trafficSource, count: 0, children: [] }
      country.children.push(traffic)
    }
    traffic.count += leaf.available

    let gender = traffic.children.find((node) => node.label === leaf.gender)
    if (!gender) {
      gender = { key: `${leaf.country}|${leaf.trafficSource}|${leaf.gender}`, label: leaf.gender, count: 0, leaves: [] }
      traffic.children.push(gender)
    }
    gender.count += leaf.available
    gender.leaves.push(leaf)
  }

  const sorted = [...countries.values()].sort((a, b) => a.label.localeCompare(b.label))
  for (const country of sorted) {
    country.children.sort((a, b) => a.label.localeCompare(b.label))
    for (const traffic of country.children) {
      traffic.children.sort((a, b) => a.label.localeCompare(b.label))
      for (const gender of traffic.children) {
        gender.leaves.sort((a, b) => a.age.localeCompare(b.age))
      }
    }
  }

  return sorted
})

const totalLeads = computed(() => market.stock.reduce((sum, leaf) => sum + leaf.available, 0))

function persistExpand(): void {
  sessionStorage.setItem(EXPAND_STORAGE, JSON.stringify([...expandedKeys.value]))
}

function seedDefaultExpand(): void {
  if (expandSeeded.value || tree.value.length === 0) {
    return
  }
  const next = new Set<string>()
  for (const country of tree.value) {
    next.add(countryExpandKey(country.key))
    for (const traffic of country.children) {
      next.add(traffic.key)
    }
  }
  expandedKeys.value = next
  expandSeeded.value = true
  persistExpand()
}

watch(tree, seedDefaultExpand, { immediate: true })

const countryKeysMigrated = ref(false)

watch(
  tree,
  () => {
    if (countryKeysMigrated.value || tree.value.length === 0) {
      return
    }
    const hasCountryKey = [...expandedKeys.value].some((key) => key.startsWith('country:'))
    if (!hasCountryKey && expandedKeys.value.size > 0) {
      const next = new Set(expandedKeys.value)
      for (const country of tree.value) {
        next.add(countryExpandKey(country.key))
      }
      expandedKeys.value = next
      persistExpand()
    }
    countryKeysMigrated.value = true
  },
  { immediate: true },
)

function isExpanded(key: string): boolean {
  return expandedKeys.value.has(key)
}

function isCountryExpanded(countryKey: string): boolean {
  return expandedKeys.value.has(countryExpandKey(countryKey))
}

function toggleCountry(countryKey: string): void {
  toggleExpand(countryExpandKey(countryKey))
}

function toggleExpand(key: string): void {
  const next = new Set(expandedKeys.value)
  if (next.has(key)) {
    next.delete(key)
  } else {
    next.add(key)
  }
  expandedKeys.value = next
  persistExpand()
}

function countryMeta(country: CountryNode): string {
  const lines = countryPresetCount(country)
  return `${country.count} leads · ${country.children.length} sources · ${lines} ${lines === 1 ? 'line' : 'lines'}`
}

function trafficMeta(traffic: TrafficNode): string {
  const presets = trafficLeaves(traffic).length
  return `${traffic.children.length} gender · ${presets} ${presets === 1 ? 'line' : 'lines'}`
}

function countryPresetCount(country: CountryNode): number {
  return countryLeaves(country).length
}

function countryLeaves(country: CountryNode): StockLeaf[] {
  return country.children.flatMap((traffic) => trafficLeaves(traffic))
}

function trafficLeaves(traffic: TrafficNode): StockLeaf[] {
  return traffic.children.flatMap((gender) => gender.leaves)
}

function countryBuySummary(country: CountryNode): string {
  return country.label
}

function rowBuySummary(
  country: CountryNode,
  node: TrafficNode | GenderNode,
  kind: 'traffic' | 'gender',
  traffic?: TrafficNode,
): string {
  if (kind === 'traffic') {
    return `${country.label} · ${trafficLabel(node.label)}`
  }
  const trafficSource = traffic?.label ?? node.key.split('|')[1] ?? ''
  return `${country.label} · ${trafficLabel(trafficSource)} · ${genderLabel(node.label)}`
}

function leafBuySummary(leaf: StockLeaf): string {
  return `${leaf.country} · ${trafficLabel(leaf.trafficSource)} · ${leaf.age} · ${genderLabel(leaf.gender)}`
}

function openBundle(leaves: StockLeaf[], summary: string): void {
  if (leaves.length === 0) {
    return
  }
  buyTarget.value = {
    country: leaves[0].country,
    summary,
    leaves,
  }
  buyOpen.value = true
}

async function refresh(): Promise<void> {
  await market.loadWarehouse()
}

async function onBought(): Promise<void> {
  await market.loadWarehouse()
  await market.loadOrders()
}

onMounted(() => {
  void market.loadWarehouse()
})
</script>

<style scoped>
.stock-state {
  background: #fff;
  border: 1px solid var(--lm-border);
  border-radius: var(--lm-radius);
  box-shadow: var(--lm-shadow);
  color: var(--lm-muted);
  padding: 40px;
  text-align: center;
}

.stock-summary {
  color: var(--lm-muted);
  font-size: 14px;
  margin-bottom: 14px;
}

.stock-summary__total {
  color: var(--lm-text);
  font-weight: 600;
}

.stock-summary__sep {
  margin-inline: 8px;
  opacity: 0.5;
}

.stock-board {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.stock-country {
  background: #fff;
  border: 1px solid var(--lm-border);
  border-radius: var(--lm-radius);
  box-shadow: var(--lm-shadow);
  overflow: hidden;
}

.stock-country__head {
  background: linear-gradient(180deg, #f4f2ff 0%, #faf9ff 100%);
  border-bottom: 1px solid #e8e4f8;
  padding: 14px 16px;
}

.stock-country__head--collapsed {
  border-bottom: none;
}

.stock-country__top {
  align-items: center;
  column-gap: 10px;
  cursor: pointer;
  display: grid;
  grid-template-columns: auto auto minmax(0, max-content) 1fr auto;
  grid-template-rows: auto auto;
  row-gap: 2px;
}

.stock-country__top:hover .stock-country__title {
  color: var(--lm-primary-deep);
}

.stock-country__chev {
  align-items: center;
  align-self: center;
  background: transparent;
  border: none;
  border-radius: 6px;
  color: var(--lm-primary-deep);
  cursor: pointer;
  display: flex;
  grid-row: 1 / 3;
  height: 32px;
  justify-content: center;
  margin: 0;
  padding: 0;
  width: 32px;
}

.stock-country__chev:hover {
  background: rgb(91 69 232 / 12%);
}

.stock-country__flag {
  align-self: center;
  grid-row: 1 / 3;
}

.stock-country__titles {
  align-self: center;
  grid-column: 3;
  grid-row: 1 / 3;
  min-width: 0;
}

.stock-country__title {
  font-size: 1.05rem;
  font-weight: 700;
  line-height: 1.25;
  margin: 0;
}

.stock-country__meta {
  color: var(--lm-muted);
  font-size: 13px;
  margin: 4px 0 0;
}

.stock-country__meter-wrap {
  align-self: center;
  grid-column: 4;
  grid-row: 1;
  min-width: 72px;
  padding-inline: 12px 8px;
}

.stock-country__meter {
  width: 100%;
}

.stock-country__meter :deep(.stock-vol__track) {
  height: 7px;
}

.stock-country__buy {
  align-self: center;
  grid-column: 5;
  grid-row: 1 / 3;
}

@media (width <= 640px) {
  .stock-country__top {
    grid-template-columns: auto auto 1fr auto;
  }

  .stock-country__meter-wrap {
    grid-column: 1 / -1;
    grid-row: 3;
    padding-inline: 42px 0;
  }

  .stock-country__buy {
    grid-column: 4;
    grid-row: 1;
  }

  .stock-country__titles {
    grid-column: 3;
    grid-row: 1 / 3;
  }
}

.stock-table__head,
.stock-table__row {
  align-items: center;
  column-gap: 16px;
  display: grid;
  grid-template-columns: minmax(0, 1fr) 108px minmax(120px, 0.9fr) 88px;
  padding-inline: 16px;
}

.stock-table__head {
  background: #faf9ff;
  border-bottom: 1px solid #edeaf8;
  color: var(--lm-muted);
  font-size: 11px;
  font-weight: 600;
  letter-spacing: 0.04em;
  padding-block: 8px;
  text-transform: uppercase;
}

.stock-table__row {
  border-bottom: 1px solid #f0edf8;
  min-height: 48px;
  transition: background 0.15s ease;
}

.stock-table__row:last-child {
  border-bottom: none;
}

.stock-table__row--traffic {
  background: #fff;
  cursor: pointer;
}

.stock-table__row--traffic:hover {
  background: rgb(91 69 232 / 4%);
}

.stock-table__row--gender {
  background: rgb(91 69 232 / 2%);
  cursor: pointer;
}

.stock-table__row--gender:hover {
  background: rgb(91 69 232 / 6%);
}

.stock-table__row--leaf {
  background: rgb(91 69 232 / 5%);
}

.stock-table__row--leaf-alt {
  background: rgb(91 69 232 / 9%);
}

.stock-table__num {
  text-align: right;
}

.stock-table__actions-head {
  min-width: 0;
}

.stock-table__criteria {
  align-items: center;
  display: flex;
  gap: 8px;
  min-width: 0;
  position: relative;
}

.stock-table__criteria[data-depth='1'] {
  padding-left: 20px;
}

.stock-table__criteria[data-depth='2'] {
  padding-left: 40px;
}

.stock-table__criteria[data-depth='1']::before,
.stock-table__criteria[data-depth='2']::before {
  background: #dcd6f5;
  bottom: 4px;
  content: '';
  left: 8px;
  position: absolute;
  top: 4px;
  width: 2px;
}

.stock-table__criteria[data-depth='2']::before {
  left: 28px;
}

.stock-table__chev {
  align-items: center;
  background: transparent;
  border: none;
  border-radius: 6px;
  color: var(--lm-primary-deep);
  cursor: pointer;
  display: flex;
  flex-shrink: 0;
  height: 28px;
  justify-content: center;
  margin: 0;
  padding: 0;
  width: 28px;
}

.stock-table__chev:hover {
  background: rgb(91 69 232 / 12%);
}

.stock-table__chev--spacer {
  pointer-events: none;
  visibility: hidden;
}

.stock-table__label {
  font-size: 15px;
  font-weight: 700;
}

.stock-table__label--secondary {
  font-size: 14px;
  font-weight: 600;
}

.stock-table__hint {
  color: var(--lm-muted);
  font-size: 12px;
  font-weight: 500;
}

.stock-table__age {
  font-size: 14px;
  font-weight: 700;
}

.stock-table__share {
  justify-self: end;
  text-align: right;
}

.stock-table__share--bar {
  max-width: 140px;
  width: 100%;
}

.stock-table__pricing {
  display: flex;
  flex-direction: column;
  font-size: 13px;
  gap: 2px;
  line-height: 1.3;
}

.stock-table__cpa {
  color: var(--lm-muted);
  font-size: 12px;
}

.stock-table__muted {
  color: var(--lm-muted);
}

.stock-table__actions {
  display: flex;
  justify-content: flex-end;
}

.stock-table__buy-branch {
  font-size: 12px;
  min-width: 0;
  opacity: 0.85;
  padding-inline: 8px;
}

.stock-table__row--traffic:hover .stock-table__buy-branch,
.stock-table__row--gender:hover .stock-table__buy-branch {
  opacity: 1;
}

@media (width <= 720px) {
  .stock-table__head {
    display: none;
  }

  .stock-table__row {
    grid-template-columns: 1fr auto;
    grid-template-rows: auto auto auto;
    padding-block: 12px;
    row-gap: 8px;
  }

  .stock-table__share,
  .stock-table__pricing {
    grid-column: 1;
  }

  .stock-table__share--bar {
    max-width: none;
  }

  .stock-table__actions {
    grid-column: 2;
    grid-row: 1 / span 3;
    align-self: center;
  }
}
</style>