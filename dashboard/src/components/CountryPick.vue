<template>
  <span v-if="!iso" class="country-pick country-pick--empty">{{ empty }}</span>
  <span v-else class="country-pick">
    <span class="country-pick__flag">
      <CountryFlag :code="iso" :size="size" />
    </span>
    <span
      v-if="showLabel"
      class="country-pick__label"
      :class="{ 'country-pick__label--code': labelMode === 'code' }"
      :title="text"
    >{{ text }}</span>
  </span>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import CountryFlag from '@/components/CountryFlag.vue'
import { countryName } from '@/countries'

const props = withDefaults(defineProps<{
  code?: string | null
  size?: number
  showCode?: boolean
  label?: 'code' | 'name'
  showLabel?: boolean
  empty?: string
}>(), {
  size: 16,
  showCode: true,
  label: undefined,
  showLabel: undefined,
  empty: '—',
})

const iso = computed(() => {
  const raw = props.code?.trim()
  if (!raw || raw === '—') {
    return ''
  }
  return raw.toUpperCase()
})

const labelMode = computed(() => props.label ?? (props.showCode ? 'code' : 'name'))

const showLabel = computed(() => props.showLabel ?? true)

const text = computed(() => (labelMode.value === 'name' ? countryName(iso.value) : iso.value))
</script>

<style scoped>
.country-pick {
  align-items: center;
  display: inline-flex;
  gap: 8px;
  max-width: 100%;
  min-width: 0;
  width: 100%;
}

.country-pick__flag {
  flex-shrink: 0;
}

.country-pick__label {
  flex: 1 1 auto;
  min-width: 0;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.country-pick__label--code {
  font-variant-numeric: tabular-nums;
}

.country-pick--empty {
  color: var(--lm-muted, #5c6078);
}
</style>