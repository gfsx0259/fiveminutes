<template>
  <section class="profile-page cabinet-page">
    <header class="profile-page__heading">
      <h1>User settings</h1>
      <p>Manage your account and security</p>
    </header>

    <div v-if="loadError" class="profile-load-error profile-card" role="alert">
      <p>{{ loadError }}</p>
      <UiButton size="sm" variant="outline" @click="reloadAll">Retry</UiButton>
    </div>

    <div class="profile-layout">
      <aside class="profile-summary">
        <div class="profile-summary__identity">
          <span class="profile-summary__avatar" aria-hidden="true">{{ initials }}</span>
          <strong>{{ email }}</strong>
          <span>{{ accountLine }}</span>
          <span class="profile-role">{{ role }}</span>
        </div>
        <button type="button" class="profile-logout" @click="emitLogout">
          Logout
        </button>
      </aside>

      <div class="profile-settings">
        <v-tabs v-model="settingsTab" color="primary" class="profile-settings-tabs">
          <v-tab value="profile">Profile</v-tab>
          <v-tab value="ui">UI</v-tab>
        </v-tabs>

        <div v-show="settingsTab === 'profile'" class="profile-settings__panels">
        <section id="profile-account" class="profile-card" tabindex="-1">
          <header class="profile-card__header">
            <v-icon icon="mdi-account-outline" size="26" />
            <div>
              <h2>Profile settings</h2>
              <p>Update your profile information</p>
            </div>
          </header>
          <form class="profile-form-row" @submit.prevent="saveEmail">
            <UiField label="E-Mail" :error="emailError || undefined">
              <UiInput v-model="emailDraft" type="email" autocomplete="email" :disabled="emailBusy" />
            </UiField>
            <div class="profile-form-row__actions">
              <UiButton type="submit" :loading="emailBusy" :disabled="emailBusy || emailDraft === email">
                Save
              </UiButton>
            </div>
          </form>
        </section>

        <section id="profile-security" class="profile-card" tabindex="-1">
          <header class="profile-card__header">
            <v-icon icon="mdi-lock-outline" size="26" />
            <div>
              <h2>Change password</h2>
              <p>Modify your password</p>
            </div>
          </header>
          <form @submit.prevent="savePassword">
            <UiField label="Password" :error="passwordError || undefined">
              <div class="profile-password-wrap">
                <UiInput
                  v-model="password"
                  :type="showPassword ? 'text' : 'password'"
                  autocomplete="new-password"
                  placeholder="Enter your password"
                  :disabled="passwordBusy"
                />
                <button
                  type="button"
                  class="profile-password-toggle"
                  :aria-label="showPassword ? 'Hide password' : 'Show password'"
                  @click="showPassword = !showPassword"
                >
                  <v-icon :icon="showPassword ? 'mdi-eye-off' : 'mdi-eye'" size="19" />
                </button>
              </div>
            </UiField>
            <div class="profile-form-row" style="margin-top: 14px; justify-content: flex-end">
              <UiButton type="submit" :loading="passwordBusy" :disabled="passwordBusy || !password">
                Save
              </UiButton>
            </div>
          </form>
        </section>

        <section id="profile-sessions" class="profile-card" tabindex="-1">
          <header class="profile-card__header">
            <v-icon icon="mdi-monitor" size="26" />
            <div>
              <h2>Active sessions</h2>
              <p>Devices and browsers currently signed in to your account</p>
            </div>
          </header>

          <p v-if="sessionsLoading" class="profile-loading" role="status">
            <v-progress-circular indeterminate size="18" width="2" />
            Loading sessions…
          </p>

          <template v-else>
            <div class="profile-sessions-table-wrap">
              <table class="profile-sessions-table">
                <thead>
                  <tr>
                    <th>Device</th>
                    <th>Location</th>
                    <th>IP</th>
                    <th>Last active</th>
                    <th><span class="sr-only">Actions</span></th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="row in sessions" :key="row.id">
                    <td data-label="Device">
                      <span class="profile-device">
                        <v-icon :icon="deviceIcon(row.kind)" size="20" />
                        <span>{{ row.device }} · {{ row.browser }}</span>
                        <span v-if="row.current" class="profile-current-badge">Current session</span>
                      </span>
                    </td>
                    <td data-label="Location">{{ row.location || 'Unknown location' }}</td>
                    <td data-label="IP">{{ row.ip || '—' }}</td>
                    <td data-label="Last active">
                      <time :datetime="row.lastActive">{{ sessionWhen(row.lastActive) }}</time>
                    </td>
                    <td data-label="Actions">
                      <div class="profile-session-actions">
                        <UiButton
                          variant="ghost"
                          size="sm"
                          :title="row.current ? 'Log out current session' : 'Terminate session'"
                          @click="confirmRevoke(row)"
                        >
                          <v-icon icon="mdi-logout" size="18" />
                        </UiButton>
                      </div>
                    </td>
                  </tr>
                </tbody>
              </table>
              <p v-if="!sessions.length" class="profile-empty">No active sessions found.</p>
            </div>
            <div class="profile-session-footer">
              <small>Terminate sessions you no longer recognize.</small>
              <UiButton
                variant="ghost"
                size="sm"
                :disabled="!otherSessions || sessionsBusy"
                @click="confirmRevokeOthers"
              >
                Log out all other sessions
              </UiButton>
            </div>
          </template>
        </section>
        </div>

        <div v-show="settingsTab === 'ui'" class="profile-settings__panels">
          <section id="profile-display" class="profile-card" tabindex="-1">
            <header class="profile-card__header">
              <v-icon icon="mdi-table-large" size="26" />
              <div>
                <h2>Display</h2>
                <p>How list tables (Buy leads, Orders, Leads) scroll</p>
              </div>
            </header>
            <UiField label="Table scrolling">
              <UiSelect
                v-model="tableScrollMode"
                :items="tableScrollModeItems"
                placeholder="Choose layout"
              />
            </UiField>
            <p class="profile-display-hint">
              <strong>Fixed height</strong> keeps filters visible and scrolls inside the table (current default).
              <strong>Full page</strong> shows all rows for your page size and scrolls the whole workspace; filters stay pinned at the top.
            </p>
          </section>
        </div>
      </div>
    </div>

    <v-dialog v-model="confirmOpen" max-width="460">
      <v-card class="pa-5">
        <v-card-title class="text-h6">{{ confirmTitle }}</v-card-title>
        <v-card-text>{{ confirmText }}</v-card-text>
        <p v-if="confirmError" class="profile-error">{{ confirmError }}</p>
        <v-card-actions class="justify-end ga-2">
          <UiButton variant="outline" :disabled="confirmBusy" @click="confirmOpen = false">Cancel</UiButton>
          <UiButton :loading="confirmBusy" @click="runConfirm">Confirm</UiButton>
        </v-card-actions>
      </v-card>
    </v-dialog>
  </section>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import UiButton from '@/components/ui/UiButton.vue'
import UiField from '@/components/ui/UiField.vue'
import UiInput from '@/components/ui/UiInput.vue'
import UiSelect from '@/components/ui/UiSelect.vue'
import {
  fetchSettingsFlow,
  listIdentitySessions,
  revokeIdentitySession,
  revokeOtherIdentitySessions,
  sessionWhen,
  updateIdentitySettings,
  type IdentityError,
  type IdentitySession,
  type SettingsFlow,
} from '@/identity'
import { identityReauthUrl, loginUrl, logout } from '@/host'
import { showToast } from '@/composables/useToast'
import { useSessionStore } from '@/stores/session'
import { useDisplayPreferencesStore, type TableScrollMode } from '@/stores/displayPreferences'

type ConfirmKind = 'session' | 'others'
type SettingsTab = 'profile' | 'ui'

const session = useSessionStore()
const settingsTab = ref<SettingsTab>('profile')
const displayPrefs = useDisplayPreferencesStore()

const tableScrollModeItems = [
  { title: 'Fixed height (scroll inside table)', value: 'viewport' satisfies TableScrollMode },
  { title: 'Full page (scroll all rows)', value: 'page' satisfies TableScrollMode },
]

const tableScrollMode = computed({
  get: () => displayPrefs.tableScrollMode,
  set: (mode: TableScrollMode) => displayPrefs.setTableScrollMode(mode),
})

const flow = ref<SettingsFlow | null>(null)
const loadError = ref('')
const sessions = ref<IdentitySession[]>([])
const sessionsLoading = ref(true)
const sessionsBusy = ref(false)

const email = computed(() => session.payload?.user?.email ?? '')
const role = computed(() => session.payload?.user?.role ?? '')
const accountLine = computed(() => {
  const user = session.payload?.user
  if (!user?.organization) return role.value
  return `${role.value} · ${user.organization.name}`
})

const initials = computed(() => {
  const local = email.value.split('@')[0] ?? ''
  const parts = local.split(/[._-]/).filter(Boolean)
  const letters = `${parts[0]?.[0] ?? ''}${parts[1]?.[0] ?? ''}`
  return (letters || local.slice(0, 2) || 'R').toUpperCase()
})

const otherSessions = computed(() => sessions.value.filter((row) => !row.current).length)

const emailDraft = ref('')
const emailBusy = ref(false)
const emailError = ref('')

const password = ref('')
const passwordBusy = ref(false)
const passwordError = ref('')
const showPassword = ref(false)

const confirmOpen = ref(false)
const confirmBusy = ref(false)
const confirmError = ref('')
const confirmTitle = ref('')
const confirmText = ref('')
const confirmKind = ref<ConfirmKind>('session')
const confirmTarget = ref<IdentitySession | null>(null)

function deviceIcon(kind: IdentitySession['kind']): string {
  if (kind === 'phone') return 'mdi-cellphone'
  if (kind === 'laptop') return 'mdi-laptop'
  return 'mdi-monitor'
}

function emitLogout() {
  logout(window.location.hostname)
}

async function ensureFlow(): Promise<SettingsFlow> {
  if (flow.value?.flowId && flow.value.csrfToken) {
    return flow.value
  }
  const returnTo = `${window.location.origin}/account`
  flow.value = await fetchSettingsFlow(returnTo)
  if (flow.value.email) {
    emailDraft.value = flow.value.email
  }
  return flow.value
}

function handleIdentityError(err: unknown): boolean {
  const identity = err as IdentityError
  if (identity?.reauth && identity.redirectBrowserTo) {
    window.location.href = identityReauthUrl(window.location.hostname, identity.redirectBrowserTo)
    return true
  }
  if (identity?.reauth) {
    const returnTo = `${window.location.origin}/account`
    window.location.href = loginUrl(window.location.hostname, returnTo)
    return true
  }
  return false
}

async function saveEmail() {
  emailError.value = ''
  if (!emailDraft.value.trim()) {
    emailError.value = 'Enter your email.'
    return
  }
  emailBusy.value = true
  try {
    const current = await ensureFlow()
    flow.value = await updateIdentitySettings({
      flowId: current.flowId,
      csrfToken: current.csrfToken,
      method: 'profile',
      email: emailDraft.value.trim(),
    })
    showToast('Email updated')
    await session.load()
  } catch (err) {
    if (!handleIdentityError(err)) {
      emailError.value = err instanceof Error ? err.message : 'Could not update email'
    }
  } finally {
    emailBusy.value = false
  }
}

async function savePassword() {
  passwordError.value = ''
  if (!password.value) {
    passwordError.value = 'Enter a new password.'
    return
  }
  passwordBusy.value = true
  try {
    const current = await ensureFlow()
    flow.value = await updateIdentitySettings({
      flowId: current.flowId,
      csrfToken: current.csrfToken,
      method: 'password',
      password: password.value,
    })
    password.value = ''
    showToast('Password updated')
  } catch (err) {
    if (!handleIdentityError(err)) {
      passwordError.value = err instanceof Error ? err.message : 'Could not update password'
    }
  } finally {
    passwordBusy.value = false
  }
}

async function loadSessions() {
  sessionsLoading.value = true
  try {
    sessions.value = await listIdentitySessions()
  } catch (err) {
    loadError.value = err instanceof Error ? err.message : 'Sessions could not be loaded'
  } finally {
    sessionsLoading.value = false
  }
}

async function reloadAll() {
  loadError.value = ''
  flow.value = null
  try {
    await ensureFlow()
    await loadSessions()
  } catch (err) {
    loadError.value = err instanceof Error ? err.message : 'Could not load account settings'
  }
}

function confirmRevoke(row: IdentitySession) {
  confirmKind.value = 'session'
  confirmTarget.value = row
  confirmTitle.value = row.current ? 'Log out current session?' : 'Terminate session?'
  confirmText.value = row.current
    ? 'You will be signed out on this device.'
    : `${row.device} · ${row.browser} will be signed out.`
  confirmError.value = ''
  confirmOpen.value = true
}

function confirmRevokeOthers() {
  confirmKind.value = 'others'
  confirmTarget.value = null
  confirmTitle.value = 'Log out all other sessions?'
  confirmText.value = `${otherSessions.value} other session(s) will be removed; this one stays active.`
  confirmError.value = ''
  confirmOpen.value = true
}

async function runConfirm() {
  confirmBusy.value = true
  confirmError.value = ''
  sessionsBusy.value = true
  try {
    if (confirmKind.value === 'others') {
      const count = await revokeOtherIdentitySessions()
      await loadSessions()
      showToast(`Removed ${count} session(s)`)
      confirmOpen.value = false
      return
    }
    const target = confirmTarget.value
    if (!target) return
    await revokeIdentitySession(target.id)
    if (target.current) {
      emitLogout()
      return
    }
    await loadSessions()
    showToast('Session terminated')
    confirmOpen.value = false
  } catch (err) {
    confirmError.value = err instanceof Error ? err.message : 'Action failed'
  } finally {
    confirmBusy.value = false
    sessionsBusy.value = false
  }
}

onMounted(() => {
  emailDraft.value = email.value
  void reloadAll()
})
</script>

<style scoped>
.profile-password-wrap {
  position: relative;
}

.profile-password-wrap .ui-input {
  padding-right: 40px;
}

.profile-password-toggle {
  align-items: center;
  background: transparent;
  border: 0;
  border-radius: 8px;
  color: #2a1e62;
  cursor: pointer;
  display: flex;
  height: 34px;
  justify-content: center;
  padding: 0;
  position: absolute;
  right: 2px;
  top: 2px;
  width: 34px;
}

.profile-password-toggle:hover {
  background: #f7f4ff;
}

.profile-password-toggle:focus-visible {
  outline: 2px solid #9486ff;
  outline-offset: 1px;
}

.sr-only {
  border: 0;
  clip: rect(0, 0, 0, 0);
  height: 1px;
  margin: -1px;
  overflow: hidden;
  padding: 0;
  position: absolute;
  white-space: nowrap;
  width: 1px;
}
</style>