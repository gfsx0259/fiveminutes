<template>
  <div class="lm-sidebar__content" :class="{ 'lm-mobile-nav': light }">
    <svg
      v-if="light"
      class="lm-mobile-nav__bg-wave"
      viewBox="0 0 400 900"
      preserveAspectRatio="none"
      aria-hidden="true"
    >
      <path d="M0 520C140 620 180 120 400 40L400 900L0 900Z" fill="#6847e8" fill-opacity=".14" />
      <path d="M20 500C160 580 220 280 400 200" fill="none" stroke="#8f74ff" stroke-opacity=".28" />
    </svg>
    <div class="lm-brand">
      <div class="lm-brand__lockup" aria-label="Rider">
        <span class="lm-brand__logo lm-brand__logo--short" aria-hidden="true">R</span>
        <span class="lm-brand__logo lm-brand__logo--full" aria-hidden="true">Rider</span>
      </div>
      <button
        v-if="showToggle && !light"
        type="button"
        class="lm-sidebar__toggle"
        :aria-label="collapsed ? 'Expand sidebar' : 'Collapse sidebar'"
        :title="collapsed ? 'Expand sidebar' : 'Collapse sidebar'"
        @click="emit('toggle')"
      >
        <v-icon :icon="collapsed ? 'mdi-chevron-double-right' : 'mdi-chevron-double-left'" size="18" />
      </button>
    </div>

    <div v-if="light && showWallet" class="lm-mobile-nav__header">
      <div v-if="showWallet" class="lm-mobile-nav__wallet lm-mobile-nav__panel">
        <div class="lm-mobile-nav__label">
          <v-icon icon="mdi-wallet-outline" size="16" />
          Wallet balance
        </div>
        <strong class="lm-mobile-nav__amount">{{ walletBalance }}</strong>
        <div class="lm-mobile-nav__wallet-actions">
          <UiButton variant="ghost" class="lm-mobile-nav__add" icon="mdi-plus" :loading="wallet.busy" @click="onDeposit">
            Add funds
          </UiButton>
          <UiButton
            v-if="role !== 'buyer'"
            variant="ghost"
            class="lm-mobile-nav__buy"
            icon="mdi-cart-outline"
            @click="onBuyLeads"
          >
            Buy leads
          </UiButton>
        </div>
      </div>
    </div>

    <div v-else-if="email" class="lm-account">
      <span class="lm-account__avatar">{{ initials }}</span>
      <span class="lm-account__text">
        <strong>{{ email }}</strong>
        <small>{{ accountLine }}</small>
      </span>
      <router-link
        to="/account"
        class="lm-account__settings"
        title="User settings"
        aria-label="User settings"
        @click="emit('navigate')"
      >
        <v-icon icon="mdi-cog-outline" size="18" />
      </router-link>
    </div>

    <nav class="lm-nav" :class="{ 'lm-mobile-nav__links': light }">
      <router-link
        v-for="item in menu"
        :key="item.to"
        :to="item.to"
        class="lm-nav__item"
        :class="{
          'lm-nav__item--active': route.path === item.to,
          'lm-nav__item--section-end': item.sectionAfter,
        }"
        :title="item.title"
        :aria-label="item.title"
        @click="emit('navigate')"
      >
        <v-icon :icon="item.icon" size="18" />
        <span class="lm-nav__text">{{ item.title }}</span>
      </router-link>
    </nav>

    <svg v-if="!sheet && !light" class="lm-sidebar__wave" viewBox="0 0 280 400" preserveAspectRatio="none" aria-hidden="true">
      <defs>
        <linearGradient id="rider-wave" x1="0" y1="0" x2="1" y2="1">
          <stop stop-color="#201d52" stop-opacity="0" />
          <stop offset=".6" stop-color="#4b30c4" stop-opacity=".3" />
          <stop offset=".78" stop-color="#6847e8" stop-opacity=".8" />
          <stop offset="1" stop-color="#261f65" stop-opacity=".1" />
        </linearGradient>
      </defs>
      <path d="M-50 335C105 390 120 60 330 0L330 195C170 170 125 320-50 400Z" fill="url(#rider-wave)" />
      <path d="M-40 320C90 375 145 193 300 160" fill="none" stroke="#6f4eea" stroke-opacity=".35" />
    </svg>

    <div class="lm-sidebar__footer">
      <span class="lm-role">{{ role }}</span>
      <button type="button" class="lm-logout" title="Logout" aria-label="Logout" @click="emit('logout')">
        <v-icon icon="mdi-logout" size="18" />
        <span>Logout</span>
      </button>
    </div>
  </div>
</template>

<script lang="ts" setup>
import { computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import UiButton from '@/components/ui/UiButton.vue'
import { dollars } from '@/money'
import { useSessionStore } from '@/stores/session'
import { useWalletStore } from '@/stores/wallet'

defineProps<{
  showToggle?: boolean
  collapsed?: boolean
  sheet?: boolean
  light?: boolean
  showWallet?: boolean
}>()

const emit = defineEmits<{
  toggle: []
  navigate: []
  logout: []
}>()

const route = useRoute()
const router = useRouter()
const session = useSessionStore()
const wallet = useWalletStore()

const walletBalance = computed(() => {
  const cents = session.payload?.user?.balanceCents ?? 0
  return dollars(cents / 100)
})

function onDeposit(): void {
  void wallet.deposit()
}

function onBuyLeads(): void {
  emit('navigate')
  void router.push('/stock')
}

interface NavItem {
  title: string
  to: string
  icon: string
  sectionAfter?: boolean
}

const leads: NavItem = {
  title: 'Leads',
  to: '/leads',
  icon: 'mdi-format-list-bulleted',
}
const performance: NavItem = { title: 'Performance', to: '/performance', icon: 'mdi-chart-timeline-variant' }
const transactions: NavItem = { title: 'Transactions', to: '/transactions', icon: 'mdi-swap-vertical' }

const menu = computed((): NavItem[] => {
  const role = session.payload?.user?.role
  if (role === 'admin') {
    return [
      { title: 'Presets', to: '/presets', icon: 'mdi-shape-outline' },
      { title: 'Orders', to: '/orders', icon: 'mdi-clipboard-text-outline' },
      { title: 'Offers', to: '/offers', icon: 'mdi-check-decagram-outline' },
      { title: 'Users', to: '/users', icon: 'mdi-account-group-outline', sectionAfter: true },
      leads,
      performance,
    ]
  }
  if (role === 'partner') {
    return [
      { title: 'Dashboard', to: '/dashboard', icon: 'mdi-view-dashboard-outline', sectionAfter: true },
      { title: 'Buy leads', to: '/stock', icon: 'mdi-cart-outline' },
      { title: 'My orders', to: '/orders', icon: 'mdi-clipboard-text-outline' },
      { title: 'My leads', to: '/leads', icon: 'mdi-account-outline', sectionAfter: true },
      { title: 'Reports', to: '/reports', icon: 'mdi-chart-timeline-variant' },
      transactions,
    ]
  }
  return [
    { title: 'Connections', to: '/connections', icon: 'mdi-key-outline', sectionAfter: true },
    { title: 'My leads', to: '/leads', icon: 'mdi-account-outline' },
    { title: 'Demands', to: '/demands', icon: 'mdi-layers-outline', sectionAfter: true },
    performance,
    transactions,
  ]
})

const email = computed(() => {
  const identity = session.payload?.session.identity
  if (!identity || typeof identity !== 'object') return session.payload?.user?.email ?? ''
  const traits = (identity as { traits?: { email?: string } }).traits
  return traits?.email || session.payload?.user?.email || ''
})

const role = computed(() => session.payload?.user?.role || 'сессия')

const accountLine = computed(() => {
  const user = session.payload?.user
  if (!user) return 'нет пользователя в организации'
  return user.organization ? `${user.role} · ${user.organization.name}` : user.role
})

const initials = computed(() => {
  const name = email.value.split('@')[0] ?? ''
  const parts = name.split(/[._-]/).filter(Boolean)
  const letters = `${parts[0]?.[0] ?? ''}${parts[1]?.[0] ?? ''}`
  return (letters || name.slice(0, 2) || 'R').toUpperCase()
})


</script>
