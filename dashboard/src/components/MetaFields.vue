<template>
  <div class="meta-fields">
    <div v-for="(row, index) in rows" :key="index" class="meta-fields__row">
      <UiInput v-model="row.key" placeholder="aff_sub1" />
      <UiInput v-model="row.value" placeholder="test" />
      <button type="button" class="meta-fields__remove" aria-label="Remove field" @click="remove(index)">×</button>
    </div>
    <button type="button" class="meta-fields__add" @click="add">Add field</button>
  </div>
</template>

<script setup lang="ts">
import UiInput from '@/components/ui/UiInput.vue'
import type { MetaRow } from '@/metaFields'

const rows = defineModel<MetaRow[]>({ required: true })

function add(): void {
  rows.value = [...rows.value, { key: '', value: '' }]
}

function remove(index: number): void {
  rows.value = rows.value.filter((_, item) => item !== index)
}
</script>

<style scoped>
.meta-fields {
  display: grid;
  gap: 8px;
}

.meta-fields__row {
  align-items: center;
  display: grid;
  gap: 8px;
  grid-template-columns: minmax(0, 1fr) minmax(0, 1fr) 32px;
}

.meta-fields__remove,
.meta-fields__add {
  background: transparent;
  border: 0;
  color: #3412b9;
  cursor: pointer;
  font: inherit;
  font-size: 13px;
  font-weight: 600;
  padding: 0;
}

.meta-fields__remove {
  color: #464d78;
  font-size: 18px;
  line-height: 1;
}
</style>
