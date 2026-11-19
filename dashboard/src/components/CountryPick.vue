<template>
  <span v-if="!iso" class="country-pick country-pick--empty">{{ empty }}</span>
  <span v-else class="country-pick">
    <CountryFlag :code="iso" :size="size" />
    <span v-if="showCode" class="country-pick__code">{{ iso }}</span>
  </span>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import CountryFlag from '@/components/CountryFlag.vue'

const props = withDefaults(defineProps<{
  code?: string | null
  size?: number
  showCode?: boolean
  empty?: string
}>(), {
  size: 16,
  showCode: true,
  empty: '—',
})

const iso = computed(() => {
  const raw = props.code?.trim()
  if (!raw || raw === '—') {
    return ''
  }
  return raw.toUpperCase()
})
</script>

<style scoped>
.country-pick {
  align-items: center;
  display: inline-flex;
  gap: 8px;
  min-width: 0;
}

.country-pick__code {
  font-variant-numeric: tabular-nums;
}

.country-pick--empty {
  color: var(--lm-muted, #5c6078);
}
</style>