<template>
  <v-tooltip location="top" max-width="280">
    <template #activator="{ props: tipProps }">
      <div
        v-bind="tipProps"
        class="stock-freshness"
        :class="{ 'stock-freshness--empty': total < 1 }"
        tabindex="0"
        role="img"
        :aria-label="ariaLabel"
      >
        <div
          v-if="total > 0"
          class="stock-freshness__track"
          aria-hidden="true"
        >
          <span
            class="stock-freshness__seg stock-freshness__seg--fresh"
            :style="{ width: `${pct.fresh}%` }"
          />
          <span
            class="stock-freshness__seg stock-freshness__seg--good"
            :style="{ width: `${pct.good}%` }"
          />
          <span
            class="stock-freshness__seg stock-freshness__seg--expired"
            :style="{ width: `${pct.expired}%` }"
          />
        </div>
        <span v-else class="stock-dim">—</span>
      </div>
    </template>
    <span class="stock-freshness__tip">{{ tooltip }}</span>
  </v-tooltip>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import type { StockFreshness } from '@/stores/market'
import { stockFreshnessPercents, stockFreshnessTooltip, stockFreshnessTotal } from '@/stockFreshness'

const props = defineProps<{
  freshness?: StockFreshness
}>()

const total = computed(() => stockFreshnessTotal(props.freshness))
const pct = computed(() => stockFreshnessPercents(props.freshness))
const tooltip = computed(() => stockFreshnessTooltip(props.freshness))
const ariaLabel = computed(() => tooltip.value.replace(/\n/g, '. '))
</script>

<style scoped>
.stock-freshness {
  align-items: center;
  cursor: help;
  display: flex;
  justify-content: center;
  width: 100%;
}

.stock-freshness__track {
  background: rgb(91 69 232 / 8%);
  border-radius: 999px;
  display: flex;
  height: 8px;
  overflow: hidden;
  width: 100%;
}

.stock-freshness__seg {
  display: block;
  height: 100%;
  min-width: 2px;
}

.stock-freshness__seg--fresh {
  background: #22a06b;
}

.stock-freshness__seg--good {
  background: #e8a317;
}

.stock-freshness__seg--expired {
  background: #d14343;
}

.stock-freshness__tip {
  white-space: pre-line;
}

.stock-dim {
  color: #9b9db5;
}
</style>