<template>
  <!-- Side panel: “second modal” beside Create order -->
  <div v-if="panel === 'side'" class="stock-side" role="complementary" aria-label="Buy from stock">
    <header class="stock-side__offer">
      <h2 class="stock-side__title">Buy leads from stock</h2>
      <p class="stock-side__subtitle">Ready-to-buy leads at shelf prices. Instant delivery — no service day.</p>
      <p class="stock-side__context">
        <strong>{{ available }}</strong> in stock
        <template v-if="requestedQty > 0">
          <span class="stock-side__context-sep">·</span>
          your order <strong>{{ requestedQty }}</strong>
          <template v-if="instantQty >= requestedQty">— fully covered</template>
          <template v-else>— up to <strong>{{ instantQty }}</strong> from shelf</template>
        </template>
      </p>
    </header>

    <div class="stock-side__main">
      <StockPickControls v-model:pick-zone="pickZone" />

      <section class="stock-side__zones" aria-labelledby="stock-zones-heading">
        <h3 id="stock-zones-heading" class="stock-side__zones-title">Shelf zones for your quantity</h3>

        <div class="stock-side__zone-scroll">
          <p v-if="quoteQty < 1" class="stock-side__empty">
            Enter quantity in the order form to see zone breakdown.
          </p>
          <p v-else-if="quoteLoading" class="stock-side__loading">
            <v-progress-circular indeterminate color="primary" size="20" width="2" />
            Calculating shelf mix…
          </p>

          <div v-else-if="quoteReady" class="stock-side__zone-list">
            <article
              v-for="line in sortedLines"
              :key="line.zoneId"
              class="stock-offer-card"
              :class="`stock-offer-card--${line.zoneId}`"
            >
          <div class="stock-offer-card__intro">
            <span class="stock-offer-card__icon" aria-hidden="true">
              <v-icon :icon="zoneIcon(line.zoneId)" size="22" />
            </span>
            <div class="stock-offer-card__intro-text">
              <div class="stock-offer-card__name-row">
                <h4 class="stock-offer-card__name">{{ line.label }} leads</h4>
                <span v-if="line.zoneId === 'fresh'" class="stock-offer-card__pill">Hot</span>
              </div>
              <p class="stock-offer-card__desc">{{ zoneBlurb(line.zoneId) }}</p>
            </div>
          </div>

          <div class="stock-offer-card__stats" role="group" aria-label="Zone metrics">
            <div class="stock-offer-card__stat">
              <v-icon icon="mdi-cube-outline" size="18" class="stock-offer-card__stat-icon" />
              <span class="stock-offer-card__stat-label">Qty</span>
              <span class="stock-offer-card__stat-value">{{ line.count }}</span>
            </div>
            <div class="stock-offer-card__stat">
              <v-icon icon="mdi-sale" size="18" class="stock-offer-card__stat-icon" />
              <span class="stock-offer-card__stat-label">Discount</span>
              <span class="stock-offer-card__stat-value">
                {{ line.discountPercent > 0 ? `−${line.discountPercent}%` : '—' }}
              </span>
            </div>
            <div class="stock-offer-card__stat">
              <v-icon icon="mdi-currency-usd" size="18" class="stock-offer-card__stat-icon" />
              <span class="stock-offer-card__stat-label">Per lead</span>
              <span class="stock-offer-card__stat-value stock-offer-card__stat-value--price">
                {{ dollars(line.unitPriceCents / 100) }}
              </span>
            </div>
          </div>

          <footer class="stock-offer-card__foot">
            <span class="stock-offer-card__foot-label">Line total</span>
            <span class="stock-offer-card__foot-price">{{ dollars(line.lineTotalCents / 100) }}</span>
          </footer>
            </article>
          </div>
        </div>
      </section>
    </div>

    <div class="stock-side__dock">
      <section
        v-if="quoteReady && quoteQty > 0"
        class="stock-side__economy"
        aria-labelledby="stock-economy-heading"
      >
      <h3 id="stock-economy-heading" class="stock-side__economy-title">Savings vs day order</h3>
      <dl class="stock-side__economy-rows">
        <div class="stock-side__economy-row">
          <dt>Day order ({{ quoteQty }} leads)</dt>
          <dd>{{ dollars(compareTotalCents / 100) }}</dd>
        </div>
        <div class="stock-side__economy-row">
          <dt>From stock</dt>
          <dd>{{ dollars(stockTotalCents / 100) }}</dd>
        </div>
        <div v-if="savingsCents > 0" class="stock-side__economy-row stock-side__economy-row--save">
          <dt>
            <v-icon icon="mdi-piggy-bank-outline" size="18" />
            You save
          </dt>
          <dd>{{ dollars(savingsCents / 100) }}</dd>
        </div>
      </dl>
      </section>

      <footer class="stock-side__foot">
        <UiButton variant="primary" icon="mdi-cart" class="stock-side__buy" @click="emit('buy')">
          Buy from stock
        </UiButton>
        <button type="button" class="stock-side__dismiss" @click="emit('dismiss')">
          Continue with day order
        </button>
        <p class="stock-side__note">
          <v-icon icon="mdi-sync" size="14" />
          Updates when you change quantity or price.
        </p>
      </footer>
    </div>
  </div>

  <!-- Compact inline (legacy / narrow contexts) -->
  <aside v-else class="stock-order-nudge" role="note" aria-label="Stock available">
    <div class="stock-order-nudge__icon-wrap" aria-hidden="true">
      <v-icon icon="mdi-warehouse" size="22" />
    </div>
    <div class="stock-order-nudge__body">
      <p class="stock-order-nudge__title">Stock available now</p>
      <p class="stock-order-nudge__text">
        <strong>{{ available }}</strong>
        {{ available === 1 ? 'lead is' : 'leads are' }} on the shelf for
        <strong>{{ verticalLabel(vertical) }}</strong> · <strong>{{ countryName(country) }}</strong> ·
        <strong>{{ trafficLabel(trafficSource) }}</strong> — instant delivery, no service day.
      </p>
      <p v-if="requestedQty > 0" class="stock-order-nudge__hint">
        You asked for <strong>{{ requestedQty }}</strong>.
        <template v-if="instantQty >= requestedQty">
          All of them can be fulfilled from stock right now.
        </template>
        <template v-else>
          Up to <strong>{{ instantQty }}</strong> can be bought from stock immediately.
        </template>
      </p>

      <StockPickControls
        v-if="quoteQty > 0"
        v-model:pick-zone="pickZone"
        class="stock-order-nudge__pick"
      />

      <div v-if="quoteQty > 0" class="stock-order-nudge__breakdown">
        <p v-if="quoteLoading" class="stock-order-nudge__breakdown-lead stock-order-nudge__muted">
          Calculating shelf mix…
        </p>
        <template v-else-if="quoteReady">
          <p class="stock-order-nudge__breakdown-lead">
            From your order of <strong>{{ requestedQty }}</strong>, buy
            <strong>{{ quoteQty }}</strong> from stock now:
          </p>
          <ul class="stock-order-nudge__zones">
            <li
              v-for="line in sortedLines"
              :key="line.zoneId"
              class="stock-order-nudge__zone"
              :class="`stock-order-nudge__zone--${line.zoneId}`"
            >
              <span class="stock-order-nudge__zone-qty">{{ line.count }}×</span>
              <span class="stock-order-nudge__zone-label">{{ line.label }}</span>
              <span class="stock-order-nudge__zone-price">{{ dollars(line.unitPriceCents / 100) }}/lead</span>
              <span v-if="line.discountPercent > 0" class="stock-order-nudge__zone-off">−{{ line.discountPercent }}%</span>
            </li>
          </ul>
          <div v-if="savingsCents > 0" class="stock-order-nudge__savings">
            <p class="stock-order-nudge__savings-title">
              Save {{ dollars(savingsCents / 100) }} on stock vs your day-order price
            </p>
          </div>
        </template>
      </div>
    </div>
    <div class="stock-order-nudge__actions">
      <UiButton variant="primary" icon="mdi-cart" @click="emit('buy')">
        Buy from stock
      </UiButton>
      <button type="button" class="stock-order-nudge__dismiss" @click="emit('dismiss')">
        Continue with day order
      </button>
    </div>
  </aside>
</template>

<script setup lang="ts">
import { computed, ref, watchEffect } from 'vue'
import type { StockQuoteLine } from '@/stores/market'
import CountryFlag from '@/components/CountryFlag.vue'
import StockPickControls from '@/components/StockPickControls.vue'
import UiButton from '@/components/ui/UiButton.vue'
import { defaultStockPickZone, type StockPickZoneId } from '@/stockPick'
import { countryName } from '@/countries'
import {
  trafficColor,
  trafficIcon,
  trafficLabel,
  verticalColor,
  verticalIcon,
  verticalLabel,
} from '@/marketOptions'
import { dollars } from '@/money'
import { useMarketStore } from '@/stores/market'

const ZONE_ORDER = ['fresh', 'good', 'expired', 'live'] as const

const ZONE_BLURB: Record<string, string> = {
  fresh: 'Fresh shelf window — best age mix',
  good: 'Good age — balanced price',
  expired: 'Older shelf — deepest discount',
  live: 'Live pool leads on shelf',
}

const props = withDefaults(defineProps<{
  presetId: string
  available: number
  requestedQty: number
  vertical: string
  country: string
  trafficSource: string
  comparePriceCents?: number
  panel?: 'inline' | 'side'
}>(), {
  comparePriceCents: 0,
  panel: 'inline',
})

const emit = defineEmits<{
  buy: []
  dismiss: []
}>()

const market = useMarketStore()
const quoteLines = ref<StockQuoteLine[]>([])
const quoteTotalCents = ref(0)
const quoteBaseCents = ref(0)
const quoteLoading = ref(false)
const pickZone = ref<StockPickZoneId>(defaultStockPickZone())

function zoneBlurb(zoneId: string): string {
  return ZONE_BLURB[zoneId] ?? 'Shelf age zone'
}

const ZONE_ICON: Record<string, string> = {
  fresh: 'mdi-sprout',
  good: 'mdi-star-circle-outline',
  expired: 'mdi-clock-alert-outline',
  live: 'mdi-flash',
}

function zoneIcon(zoneId: string): string {
  return ZONE_ICON[zoneId] ?? 'mdi-package-variant'
}

const instantQty = computed(() => {
  if (props.requestedQty < 1) {
    return props.available
  }
  return Math.min(props.available, props.requestedQty)
})

const quoteQty = computed(() => (props.requestedQty > 0 ? instantQty.value : 0))

const quoteReady = computed(() => quoteTotalCents.value > 0 && quoteLines.value.length > 0)

const sortedLines = computed(() => {
  const rank = (id: string) => {
    const idx = ZONE_ORDER.indexOf(id as (typeof ZONE_ORDER)[number])
    return idx === -1 ? 99 : idx
  }
  return [...quoteLines.value].sort((a, b) => rank(a.zoneId) - rank(b.zoneId))
})

const stockTotalCents = computed(() => quoteTotalCents.value)

const unitCompareCents = computed(() => {
  if (props.comparePriceCents != null && props.comparePriceCents > 0) {
    return props.comparePriceCents
  }
  if (quoteBaseCents.value > 0) {
    return quoteBaseCents.value
  }
  return 0
})

const compareTotalCents = computed(() => {
  if (quoteQty.value < 1 || unitCompareCents.value < 1) {
    return 0
  }
  return quoteQty.value * unitCompareCents.value
})

const savingsCents = computed(() => {
  if (!quoteReady.value || compareTotalCents.value < 1) {
    return 0
  }
  return Math.max(0, compareTotalCents.value - stockTotalCents.value)
})

watchEffect((onCleanup) => {
  const qty = quoteQty.value
  const presetId = props.presetId
  const pick = { pickZone: pickZone.value }
  if (!presetId || qty < 1) {
    quoteLines.value = []
    quoteTotalCents.value = 0
    quoteBaseCents.value = 0
    return
  }
  let cancelled = false
  onCleanup(() => {
    cancelled = true
  })
  quoteLoading.value = true
  void market.fetchStockQuote(presetId, qty, '', pick).then((quote) => {
    if (cancelled) {
      return
    }
    quoteLines.value = quote.lines
    quoteTotalCents.value = quote.totalCents
    quoteBaseCents.value = quote.basePriceCents
  }).catch(() => {
    if (!cancelled) {
      quoteLines.value = []
      quoteTotalCents.value = 0
      quoteBaseCents.value = 0
    }
  }).finally(() => {
    if (!cancelled) {
      quoteLoading.value = false
    }
  })
})
</script>

<style scoped>
/* —— Side panel (business mock) —— */
.stock-side {
  display: flex;
  flex-direction: column;
  gap: 12px;
  height: 100%;
  margin: 0;
  min-height: 0;
  min-width: 0;
}

.stock-side__main {
  display: flex;
  flex: 1 1 auto;
  flex-direction: column;
  gap: 8px;
  min-height: 0;
  overflow: hidden;
}

.stock-side__zones {
  display: flex;
  flex: 1 1 auto;
  flex-direction: column;
  min-height: 0;
}

.stock-side__zone-scroll {
  flex: 1 1 auto;
  margin: 0 -4px;
  min-height: 80px;
  overflow-x: hidden;
  overflow-y: auto;
  overscroll-behavior: contain;
  padding: 0 4px 2px;
  scrollbar-gutter: stable;
}

.stock-side__dock {
  background: #fff;
  border-top: 1px solid #f0eef6;
  display: flex;
  flex: 0 0 auto;
  flex-direction: column;
  gap: 10px;
  margin-top: auto;
  padding-top: 10px;
}

.stock-side__offer {
  margin: 0;
}

.stock-side__title {
  color: #1e1933;
  font-size: 20px;
  font-weight: 700;
  line-height: 1.25;
  margin: 0 0 6px;
}

.stock-side__subtitle {
  color: #6d6a88;
  font-size: 13px;
  line-height: 1.4;
  margin: 0 0 8px;
}

.stock-side__context {
  color: #5c6078;
  font-size: 13px;
  line-height: 1.45;
  margin: 0;
}

.stock-side__context-sep {
  margin: 0 4px;
}

.stock-side__zones-title,
.stock-side__economy-title {
  color: #303861;
  font-size: 11px;
  font-weight: 700;
  letter-spacing: 0.04em;
  margin: 0 0 8px;
  text-transform: uppercase;
}

.stock-side__empty,
.stock-side__loading {
  align-items: center;
  color: #8b90a8;
  display: flex;
  font-size: 13px;
  gap: 10px;
  margin: 0;
}

.stock-side__zone-list {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.stock-offer-card {
  background: #fff;
  border: 1px solid #e8e6f0;
  border-radius: 12px;
  box-shadow: 0 2px 10px rgb(30 25 51 / 5%);
  flex-shrink: 0;
  overflow: hidden;
}

.stock-offer-card__intro {
  display: flex;
  gap: 10px;
  padding: 12px 12px 10px;
}

.stock-offer-card__icon {
  align-items: center;
  border-radius: 50%;
  display: flex;
  flex-shrink: 0;
  height: 40px;
  justify-content: center;
  width: 40px;
}

.stock-offer-card--fresh .stock-offer-card__icon {
  background: #e8f8f0;
  box-shadow: 0 0 0 3px rgb(34 160 107 / 12%);
  color: #1a7a52;
}

.stock-offer-card--good .stock-offer-card__icon {
  background: #fff6e6;
  box-shadow: 0 0 0 3px rgb(232 163 23 / 15%);
  color: #9a6a08;
}

.stock-offer-card--expired .stock-offer-card__icon {
  background: #fcecec;
  box-shadow: 0 0 0 3px rgb(209 67 67 / 12%);
  color: #b33a3a;
}

.stock-offer-card--live .stock-offer-card__icon {
  background: #f0ecff;
  box-shadow: 0 0 0 3px rgb(91 69 232 / 12%);
  color: #5b45e8;
}

.stock-offer-card__intro-text {
  min-width: 0;
}

.stock-offer-card__name-row {
  align-items: center;
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  margin-bottom: 4px;
}

.stock-offer-card__name {
  color: #1e1933;
  font-size: 15px;
  font-weight: 700;
  margin: 0;
}

.stock-offer-card__pill {
  background: #e8f8f0;
  border-radius: 999px;
  color: #1a7a52;
  font-size: 11px;
  font-weight: 700;
  padding: 2px 8px;
  text-transform: uppercase;
}

.stock-offer-card__desc {
  color: #8b90a8;
  font-size: 12px;
  line-height: 1.35;
  margin: 0;
}

.stock-offer-card__stats {
  border-top: 1px solid #f0eef6;
  display: grid;
  gap: 6px;
  grid-template-columns: repeat(3, 1fr);
  padding: 10px 12px;
}

.stock-offer-card__stat {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.stock-offer-card__stat-icon {
  color: #8b90a8;
  margin-bottom: 2px;
}

.stock-offer-card__stat-label {
  color: #8b90a8;
  font-size: 11px;
  font-weight: 500;
}

.stock-offer-card__stat-value {
  color: #303861;
  font-size: 14px;
  font-weight: 700;
}

.stock-offer-card--fresh .stock-offer-card__stat-value {
  color: #1a7a52;
}

.stock-offer-card__stat-value--price {
  color: #5b45e8;
}

.stock-offer-card__foot {
  align-items: center;
  background: #faf9fc;
  border-top: 1px solid #f0eef6;
  display: flex;
  justify-content: space-between;
  padding: 8px 12px;
}

.stock-offer-card__foot-label {
  color: #6d6a88;
  font-size: 12px;
}

.stock-offer-card__foot-price {
  color: #5b45e8;
  font-size: 16px;
  font-weight: 700;
}

.stock-side__economy {
  background: linear-gradient(135deg, #f4f0ff 0%, #eef8ff 100%);
  border: 1px solid #c8baff;
  border-radius: 12px;
  padding: 12px 14px;
}

.stock-side__economy-rows {
  display: flex;
  flex-direction: column;
  gap: 6px;
  margin: 0;
}

.stock-side__economy-row {
  align-items: center;
  display: flex;
  justify-content: space-between;
}

.stock-side__economy-row dt {
  align-items: center;
  color: #5c6078;
  display: flex;
  font-size: 13px;
  font-weight: 500;
  gap: 6px;
}

.stock-side__economy-row dd {
  color: #303861;
  font-size: 14px;
  font-weight: 600;
  margin: 0;
}

.stock-side__economy-row--save dt,
.stock-side__economy-row--save dd {
  color: #2a1578;
  font-size: 15px;
  font-weight: 700;
}

.stock-side__foot {
  display: flex;
  flex-direction: column;
  gap: 8px;
  margin-top: 0;
  padding-top: 0;
}

.stock-side__buy {
  width: 100%;
}

.stock-side__dismiss {
  background: none;
  border: 0;
  color: #6d6a88;
  cursor: pointer;
  font-size: 13px;
  padding: 4px 0;
  text-align: center;
  text-decoration: underline;
  text-underline-offset: 2px;
}

.stock-side__dismiss:hover {
  color: #3512bc;
}

.stock-side__note {
  align-items: center;
  color: #8b90a8;
  display: flex;
  font-size: 11px;
  gap: 6px;
  justify-content: center;
  line-height: 1.35;
  margin: 2px 0 0;
  text-align: center;
}

/* —— Inline —— */
.stock-order-nudge {
  background: linear-gradient(135deg, #f4f0ff 0%, #eef8ff 100%);
  border: 1px solid #c8baff;
  border-radius: 14px;
  display: grid;
  gap: 12px;
  grid-template-columns: auto 1fr;
  margin: 0 0 16px;
  padding: 14px 16px;
}

.stock-order-nudge__icon-wrap {
  align-items: center;
  background: #fff;
  border-radius: 12px;
  box-shadow: 0 2px 8px rgb(91 69 232 / 10%);
  color: #5b45e8;
  display: flex;
  height: 44px;
  justify-content: center;
  width: 44px;
}

.stock-order-nudge__body {
  min-width: 0;
}

.stock-order-nudge__title {
  color: #2a1578;
  font-size: 14px;
  font-weight: 700;
  margin: 0 0 6px;
}

.stock-order-nudge__text,
.stock-order-nudge__hint {
  color: #4a4668;
  font-size: 13px;
  line-height: 1.45;
  margin: 0;
}

.stock-order-nudge__hint {
  margin-top: 8px;
}

.stock-order-nudge__muted {
  color: #8b90a8;
}

.stock-order-nudge__breakdown {
  margin-top: 12px;
}

.stock-order-nudge__breakdown-lead {
  color: #303861;
  font-size: 13px;
  font-weight: 600;
  line-height: 1.4;
  margin: 0 0 8px;
}

.stock-order-nudge__zones {
  display: flex;
  flex-direction: column;
  gap: 6px;
  list-style: none;
  margin: 0;
  padding: 0;
}

.stock-order-nudge__zone {
  align-items: center;
  border-radius: 8px;
  display: flex;
  flex-wrap: wrap;
  font-size: 13px;
  gap: 6px 10px;
  line-height: 1.3;
  padding: 8px 10px;
}

.stock-order-nudge__zone--fresh {
  background: rgb(34 160 107 / 12%);
  border: 1px solid rgb(34 160 107 / 35%);
}

.stock-order-nudge__zone--good {
  background: rgb(232 163 23 / 14%);
  border: 1px solid rgb(232 163 23 / 40%);
}

.stock-order-nudge__zone--expired {
  background: rgb(209 67 67 / 10%);
  border: 1px solid rgb(209 67 67 / 32%);
}

.stock-order-nudge__zone-qty {
  font-weight: 700;
}

.stock-order-nudge__zone-label {
  font-weight: 600;
}

.stock-order-nudge__zone-price {
  color: #4a4668;
}

.stock-order-nudge__zone-off {
  color: #2e9b6a;
  font-size: 12px;
  font-weight: 700;
}

.stock-order-nudge__savings {
  background: linear-gradient(135deg, #fff8e8 0%, #e8fff3 100%);
  border: 1px solid #f0d48a;
  border-radius: 10px;
  margin-top: 10px;
  padding: 10px 12px;
}

.stock-order-nudge__savings-title {
  color: #1a7a52;
  font-size: 14px;
  font-weight: 700;
  margin: 0;
}

.stock-order-nudge__actions {
  align-items: center;
  display: flex;
  flex-direction: column;
  gap: 8px;
  grid-column: 1 / -1;
  justify-content: center;
}

.stock-order-nudge__dismiss {
  background: none;
  border: 0;
  color: #6d6a88;
  cursor: pointer;
  font-size: 13px;
  padding: 4px 0;
  text-decoration: underline;
  text-underline-offset: 2px;
}

.stock-order-nudge__dismiss:hover {
  color: #3512bc;
}

@media (max-width: 767px) {
  .stock-side {
    gap: 10px;
    min-height: 0;
  }

  .stock-side__title {
    font-size: 17px;
    padding-right: 44px;
  }

  .stock-side__subtitle,
  .stock-side__context {
    font-size: 12px;
  }

  .stock-side__dock {
    gap: 10px;
  }

  .stock-side__buy {
    width: 100%;
  }

  .stock-offer-card__stats {
    grid-template-columns: repeat(3, minmax(0, 1fr));
  }
}
</style>