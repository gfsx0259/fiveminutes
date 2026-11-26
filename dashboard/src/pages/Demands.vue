<template>
  <div class="demands-page" :class="tableListLayoutClass">
    <div class="page-head">
      <div>
        <h1>Demands</h1>
        <p>Partner requests for traffic. Accept a demand and say how many leads you will supply for that preset.</p>
      </div>
    </div>

    <div class="demands-kpis">
      <article v-for="kpi in kpis" :key="kpi.label" class="demands-kpi">
        <span class="demands-kpi__icon">
          <v-icon :icon="kpi.icon" size="25" />
        </span>
        <div>
          <span>{{ kpi.label }}</span>
          <strong>{{ kpi.value }}</strong>
        </div>
      </article>
    </div>

    <div class="demands-tabs" role="tablist" aria-label="Demands views">
      <button
        type="button"
        role="tab"
        :aria-selected="tab === 'available'"
        @click="setTab('available')"
      >
        Available Demands
        <span>{{ availableScoped.length }}</span>
      </button>
      <button
        type="button"
        role="tab"
        :aria-selected="tab === 'accepted'"
        @click="setTab('accepted')"
      >
        My Accepted
        <span>{{ acceptedScoped.length }}</span>
      </button>
    </div>

    <section class="demands-card" role="tabpanel">
      <CollapsibleFilterPanel class="demands-filter-panel" :active-count="activeFilterCount">
        <template #mobile-actions>
          <UiButton variant="ghost" icon="mdi-refresh" @click="clearFilters">Reset</UiButton>
        </template>
      <div class="demands-filters">
        <UiSelect v-model="filterScope" :items="scopeItems" aria-label="Date scope" />
        <UiSelect
          v-model="filterVertical"
          :items="verticalItems"
          placeholder="All verticals"
          empty-label="All verticals"
          clearable
        >
          <template #value="{ items }">
            <span v-if="items.length === 0" class="stock-select__placeholder">All verticals</span>
            <span v-else class="stock-select-picks">
              <span v-for="item in items" :key="String(item.value)" class="stock-select-pick">
                <v-icon :icon="verticalIcon(String(item.value))" :color="verticalColor(String(item.value))" size="14" />
                {{ item.title }}
              </span>
            </span>
          </template>
          <template #option="{ item }">
            <v-icon :icon="verticalIcon(String(item.value))" :color="verticalColor(String(item.value))" size="16" />
            <span>{{ item.title }}</span>
          </template>
        </UiSelect>
        <CountryMultiSelect v-model="filterGeo" />
        <UiSelect
          v-model="filterSource"
          :items="sourceItems"
          placeholder="All traffic sources"
          empty-label="All traffic sources"
          clearable
        >
          <template #value="{ items }">
            <span v-if="items.length === 0" class="stock-select__placeholder">All traffic sources</span>
            <span v-else class="stock-select-picks">
              <span v-for="item in items" :key="String(item.value)" class="stock-select-pick">
                <v-icon :icon="trafficIcon(String(item.value))" :color="trafficColor(String(item.value))" size="14" />
                {{ item.title }}
              </span>
            </span>
          </template>
          <template #option="{ item }">
            <v-icon :icon="trafficIcon(String(item.value))" :color="trafficColor(String(item.value))" size="16" />
            <span>{{ item.title }}</span>
          </template>
        </UiSelect>
        <UiSelect
          v-model="filterCap"
          :items="capItems"
          placeholder="Any remaining cap"
          empty-label="Any remaining cap"
          clearable
        />
      </div>
      </CollapsibleFilterPanel>

      <div v-if="market.loading && currentRows.length === 0" class="demands-empty">Loading demands…</div>
      <div v-else class="demands-table-scroll">
        <table class="demands-table">
          <thead>
            <tr>
              <th>Vertical</th>
              <th>Country</th>
              <th>Traffic Source</th>
              <th>Payment Model</th>
              <th>Min Price</th>
              <th>Daily Cap</th>
              <th><span class="demands-sr">Actions</span></th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="row in pageRows" :key="row.key">
              <td class="demands-copy" @click="copyCell($event, verticalLabel(row.vertical))">
                <span class="demands-entity">
                  <v-icon :icon="verticalIcon(row.vertical)" :color="verticalColor(row.vertical)" size="18" />
                  {{ verticalLabel(row.vertical) }}
                </span>
              </td>
              <td class="demands-copy" @click="copyCell($event, countryName(row.geo))"><span class="demands-geo"><CountryPick :code="row.geo" label="code" /></span></td>
              <td class="demands-copy" @click="copyCell($event, trafficLabel(row.trafficSource))"><span class="demands-source">{{ trafficLabel(row.trafficSource) }}</span></td>
              <td class="demands-copy" @click="copyCell($event, modelLabel(row.paymentModel))"><ModelTag :model="row.paymentModel" /></td>
              <td class="demands-number demands-copy" @click="copyCell($event, row.priceCents > 0 ? dollars(row.priceCents / 100) : '')">{{ row.priceCents > 0 ? dollars(row.priceCents / 100) : '—' }}</td>
              <td>
                <StockVolumeBar :value="progressSupplied(row)" :max="progressCap(row)" :parent="progressCap(row)" />
              </td>
              <td>
                <div class="demands-actions">
                  <StatusChip v-if="row.kind === 'cap' && row.expired" status="expired" label="Expired" tone="default" />
                  <UiButton v-else-if="row.kind === 'cap'" icon="mdi-check" @click="openAccept(row)">Accept</UiButton>
                  <span v-else class="demands-status-help">
                    <StatusChip :status="row.status" :label="statusLabel(row.status)" :tone="statusTone(row.status)" />
                    <v-tooltip location="top" max-width="280">
                      <template #activator="{ props: tipProps }">
                        <span
                          class="demands-status-question"
                          v-bind="tipProps"
                          tabindex="0"
                          role="button"
                          :aria-label="`About ${statusLabel(row.status)} status`"
                        >
                          <v-icon icon="mdi-information-outline" size="16" />
                        </span>
                      </template>
                      <span>{{ statusExplanation(row.status) }}</span>
                    </v-tooltip>
                  </span>
                  <UiButton variant="outline" @click="openDetails(row)">Details</UiButton>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <div v-if="!market.loading && filtered.length === 0" class="demands-empty">
        <strong>No demands found</strong>
        <p>Try a different filter or tab.</p>
        <UiButton variant="outline" @click="clearFilters">Clear filters</UiButton>
      </div>

      <UiTablePagination
        v-if="filtered.length > 0"
        :page="page"
        :page-count="pageCount"
        :items-length="filtered.length"
        :items-per-page="pageSize"
        :items-per-page-options="[100, 200, 500]"
        @update:page="page = $event"
        @update:items-per-page="pageSize = $event"
      />
    </section>

    <v-dialog
      v-model="dialogOpen"
      :max-width="acceptedDetails ? 1200 : 510"
      class="demands-dialog-root"
      scrim="#11162c"
    >
      <v-card v-if="selected" class="demands-dialog" rounded="0" elevation="0">
        <h2>
          {{ dialogTitle }}
          <StatusChip v-if="acceptedDetails" status="approved" label="Accepted" tone="success" />
        </h2>
        <button type="button" class="demands-dialog__close" aria-label="Close dialog" @click="dialogOpen = false">
          <v-icon icon="mdi-close" size="20" />
        </button>

        <AcceptedOfferDetails v-if="acceptedDetails" :row="selected" />

        <div v-if="!acceptedDetails" class="demands-modal-intro">
          <strong>{{ offerDisplayId(selected.presetId) }}</strong>
          <p v-if="dialogMode === 'accept'">
            Review this demand and enter the daily cap you can deliver. An admin approves the offer before leads start.
          </p>
          <p v-else>
            Verified {{ selected.vertical && selected.vertical !== 'other' ? `${verticalLabel(selected.vertical).toLowerCase()} ` : '' }}traffic is requested.
            Deliver leads from {{ selected.geo }} using the {{ modelLabel(selected.paymentModel) }} model.
          </p>
        </div>

        <dl v-if="!acceptedDetails" class="demands-summary">
          <div><dt>Traffic Source</dt><dd>{{ trafficLabel(selected.trafficSource) }}</dd></div>
          <div><dt>Remaining Cap</dt><dd>{{ remaining(selected).toLocaleString('en-US') }} ({{ remainingPercent(selected) }}%)</dd></div>
          <div>
            <dt>Vertical</dt>
            <dd>
              <span class="demands-entity">
                <v-icon :icon="verticalIcon(selected.vertical)" :color="verticalColor(selected.vertical)" size="18" />
                {{ verticalLabel(selected.vertical) }}
              </span>
            </dd>
          </div>
          <div><dt>Country</dt><dd><span class="demands-geo"><CountryPick :code="selected.geo" label="code" /></span></dd></div>
          <div><dt>Date</dt><dd>{{ selected.serviceOn || 'Stock' }}</dd></div>
          <div><dt>Age</dt><dd>{{ selected.age || 'Any' }}</dd></div>
          <div><dt>Gender</dt><dd>{{ genderLabel(selected.gender) }}</dd></div>
          <div><dt>Daily Cap</dt><dd>{{ selected.volume.toLocaleString('en-US') }}</dd></div>
          <div v-if="selected.yourVolume != null"><dt>Your Daily Cap</dt><dd>{{ selected.yourVolume.toLocaleString('en-US') }}</dd></div>
          <div><dt>Payment Model</dt><dd>{{ modelLabel(selected.paymentModel) }}</dd></div>
          <div><dt>Min Price</dt><dd>{{ selected.priceCents > 0 ? dollars(selected.priceCents / 100) : '—' }}</dd></div>
          <div v-if="selected.kind === 'offer'">
            <dt>Status</dt>
            <dd><StatusChip :status="selected.status" :label="statusLabel(selected.status)" :tone="statusTone(selected.status)" /></dd>
          </div>
          <div class="demands-summary__total"><dt>Total Value</dt><dd>{{ selected.priceCents > 0 ? dollars((selected.volume * selected.priceCents) / 100) : '—' }}</dd></div>
        </dl>

        <form v-if="dialogMode === 'accept'" id="demand-accept-form" class="demands-accept-form" @submit.prevent="accept">
          <label for="demand-daily-cap">Your Daily Cap</label>
          <input
            id="demand-daily-cap"
            v-model="acceptVolume"
            type="text"
            inputmode="numeric"
            autofocus
            required
            placeholder="Enter the daily cap you can deliver"
            aria-describedby="demand-daily-cap-help"
            :aria-invalid="acceptVolume !== '' && !validDailyCap"
          >
          <small id="demand-daily-cap-help" aria-live="polite" :class="{ 'demands-form-error': formError || (acceptVolume !== '' && !validDailyCap) }">
            {{ dailyCapHelp }}
          </small>
        </form>

        <div class="demands-modal-footer">
          <UiButton variant="outline" @click="dialogOpen = false">Close</UiButton>
          <UiButton
            v-if="dialogMode === 'accept'"
            :loading="busy"
            :disabled="!validDailyCap"
            icon="mdi-check"
            @click="accept"
          >
            Accept
          </UiButton>
          <UiButton v-else-if="selected.kind === 'cap' && !selected.expired" icon="mdi-check" @click="openAccept(selected)">Accept Demand</UiButton>
        </div>
      </v-card>
    </v-dialog>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import ModelTag from '@/components/ModelTag.vue'
import StatusChip from '@/components/StatusChip.vue'
import StockVolumeBar from '@/components/StockVolumeBar.vue'
import AcceptedOfferDetails from '@/components/demands/AcceptedOfferDetails.vue'
import CountryMultiSelect from '@/components/CountryMultiSelect.vue'
import CountryPick from '@/components/CountryPick.vue'
import CollapsibleFilterPanel from '@/components/ui/CollapsibleFilterPanel.vue'
import UiButton from '@/components/ui/UiButton.vue'
import UiSelect from '@/components/ui/UiSelect.vue'
import UiTablePagination from '@/components/ui/UiTablePagination.vue'
import { useLoadErrorToast } from '@/composables/useLoadErrorToast'
import { useTableListLayout } from '@/composables/useTableListLayout'
import { showToast } from '@/composables/useToast'
import { copyText } from '@/copyText'
import { countFilledFilters } from '@/filterCount'
import { countryName } from '@/countries'
import { copyTextFromExportCell, isTableCellCopyTarget } from '@/tableCellCopy'
import { dollars } from '@/money'
import { useViewport } from '@/viewport'
import { offerDisplayId } from '@/orderDisplay'
import {
  genderLabel,
  modelLabel,
  trafficColor,
  trafficIcon,
  trafficSources,
  trafficLabel,
  verticalColor,
  verticalIcon,
  verticalLabel,
  verticals,
} from '@/marketOptions'
import { useMarketStore, type CapRow, type OfferRow, type PresetRow } from '@/stores/market'

/** `finished` comes from the API once the buyer's own deliveries reach the offer volume. */
type DemandStatus = 'available' | 'pending' | 'approved' | 'finished' | 'rejected'

interface DemandRow {
  key: string
  kind: 'cap' | 'offer'
  presetId: string
  trafficSource: string
  vertical: string
  geo: string
  paymentModel: string
  priceCents: number
  volume: number
  supplied: number
  serviceOn: string | null
  expired: boolean
  age: string
  gender: string
  status: DemandStatus
  yourVolume: number | null
  ownSupplied: number
}

const market = useMarketStore()
const { mobile } = useViewport()
// Same as the partner list pages: the page fills the viewport and the table scrolls inside.
const { tableListLayoutClass } = useTableListLayout(() => !mobile.value)
const tab = ref<'available' | 'accepted'>('available')
type DemandScope = 'active' | 'past' | 'all'
const filterScope = ref<DemandScope>('active')
const scopeItems = [
  { title: 'Active', value: 'active' as const },
  { title: 'Past', value: 'past' as const },
  { title: 'All', value: 'all' as const },
]

/** UTC day: the API accepts offers only for UTC today or tomorrow. */
function utcToday(): string {
  return new Date().toISOString().slice(0, 10)
}

function isExpired(serviceOn: string | null): boolean {
  return serviceOn !== null && serviceOn.slice(0, 10) < utcToday()
}

/**
 * Table progress: for an accepted offer it is the buyer's own cap and own deliveries
 * (same numbers as the offer details), for an open demand it is the whole demand.
 */
function progressCap(row: DemandRow): number {
  return row.kind === 'offer' && row.yourVolume != null ? row.yourVolume : row.volume
}

function progressSupplied(row: DemandRow): number {
  return row.kind === 'offer' ? row.ownSupplied : row.supplied
}

function inScope(row: DemandRow): boolean {
  if (filterScope.value === 'active') return !row.expired
  if (filterScope.value === 'past') return row.expired
  return true
}
const filterSource = ref<string | null>(null)
const filterVertical = ref<string | null>(null)
const filterGeo = ref<string[]>([])
const filterCap = ref<string | null>(null)
const page = ref(1)
const pageSize = ref(100)
const dialogOpen = ref(false)
const dialogMode = ref<'details' | 'accept'>('details')
const selected = ref<DemandRow | null>(null)
const acceptVolume = ref('')
const formError = ref('')
const busy = ref(false)

const presetsById = computed(() => new Map(market.presets.map((preset) => [preset.id, preset])))

function capKey(presetId: string, serviceOn: string | null): string {
  return `${presetId}|${serviceOn ?? 'stock'}`
}

function presetOf(presetId: string): PresetRow | undefined {
  return presetsById.value.get(presetId)
}

const activeOfferKeys = computed(() => {
  const keys = new Set<string>()
  for (const offer of market.offers) {
    if (offer.status === 'pending' || offer.status === 'approved' || offer.status === 'finished') {
      keys.add(capKey(offer.presetId, offer.serviceOn))
    }
  }
  return keys
})

function rowFromCap(cap: CapRow): DemandRow {
  const preset = presetOf(cap.presetId)
  return {
    key: capKey(cap.presetId, cap.serviceOn),
    kind: 'cap',
    presetId: cap.presetId,
    trafficSource: cap.trafficSource,
    vertical: preset?.vertical ?? '',
    geo: cap.geo,
    paymentModel: cap.paymentModel,
    priceCents: preset?.basePriceCents ?? 0,
    volume: cap.volume,
    supplied: cap.supplied,
    serviceOn: cap.serviceOn,
    expired: isExpired(cap.serviceOn),
    age: cap.age,
    gender: cap.gender,
    status: 'available',
    yourVolume: null,
    ownSupplied: 0,
  }
}

function rowFromOffer(offer: OfferRow): DemandRow {
  const preset = presetOf(offer.presetId)
  const cap = market.caps.find((item) => capKey(item.presetId, item.serviceOn) === capKey(offer.presetId, offer.serviceOn))
  const status: DemandStatus = offer.status === 'approved' || offer.status === 'pending' || offer.status === 'rejected' || offer.status === 'finished'
    ? offer.status
    : 'pending'
  return {
    key: offer.id,
    kind: 'offer',
    presetId: offer.presetId,
    trafficSource: offer.trafficSource || preset?.trafficSource || '',
    vertical: preset?.vertical ?? '',
    geo: offer.geo,
    paymentModel: preset?.paymentModel ?? '',
    priceCents: preset?.basePriceCents ?? 0,
    volume: cap?.volume ?? offer.volume,
    supplied: cap?.supplied ?? offer.supplied,
    serviceOn: offer.serviceOn,
    expired: isExpired(offer.serviceOn),
    age: offer.age || preset?.age || '',
    gender: offer.gender || preset?.gender || '',
    status,
    yourVolume: offer.volume,
    ownSupplied: offer.supplied,
  }
}

const availableRows = computed(() => market.caps
  .filter((cap) => !cap.stock && cap.volume > cap.supplied && !activeOfferKeys.value.has(capKey(cap.presetId, cap.serviceOn)))
  .map(rowFromCap))

const acceptedRows = computed(() => market.offers.map(rowFromOffer))

const availableScoped = computed(() => availableRows.value.filter(inScope))
const acceptedScoped = computed(() => acceptedRows.value.filter(inScope))
const availableActiveCount = computed(() => availableRows.value.filter((row) => !row.expired).length)

const sourceItems = trafficSources.map((item) => ({ title: item.title, value: item.value }))
const verticalItems = computed(() => verticals.map((item) => ({ title: item.title, value: item.value })))
const capItems = [
  { title: 'Up to 20% remaining', value: '20' },
  { title: 'Up to 40% remaining', value: '40' },
  { title: 'Up to 60% remaining', value: '60' },
  { title: 'Up to 80% remaining', value: '80' },
  { title: 'Up to 100% remaining', value: '100' },
]

const currentRows = computed(() => (tab.value === 'available' ? availableScoped.value : acceptedScoped.value))

const filtered = computed(() => currentRows.value.filter((row) => {
  if (filterSource.value && row.trafficSource !== filterSource.value) return false
  if (filterVertical.value && row.vertical !== filterVertical.value) return false
  if (filterGeo.value.length > 0 && !filterGeo.value.includes(row.geo)) return false
  if (filterCap.value) {
    const cap = progressCap(row)
    const left = cap > 0 ? ((cap - progressSupplied(row)) / cap) * 100 : 0
    if (left > Number(filterCap.value)) return false
  }
  return true
}))

const pageCount = computed(() => Math.max(1, Math.ceil(filtered.value.length / pageSize.value)))
const pageRows = computed(() => {
  const start = (Math.min(page.value, pageCount.value) - 1) * pageSize.value
  return filtered.value.slice(start, start + pageSize.value)
})

const kpis = computed(() => {
  const inProgress = acceptedRows.value.filter((row) => row.status === 'approved').length
  const accepted = market.offers.filter((offer) => offer.status !== 'rejected')
  const acceptedValue = accepted.reduce((sum, offer) => {
    const price = presetOf(offer.presetId)?.basePriceCents ?? 0
    return sum + offer.volume * price
  }, 0)
  return [
    { label: 'Available Demands', value: availableActiveCount.value.toLocaleString('en-US'), icon: 'mdi-format-list-bulleted' },
    { label: 'In Progress', value: inProgress.toLocaleString('en-US'), icon: 'mdi-lightning-bolt-outline' },
    { label: 'Accepted', value: accepted.length.toLocaleString('en-US'), icon: 'mdi-check' },
    { label: 'Accepted value', value: dollars(acceptedValue / 100), icon: 'mdi-cash-multiple' },
  ]
})

watch([filterScope, filterSource, filterVertical, filterGeo, filterCap, tab, pageSize], () => {
  page.value = 1
})

watch(pageCount, (count) => {
  if (page.value > count) page.value = count
})

function setTab(next: 'available' | 'accepted'): void {
  tab.value = next
}

function remaining(row: DemandRow): number {
  return Math.max(0, row.volume - row.supplied)
}

function remainingPercent(row: DemandRow): number {
  if (row.volume < 1) return 0
  return Math.max(0, Math.min(100, Math.round((remaining(row) / row.volume) * 100)))
}

/** After an admin approves the offer the details grow into the delivery workspace. */
const acceptedDetails = computed(() => dialogMode.value === 'details'
  && selected.value?.kind === 'offer'
  && (selected.value.status === 'approved' || selected.value.status === 'finished'))

const dialogTitle = computed(() => {
  if (dialogMode.value === 'accept') return 'Accept Demand'
  return selected.value?.kind === 'offer' ? 'Offer Details' : 'Demand Details'
})

const dailyCapLimit = computed(() => (selected.value ? Math.max(remaining(selected.value), 1) : 1))
const validDailyCap = computed(() => {
  const value = acceptVolume.value.trim()
  if (!/^\d+$/.test(value)) return false
  const volume = Number(value)
  return volume >= 1 && volume <= dailyCapLimit.value
})
const dailyCapHelp = computed(() => {
  if (formError.value) return formError.value
  if (acceptVolume.value !== '' && !validDailyCap.value) {
    return `Daily cap must be a whole number from 1 to ${dailyCapLimit.value.toLocaleString('en-US')}.`
  }
  return 'Please specify your planned daily cap.'
})

function statusTone(status: DemandStatus): string {
  if (status === 'pending') return 'lavender'
  if (status === 'approved') return 'primary'
  if (status === 'finished') return 'success'
  if (status === 'rejected') return 'error'
  return 'default'
}

function statusLabel(status: DemandStatus): string {
  if (status === 'pending') return 'Under Review'
  if (status === 'approved') return 'In Progress'
  if (status === 'finished') return 'Finished'
  if (status === 'rejected') return 'Rejected'
  return 'Available'
}

function statusExplanation(status: DemandStatus): string {
  if (status === 'pending') return 'Your acceptance is awaiting review. Delivery is not active yet.'
  if (status === 'approved') return 'Your acceptance has been approved and delivery is active.'
  if (status === 'finished') return 'You have delivered the full daily cap for this offer.'
  if (status === 'rejected') return 'Your acceptance was rejected. Delivery is not active.'
  return ''
}

function openDetails(row: DemandRow): void {
  selected.value = row
  dialogMode.value = 'details'
  formError.value = ''
  dialogOpen.value = true
}

function openAccept(row: DemandRow): void {
  selected.value = row
  dialogMode.value = 'accept'
  acceptVolume.value = ''
  formError.value = ''
  dialogOpen.value = true
  // Move focus into the field before the dialog's own focus trap picks the close button.
  window.setTimeout(() => document.getElementById('demand-daily-cap')?.focus(), 60)
}

async function accept(): Promise<void> {
  const row = selected.value
  if (!row) return
  if (!validDailyCap.value) return
  const volume = Number(acceptVolume.value.trim())
  busy.value = true
  formError.value = ''
  try {
    await market.createOffer({
      preset_id: row.presetId,
      geo: row.geo,
      volume,
      kind: 'day',
      service_on: row.serviceOn ?? '',
    })
    dialogOpen.value = false
    tab.value = 'accepted'
    showToast('Demand accepted. Leads start after an admin approves the offer.')
  } catch (error) {
    formError.value = error instanceof Error ? error.message : 'The offer was not created'
  } finally {
    busy.value = false
  }
}

async function copyCell(event: MouseEvent, value: string): Promise<void> {
  if (!isTableCellCopyTarget(event.target)) return
  const text = copyTextFromExportCell(value)
  if (!text) return
  showToast((await copyText(text)) ? 'Copied' : 'Copy unavailable')
}

const activeFilterCount = computed(() => countFilledFilters({
  scope: filterScope.value === 'active' ? '' : filterScope.value,
  vertical: filterVertical.value,
  geo: filterGeo.value,
  source: filterSource.value,
  cap: filterCap.value,
}))

function clearFilters(): void {
  filterScope.value = 'active'
  filterSource.value = null
  filterVertical.value = null
  filterGeo.value = []
  filterCap.value = null
}

function loadDemands(): Promise<unknown> {
  return Promise.all([market.loadCaps(), market.loadOffers(), market.loadPresets()])
}

const marketLoadError = computed(() => market.error)
useLoadErrorToast(marketLoadError, () => { void loadDemands() })

onMounted(() => {
  void loadDemands()
})
</script>

<style scoped>
.demands-page {
  display: grid;
  gap: 16px;
  min-width: 0;
}

/* The grid gap already spaces the blocks; the shared heading margin would double it. */
.demands-page > .page-head {
  margin-bottom: 0;
}

.demands-kpis {
  display: grid;
  gap: 14px;
  grid-template-columns: repeat(4, minmax(0, 1fr));
}

.demands-kpi,
.demands-card {
  background: linear-gradient(115deg, #ffffffbc, #ffffffed);
  border: 1px solid #dedbff;
  border-radius: 16px;
  box-shadow: 0 5px 12px #2720640d, 0 2px 3px #4b36c005;
}

.demands-kpi {
  align-items: center;
  display: flex;
  gap: 15px;
  min-height: 88px;
  min-width: 0;
  padding: 18px 20px;
}

.demands-kpi__icon {
  align-items: center;
  background: #eeebff;
  border-radius: 12px;
  color: #5b45e8;
  display: inline-flex;
  flex-shrink: 0;
  height: 44px;
  justify-content: center;
  width: 44px;
}

.demands-kpi > div {
  min-width: 0;
}

.demands-kpi > div > span {
  font-size: 12px;
  font-weight: 500;
}

.demands-kpi strong {
  display: block;
  font-size: 26px;
  font-variant-numeric: tabular-nums;
  font-weight: 700;
  letter-spacing: -0.7px;
  line-height: 1.1;
  margin-top: 4px;
  overflow-wrap: anywhere;
}

.demands-tabs {
  display: flex;
  flex-wrap: wrap;
  gap: 4px;
  margin-top: 4px;
}

.demands-tabs > button {
  align-items: center;
  background: #ffffffb3;
  border: 1px solid #dedbff;
  border-radius: 9px;
  color: #10132d;
  display: inline-flex;
  font-size: 13px;
  font-weight: 600;
  gap: 12px;
  justify-content: center;
  min-height: 40px;
  padding: 10px 24px;
  transition: background 150ms, color 150ms;
}

.demands-tabs > button[aria-selected='true'] {
  background: #5b45e8;
  border-color: #5b45e8;
  box-shadow: 0 3px 8px #6635df22;
  color: #fff;
}

.demands-tabs > button > span {
  align-items: center;
  background: #eeebff;
  border-radius: 11px;
  color: #3412b9;
  display: inline-flex;
  font-size: 12px;
  height: 22px;
  justify-content: center;
  min-width: 25px;
  padding: 0 6px;
}

.demands-tabs > button[aria-selected='true'] > span {
  background: #f4efff;
}

.demands-card {
  min-width: 0;
  padding: 14px;
}

/* Fill layout (desktop): cards stay fixed, the table scrolls inside the remaining height. */
.demands-page.cabinet-page--fill {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.demands-page.cabinet-page--fill > .demands-card {
  display: flex;
  flex: 1 1 auto;
  flex-direction: column;
  min-height: 0;
  overflow: hidden;
}

.demands-page.cabinet-page--fill > .demands-card > * {
  flex: 0 0 auto;
}

.demands-page.cabinet-page--fill > .demands-card > .demands-table-scroll {
  flex: 1 1 auto;
  min-height: 0;
}

.demands-filters {
  display: grid;
  gap: 12px;
  grid-template-columns: repeat(5, minmax(0, 1fr));
  margin-bottom: 14px;
}

/* The filters live inside the table card, so the shared panel must not draw a second card. */
.demands-card .demands-filter-panel.ui-filter-panel {
  background: transparent;
  border: 0;
  box-shadow: none;
  margin-bottom: 0;
  padding: 0;
}

@media (max-width: 767px) {
  .demands-card .demands-filter-panel.ui-filter-panel {
    margin-bottom: 12px;
    padding: 0;
  }

  .demands-filters {
    grid-template-columns: minmax(0, 1fr);
    margin-bottom: 0;
  }
}

.demands-table-scroll {
  max-width: 100%;
  overflow: auto;
  scrollbar-color: #b9a9f4 transparent;
  scrollbar-width: thin;
}

.demands-table {
  border-collapse: separate;
  border-spacing: 0;
  font-size: 12px;
  min-width: 1090px;
  table-layout: fixed;
  width: 100%;
}

.demands-table th:nth-child(2) {
  width: 140px;
}

.demands-table th:nth-child(2),
.demands-table td:nth-child(2) {
  padding-left: 16px;
}

.demands-table th:nth-child(3) {
  width: 170px;
}

.demands-table th:nth-child(4) {
  text-align: center;
  width: 100px;
}

.demands-table th:nth-child(5) {
  text-align: right;
  width: 110px;
}

.demands-table th:nth-child(6),
.demands-table td:nth-child(6) {
  padding-left: 24px;
  padding-right: 24px;
}

/* Wide screens: keep the cap bar from stretching across the whole column. */
@media (min-width: 1700px) {
  .demands-table th:nth-child(6),
  .demands-table td:nth-child(6) {
    padding-left: 96px;
    padding-right: 96px;
  }
}

.demands-table th:nth-child(7) {
  width: 200px;
}

.demands-table th {
  background: #eeebff;
  border-bottom: 1px solid #eae8fc;
  box-sizing: border-box;
  color: #10132d;
  font-weight: 600;
  height: 32px;
  padding: 11px 7px;
  position: sticky;
  text-align: left;
  top: 0;
  white-space: nowrap;
  z-index: 2;
}

.demands-table th:first-child,
.demands-table td:first-child {
  border-right: 1px solid #efedfb;
  left: 0;
  min-width: 210px;
  position: sticky;
  text-align: left;
  width: 210px;
}

.demands-table th:first-child {
  border-radius: 9px 0 0 9px;
  padding-left: 11px;
  z-index: 3;
}

.demands-table th:last-child {
  border-radius: 0 9px 9px 0;
}

/* Body cells are right-aligned in the prototype (global td rule); Country and source read as text, keep them left. */
.demands-table td {
  background: #fdfdff;
  border-bottom: 1px solid #eeecfa;
  box-sizing: border-box;
  height: 49px;
  padding: 9px 7px;
  text-align: right;
  vertical-align: middle;
  white-space: nowrap;
}

.demands-table td:first-child {
  padding-left: 11px;
  z-index: 1;
}

.demands-table td:nth-child(2),
.demands-table td:nth-child(3) {
  text-align: left;
}

.demands-table td:nth-child(4) {
  text-align: center;
}

.demands-table td:last-child {
  min-width: 96px;
}

/* The pagination below draws its own top border; avoid a double line. */
.demands-table tbody tr:last-child td {
  border-bottom: 0;
}

/* Mobile: the table scrolls horizontally, the first column scrolls with it. */
@media (max-width: 767px) {
  .demands-table th:first-child,
  .demands-table td:first-child {
    border-right: 0;
    left: auto;
    min-width: 150px;
    width: 150px;
  }

  .demands-table td:first-child {
    position: static;
  }
}

.demands-table td.demands-copy {
  cursor: pointer;
}

.demands-table td.demands-copy:hover {
  color: #3512bc;
}

.demands-table tbody tr:hover td {
  background: #f5f2ff;
}

.demands-source {
  display: block;
  font-weight: 500;
  overflow-wrap: anywhere;
  white-space: normal;
}

.demands-number {
  font-variant-numeric: tabular-nums;
  text-align: right;
}

.demands-geo {
  align-items: center;
  display: inline-flex;
  gap: 6px;
}

.demands-entity {
  align-items: center;
  display: inline-flex;
  gap: 8px;
  min-width: 0;
}

.demands-actions {
  align-items: center;
  display: flex;
  gap: 8px;
  justify-content: flex-end;
}

.demands-actions :deep(.ui-btn) {
  border-radius: 8px;
  box-shadow: none;
  font-size: 12px;
  height: 31px;
  min-height: 31px;
  min-width: 0;
  padding: 0 12px;
}

.demands-actions :deep(.ui-btn .v-btn__prepend) {
  margin-inline-end: 0;
}

.demands-actions :deep(.ui-btn .v-btn__content) {
  gap: 6px;
}

.demands-status-help {
  align-items: center;
  display: inline-flex;
  gap: 6px;
  white-space: nowrap;
}

.demands-status-question {
  align-items: center;
  border-radius: 8px;
  color: #464d78;
  cursor: help;
  display: inline-flex;
  height: 30px;
  justify-content: center;
  width: 30px;
}

.demands-status-question:hover,
.demands-status-question:focus-visible {
  background: #eeebff;
  color: #3412b9;
  outline: none;
}

.demands-empty {
  color: #464d78;
  display: grid;
  font-size: 13px;
  gap: 10px;
  justify-items: center;
  padding: 42px 14px;
  text-align: center;
}

.demands-empty strong {
  color: #10132d;
}

.demands-dialog {
  background: #fff;
  border: 1px solid #dedbff;
  border-radius: 18px;
  box-shadow: 0 20px 70px #10153533;
  color: #10132d;
  line-height: 1.5;
  max-height: calc(100dvh - 40px);
  overflow-y: auto;
  padding: 28px;
  position: relative;
}

.demands-dialog > h2 {
  align-items: center;
  display: flex;
  flex-wrap: wrap;
  font-size: 22px;
  gap: 12px;
  font-weight: 700;
  letter-spacing: -0.5px;
  line-height: 1.5;
  margin: 0;
  padding-right: 25px;
}

.demands-dialog__close {
  align-items: center;
  background: transparent;
  border: 0;
  border-radius: 6px;
  cursor: pointer;
  color: #626680;
  display: flex;
  height: 30px;
  justify-content: center;
  position: absolute;
  right: 18px;
  top: 19px;
  width: 30px;
}

.demands-dialog__close:hover {
  background: #f0ecff;
}

.demands-modal-intro {
  font-size: 13px;
  margin: 18px 0 10px;
}

.demands-modal-intro > strong {
  color: #3412b9;
  font-weight: 700;
}

.demands-modal-intro > p {
  color: #464d78;
  line-height: 1.6;
  margin: 6px 0 0;
}

.demands-summary {
  font-size: 13px;
  margin: 13px 0;
}

.demands-summary > div {
  align-items: center;
  border-bottom: 1px solid #dedbff;
  display: flex;
  gap: 18px;
  justify-content: space-between;
  padding: 9px 0;
}

.demands-summary dt {
  color: #464d78;
}

.demands-summary dd {
  margin: 0;
  text-align: right;
}

.demands-summary .demands-summary__total {
  font-weight: 700;
  padding: 13px 0;
}

.demands-summary__total dd {
  color: #3412b9;
  font-size: 18px;
}

.demands-accept-form {
  display: grid;
  font-size: 13px;
  gap: 6px;
  margin-top: 16px;
}

.demands-accept-form > input {
  background: #fff;
  border: 1px solid #cdd0e6;
  border-radius: 9px;
  color: #10132d;
  font: inherit;
  font-size: 13px;
  height: 38px;
  min-width: 0;
  padding: 0 12px;
  width: 100%;
}

.demands-accept-form > input:focus-visible {
  outline: 3px solid #9486ff;
  outline-offset: 3px;
}

.demands-accept-form > small {
  color: #464d78;
  font-size: smaller;
}

.demands-accept-form > small.demands-form-error {
  color: #b82732;
}

.demands-modal-footer {
  align-items: center;
  display: flex;
  flex-wrap: wrap;
  gap: 10px;
  justify-content: flex-end;
  padding-top: 18px;
}

.demands-modal-footer :deep(.ui-btn) {
  border-radius: 10px;
  font-size: 13px;
  gap: 8px;
  height: 36px;
  min-height: 36px;
  padding: 0 14px;
}

.demands-modal-footer :deep(.ui-btn .v-btn__prepend) {
  margin-inline-end: 0;
}

@media (max-width: 600px) {
  .demands-dialog {
    padding: 24px;
  }
}

.demands-sr {
  clip: rect(0 0 0 0);
  height: 1px;
  overflow: hidden;
  position: absolute;
  width: 1px;
}

@media (max-width: 1350px) {
  .demands-filters {
    gap: 8px;
  }

  .demands-kpi {
    gap: 10px;
    padding: 16px;
  }

  .demands-kpi strong {
    font-size: 24px;
  }
}

@media (max-width: 1000px) {
  .demands-kpis {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}

@media (max-width: 600px) {
  .demands-page {
    gap: 12px;
  }

  .demands-tabs > button {
    flex: 1;
    font-size: 12px;
    gap: 7px;
    padding: 9px 12px;
  }

  .demands-kpi {
    align-items: flex-start;
    padding: 13px;
  }

  .demands-kpi strong {
    font-size: 22px;
  }

}

@media (max-width: 380px) {
  .demands-kpis {
    grid-template-columns: minmax(0, 1fr);
  }
}
</style>

<style>
.demands-dialog-root {
  --v-overlay-opacity: 0.44;
}
</style>
