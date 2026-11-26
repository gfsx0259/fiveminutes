<template>
  <div class="offer-details" data-inspect="demands.offer-details">
    <p class="offer-details__id">Offer {{ offerDisplayId(row.presetId) }}</p>
    <div class="offer-details__grid">
      <section class="offer-card" aria-labelledby="offer-general-heading">
        <h3 id="offer-general-heading">General Information</h3>
        <dl class="offer-rows">
          <div v-for="item in rows" :key="item.label">
            <dt><v-icon :icon="item.icon" size="17" />{{ item.label }}</dt>
            <dd>
              <span v-if="item.kind === 'vertical'" class="offer-inline">
                <v-icon :icon="verticalIcon(row.vertical)" :color="verticalColor(row.vertical)" size="18" />
                {{ verticalLabel(row.vertical) }}
              </span>
              <span v-else-if="item.kind === 'country'" class="offer-inline">
                <CountryPick :code="row.geo" label="name" />
              </span>
              <template v-else>{{ item.value }}</template>
            </dd>
          </div>
        </dl>
      </section>

      <div class="offer-details__column">
        <section class="offer-card offer-report" aria-labelledby="offer-report-heading">
          <h3 id="offer-report-heading">My Report</h3>
          <div class="offer-report__cap">
            <span><v-icon icon="mdi-calendar-today" size="18" />Daily Cap</span>
            <strong>{{ row.ownSupplied.toLocaleString('en-US') }} <span>/ {{ cap.toLocaleString('en-US') }}</span></strong>
            <div class="offer-report__progress">
              <div
                role="progressbar"
                aria-label="Daily cap delivered"
                aria-valuemin="0"
                :aria-valuemax="cap"
                :aria-valuenow="row.ownSupplied"
              >
                <span :style="{ width: `${percent}%` }" />
              </div>
              <b>{{ percent }}%</b>
            </div>
          </div>
          <div class="offer-report__metric">
            <span><v-icon icon="mdi-check-circle-outline" size="19" />Delivered</span>
            <strong>{{ row.ownSupplied.toLocaleString('en-US') }}</strong>
          </div>
          <div class="offer-report__metric">
            <span><v-icon icon="mdi-timer-sand" size="19" />Remaining</span>
            <strong>{{ Math.max(0, cap - row.ownSupplied).toLocaleString('en-US') }}</strong>
          </div>
        </section>
        <div class="offer-notice">
          <v-icon icon="mdi-information-outline" size="21" />
          <p v-if="ended">The delivery day of this offer has ended.</p>
          <p v-else>Leads are being delivered. Your offer is approved: send leads with any method on the right.</p>
        </div>
      </div>

      <OfferIntegration
        class="offer-details__integration"
        :geo="row.geo"
        :traffic-source="row.trafficSource"
        :age="row.age"
        :gender="row.gender"
      />
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import CountryPick from '@/components/CountryPick.vue'
import OfferIntegration from '@/components/demands/OfferIntegration.vue'
import { dollars } from '@/money'
import { offerDisplayId } from '@/orderDisplay'
import {
  genderLabel,
  modelLabel,
  trafficLabel,
  verticalColor,
  verticalIcon,
  verticalLabel,
} from '@/marketOptions'

const props = defineProps<{
  row: {
    presetId: string
    vertical: string
    geo: string
    trafficSource: string
    paymentModel: string
    priceCents: number
    serviceOn: string | null
    age: string
    gender: string
    yourVolume: number | null
    ownSupplied: number
  }
}>()

const ended = computed(() => timeLeft(props.row.serviceOn) === 'Ended')
const cap = computed(() => props.row.yourVolume ?? 0)
const percent = computed(() => (cap.value > 0 ? Math.min(100, Math.round((props.row.ownSupplied / cap.value) * 100)) : 0))

/** Delivery runs for one UTC day; the offer has until its end. */
function timeLeft(serviceOn: string | null): string {
  if (!serviceOn) return 'No limit'
  const end = Date.parse(`${serviceOn.slice(0, 10)}T00:00:00Z`) + 86400000
  const minutes = Math.max(0, Math.ceil((end - Date.now()) / 60000))
  if (minutes === 0) return 'Ended'
  return `${Math.floor(minutes / 60)}h ${String(minutes % 60).padStart(2, '0')}m`
}

const rows = computed(() => [
  { label: 'Country', icon: 'mdi-map-marker-outline', kind: 'country', value: '' },
  { label: 'Vertical', icon: 'mdi-layers-outline', kind: 'vertical', value: '' },
  { label: 'Traffic Source', icon: 'mdi-source-branch', kind: 'text', value: trafficLabel(props.row.trafficSource) },
  { label: 'Payment Model', icon: 'mdi-cash-multiple', kind: 'text', value: modelLabel(props.row.paymentModel) },
  {
    label: 'Price',
    icon: 'mdi-tag-outline',
    kind: 'text',
    value: props.row.priceCents > 0
      ? `${dollars(props.row.priceCents / 100)} per ${props.row.paymentModel === 'cpa' ? 'acquisition' : 'lead'}`
      : '—',
  },
  { label: 'Age', icon: 'mdi-account-clock-outline', kind: 'text', value: props.row.age || 'Any' },
  { label: 'Gender', icon: 'mdi-gender-male-female', kind: 'text', value: genderLabel(props.row.gender) },
  { label: 'Daily Cap', icon: 'mdi-chart-bar', kind: 'text', value: `${cap.value.toLocaleString('en-US')} leads/day` },
  { label: 'Date', icon: 'mdi-calendar-month-outline', kind: 'text', value: props.row.serviceOn ? props.row.serviceOn.slice(0, 10) : 'Stock' },
  { label: 'Time Left', icon: 'mdi-clock-outline', kind: 'text', value: timeLeft(props.row.serviceOn) },
])
</script>

<style scoped>
.offer-details__id {
  color: var(--lm-muted);
  font-size: 14px;
  margin: 8px 0 14px;
}

.offer-details__grid {
  align-items: start;
  display: grid;
  gap: 16px;
  grid-template-columns: 1fr 0.95fr 1.1fr;
}

.offer-details__grid > * {
  min-width: 0;
}

.offer-card {
  background: #fff;
  border: 1px solid var(--lm-border);
  border-radius: var(--lm-radius);
  box-shadow: var(--lm-shadow);
  padding: 18px;
}

.offer-card h3 {
  font-size: 20px;
  letter-spacing: -0.5px;
  line-height: 1.35;
  margin: 0 0 8px;
}

.offer-rows {
  font-size: 13px;
  margin: 0;
}

.offer-rows > div {
  align-items: center;
  border-bottom: 1px solid var(--lm-lavender);
  display: grid;
  gap: 6px;
  grid-template-columns: 44% minmax(0, 1fr);
  min-height: 46px;
  padding: 6px 0;
}

.offer-rows > div:last-child {
  border-bottom: 0;
}

.offer-rows dt,
.offer-report__cap > span,
.offer-report__metric > span {
  align-items: center;
  color: var(--lm-muted);
  display: flex;
  gap: 8px;
}

.offer-rows dd {
  font-weight: 600;
  margin: 0;
  overflow-wrap: anywhere;
}

.offer-inline {
  align-items: center;
  display: inline-flex;
  gap: 8px;
}

.offer-details__column {
  display: grid;
  gap: 16px;
}

.offer-report {
  overflow: hidden;
  padding: 0;
}

.offer-report h3 {
  background: var(--lm-bg);
  margin: 0;
  padding: 16px 18px;
}

.offer-report__cap,
.offer-report__metric {
  border-top: 1px solid var(--lm-lavender);
  font-size: 13px;
  padding: 16px 18px;
}

.offer-report__cap > strong {
  display: block;
  font-size: 20px;
  margin: 8px 0 6px;
}

.offer-report__cap strong span {
  color: var(--lm-muted);
  font-weight: 500;
}

.offer-report__progress {
  align-items: center;
  color: var(--lm-muted);
  display: flex;
  gap: 10px;
}

.offer-report__progress > div {
  background: var(--lm-lavender);
  border-radius: 8px;
  flex: 1;
  height: 8px;
  overflow: hidden;
}

.offer-report__progress > div > span {
  background: var(--lm-primary);
  border-radius: inherit;
  display: block;
  height: 100%;
}

.offer-report__metric {
  align-items: center;
  display: flex;
  gap: 10px;
  justify-content: space-between;
  min-height: 64px;
}

.offer-report__metric strong {
  font-size: 20px;
}

.offer-notice {
  background: var(--lm-lavender);
  border-radius: var(--lm-radius);
  color: var(--lm-primary-deep);
  display: flex;
  font-size: 13px;
  gap: 10px;
  line-height: 1.65;
  padding: 16px 18px;
}

.offer-notice p {
  margin: 0;
}

.offer-notice .v-icon {
  flex-shrink: 0;
  margin-top: 2px;
}

@media (max-width: 1050px) {
  .offer-details__grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }

  .offer-details__integration {
    grid-column: 1 / -1;
  }
}

@media (max-width: 650px) {
  .offer-details__grid {
    grid-template-columns: minmax(0, 1fr);
  }

  .offer-details__integration {
    grid-column: auto;
  }
}
</style>
