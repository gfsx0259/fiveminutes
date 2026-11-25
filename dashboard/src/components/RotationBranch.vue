<template>
  <ul class="rotation-branch" :class="{ 'rotation-branch--root': depth === 0 }">
    <li v-for="node in nodes" :key="`${node.axis}:${node.value}`" class="rotation-branch__item">
      <div class="rotation-branch__label">
        <CountryPick v-if="node.axis === 'country'" :code="node.value" :size="16" />
        <span v-else>{{ rotationValueLabel(node.axis, node.value) }}</span>
        <span class="rotation-branch__cap" title="Supplied / volume">{{ nodeCap(node).supplied }}/{{ nodeCap(node).volume }}</span>
      </div>
      <RotationBranch v-if="node.children?.length" :nodes="node.children" :axes="axes" :depth="depth + 1" />
      <ul v-if="node.presets" class="rotation-presets">
        <li v-for="preset in node.presets" :key="preset.id" class="rotation-preset">
          <div class="rotation-preset__title">
            <span>{{ presetDisplayId(preset.id) }}</span>
            <span v-if="presetRemainder(preset, axes)" class="rotation-preset__rest">{{ presetRemainder(preset, axes) }}</span>
            <span class="rotation-branch__cap" title="Supplied / volume">{{ ordersCap(preset.orders).supplied }}/{{ ordersCap(preset.orders).volume }}</span>
          </div>
          <ul v-if="preset.orders.length" class="rotation-orders">
            <li v-for="order in preset.orders" :key="order.id">
              <span class="rotation-orders__id">{{ orderDisplayId(order.id) }}</span>
              <span>{{ order.stock ? 'Stock' : order.serviceOn }}</span>
              <span>{{ order.supplied }}/{{ order.volume }}</span>
              <span v-if="order.partnerEmail">{{ order.partnerEmail }}</span>
              <span>{{ order.opensAt }}–{{ order.closesAt }}</span>
            </li>
          </ul>
          <p v-else class="rotation-orders__empty">No active orders</p>
        </li>
      </ul>
    </li>
  </ul>
</template>

<script setup lang="ts">
import CountryPick from '@/components/CountryPick.vue'
import { orderDisplayId, presetDisplayId } from '@/orderDisplay'
import { nodeCap, ordersCap, presetRemainder, rotationValueLabel, type RotationNode } from '@/rotation'

withDefaults(defineProps<{
  nodes: RotationNode[]
  axes: string[]
  depth?: number
}>(), {
  depth: 0,
})
</script>

<style scoped>
.rotation-branch {
  display: flex;
  flex-direction: column;
  gap: 10px;
  list-style: none;
  margin: 8px 0 0;
  padding: 0 0 0 18px;
}

.rotation-branch--root {
  margin-top: 0;
  padding-left: 0;
}

.rotation-branch__label {
  align-items: center;
  color: #101024;
  display: flex;
  flex-wrap: wrap;
  font-size: 14px;
  font-weight: 700;
  gap: 8px;
}

.rotation-branch__cap {
  color: var(--lm-muted);
  font-size: 13px;
  font-weight: 650;
}

.rotation-presets,
.rotation-orders {
  display: flex;
  flex-direction: column;
  gap: 6px;
  list-style: none;
  margin: 6px 0 0;
  padding: 0 0 0 18px;
}

.rotation-preset__title {
  align-items: baseline;
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  font-size: 13px;
  font-weight: 650;
}

.rotation-preset__rest,
.rotation-orders,
.rotation-orders__empty {
  color: var(--lm-muted);
  font-size: 13px;
  font-weight: 500;
}

.rotation-orders li {
  align-items: baseline;
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}

.rotation-orders__id {
  color: var(--lm-primary-deep);
  font-weight: 650;
}

.rotation-orders__empty {
  margin: 4px 0 0 18px;
}
</style>
