<template>
  <div class="lm-topbar-wallet">
    <span class="lm-topbar-wallet__pill">
      <v-icon icon="mdi-wallet-outline" size="18" class="lm-topbar-wallet__icon" />
      <span class="lm-topbar-wallet__label">Wallet balance:</span>
      <strong class="lm-topbar-wallet__amount">{{ balance }}</strong>
    </span>
    <UiButton variant="outline" class="lm-topbar-wallet__add" :loading="wallet.busy" @click="wallet.deposit()">
      Add funds
    </UiButton>
    <UiButton
      variant="primary"
      icon="mdi-cart-outline"
      class="lm-topbar-wallet__buy"
      @click="router.push('/stock')"
    >
      Buy leads
    </UiButton>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { useRouter } from 'vue-router'
import UiButton from '@/components/ui/UiButton.vue'
import { dollars } from '@/money'
import { useSessionStore } from '@/stores/session'
import { useWalletStore } from '@/stores/wallet'

const router = useRouter()
const session = useSessionStore()
const wallet = useWalletStore()

const balance = computed(() => {
  const cents = session.payload?.user?.balanceCents ?? 0
  return dollars(cents / 100)
})
</script>
