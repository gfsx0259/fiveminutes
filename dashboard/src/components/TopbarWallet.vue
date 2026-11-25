<template>
  <div class="lm-topbar-wallet" :class="{ 'lm-topbar-wallet--compact': compact }">
    <div class="lm-topbar-wallet__pill">
      <v-icon icon="mdi-wallet-outline" size="18" class="lm-topbar-wallet__icon" />
      <span class="lm-topbar-wallet__label">Wallet balance:</span>
      <strong class="lm-topbar-wallet__amount">{{ balance }}</strong>
      <button
        type="button"
        class="lm-topbar-wallet__deposit"
        title="Add funds"
        aria-label="Add funds"
        :disabled="wallet.busy"
        @click="wallet.deposit()"
      >
        <v-progress-circular
          v-if="wallet.busy"
          indeterminate
          size="18"
          width="2"
          color="primary"
        />
        <v-icon v-else icon="mdi-plus" size="20" />
      </button>
    </div>
    <UiButton
      v-if="canBuyLeads"
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

defineProps<{
  compact?: boolean
}>()

const router = useRouter()
const session = useSessionStore()
const wallet = useWalletStore()

// Buyers supply leads, they don't buy them.
const canBuyLeads = computed(() => session.payload?.user?.role !== 'buyer')

const balance = computed(() => {
  const cents = session.payload?.user?.balanceCents ?? 0
  return dollars(cents / 100)
})
</script>