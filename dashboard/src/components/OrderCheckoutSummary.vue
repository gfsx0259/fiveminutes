<template>
  <div class="order-checkout__grid">
    <div class="order-checkout__panel">
      <div class="order-checkout__line">
        <span>{{ priceLabel }}</span>
        <span>{{ pricePerLeadCents > 0 ? dollars(pricePerLeadCents / 100) : '—' }}</span>
      </div>
      <div class="order-checkout__line">
        <span>Quantity</span>
        <span>{{ quantity > 0 ? quantity : '—' }}</span>
      </div>
      <div
        v-if="catalogTotalCents != null && catalogTotalCents > totalCents && totalCents > 0"
        class="order-checkout__line"
      >
        <span>Catalog total</span>
        <span class="order-checkout__catalog">{{ dollars(catalogTotalCents / 100) }}</span>
      </div>
      <div
        v-if="savingsCents > 0"
        class="order-checkout__line order-checkout__line--savings"
      >
        <span>Discount</span>
        <span>−{{ dollars(savingsCents / 100) }}</span>
      </div>
      <div class="order-checkout__line order-checkout__line--total">
        <span>{{ totalLabel }}</span>
        <strong>{{ totalCents > 0 ? dollars(totalCents / 100) : '—' }}</strong>
      </div>
    </div>
    <div class="order-checkout__panel">
      <div class="order-checkout__line">
        <span>Your balance</span>
        <span>{{ dollars(balanceCents / 100) }}</span>
      </div>
      <div v-if="insufficient > 0" class="ui-notice ui-notice--warning order-checkout__alert" role="status">
        <strong class="order-checkout__notice-title">Insufficient balance</strong>
        Top up by {{ dollars(insufficient / 100) }}.
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { dollars } from '@/money'

const props = withDefaults(
  defineProps<{
    pricePerLeadCents: number
    quantity: number
    balanceCents: number
    priceLabel?: string
    totalLabel?: string
    totalCentsOverride?: number | null
    catalogTotalCents?: number | null
  }>(),
  {
    priceLabel: 'Price per lead',
    totalLabel: 'Total cost',
    totalCentsOverride: null,
    catalogTotalCents: null,
  },
)

const totalCents = computed(() => {
  if (props.totalCentsOverride != null && props.totalCentsOverride > 0) {
    return props.totalCentsOverride
  }
  if (props.quantity < 1 || props.pricePerLeadCents < 1) {
    return 0
  }
  return props.quantity * props.pricePerLeadCents
})

const insufficient = computed(() => Math.max(0, totalCents.value - props.balanceCents))

const savingsCents = computed(() => {
  if (props.catalogTotalCents == null || totalCents.value < 1) {
    return 0
  }
  return Math.max(0, props.catalogTotalCents - totalCents.value)
})
</script>

<style scoped>
.order-checkout__grid {
  display: grid;
  gap: 12px;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  margin: 8px 0 4px;
}

.order-checkout__panel {
  background: #f6f4fc;
  border-radius: 10px;
  padding: 12px 14px;
}

.order-checkout__line {
  display: flex;
  font-size: 14px;
  justify-content: space-between;
  padding: 4px 0;
}

.order-checkout__line--total strong {
  color: #5b45e8;
  font-size: 18px;
}

.order-checkout__catalog {
  color: #8b90a8;
  text-decoration: line-through;
}

.order-checkout__line--savings {
  color: var(--success-strong);
  font-weight: 600;
}

.order-checkout__alert {
  margin-top: 8px;
}

.order-checkout__notice-title {
  display: block;
  font-weight: 600;
  margin-bottom: 4px;
}

@media (max-width: 767px) {
  .order-checkout__grid {
    gap: 8px;
    grid-template-columns: minmax(0, 1fr);
    margin: 6px 0 2px;
  }

  .order-checkout__panel {
    padding: 10px;
  }

  .order-checkout__line {
    font-size: 13px;
    padding: 3px 0;
  }

  .order-checkout__line--total strong {
    font-size: 16px;
  }
}
</style>