<template>
  <div class="top-geos">
    <div class="top-geos__row top-geos__row--head">
      <span>Country</span>
      <span>{{ status ? 'Approved share' : 'Share of spend' }}</span>
      <span class="top-geos__num">{{ status ? 'Approved' : 'Leads' }}</span>
      <span class="top-geos__num">{{ status ? 'With status' : 'Avg cost' }}</span>
      <span class="top-geos__num">{{ status ? 'CR' : 'Spent' }}</span>
    </div>
    <p v-if="rows.length === 0" class="top-geos__empty">{{ status ? 'No statuses for the period' : 'No purchases for the period' }}</p>
    <div v-for="row in rows" :key="row.country" class="top-geos__row">
      <span class="top-geos__country">
        <CountryPick :code="row.country" />
        <span class="top-geos__name">{{ countryName(row.country) }}</span>
      </span>
      <span class="top-geos__bar" :data-tone="status ? 'own' : 'market'"><i :style="{ width: `${row.share * 100}%` }" /></span>
      <template v-if="status">
        <span class="top-geos__num">{{ row.approved.toLocaleString('en-US') }}</span>
        <span class="top-geos__num">{{ (row.approved + row.rejected + row.pending).toLocaleString('en-US') }}</span>
        <span class="top-geos__num"><b>{{ percent(row.approvalRate) }}</b></span>
      </template>
      <template v-else>
        <span class="top-geos__num">{{ row.leads.toLocaleString('en-US') }}</span>
        <span class="top-geos__num">{{ dollars(row.averageCost) }}</span>
        <span class="top-geos__num"><b>{{ wholeDollars(row.spent) }}</b></span>
      </template>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import CountryPick from '@/components/CountryPick.vue'
import { countryName } from '@/countries'
import { dollars, wholeDollars } from '@/money'
import { percent, type Purchase } from '@/purchases'
import { reportTree } from '@/reportFields'

const props = withDefaults(defineProps<{
  purchases: Purchase[]
  mode?: 'spend' | 'status'
}>(), {
  mode: 'spend',
})

const status = computed(() => props.mode === 'status')

const rows = computed(() => {
  const all = reportTree(props.purchases, ['country']).map((node) => ({ country: node.value || '—', ...node.totals }))
  if (status.value) {
    return all
      .filter((row) => row.approved + row.rejected + row.pending > 0)
      .sort((left, right) => right.approvalRate - left.approvalRate || right.approved - left.approved)
      .slice(0, 6)
      .map((row) => ({ ...row, share: row.approvalRate }))
  }
  const top = all.sort((left, right) => right.spent - left.spent).slice(0, 6)
  const maxSpent = Math.max(1, ...top.map((row) => row.spent))
  return top.map((row) => ({ ...row, share: row.spent / maxSpent }))
})
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
  grid-template-columns: minmax(170px, 1.6fr) minmax(0, 1.4fr) 64px 84px 84px;
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

.top-geos__country > .country-pick {
  flex: none;
  width: auto;
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

.top-geos__bar[data-tone='own'] {
  background: #e7f6ec;
}

.top-geos__bar[data-tone='own'] i {
  background: linear-gradient(90deg, #7fd3a0, #2f9e5b);
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
