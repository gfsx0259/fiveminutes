<template>
  <PartnerPresets v-if="isPartner" />
  <Presets v-else />
</template>

<script setup lang="ts">
import { computed } from 'vue'
import PartnerPresets from '@/pages/PartnerPresets.vue'
import Presets from '@/pages/Presets.vue'
import { useSessionStore } from '@/stores/session'

const session = useSessionStore()
const isPartner = computed(() => session.payload?.user?.role === 'partner')
</script>
