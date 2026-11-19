<template>
  <div class="top-geos">
    <div class="top-geos__row top-geos__row--head">
      <span>Country</span>
      <span>Share of spend</span>
      <span class="top-geos__num">Leads</span>
      <span class="top-geos__num">CR</span>
      <span class="top-geos__num">Spent</span>
    </div>
    <p v-if="rows.length === 0" class="top-geos__empty">No purchases for the period</p>
    <div v-for="row in rows" :key="row.country" class="top-geos__row">
      <span class="top-geos__country">
        <CountryPick :code="row.country" />
        <span class="top-geos__name">{{ countryName(row.country) }}</span>
      </span>
      <span class="top-geos__bar"><i :style="{ width: `${row.spent / maxSpent * 100}%` }" /></span>
      <span class="top-geos__num">{{ row.leads.toLocaleString('en-US') }}</span>
      <span class="top-geos__num">{{ percent(row.approvalRate) }}</span>
      <span class="top-geos__num"><b>{{ wholeDollars(row.spent) }}</b></span>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import CountryPick from '@/components/CountryPick.vue'
import { countryName } from '@/countries'
import { wholeDollars } from '@/money'
import { percent, totalsOf, type Purchase } from '@/purchases'

const props = defineProps<{
  purchases: Purchase[]
}>()

const rows = computed(() => {
  const groups = new Map<string, Purchase[]>()
  for (const purchase of props.purchases) {
    const country = purchase.lead.country || '—'
    groups.set(country, [...(groups.get(country) ?? []), purchase])
  }
  return [...groups.entries()]
    .map(([country, list]) => ({ country, ...totalsOf(list) }))
    .sort((left, right) => right.spent - left.spent)
    .slice(0, 6)
})

const maxSpent = computed(() => Math.max(1, ...rows.value.map((row) => row.spent)))
</script>

<style scoped>
.top-geos {
  display: flex;
  flex-direction: column;
}

.top-geos__row {
  align-items: center;
  border-bottom: 1px solid var(--lm-lavender);
  display: grid;
  font-size: 13px;
  gap: 12px;
  grid-template-columns: minmax(120px, 1.2fr) minmax(0, 2fr) 56px 64px 92px;
  min-height: 42px;
  padding: 0 12px;
}

.top-geos__row:last-child {
  border-bottom: 0;
}

.top-geos__row--head {
  background: var(--lm-lavender);
  border-bottom: 0;
  border-radius: 10px 10px 0 0;
  font-size: 12px;
  font-weight: 600;
  min-height: 32px;
}

.top-geos__country {
  align-items: center;
  display: inline-flex;
  font-weight: 600;
  gap: 8px;
  min-width: 0;
}

.top-geos__name {
  color: var(--lm-muted);
  font-weight: 400;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.top-geos__bar {
  background: var(--lm-lavender);
  border-radius: 999px;
  height: 8px;
  overflow: hidden;
}

.top-geos__bar i {
  background: linear-gradient(90deg, #8d7bf5, #5b45e8);
  border-radius: 999px;
  display: block;
  height: 100%;
}

.top-geos__num {
  font-variant-numeric: tabular-nums;
  text-align: right;
}

.top-geos__empty {
  color: var(--lm-muted);
  font-size: 13px;
  margin: 16px 12px;
}

@media (max-width: 767px) {
  .top-geos__row {
    grid-template-columns: minmax(100px, 1fr) minmax(0, 1fr) 72px;
  }

  .top-geos__row > :nth-child(3),
  .top-geos__row > :nth-child(4) {
    display: none;
  }
}
</style>
