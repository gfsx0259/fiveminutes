<script setup lang="ts">
import { computed, onMounted } from 'vue'
import { loginUrl } from '@/host'
import { useSessionStore } from '@/stores/session'

const session = useSessionStore()
const host = window.location.host

onMounted(() => {
  void session.load()
})

const identity = computed(() => {
  const value = session.payload?.session.identity
  return value && typeof value === 'object' ? value as Record<string, unknown> : null
})

const traits = computed(() => {
  const value = identity.value?.traits
  return value && typeof value === 'object' ? value as Record<string, unknown> : null
})

const email = computed(() => typeof traits.value?.email === 'string' ? traits.value.email : '')
const role = computed(() => session.payload?.user?.role ?? '')
const organization = computed(() => session.payload?.user?.organization ?? null)
const expiresAt = computed(() => {
  const value = session.payload?.session.expires_at
  return typeof value === 'string' ? value : ''
})
const raw = computed(() => JSON.stringify(session.payload?.session ?? null, null, 2))

function signIn(): void {
  window.location.href = loginUrl(window.location.hostname, window.location.origin + '/')
}
</script>

<template>
  <v-app>
    <v-main class="bg-background">
      <v-container class="py-8" style="max-width: 880px">
        <div class="text-h5 mb-1">Сессия</div>
        <div class="text-body-2 text-medium-emphasis mb-6">{{ host }}</div>

        <v-progress-circular v-if="session.loading" indeterminate color="primary" />

        <v-card v-else-if="session.missing" class="pa-6">
          <div class="text-body-1 mb-4">Сессии нет. Вход на этом апексе откроет кабинет обратно.</div>
          <v-btn color="primary" @click="signIn">Войти</v-btn>
        </v-card>

        <v-alert v-else-if="session.error" type="error" variant="tonal">{{ session.error }}</v-alert>

        <template v-else-if="session.payload">
          <v-card class="pa-6 mb-4">
            <v-row>
              <v-col cols="12" sm="6">
                <div class="text-caption text-medium-emphasis">Почта</div>
                <div class="text-body-1">{{ email }}</div>
              </v-col>
              <v-col cols="12" sm="6">
                <div class="text-caption text-medium-emphasis">Роль</div>
                <div class="text-body-1">{{ role || 'в приложении пользователя нет' }}</div>
              </v-col>
              <v-col cols="12" sm="6">
                <div class="text-caption text-medium-emphasis">Организация</div>
                <div class="text-body-1">{{ organization ? `${organization.name} · ${organization.apex}` : '—' }}</div>
              </v-col>
              <v-col cols="12" sm="6">
                <div class="text-caption text-medium-emphasis">Сессия до</div>
                <div class="text-body-1">{{ expiresAt }}</div>
              </v-col>
            </v-row>
          </v-card>

          <v-card class="pa-6">
            <div class="text-subtitle-1 mb-3">Ответ Kratos</div>
            <pre class="text-body-2" style="white-space: pre-wrap">{{ raw }}</pre>
          </v-card>
        </template>
      </v-container>
    </v-main>
  </v-app>
</template>
