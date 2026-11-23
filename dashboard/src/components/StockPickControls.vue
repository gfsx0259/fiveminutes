<template>
  <div class="stock-pick" role="group" aria-label="Shelf zone for stock purchase">
    <div class="stock-pick__row">
      <span class="stock-pick__label">Shelf zone</span>
      <UiSegmented v-model="pickZone" :items="zoneItems" class="stock-pick__segmented stock-pick__segmented--zones" />
    </div>
    <p class="stock-pick__hint">
      <template v-if="pickZone !== ''">Only <strong>{{ pickZone }}</strong> leads — quote shows what you get.</template>
      <template v-else>Any zone — we pick oldest shelf stock first (expired → fresh).</template>
    </p>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import UiSegmented from '@/components/ui/UiSegmented.vue'
import type { StockPickZoneId } from '@/stockPick'
import { useMarketStore } from '@/stores/market'

const pickZone = defineModel<StockPickZoneId>('pickZone', { default: '' })

const market = useMarketStore()

const SELLABLE_ZONE_ORDER = ['fresh', 'good', 'expired'] as const

const zoneItems = computed(() => {
  const items: { title: string, value: string }[] = [{ title: 'Any zone', value: '' }]
  const policy = market.shelfAgePolicy
  if (!policy?.zones?.length) {
    return [
      ...items,
      { title: 'Fresh', value: 'fresh' },
      { title: 'Good', value: 'good' },
      { title: 'Expired', value: 'expired' },
    ]
  }
  const sellable = policy.zones.filter((zone) => zone.sellableOnShelf && zone.id !== 'live')
  sellable.sort((a, b) => {
    const ai = SELLABLE_ZONE_ORDER.indexOf(a.id as (typeof SELLABLE_ZONE_ORDER)[number])
    const bi = SELLABLE_ZONE_ORDER.indexOf(b.id as (typeof SELLABLE_ZONE_ORDER)[number])
    return (ai === -1 ? 99 : ai) - (bi === -1 ? 99 : bi)
  })
  for (const zone of sellable) {
    items.push({ title: zone.label, value: zone.id })
  }
  return items
})
</script>

<style scoped>
.stock-pick {
  display: flex;
  flex-direction: column;
  gap: 8px;
  margin: 0 0 12px;
}

.stock-pick__row {
  align-items: center;
  display: flex;
  flex-wrap: wrap;
  gap: 8px 12px;
  justify-content: space-between;
}

.stock-pick__label {
  color: #5a6080;
  font-size: 12px;
  font-weight: 600;
  letter-spacing: 0.02em;
  text-transform: uppercase;
}

.stock-pick__segmented {
  flex: 1 1 auto;
  max-width: 100%;
}

.stock-pick__segmented--zones {
  flex-wrap: wrap;
}

.stock-pick__hint {
  color: #8b90a8;
  font-size: 12px;
  line-height: 1.4;
  margin: 0;
}

.stock-pick__hint strong {
  color: #4a5070;
  font-weight: 600;
  text-transform: capitalize;
}
</style>