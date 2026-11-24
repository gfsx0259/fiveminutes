<template>
  <v-dialog
    :model-value="modelValue"
    :max-width="showAside ? splitDialogMaxWidth : maxWidth"
    :content-class="overlayContentClass"
    scrollable
    @update:model-value="emit('update:modelValue', $event)"
  >
    <div
      v-if="showAside"
      class="form-dialog__cluster"
      :class="{
        'form-dialog__cluster--aside-collapsed': mobileAside && !asideLayerOpen,
        'form-dialog__cluster--aside-pending': asideRevealPending,
        'form-dialog__cluster--aside-shifted': asideShifted && !asideRevealed,
        'form-dialog__cluster--aside-open': asideRevealed,
      }"
      :style="{ '--form-dialog-main-w': `${maxWidth}px` }"
    >
      <v-card class="form-dialog__main-card" elevation="8" rounded="lg">
        <v-card-title class="form-dialog__title">
          <span>{{ title }}</span>
          <UiButton
            v-if="shareable"
            variant="ghost"
            icon="mdi-share-variant"
            class="form-dialog__share"
            title="Copy link with current form"
            @click="emit('share')"
          >
            Share
          </UiButton>
        </v-card-title>
        <v-card-text class="form-dialog__main-body">
          <p v-if="hint" class="form-dialog__hint">{{ hint }}</p>
          <div v-if="error" class="form-dialog__error ui-notice ui-notice--error" role="alert">
            {{ error }}
          </div>
          <div
            v-if="mobileAside && showAside && !asideLayerOpen"
            class="form-dialog__stock-peek"
          >
            <UiButton
              variant="outline"
              icon="mdi-warehouse"
              class="form-dialog__stock-peek-btn"
              @click="asideLayerOpen = true"
            >
              View stock offer
            </UiButton>
          </div>
          <form class="ui-form" @submit.prevent="emit('submit')">
            <slot />
          </form>
        </v-card-text>
        <v-card-actions class="form-dialog__actions">
          <UiButton variant="outline" :disabled="loading" @click="emit('update:modelValue', false)">
            Cancel
          </UiButton>
          <UiButton :loading="loading" :icon="submitIcon" @click="emit('submit')">{{ submitLabel }}</UiButton>
        </v-card-actions>
      </v-card>
      <v-card
        v-show="!mobileAside || asideLayerOpen"
        class="form-dialog__aside-card form-dialog__aside-card--layer"
        elevation="8"
        rounded="lg"
      >
        <button
          type="button"
          class="form-dialog__aside-close"
          aria-label="Close stock offer"
          @click="onAsideClose"
        >
          <v-icon icon="mdi-close" size="22" />
        </button>
        <v-card-text class="form-dialog__aside-body">
          <slot name="aside" />
        </v-card-text>
      </v-card>
    </div>
    <v-card v-else elevation="12" rounded="lg">
      <v-card-title class="form-dialog__title">
        <span>{{ title }}</span>
        <UiButton
          v-if="shareable"
          variant="ghost"
          icon="mdi-share-variant"
          class="form-dialog__share"
          title="Copy link with current form"
          @click="emit('share')"
        >
          Share
        </UiButton>
      </v-card-title>
      <v-card-text>
        <p v-if="hint">{{ hint }}</p>
        <div v-if="error" class="form-dialog__error ui-notice ui-notice--error" role="alert">
          {{ error }}
        </div>
        <form class="ui-form" @submit.prevent="emit('submit')">
          <slot />
        </form>
      </v-card-text>
      <v-card-actions>
        <UiButton variant="outline" :disabled="loading" @click="emit('update:modelValue', false)">
          Cancel
        </UiButton>
        <UiButton :loading="loading" :icon="submitIcon" @click="emit('submit')">{{ submitLabel }}</UiButton>
      </v-card-actions>
    </v-card>
  </v-dialog>
</template>

<script setup lang="ts">
import { Comment, Text, computed, onMounted, onUnmounted, ref, useSlots, watch } from 'vue'
import type { VNode } from 'vue'
import UiButton from '@/components/ui/UiButton.vue'

const props = withDefaults(defineProps<{
  modelValue: boolean
  title: string
  hint?: string
  error?: string
  submitLabel: string
  loading?: boolean
  maxWidth?: number
  clusterMaxWidth?: number
  submitIcon?: string
  shareable?: boolean
  /** Parent toggles split layout (stock panel + form) */
  asideOpen?: boolean
  /** Desktop: wait after open, then shift main to make room (ms). */
  asideRevealDelayMs?: number
  /** Desktop: delay after shift before stock panel appears (ms). */
  asidePanelDelayMs?: number
}>(), {
  hint: '',
  error: '',
  loading: false,
  maxWidth: 520,
  clusterMaxWidth: 1180,
  submitIcon: '',
  shareable: false,
  asideOpen: false,
  asideRevealDelayMs: 1100,
  asidePanelDelayMs: 240,
})

const emit = defineEmits<{
  'update:modelValue': [value: boolean]
  submit: []
  share: []
  asideDismiss: []
}>()

const slots = useSlots()

function slotHasContent(nodes: VNode[] | undefined): boolean {
  if (!nodes?.length) {
    return false
  }
  return nodes.some((node) => {
    if (node.type === Comment) {
      return false
    }
    if (node.type === Text) {
      return String(node.children ?? '').trim() !== ''
    }
    return true
  })
}

const showAside = computed(() => {
  if (!props.asideOpen) {
    return false
  }
  const render = slots.aside
  if (!render) {
    return false
  }
  return slotHasContent(render())
})

const MOBILE_SPLIT_BP = 768
const mobileAside = ref(
  typeof window !== 'undefined'
    ? window.matchMedia(`(max-width: ${MOBILE_SPLIT_BP}px)`).matches
    : false,
)
const asideLayerOpen = ref(true)
const asideShifted = ref(false)
const asideRevealed = ref(false)
const asideRevealTimers: ReturnType<typeof setTimeout>[] = []

const asideRevealPending = computed(
  () => showAside.value && !mobileAside.value && !asideShifted.value,
)

const splitDialogMaxWidth = computed(() => {
  if (!showAside.value) {
    return props.maxWidth
  }
  if (mobileAside.value) {
    return props.clusterMaxWidth
  }
  if (!asideShifted.value) {
    return props.maxWidth
  }
  return props.clusterMaxWidth
})

const overlayContentClass = computed(() => {
  if (!showAside.value) {
    return ''
  }
  const classes = ['form-dialog__overlay', 'form-dialog__overlay--split']
  if (asideRevealPending.value) {
    classes.push('form-dialog__overlay--aside-pending')
  }
  return classes.join(' ')
})

function clearAsideRevealTimers(): void {
  while (asideRevealTimers.length > 0) {
    const timer = asideRevealTimers.pop()
    if (timer) {
      clearTimeout(timer)
    }
  }
}

function prefersReducedMotion(): boolean {
  if (typeof window === 'undefined') {
    return false
  }
  return window.matchMedia('(prefers-reduced-motion: reduce)').matches
}

function scheduleAsideReveal(): void {
  clearAsideRevealTimers()
  asideShifted.value = false
  asideRevealed.value = false
  if (!props.modelValue || !showAside.value || mobileAside.value) {
    asideShifted.value = true
    asideRevealed.value = true
    return
  }
  if (prefersReducedMotion() || props.asideRevealDelayMs < 1) {
    asideShifted.value = true
    asideRevealed.value = true
    return
  }
  const shiftAt = Math.max(0, props.asideRevealDelayMs)
  const panelAt = shiftAt + Math.max(0, props.asidePanelDelayMs)
  asideRevealTimers.push(setTimeout(() => {
    asideShifted.value = true
  }, shiftAt))
  asideRevealTimers.push(setTimeout(() => {
    asideRevealed.value = true
  }, panelAt))
}

function onAsideClose(): void {
  emit('asideDismiss')
  if (mobileAside.value) {
    asideLayerOpen.value = false
  }
}

function syncMobileAside(): void {
  if (typeof window === 'undefined') {
    return
  }
  mobileAside.value = window.matchMedia(`(max-width: ${MOBILE_SPLIT_BP}px)`).matches
}

onMounted(() => {
  syncMobileAside()
  window.addEventListener('resize', syncMobileAside)
})

onUnmounted(() => {
  window.removeEventListener('resize', syncMobileAside)
  clearAsideRevealTimers()
})

watch(showAside, (open) => {
  if (open) {
    asideLayerOpen.value = true
    scheduleAsideReveal()
  } else {
    clearAsideRevealTimers()
    asideShifted.value = false
    asideRevealed.value = false
  }
})

watch(() => props.modelValue, (open) => {
  if (open) {
    syncMobileAside()
    if (showAside.value) {
      scheduleAsideReveal()
    }
  } else {
    clearAsideRevealTimers()
    asideShifted.value = false
    asideRevealed.value = false
  }
})
</script>

<style scoped>
.form-dialog__title {
  align-items: center;
  display: flex;
  gap: 12px;
  justify-content: space-between;
}

.form-dialog__share {
  flex-shrink: 0;
  margin-left: auto;
}

.form-dialog__cluster {
  align-items: stretch;
  display: flex;
  gap: 24px;
  justify-content: center;
  margin-inline: auto;
  max-width: 100%;
  transition: gap 0.24s cubic-bezier(0.4, 0, 0.2, 1);
  width: fit-content;
}

.form-dialog__cluster--aside-pending {
  align-items: flex-start;
  gap: 0;
}

.form-dialog__main-card {
  flex: 0 0 var(--form-dialog-main-w, 560px);
  max-width: min(var(--form-dialog-main-w, 560px), 100%);
  min-width: 0;
  width: var(--form-dialog-main-w, 560px);
}

.form-dialog__hint {
  color: #6d6a88;
  font-size: 14px;
  line-height: 1.45;
  margin: 0 0 16px;
}

.form-dialog__aside-card {
  flex: 0 0 440px;
  max-width: 460px;
  min-width: 0;
  opacity: 1;
  overflow: hidden;
  transform: translateX(0);
  transition:
    flex-basis 0.24s cubic-bezier(0.4, 0, 0.2, 1),
    max-width 0.24s cubic-bezier(0.4, 0, 0.2, 1),
    opacity 0.2s ease,
    transform 0.24s cubic-bezier(0.4, 0, 0.2, 1);
}

.form-dialog__cluster--aside-pending .form-dialog__main-card {
  flex: 0 0 var(--form-dialog-main-w, 560px);
  height: auto;
  max-height: calc(100dvh - 96px);
  width: var(--form-dialog-main-w, 560px);
}

.form-dialog__cluster--aside-pending .form-dialog__aside-card {
  align-self: flex-start;
  border-width: 0;
  flex: 0 0 0;
  margin: 0;
  max-height: 0 !important;
  max-width: 0;
  min-height: 0 !important;
  opacity: 0;
  padding: 0 !important;
  pointer-events: none;
  transform: translateX(-40px);
  visibility: hidden;
}

/* Phase 2: main shifted; empty slot reserved on the right */
.form-dialog__cluster--aside-shifted:not(.form-dialog__cluster--aside-open) {
  align-items: flex-start;
}

.form-dialog__cluster--aside-shifted:not(.form-dialog__cluster--aside-open) .form-dialog__aside-card {
  align-self: flex-start;
  border-width: 0;
  flex: 0 0 440px;
  margin: 0;
  max-height: 0 !important;
  max-width: 440px;
  min-height: 0 !important;
  opacity: 0;
  padding: 0 !important;
  pointer-events: none;
  transform: translateX(0);
  visibility: hidden;
}

/* Phase 3: stock panel visible */
.form-dialog__cluster--aside-open {
  align-items: stretch;
}

.form-dialog__cluster--aside-open .form-dialog__aside-card {
  visibility: visible;
}

.form-dialog__aside-card :deep(.form-dialog__aside-body) {
  display: flex;
  flex: 1 1 auto;
  flex-direction: column;
  min-height: 0;
  overflow: hidden;
}

.form-dialog__stock-peek {
  margin: 14px 0 12px;
}

.form-dialog__stock-peek-btn {
  width: 100%;
}

.form-dialog__aside-close {
  align-items: center;
  background: transparent;
  border: none;
  border-radius: 8px;
  color: #5c5878;
  cursor: pointer;
  display: flex;
  height: 40px;
  justify-content: center;
  opacity: 0.85;
  position: absolute;
  right: 8px;
  top: 8px;
  width: 40px;
  z-index: 3;
}

.form-dialog__aside-close:hover {
  color: #2a1578;
  opacity: 1;
}

@media (min-width: 769px) and (max-width: 960px) {
  .form-dialog__cluster {
    flex-direction: column;
    width: 100%;
  }

  .form-dialog__aside-card {
    flex: 1 1 auto;
    max-width: none;
    order: 2;
    width: 100%;
  }

  .form-dialog__main-card {
    flex: 1 1 auto;
    order: 1;
    width: 100%;
  }
}
</style>

<style>
/* Two separate “modals” — transparent shell, shadow on each card */
.form-dialog__overlay--split.v-overlay__content {
  align-items: flex-start;
  background: transparent !important;
  box-shadow: none !important;
  justify-content: center;
  margin: 32px 24px;
  max-height: calc(100dvh - 64px);
  max-width: min(1200px, 94vw) !important;
  overflow: visible;
  padding: 0;
  transition: max-width 0.24s cubic-bezier(0.4, 0, 0.2, 1);
  width: auto !important;
}

.v-dialog > .v-overlay__content.form-dialog__overlay--split:not(.form-dialog__overlay--aside-pending) > .form-dialog__cluster {
  align-items: stretch;
}

@media (min-width: 961px) {
  .v-dialog > .v-overlay__content > .form-dialog__cluster > .form-dialog__main-card {
    flex: 0 0 var(--form-dialog-main-w, 560px) !important;
    max-width: min(var(--form-dialog-main-w, 560px), 100%) !important;
    width: var(--form-dialog-main-w, 560px) !important;
  }
}

/* Before stock panel reveals: main card stays content-sized (not stretched by hidden aside). */
.v-dialog > .v-overlay__content.form-dialog__overlay--split.form-dialog__overlay--aside-pending {
  align-items: flex-start;
  height: auto;
  max-height: none;
  width: auto !important;
}

.v-dialog > .v-overlay__content.form-dialog__overlay--split.form-dialog__overlay--aside-pending > .form-dialog__cluster {
  align-items: flex-start;
  align-self: center;
  max-height: none;
}

@media (prefers-reduced-motion: reduce) {
  .form-dialog__overlay--split.v-overlay__content,
  .form-dialog__cluster,
  .form-dialog__main-card,
  .form-dialog__aside-card {
    transition: none !important;
  }
}

</style>