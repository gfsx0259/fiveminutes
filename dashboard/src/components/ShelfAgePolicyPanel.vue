<template>
  <div class="shelf-policy">
    <p v-if="loading" class="text-medium-emphasis">Loading…</p>
    <template v-else>
      <div class="shelf-policy__bar" aria-hidden="true">
        <span class="shelf-policy__seg shelf-policy__seg--live" :style="{ width: `${segPct.live}%` }" />
        <span class="shelf-policy__seg shelf-policy__seg--fresh" :style="{ width: `${segPct.fresh}%` }" />
        <span class="shelf-policy__seg shelf-policy__seg--good" :style="{ width: `${segPct.good}%` }" />
        <span class="shelf-policy__seg shelf-policy__seg--expired" :style="{ width: `${segPct.expired}%` }" />
      </div>
      <div class="shelf-policy__scale text-medium-emphasis">
        <span>0</span>
        <span>{{ formatMinutes(HORIZON) }} max</span>
      </div>

      <div class="shelf-policy__thumbs">
        <UiField :label="`Live → Fresh (${formatMinutes(boundaries[0])})`">
          <input
            v-model.number="boundaries[0]"
            class="shelf-policy__range"
            type="range"
            :min="MIN_GAP"
            :max="boundaries[1] - MIN_GAP"
            step="5"
          >
        </UiField>
        <UiField :label="`Fresh → Aged (${formatMinutes(boundaries[1])})`">
          <input
            v-model.number="boundaries[1]"
            class="shelf-policy__range"
            type="range"
            :min="boundaries[0] + MIN_GAP"
            :max="boundaries[2] - MIN_GAP"
            step="5"
          >
        </UiField>
        <UiField :label="`Aged → Expired (${formatMinutes(boundaries[2])})`">
          <input
            v-model.number="boundaries[2]"
            class="shelf-policy__range"
            type="range"
            :min="boundaries[1] + MIN_GAP"
            :max="HORIZON - MIN_GAP"
            step="5"
          >
        </UiField>
      </div>

      <div class="shelf-policy__discounts">
        <div class="shelf-policy__zone-meta">
          <span class="shelf-policy__zone-name">Live</span>
          <span class="text-medium-emphasis">0% — day orders only</span>
        </div>
        <UiField label="Fresh discount, %">
          <UiInput v-model="discounts.fresh" type="number" min="1" max="99" />
        </UiField>
        <UiField label="Aged discount, %">
          <UiInput v-model="discounts.good" type="number" min="1" max="99" />
        </UiField>
        <UiField label="Expired discount, %">
          <UiInput v-model="discounts.expired" type="number" min="1" max="99" />
        </UiField>
      </div>

      <p class="shelf-policy__preview text-medium-emphasis">
        Example at ${{ exampleBase }} catalog:
        Live ${{ preview.live }},
        Fresh ${{ preview.fresh }},
        Aged ${{ preview.good }},
        Expired ${{ preview.expired }}.
      </p>
    </template>
  </div>
</template>

<script setup lang="ts">
import { computed, reactive, watch } from 'vue'
import UiField from '@/components/ui/UiField.vue'
import UiInput from '@/components/ui/UiInput.vue'
import type { ShelfAgePolicy } from '@/stores/market'

const HORIZON = 2880
const MIN_GAP = 5
const exampleBase = 1

const props = defineProps<{
  policy: ShelfAgePolicy | null
  loading?: boolean
}>()

const emit = defineEmits<{
  'update:policy': [ShelfAgePolicy]
}>()

const boundaries = reactive([30, 120, 360])

const discounts = reactive({
  fresh: '10',
  good: '30',
  expired: '70',
})

function formatMinutes(minutes: number): string {
  if (minutes < 60) {
    return `${minutes}m`
  }
  if (minutes < 1440) {
    const hours = minutes / 60
    return Number.isInteger(hours) ? `${hours}h` : `${hours.toFixed(1)}h`
  }
  const days = minutes / 1440
  return Number.isInteger(days) ? `${days}d` : `${days.toFixed(1)}d`
}

function effectivePrice(base: number, discountPercent: number): string {
  const cents = Math.round(base * 100 * (100 - discountPercent) / 100)
  return (cents / 100).toFixed(2)
}

const segPct = computed(() => {
  const [liveEnd, freshEnd, goodEnd] = boundaries
  const span = HORIZON
  return {
    live: (liveEnd / span) * 100,
    fresh: ((freshEnd - liveEnd) / span) * 100,
    good: ((goodEnd - freshEnd) / span) * 100,
    expired: ((span - goodEnd) / span) * 100,
  }
})

const preview = computed(() => ({
  live: effectivePrice(exampleBase, 0),
  fresh: effectivePrice(exampleBase, Number(discounts.fresh) || 0),
  good: effectivePrice(exampleBase, Number(discounts.good) || 0),
  expired: effectivePrice(exampleBase, Number(discounts.expired) || 0),
}))

function applyPolicy(policy: ShelfAgePolicy): void {
  const byId = Object.fromEntries(policy.zones.map((z) => [z.id, z]))
  boundaries[0] = byId.live?.endMinutes ?? 30
  boundaries[1] = byId.fresh?.endMinutes ?? 120
  boundaries[2] = byId.good?.endMinutes ?? 360
  discounts.fresh = String(byId.fresh?.discountPercent ?? 10)
  discounts.good = String(byId.good?.discountPercent ?? 30)
  discounts.expired = String(byId.expired?.discountPercent ?? 70)
}

function buildPolicy(): ShelfAgePolicy {
  const [liveEnd, freshEnd, goodEnd] = boundaries
  return {
    horizonMinutes: HORIZON,
    zones: [
      { id: 'live', label: 'Live', startMinutes: 0, endMinutes: liveEnd, discountPercent: 0, sellableOnShelf: false },
      { id: 'fresh', label: 'Fresh', startMinutes: liveEnd, endMinutes: freshEnd, discountPercent: Number(discounts.fresh), sellableOnShelf: true },
      { id: 'good', label: 'Aged', startMinutes: freshEnd, endMinutes: goodEnd, discountPercent: Number(discounts.good), sellableOnShelf: true },
      { id: 'expired', label: 'Expired', startMinutes: goodEnd, endMinutes: null, discountPercent: Number(discounts.expired), sellableOnShelf: true },
    ],
  }
}

watch(
  () => props.policy,
  (policy) => {
    if (policy?.zones?.length) {
      applyPolicy(policy)
    }
  },
  { immediate: true },
)

watch(
  () => [boundaries[0], boundaries[1], boundaries[2], discounts.fresh, discounts.good, discounts.expired],
  () => {
    emit('update:policy', buildPolicy())
  },
)
</script>

<style scoped>
.shelf-policy__bar {
  background: rgb(91 69 232 / 8%);
  border-radius: 999px;
  display: flex;
  height: 12px;
  margin-bottom: 6px;
  overflow: hidden;
  width: 100%;
}

.shelf-policy__seg {
  display: block;
  height: 100%;
  min-width: 2px;
}

.shelf-policy__seg--live {
  background: #5b45e8;
}

.shelf-policy__seg--fresh {
  background: var(--stock-fresh);
}

.shelf-policy__seg--good {
  background: var(--stock-aged);
}

.shelf-policy__seg--expired {
  background: var(--stock-expired);
}

.shelf-policy__scale {
  display: flex;
  font-size: 0.75rem;
  justify-content: space-between;
  margin-bottom: 1rem;
}

.shelf-policy__thumbs {
  display: grid;
  gap: 0.75rem;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  margin-bottom: 1rem;
}

.shelf-policy__range {
  width: 100%;
}

.shelf-policy__discounts {
  display: grid;
  gap: 0.75rem;
  grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
  margin-bottom: 0.75rem;
}

.shelf-policy__zone-meta {
  display: flex;
  flex-direction: column;
  gap: 4px;
  justify-content: center;
}

.shelf-policy__zone-name {
  font-weight: 600;
}

.shelf-policy__preview {
  font-size: 0.875rem;
  margin: 0 0 0.75rem;
}
</style>