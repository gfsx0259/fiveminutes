<template>
  <div class="group-levels">
    <template v-for="(dimension, level) in model" :key="level">
      <v-icon v-if="level > 0" icon="mdi-chevron-right" size="18" class="group-levels__arrow" />
      <div class="group-levels__level">
        <span>Level {{ level + 1 }}</span>
        <UiSelect :model-value="dimension" :items="itemsFor(level)" @update:model-value="set(level, $event)" />
        <button v-if="model.length > 1" type="button" aria-label="Remove level" @click="remove(level)">
          <v-icon icon="mdi-close" size="16" />
        </button>
      </div>
    </template>
    <UiButton v-if="model.length < MAX_GROUPS" variant="ghost" icon="mdi-plus" @click="add">Add level</UiButton>
  </div>
</template>

<script setup lang="ts">
import UiButton from '@/components/ui/UiButton.vue'
import UiSelect from '@/components/ui/UiSelect.vue'
import { MAX_GROUPS, dimensions, type ReportDimension } from '@/reportFields'

const model = defineModel<ReportDimension[]>({ required: true })

function itemsFor(level: number): { title: string, value: string }[] {
  return dimensions
    .filter((item) => item.key === model.value[level] || !model.value.includes(item.key))
    .map((item) => ({ title: item.label, value: item.key }))
}

function set(level: number, value: ReportDimension): void {
  const next = [...model.value]
  next[level] = value
  model.value = next
}

function remove(level: number): void {
  model.value = model.value.filter((_, index) => index !== level)
}

function add(): void {
  const free = dimensions.find((item) => !model.value.includes(item.key))
  if (free) model.value = [...model.value, free.key]
}
</script>

<style scoped>
.group-levels {
  align-items: center;
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}

.group-levels__level {
  align-items: center;
  background: #faf9ff;
  border: 1px solid var(--lm-border);
  border-radius: 11px;
  display: flex;
  gap: 8px;
  padding: 3px 4px 3px 10px;
}

.group-levels__level > span {
  color: var(--lm-muted);
  font-size: 11px;
  font-weight: 700;
  white-space: nowrap;
}

.group-levels__level :deep(.ui-select) {
  min-width: 160px;
}

.group-levels__level > button {
  align-items: center;
  background: none;
  border: 0;
  border-radius: 8px;
  color: var(--lm-muted);
  cursor: pointer;
  display: flex;
  height: 28px;
  justify-content: center;
  width: 28px;
}

.group-levels__level > button:hover {
  background: #fde8ee;
  color: #c2405f;
}

.group-levels__arrow {
  color: var(--lm-muted);
}
</style>
