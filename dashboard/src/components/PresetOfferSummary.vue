<template>
  <div class="preset-offer-summary">
    <header v-if="heading" class="preset-offer-summary__head">
      <div class="preset-offer-summary__title-row">
        <strong class="preset-offer-summary__title">{{ heading }}</strong>
        <span v-if="badge" class="preset-offer-summary__badge">{{ badge }}</span>
      </div>
      <span v-if="paymentModel" class="preset-offer-summary__model">{{ modelLabel(paymentModel) }}</span>
    </header>
    <div class="preset-offer-summary__body">
      <div class="preset-offer-summary__row">
        <div class="preset-offer-summary__cell">
          <span class="preset-offer-summary__label">GEO</span>
          <span class="preset-offer-summary__value">
            <CountryFlag :code="country" :size="16" />
            <span>{{ countryName }}</span>
          </span>
        </div>
        <span class="preset-offer-summary__sep" aria-hidden="true" />
        <div class="preset-offer-summary__cell">
          <span class="preset-offer-summary__label">Vertical</span>
          <span class="preset-offer-summary__value">
            <v-icon :icon="verticalIcon(vertical)" :color="verticalColor(vertical)" size="16" />
            <span>{{ verticalLabel(vertical) }}</span>
          </span>
        </div>
        <span class="preset-offer-summary__sep" aria-hidden="true" />
        <div class="preset-offer-summary__cell">
          <span class="preset-offer-summary__label">Traffic</span>
          <span class="preset-offer-summary__value">
            <v-icon :icon="trafficIcon(trafficSource)" :color="trafficColor(trafficSource)" size="16" />
            <span>{{ trafficLabel(trafficSource) }}</span>
          </span>
        </div>
      </div>
      <div class="preset-offer-summary__row">
        <div v-if="conversion != null && conversion > 0" class="preset-offer-summary__cell">
          <span class="preset-offer-summary__label">CR</span>
          <span class="preset-offer-summary__cr">{{ conversion }}%</span>
        </div>
        <span v-if="conversion != null && conversion > 0" class="preset-offer-summary__sep" aria-hidden="true" />
        <div class="preset-offer-summary__cell">
          <span class="preset-offer-summary__label">Payout</span>
          <span class="preset-offer-summary__payout">{{ dollars(payoutCents / 100) }}</span>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import CountryFlag from '@/components/CountryFlag.vue'
import { ISO2_COUNTRIES } from '@/countries'
import {
  modelLabel,
  trafficColor,
  trafficIcon,
  trafficLabel,
  verticalColor,
  verticalIcon,
  verticalLabel,
} from '@/marketOptions'
import { dollars } from '@/money'

const props = defineProps<{
  vertical: string
  country: string
  trafficSource: string
  paymentModel?: string
  payoutCents: number
  conversion?: number | null
  heading?: string
  badge?: string
}>()

const countryName = computed(() =>
  ISO2_COUNTRIES.find((c) => c.code === props.country)?.name ?? props.country,
)
</script>

<style scoped>
.preset-offer-summary {
  background: #faf9ff;
  border: 1px solid #e8e4f8;
  border-radius: 12px;
  margin-bottom: 16px;
  padding: 14px 16px;
}

.preset-offer-summary__head {
  align-items: flex-start;
  display: flex;
  gap: 12px;
  justify-content: space-between;
  margin-bottom: 12px;
}

.preset-offer-summary__title-row {
  align-items: center;
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}

.preset-offer-summary__title {
  color: #303861;
  font-size: 15px;
  font-weight: 700;
}

.preset-offer-summary__badge {
  background: #e8f5e9;
  border-radius: 999px;
  color: #2e7d32;
  font-size: 11px;
  font-weight: 700;
  padding: 2px 10px;
}

.preset-offer-summary__model {
  background: rgb(91 69 232 / 12%);
  border-radius: 999px;
  color: #5b45e8;
  font-size: 12px;
  font-weight: 600;
  padding: 4px 10px;
  white-space: nowrap;
}

.preset-offer-summary__body {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.preset-offer-summary__row {
  align-items: center;
  display: flex;
  flex-wrap: wrap;
  gap: 10px 18px;
}

.preset-offer-summary__cell {
  align-items: center;
  display: inline-flex;
  flex-wrap: wrap;
  gap: 8px;
  min-width: 0;
  padding-inline: 2px;
}

.preset-offer-summary__label {
  color: #8b90a8;
  font-size: 11px;
  font-weight: 600;
  white-space: nowrap;
}

.preset-offer-summary__value {
  align-items: center;
  color: #303861;
  display: inline-flex;
  font-size: 13px;
  font-weight: 600;
  gap: 5px;
  min-width: 0;
}

.preset-offer-summary__value > span:last-child {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.preset-offer-summary__sep {
  background: #d8d5eb;
  flex-shrink: 0;
  height: 22px;
  width: 1px;
}

.preset-offer-summary__payout {
  color: #3512bc;
  font-size: 14px;
  font-weight: 700;
  white-space: nowrap;
}

.preset-offer-summary__cr {
  background: #e8f5e9;
  border-radius: 8px;
  color: #2e7d32;
  font-size: 12px;
  font-weight: 700;
  padding: 2px 8px;
  white-space: nowrap;
}

@media (max-width: 640px) {
  .preset-offer-summary__sep {
    display: none;
  }

  .preset-offer-summary__row {
    gap: 12px 16px;
  }
}
</style>