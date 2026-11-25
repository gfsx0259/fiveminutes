<template>
  <div class="report-condition">
    <span class="report-condition__joint">{{ first ? 'Where' : 'And' }}</span>
    <UiSelect
      :model-value="condition.field"
      :items="fieldItems"
      class="report-condition__field"
      @update:model-value="change({ field: $event, values: [] })"
    />
    <UiSelect
      :model-value="condition.operator"
      :items="operatorItems"
      class="report-condition__operator"
      @update:model-value="change({ operator: $event })"
    />
    <UiSelect
      :model-value="condition.values"
      :items="valueItems"
      multiple
      searchable
      search-placeholder="Search"
      placeholder="Any value"
      class="report-condition__values"
      @update:model-value="change({ values: $event ?? [] })"
    />
    <button type="button" class="report-condition__remove" aria-label="Remove condition" @click="emit('remove')">
      <v-icon icon="mdi-close" size="18" />
    </button>
  </div>
</template>

<script setup lang="ts">
import UiSelect from '@/components/ui/UiSelect.vue'
import { dimensions, type ReportCondition } from '@/reportFields'

const props = defineProps<{
  condition: ReportCondition
  first: boolean
  valueItems: { title: string, value: string }[]
}>()

const emit = defineEmits<{
  (event: 'update', value: ReportCondition): void
  (event: 'remove'): void
}>()

const fieldItems = dimensions.filter((item) => item.filterable).map((item) => ({ title: item.label, value: item.key }))

const operatorItems = [
  { title: 'is', value: 'in' },
  { title: 'is not', value: 'not_in' },
]

function change(patch: Partial<ReportCondition>): void {
  emit('update', { ...props.condition, ...patch })
}
</script>

<style scoped>
.report-condition {
  align-items: center;
  display: grid;
  gap: 8px;
  grid-template-columns: 52px 180px 110px minmax(0, 1fr) 32px;
}

.report-condition__joint {
  color: var(--lm-muted);
  font-size: 12px;
  font-weight: 700;
  text-transform: uppercase;
}

.report-condition__remove {
  align-items: center;
  background: none;
  border: 0;
  border-radius: 8px;
  color: var(--lm-muted);
  cursor: pointer;
  display: flex;
  height: 32px;
  justify-content: center;
  width: 32px;
}

.report-condition__remove:hover {
  background: #fde8ee;
  color: #c2405f;
}

@media (max-width: 767px) {
  .report-condition {
    grid-template-columns: minmax(0, 1fr) minmax(0, 1fr) 32px;
  }

  .report-condition__joint {
    grid-column: 1 / -1;
  }

  .report-condition__values {
    grid-column: 1 / 3;
  }
}
</style>
