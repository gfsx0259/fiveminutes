<template>
  <nav v-if="crumb" class="lm-breadcrumbs" aria-label="Breadcrumb">
    <span class="lm-breadcrumbs__section">{{ crumb.section }}</span>
    <span class="lm-breadcrumbs__sep" aria-hidden="true">/</span>
    <span class="lm-breadcrumbs__current">{{ crumb.current }}</span>
  </nav>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { useRoute } from 'vue-router'
import { useSessionStore } from '@/stores/session'

const route = useRoute()
const session = useSessionStore()

const crumb = computed(() => {
  const path = route.path
  const role = session.payload?.user?.role

  const section = role === 'partner'
    ? 'Partner Cabinet'
    : role === 'admin'
      ? 'Admin'
      : 'Buyer cabinet'

  const byPath: Record<string, string> = {
    '/stock': 'Buy Leads',
    '/orders': role === 'partner' ? 'My orders' : 'Orders',
    '/leads': role === 'partner' ? 'My leads' : 'Leads',
    '/performance': 'Performance',
    '/presets': 'Presets',
    '/offers': 'Offers',
    '/caps': 'Caps',
  }

  const current = byPath[path]
  if (!current) {
    return null
  }

  return { section, current }
})
</script>