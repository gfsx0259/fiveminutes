<template>
  <nav v-if="crumb" class="lm-breadcrumbs" aria-label="Breadcrumb">
    <span class="lm-breadcrumbs__section">{{ crumb.section }}</span>
    <span class="lm-breadcrumbs__sep" aria-hidden="true">/</span>
    <span class="lm-breadcrumbs__current">{{ crumb.current }}</span>
  </nav>
</template>

<script setup lang="ts">
import { routePageTitle } from '@/pageTitle'
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

  const current = routePageTitle(path, role)
  if (!current) {
    return null
  }

  return { section, current }
})
</script>