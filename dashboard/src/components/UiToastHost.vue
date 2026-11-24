<template>
  <v-snackbar
    v-model="open"
    location="bottom center"
    :timeout="snackbarTimeout"
    :class="['ui-toast-snackbar', { 'ui-toast-snackbar--error': isError }]"
    rounded="lg"
  >
    <div
      class="ui-toast-snackbar__body"
      :class="{ 'ui-toast-snackbar__body--rich': hasLines }"
    >
      <v-icon
        v-if="isError"
        class="ui-toast-snackbar__icon"
        icon="mdi-alert-circle"
        size="22"
      />
      <div class="ui-toast" :class="{ 'ui-toast--rich': hasLines }">
        <p class="ui-toast__title">{{ content.title }}</p>
        <p v-for="(line, index) in content.lines" :key="index" class="ui-toast__line">
          {{ line }}
        </p>
      </div>
    </div>
    <template v-if="hasRefresh" #actions>
      <button
        type="button"
        class="ui-toast-snackbar__refresh"
        :aria-label="`Refresh in ${refreshCountdown} seconds`"
        @click="onRefresh"
      >
        <v-icon icon="mdi-refresh" size="20" />
        <span class="ui-toast-snackbar__countdown">{{ refreshCountdown }}s</span>
      </button>
    </template>
  </v-snackbar>
</template>

<script setup lang="ts">
import { computed, onUnmounted, ref, watch } from 'vue'
import { useToastState } from '@/composables/useToast'

const REFRESH_COUNTDOWN_START = 5

const { open, content, timeoutMs, refreshHandler } = useToastState()
const hasLines = computed(() => (content.value.lines?.length ?? 0) > 0)
const isError = computed(() => content.value.tone === 'error')
const hasRefresh = computed(() => refreshHandler.value != null)
const snackbarTimeout = computed(() => (hasRefresh.value ? -1 : timeoutMs.value))

const refreshCountdown = ref(REFRESH_COUNTDOWN_START)
let countdownTimer: ReturnType<typeof setInterval> | null = null

function clearCountdown(): void {
  if (countdownTimer != null) {
    clearInterval(countdownTimer)
    countdownTimer = null
  }
}

function onRefresh(): void {
  clearCountdown()
  refreshHandler.value?.()
  window.setTimeout(() => {
    if (open.value && hasRefresh.value) {
      startCountdown()
    }
  }, 50)
}

function startCountdown(): void {
  clearCountdown()
  refreshCountdown.value = REFRESH_COUNTDOWN_START
  countdownTimer = setInterval(() => {
    if (refreshCountdown.value <= 0) {
      onRefresh()
      return
    }
    refreshCountdown.value -= 1
    if (refreshCountdown.value === 0) {
      onRefresh()
    }
  }, 1000)
}

watch(
  () => [open.value, hasRefresh.value, content.value.title] as const,
  ([isOpen, refresh]) => {
    if (isOpen && refresh) {
      startCountdown()
    } else {
      clearCountdown()
      refreshCountdown.value = REFRESH_COUNTDOWN_START
    }
  },
)

onUnmounted(() => {
  clearCountdown()
})
</script>

<style scoped>
.ui-toast-snackbar__body {
  align-items: center;
  display: flex;
  gap: 14px;
  max-width: min(420px, 88vw);
}

.ui-toast-snackbar__body--rich {
  align-items: flex-start;
}

.ui-toast-snackbar__icon {
  color: #ff6b6b;
  flex-shrink: 0;
  line-height: 1;
}

.ui-toast {
  text-align: left;
}

.ui-toast__title {
  font-size: 13px;
  font-weight: 600;
  line-height: 1.35;
  margin: 0;
}

.ui-toast--rich .ui-toast__title {
  margin-bottom: 4px;
}

.ui-toast__line {
  color: rgb(255 255 255 / 88%);
  font-size: 12px;
  font-weight: 400;
  line-height: 1.45;
  margin: 0;
}

.ui-toast__line + .ui-toast__line {
  margin-top: 2px;
}

.ui-toast-snackbar__body--rich .ui-toast-snackbar__icon {
  margin-top: 1px;
}

.ui-toast-snackbar__refresh {
  align-items: center;
  background: transparent;
  border: none;
  color: #fff;
  cursor: pointer;
  display: inline-flex;
  font: inherit;
  gap: 8px;
  line-height: 1;
  padding: 4px 2px;
}

.ui-toast-snackbar__refresh:focus-visible {
  outline: 2px solid rgb(255 255 255 / 55%);
  outline-offset: 2px;
}

.ui-toast-snackbar__countdown {
  font-size: 13px;
  font-variant-numeric: tabular-nums;
  font-weight: 600;
  min-width: 1.4em;
}
</style>

<style>
.ui-toast-snackbar--error.v-snackbar .v-snackbar__wrapper {
  align-items: center;
}

.ui-toast-snackbar.v-snackbar .v-snackbar__actions {
  margin-inline-start: 12px;
  padding-inline-start: 0;
}
</style>