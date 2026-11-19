<template>
  <Presets v-if="!isPartner" />
</template>

<script setup lang="ts">
import { computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import Presets from '@/pages/Presets.vue'
import { useSessionStore } from '@/stores/session'

const session = useSessionStore()
const router = useRouter()
const isPartner = computed(() => session.payload?.user?.role === 'partner')

onMounted(() => {
  if (isPartner.value) {
    void router.replace('/stock')
  }
})
</script>