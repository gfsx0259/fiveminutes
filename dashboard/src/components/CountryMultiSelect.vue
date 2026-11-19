<template>
  <UiSelect
    v-model="model"
    :items="countryItems"
    multiple
    searchable
    clearable
    placeholder="All countries"
    search-placeholder="Search country"
    empty-label="All countries"
    :menu-min-width="280"
  >
    <template #value="{ items }">
      <span v-if="items.length === 0" class="stock-select__placeholder">All countries</span>
      <span v-else class="stock-select-picks">
        <span v-for="item in items" :key="String(item.value)" class="stock-select-pick">
          <CountryFlag :code="String(item.value)" :size="14" />
          {{ item.value }}
        </span>
      </span>
    </template>
    <template #empty>
      <v-icon icon="mdi-earth" size="16" color="#5b45e8" />
      <span>All countries</span>
    </template>
    <template #option="{ item }">
      <CountryFlag :code="String(item.value)" :size="16" />
      <span>{{ item.title }}</span>
    </template>
  </UiSelect>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import CountryFlag from '@/components/CountryFlag.vue'
import UiSelect from '@/components/ui/UiSelect.vue'
import { ISO2_COUNTRIES } from '@/countries'

const model = defineModel<string[]>({ default: () => [] })

const countryItems = computed(() =>
  ISO2_COUNTRIES.map((c) => ({ title: `${c.code} — ${c.name}`, value: c.code })),
)
</script>