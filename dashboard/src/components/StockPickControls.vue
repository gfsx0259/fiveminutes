<template>
  <div
    class="stock-pick"
    :class="{ 'stock-pick--bare': hideLabel }"
    role="group"
    aria-label="Zone for stock purchase"
  >
    <div class="stock-pick__field">
      <span v-if="!hideLabel" class="stock-pick__label">Zone</span>
      <UiSegmented v-model="pickZone" :items="zoneItems" class="stock-pick__segmented stock-pick__segmented--zones" />
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import UiSegmented from '@/components/ui/UiSegmented.vue'
import { stockZoneDisplayLabel, type StockPickZoneId } from '@/stockPick'
import { useMarketStore } from '@/stores/market'

withDefaults(
  defineProps<{
    hideLabel?: boolean
  }>(),
  { hideLabel: false },
)

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
      { title: stockZoneDisplayLabel('good'), value: 'good' },
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
    items.push({ title: stockZoneDisplayLabel(zone.id, zone.label), value: zone.id })
  }
  return items
})
</script>

<style scoped>
.stock-pick {
  display: flex;
  flex-direction: column;
  gap: 6px;
  margin: 0;
}

.stock-pick__field {
  display: flex;
  flex-direction: column;
  gap: 6px;
  min-width: 0;
}

.stock-pick__label {
  color: #5c6078;
  font-size: 12px;
  font-weight: 600;
  line-height: 1.2;
}

.stock-pick__segmented.ui-segmented {
  display: flex;
  width: 100%;
}

.stock-pick__segmented :deep(.ui-segmented__item) {
  flex: 1 1 0;
  min-width: 0;
  padding-inline: 6px;
  text-align: center;
}

.stock-pick--bare,
.stock-pick--bare .stock-pick__field {
  gap: 0;
}
</style>