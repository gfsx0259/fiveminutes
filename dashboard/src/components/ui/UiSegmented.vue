<template>
  <div class="ui-segmented" role="group">
    <button
      v-for="item in items"
      :key="item.value"
      type="button"
      class="ui-segmented__item"
      :class="{ 'ui-segmented__item--on': model === item.value }"
      :aria-pressed="model === item.value"
      @click="model = item.value"
    >
      {{ item.title }}
    </button>
  </div>
</template>

<script setup lang="ts">
defineProps<{
  items: { title: string, value: string }[]
}>()

const model = defineModel<string>({ required: true })
</script>

<style scoped>
.ui-segmented {
  background: #fff;
  border: 1px solid var(--lm-border);
  border-radius: 11px;
  box-shadow: var(--lm-shadow);
  display: inline-flex;
  gap: 2px;
  padding: 3px;
}

.ui-segmented__item {
  background: none;
  border: 0;
  border-radius: 8px;
  color: var(--lm-muted);
  cursor: pointer;
  font: inherit;
  font-size: 13px;
  font-weight: 600;
  height: 32px;
  padding: 0 12px;
  white-space: nowrap;
}

.ui-segmented__item:hover {
  color: var(--lm-primary-deep);
}

.ui-segmented__item--on {
  background: var(--lm-lavender);
  color: var(--lm-primary-deep);
}
</style>
