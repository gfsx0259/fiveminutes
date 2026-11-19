<template>
  <div class="lm-topbar-wallet">
    <span class="lm-topbar-wallet__pill">
      <v-icon icon="mdi-wallet-outline" size="18" class="lm-topbar-wallet__icon" />
      <span class="lm-topbar-wallet__label">Wallet balance:</span>
      <strong class="lm-topbar-wallet__amount">{{ balance }}</strong>
    </span>
    <UiButton variant="primary" class="lm-topbar-wallet__add" :loading="busy" @click="deposit">
      Add funds
    </UiButton>
  </div>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'
import { apiPost } from '@/api'
import UiButton from '@/components/ui/UiButton.vue'
import { dollars } from '@/money'
import { useSessionStore } from '@/stores/session'

const session = useSessionStore()
const busy = ref(false)

const balance = computed(() => {
  const cents = session.payload?.user?.balanceCents ?? 0
  return dollars(cents / 100)
})

async function deposit(): Promise<void> {
  busy.value = true
  try {
    const data = await apiPost<{ balanceCents: number }>('/wallet/deposit', {})
    if (session.payload?.user) {
      session.payload.user.balanceCents = data.balanceCents
    }
  } finally {
    busy.value = false
  }
}
</script>