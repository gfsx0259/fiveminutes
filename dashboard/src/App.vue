<template>
  <v-app v-if="session.loading || session.missing || session.error">
    <v-main class="lm-workspace">
      <v-container class="py-10" style="max-width: 520px">
        <div v-if="session.loading" class="text-center py-10">
          <v-progress-circular indeterminate color="primary" />
        </div>
        <v-card v-else-if="session.missing" class="pa-6">
          <h1 class="text-h5 mb-2">Rider</h1>
          <p class="mb-4">Сессии нет. Вход на этом апексе откроет кабинет обратно.</p>
          <UiButton @click="signIn">Войти</UiButton>
        </v-card>
        <v-alert v-else type="error" variant="tonal">{{ session.error }}</v-alert>
      </v-container>
    </v-main>
  </v-app>

  <v-app v-else class="lm-app" :class="{ 'lm-app--collapsed': rail }">
    <v-system-bar
      v-if="impersonation"
      window
      :order="0"
      height="40"
      class="impersonation-banner"
    >
      <span>
        Viewing as <strong>{{ session.payload?.user?.email }}</strong>
        ({{ session.payload?.user?.role }}) · admin {{ impersonation.actor.email }}
      </span>
      <v-spacer />
      <button type="button" class="impersonation-banner__stop" @click="stopImpersonation">
        Stop impersonating
      </button>
    </v-system-bar>

    <v-navigation-drawer
      v-if="!overlayNav"
      :model-value="true"
      :width="rail ? 72 : 256"
      permanent
      class="lm-sidebar"
      :class="{ 'lm-sidebar--rail': rail }"
    >
      <ShellNav
        :show-toggle="!tabletRail"
        :collapsed="collapsed"
        @toggle="toggleCollapsed"
        @logout="handleLogout"
      />
    </v-navigation-drawer>

    <Teleport to="body">
      <div
        v-if="overlayNav && drawer"
        class="lm-nav-dialog"
        @mousedown.self="drawer = false"
      >
        <div class="lm-nav-dialog__panel" role="dialog" aria-modal="true" aria-label="Navigation">
          <button type="button" class="lm-nav-dialog__close" aria-label="Close menu" @click="drawer = false">
            <v-icon icon="mdi-close" size="20" />
          </button>
          <ShellNav
            light
            :show-wallet="showWallet"
            @navigate="drawer = false"
            @logout="handleLogout"
          />
        </div>
      </div>
    </Teleport>

    <v-main class="lm-workspace">
      <header class="lm-topbar">
        <button
          v-if="overlayNav"
          type="button"
          class="lm-menu"
          aria-label="Open navigation"
          @click="drawer = true"
        >
          <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <line x1="4" x2="20" y1="12" y2="12" />
            <line x1="4" x2="20" y1="6" y2="6" />
            <line x1="4" x2="20" y1="18" y2="18" />
          </svg>
        </button>
        <div class="lm-topbar__right">
          <TopbarWallet v-if="showWallet" :compact="overlayNav" />
        </div>
      </header>
      <div class="lm-page">
        <router-view />
      </div>
    </v-main>
    <UiToastHost />
  </v-app>
</template>

<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import ShellNav from '@/components/ShellNav.vue'
import UiToastHost from '@/components/UiToastHost.vue'
import TopbarWallet from '@/components/TopbarWallet.vue'
import UiButton from '@/components/ui/UiButton.vue'
import { loginUrl, logout } from '@/host'
import { documentTitle } from '@/pageTitle'
import { maybeResolveAdminStockShare } from '@/stockShareAssist'
import { useSessionStore } from '@/stores/session'
import { useViewport } from '@/viewport'

const COLLAPSE_KEY = 'rider.sidebar.collapsed'

const session = useSessionStore()
const route = useRoute()
const router = useRouter()

const impersonation = computed(() => session.payload?.impersonation ?? null)
const { mobile: overlayNav, tablet: tabletRail } = useViewport()
const drawer = ref(false)
const collapsed = ref(false)

const rail = computed(() => !overlayNav.value && (tabletRail.value || collapsed.value))

const showWallet = computed(() => {
  const role = session.payload?.user?.role
  return role === 'partner' || role === 'buyer'
})

try {
  collapsed.value = localStorage.getItem(COLLAPSE_KEY) === '1'
} catch {
  collapsed.value = false
}

watch(overlayNav, (overlay) => {
  drawer.value = !overlay
}, { immediate: true })

watch(
  () => [route.path, session.payload?.user?.role] as const,
  ([path, role]) => {
    document.title = documentTitle(path, role)
  },
  { immediate: true },
)

function toggleCollapsed(): void {
  collapsed.value = !collapsed.value
  try {
    localStorage.setItem(COLLAPSE_KEY, collapsed.value ? '1' : '0')
  } catch {
    // Private mode can reject the write. The rail still toggles for this visit.
  }
}

function signIn(): void {
  window.location.href = loginUrl(window.location.hostname, `${window.location.origin}/`)
}

function handleLogout(): void {
  void logout(window.location.hostname)
}

async function stopImpersonation(): Promise<void> {
  await session.stopImpersonation()
}

watch(
  () => [session.loading, route.fullPath] as const,
  () => {
    void maybeResolveAdminStockShare(session, route, router)
  },
)

onMounted(() => {
  void session.load()
})
</script>
