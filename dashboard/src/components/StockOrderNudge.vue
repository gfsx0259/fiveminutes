<template>
  <div class="stock-banner" role="complementary" aria-label="Buy from stock">
    <header class="stock-banner__head">
      <h2 class="stock-banner__title">Buy Leads from Stock</h2>
      <p class="stock-banner__subtitle">Ready-to-buy leads at special conditions.</p>
    </header>

    <div class="stock-banner__list">
      <article
        v-for="zone in zones"
        :key="zone.id"
        class="stock-zone"
        :class="`stock-zone--${zone.id}`"
      >
        <div class="stock-zone__top">
          <div class="stock-zone__head">
            <span class="stock-zone__icon" aria-hidden="true">
              <v-icon :icon="zone.icon" size="24" />
            </span>
            <h3 class="stock-zone__name">{{ zone.title }}</h3>
            <span v-if="zone.hot" class="stock-zone__hot">Hot</span>
          </div>
          <p class="stock-zone__desc">{{ zone.description }}</p>

          <div class="stock-zone__stats">
            <div class="stock-zone__stat">
              <v-icon icon="mdi-lightning-bolt-outline" size="20" class="stock-zone__stat-icon stock-zone__stat-icon--cr" />
              <div class="stock-zone__stat-text">
                <span class="stock-zone__stat-label">CR</span>
                <span class="stock-zone__stat-value stock-zone__stat-value--cr">{{ crLabel }}</span>
              </div>
            </div>
            <div class="stock-zone__stat">
              <v-icon icon="mdi-layers-outline" size="20" class="stock-zone__stat-icon stock-zone__stat-icon--quality" />
              <div class="stock-zone__stat-text">
                <span class="stock-zone__stat-label">Quality</span>
                <span class="stock-zone__stat-value stock-zone__stat-value--quality">{{ zone.quality }}</span>
              </div>
            </div>
            <div class="stock-zone__stat">
              <v-icon icon="mdi-cube-outline" size="20" class="stock-zone__stat-icon stock-zone__stat-icon--volume" />
              <div class="stock-zone__stat-text">
                <span class="stock-zone__stat-label">Volume</span>
                <span class="stock-zone__stat-value">{{ zone.volume.toLocaleString('en-US') }}</span>
              </div>
            </div>
          </div>
        </div>

        <footer class="stock-zone__foot">
          <span class="stock-zone__price-label">Price per lead</span>
          <span class="stock-zone__price">{{ dollars(zone.priceCents / 100) }}</span>
          <UiButton
            class="stock-zone__buy"
            variant="primary"
            icon="mdi-cart-outline"
            @click="emit('buy', zone.id)"
          >
            Buy now
          </UiButton>
        </footer>
      </article>
    </div>

    <p class="stock-banner__note">
      <v-icon icon="mdi-information-outline" size="20" class="stock-banner__note-icon" />
      Stock is limited and updated in real time.
    </p>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import UiButton from '@/components/ui/UiButton.vue'
import { dollars } from '@/money'
import type { StockBuyZone } from '@/stockModalIntent'

type ZoneAge = { startMinutes: number; endMinutes: number | null }

const props = defineProps<{
  available: number
  fresh?: number
  aged?: number
  expired?: number
  conversion?: number | null
  /** Catalog price. Each card uses its shelf-zone unit price when that zone has one. */
  priceCents: number
  zonePrices?: Partial<Record<StockBuyZone, number>>
  /** Shelf-age window of each zone; turns the card description into the real lead age. */
  zoneAges?: Partial<Record<StockBuyZone, ZoneAge>>
}>()

const emit = defineEmits<{
  buy: [zone: StockBuyZone]
}>()

const crLabel = computed(() => {
  const value = props.conversion
  if (value == null || value <= 0) {
    return '—'
  }
  return `${value}%`
})

type ZoneCard = {
  id: StockBuyZone
  title: string
  description: string
  quality: string
  icon: string
  hot: boolean
  volume: number
  priceCents: number
}

function zonePrice(id: StockBuyZone): number {
  const priced = props.zonePrices?.[id]
  if (priced != null && priced > 0) {
    return priced
  }
  return props.priceCents
}

function ageLabel(minutes: number): string {
  if (minutes >= 1440 && minutes % 1440 === 0) {
    const days = minutes / 1440
    return `${days} ${days === 1 ? 'day' : 'days'}`
  }
  if (minutes >= 60) {
    const hours = Math.round((minutes / 60) * 10) / 10
    return `${hours} ${hours === 1 ? 'hour' : 'hours'}`
  }
  return `${minutes} ${minutes === 1 ? 'minute' : 'minutes'}`
}

function zoneDescription(id: StockBuyZone, fallback: string): string {
  const age = props.zoneAges?.[id]
  if (!age) {
    return fallback
  }
  if (id === 'fresh') {
    return age.endMinutes != null && age.endMinutes > 0
      ? `Leads generated within the last ${ageLabel(age.endMinutes)}.`
      : fallback
  }
  if (id === 'expired' || age.endMinutes == null) {
    return age.startMinutes > 0 ? `Leads older than ${ageLabel(age.startMinutes)}.` : fallback
  }
  return age.startMinutes > 0
    ? `Leads from ${ageLabel(age.startMinutes)} to ${ageLabel(age.endMinutes)} old.`
    : fallback
}

const zones = computed<ZoneCard[]>(() => {
  const known = (props.fresh ?? 0) + (props.aged ?? 0) + (props.expired ?? 0)
  const fresh = known > 0 ? (props.fresh ?? 0) : props.available
  const aged = props.aged ?? 0
  const expired = props.expired ?? 0
  const cards: ZoneCard[] = []
  if (fresh > 0) {
    cards.push({
      id: 'fresh',
      title: 'Fresh Leads',
      description: zoneDescription('fresh', 'Newest leads on the shelf.'),
      quality: 'High',
      icon: 'mdi-lightning-bolt-outline',
      hot: true,
      volume: fresh,
      priceCents: zonePrice('fresh'),
    })
  }
  if (aged > 0) {
    cards.push({
      id: 'good',
      title: 'Aged Leads',
      description: zoneDescription('good', 'Leads past the fresh window.'),
      quality: 'Medium',
      icon: 'mdi-calendar-month-outline',
      hot: false,
      volume: aged,
      priceCents: zonePrice('good'),
    })
  }
  if (expired > 0) {
    cards.push({
      id: 'expired',
      title: 'Expired Leads',
      description: zoneDescription('expired', 'Oldest leads still on the shelf.'),
      quality: 'Low',
      icon: 'mdi-clock-outline',
      hot: false,
      volume: expired,
      priceCents: zonePrice('expired'),
    })
  }
  return cards
})

defineExpose({
  volumeOf(zone: StockBuyZone): number {
    return zones.value.find((card) => card.id === zone)?.volume ?? 0
  },
})
</script>

<style scoped>
/* Fills the banner panel: header and note stay put, the zone list scrolls. */
.stock-banner {
  display: flex;
  flex: 1 1 auto;
  flex-direction: column;
  min-height: 0;
  min-width: 0;
}

.stock-banner__head {
  flex: 0 0 auto;
  padding: 0 44px 18px 2px;
}

.stock-banner__title {
  color: #070916;
  font-size: 22px;
  font-weight: 700;
  letter-spacing: -0.5px;
  line-height: 1.25;
  margin: 0;
}

.stock-banner__subtitle {
  color: #464d78;
  font-size: 14px;
  line-height: 1.45;
  margin: 8px 0 0;
}

.stock-banner__list {
  display: flex;
  flex: 1 1 auto;
  flex-direction: column;
  gap: 14px;
  margin: 0 -8px;
  min-height: 0;
  overflow-y: auto;
  overscroll-behavior: contain;
  padding: 2px 8px 14px;
}

.stock-zone {
  background: #fff;
  border: 1px solid #ebe9f7;
  border-radius: 16px;
  box-shadow: 0 6px 18px rgb(30 25 51 / 7%);
  flex: 0 0 auto;
  overflow: hidden;
}

.stock-zone__top {
  padding: 16px 18px 16px;
}

.stock-zone--fresh .stock-zone__top {
  background: linear-gradient(180deg, #e6f8ee 0%, #f5fcf8 100%);
}

.stock-zone--good .stock-zone__top {
  background: linear-gradient(180deg, #edeafd 0%, #f8f7fe 100%);
}

.stock-zone--expired .stock-zone__top {
  background: linear-gradient(180deg, #fdebef 0%, #fef8f9 100%);
}

.stock-zone__head {
  align-items: center;
  display: flex;
  gap: 12px;
}

.stock-zone__icon {
  align-items: center;
  border-radius: 12px;
  display: flex;
  flex: 0 0 auto;
  height: 42px;
  justify-content: center;
  width: 42px;
}

.stock-zone--fresh .stock-zone__icon {
  background: #cdf3dc;
  color: #17a34a;
}

.stock-zone--good .stock-zone__icon {
  background: #ddd7fb;
  color: #5b45e8;
}

.stock-zone--expired .stock-zone__icon {
  background: #fbd9e0;
  color: #d03a5c;
}

.stock-zone__name {
  color: #10132d;
  font-size: 18px;
  font-weight: 700;
  letter-spacing: -0.3px;
  line-height: 1.2;
  margin: 0;
}

.stock-zone__hot {
  background: #2fca73;
  border-radius: 999px;
  color: #06361c;
  font-size: 11px;
  font-weight: 700;
  line-height: 1;
  padding: 5px 10px;
}

.stock-zone__desc {
  color: #464d78;
  font-size: 13px;
  line-height: 1.4;
  margin: 10px 0 0;
}

.stock-zone__stats {
  display: grid;
  gap: 10px;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  margin-top: 16px;
}

.stock-zone__stat {
  align-items: flex-start;
  display: flex;
  gap: 8px;
  min-width: 0;
}

.stock-zone__stat-icon {
  flex: 0 0 auto;
  margin-top: 1px;
}

.stock-zone__stat-icon--cr {
  color: #ef4444;
}

.stock-zone__stat-icon--volume {
  color: #5b45e8;
}

.stock-zone__stat-text {
  display: flex;
  flex-direction: column;
  gap: 3px;
  min-width: 0;
}

.stock-zone__stat-label {
  color: #10132d;
  font-size: 13px;
  font-weight: 700;
  line-height: 1.2;
}

.stock-zone__stat-value {
  color: #10132d;
  font-size: 13px;
  font-weight: 600;
  line-height: 1.2;
}

/* Fresh: green CR and quality. Aged: orange quality. Expired: rose quality. */
.stock-zone--fresh .stock-zone__stat-value--cr,
.stock-zone--fresh .stock-zone__stat-value--quality,
.stock-zone--fresh .stock-zone__stat-icon--quality {
  color: #17a34a;
}

.stock-zone--good .stock-zone__stat-value--quality,
.stock-zone--good .stock-zone__stat-icon--quality {
  color: #f59e0b;
}

.stock-zone--expired .stock-zone__stat-value--quality,
.stock-zone--expired .stock-zone__stat-icon--quality {
  color: #d03a5c;
}

.stock-zone__foot {
  align-items: center;
  background: #fff;
  border-top: 1px solid #efedf8;
  display: flex;
  gap: 16px;
  padding: 12px 14px 12px 18px;
}

.stock-zone__price-label {
  color: #10132d;
  font-size: 13px;
  font-weight: 700;
  margin-right: auto;
}

.stock-zone__price {
  color: #4a2fe0;
  font-size: 19px;
  font-weight: 700;
  letter-spacing: -0.3px;
  white-space: nowrap;
}

.stock-zone__foot .stock-zone__buy {
  flex: 0 0 auto;
  font-size: 14px;
  height: 44px;
  min-width: 148px;
}

.stock-banner__note {
  align-items: center;
  border-top: 1px solid #efedf8;
  color: #10132d;
  display: flex;
  flex: 0 0 auto;
  font-size: 13px;
  gap: 10px;
  margin: 0;
  padding: 16px 2px 0;
}

.stock-banner__note-icon {
  color: #5b45e8;
}

@media (max-width: 420px) {
  .stock-zone__foot {
    flex-wrap: wrap;
  }

  .stock-zone__foot .stock-zone__buy {
    flex: 1 1 100%;
  }
}
</style>
