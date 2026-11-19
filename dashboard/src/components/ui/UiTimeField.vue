<template>
  <div
    ref="rootEl"
    class="ui-time"
    :class="{ 'ui-time--open': open, 'ui-time--invalid': invalid }"
  >
    <input
      ref="inputEl"
      class="ui-time__input"
      :value="text"
      :aria-label="label"
      type="text"
      inputmode="numeric"
      maxlength="5"
      placeholder="HH:MM"
      autocomplete="off"
      @focus="onInputFocus"
      @mousedown="onInputMouseDown"
      @input="onType(($event.target as HTMLInputElement).value)"
      @keydown.enter.prevent="onEnterKey"
    >
    <button
      type="button"
      class="ui-time__toggle"
      :aria-label="`Open ${label} picker`"
      aria-haspopup="dialog"
      :aria-expanded="open"
      @click.stop="toggle"
    >
      <v-icon icon="mdi-chevron-down" size="18" />
    </button>
  </div>
  <Teleport to="body">
    <div
      v-if="open"
      ref="popupEl"
      class="ui-time__popup"
      role="dialog"
      :aria-label="`${label} time`"
      :style="popupStyle"
      @mousedown.stop
    >
      <div class="ui-time__wheel-panel">
        <div class="ui-time__wheel-columns">
          <p class="ui-time__wheel-title ui-time__wheel-title--hour">Hour</p>
          <p class="ui-time__wheel-title ui-time__wheel-title--min">Minute</p>
          <div class="ui-time__wheel-col">
            <div class="ui-time__wheel-mask">
              <div
                ref="hourColEl"
                class="ui-time__wheel-scroll"
                role="listbox"
                aria-label="Hour"
              >
                <div class="ui-time__wheel-pad" aria-hidden="true" />
                <button
                  v-for="h in hours"
                  :key="h"
                  type="button"
                  class="ui-time__wheel-item"
                  :class="{ 'ui-time__wheel-item--selected': h === selectedH }"
                  @click="pickHour(h)"
                >
                  {{ pad(h) }}
                </button>
                <div class="ui-time__wheel-pad" aria-hidden="true" />
              </div>
            </div>
          </div>
          <div class="ui-time__wheel-col ui-time__wheel-col--min">
            <div class="ui-time__wheel-mask">
              <div
                ref="minuteColEl"
                class="ui-time__wheel-scroll"
                role="listbox"
                aria-label="Minute"
              >
                <div class="ui-time__wheel-pad" aria-hidden="true" />
                <button
                  v-for="m in minuteList"
                  :key="m"
                  type="button"
                  class="ui-time__wheel-item"
                  :class="{ 'ui-time__wheel-item--selected': m === selectedM }"
                  @click="pickMinute(m)"
                >
                  {{ pad(m) }}
                </button>
                <div class="ui-time__wheel-pad" aria-hidden="true" />
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </Teleport>
</template>

<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue'
import { formatHm, parseHm } from '@/timeDefaults'

const props = withDefaults(defineProps<{
  label?: string
  minuteStep?: number
  extraMinutes?: number[]
}>(), {
  label: 'Time',
  minuteStep: 15,
  extraMinutes: () => [],
})

const model = defineModel<string>({ default: '' })

const hours = Array.from({ length: 24 }, (_, i) => i)

const minuteList = computed(() => {
  const step = Math.max(1, Math.min(30, props.minuteStep))
  const values = new Set<number>()
  for (let m = 0; m < 60; m += step) {
    values.add(m)
  }
  for (const m of props.extraMinutes) {
    if (m >= 0 && m < 60) {
      values.add(m)
    }
  }
  const parsed = parseHm(model.value)
  if (parsed) {
    values.add(parsed.m)
  }
  return Array.from(values).sort((a, b) => a - b)
})

const inputEl = ref<HTMLInputElement | null>(null)
const rootEl = ref<HTMLElement | null>(null)
const popupEl = ref<HTMLElement | null>(null)
const hourColEl = ref<HTMLElement | null>(null)
const minuteColEl = ref<HTMLElement | null>(null)
const open = ref(false)
const text = ref('')
const popupStyle = ref<Record<string, string>>({})

const scrollSnapTimers = new WeakMap<HTMLElement, ReturnType<typeof setTimeout>>()
let hourScrollHandler: ((event: Event) => void) | null = null
let minuteScrollHandler: ((event: Event) => void) | null = null
let suppressWheelSnapUntil = 0

const parsed = computed(() => parseHm(text.value))
const invalid = computed(() => text.value.length > 0 && !parsed.value)
const selectedH = computed(() => parsed.value?.h ?? 0)
const selectedM = computed(() => parsed.value?.m ?? 0)

function pad(n: number): string {
  return String(n).padStart(2, '0')
}

function syncFromModel(value: string): void {
  const p = parseHm(value)
  text.value = p ? formatHm(p.h, p.m) : value
}

function commit(h: number, m: number): void {
  const next = formatHm(h, m)
  text.value = next
  model.value = next
}

function formatTimeWhileTyping(raw: string): string {
  const stripped = raw.replace(/[^\d:]/g, '')
  if (stripped.includes(':')) {
    const [hPart, mPart = ''] = stripped.split(':')
    const h = hPart.replace(/\D/g, '').slice(0, 2)
    const m = mPart.replace(/\D/g, '').slice(0, 2)
    return m.length > 0 ? `${h}:${m}` : h.length > 0 ? `${h}:` : ''
  }
  const digits = stripped.replace(/\D/g, '').slice(0, 4)
  if (digits.length <= 2) {
    return digits
  }
  return `${digits.slice(0, 2)}:${digits.slice(2)}`
}

function onType(raw: string): void {
  const cleaned = formatTimeWhileTyping(raw)
  text.value = cleaned
  const p = parseHm(cleaned)
  if (p) {
    model.value = formatHm(p.h, p.m)
  } else if (!cleaned) {
    model.value = ''
  }
}

function onInputFocus(event: FocusEvent): void {
  const el = event.target as HTMLInputElement
  if (!el.value) {
    return
  }
  requestAnimationFrame(() => {
    if (document.activeElement === el) {
      el.select()
    }
  })
}

function onInputMouseDown(event: MouseEvent): void {
  const el = event.target as HTMLInputElement
  if (document.activeElement === el) {
    return
  }
  event.preventDefault()
  el.focus()
  el.select()
}

function onEnterKey(): void {
  if (parsed.value) {
    open.value = false
  }
}

function pickHour(h: number): void {
  const m = parsed.value?.m ?? 0
  commit(h, m)
  void nextTick(() => {
    scrollSelectionIntoView()
    focusMinuteColumn()
  })
}

function pickMinute(m: number): void {
  const h = parsed.value?.h ?? 0
  commit(h, m)
  void nextTick(() => {
    open.value = false
  })
}

function focusMinuteColumn(): void {
  const col = minuteColEl.value
  if (!col) {
    return
  }
  const target = col.querySelector<HTMLButtonElement>('.ui-time__wheel-item--selected')
    ?? col.querySelector<HTMLButtonElement>('.ui-time__wheel-item')
  target?.focus({ preventScroll: true })
  const selected = col.querySelector('.ui-time__wheel-item--selected')
  selected?.scrollIntoView({ block: 'center' })
}

function valueAtWheelCenter(col: HTMLElement): number | null {
  const rect = col.getBoundingClientRect()
  const centerY = rect.top + rect.height / 2
  const items = Array.from(col.querySelectorAll<HTMLButtonElement>('.ui-time__wheel-item'))
  let best: { dist: number; value: number } | null = null
  for (const el of items) {
    const itemRect = el.getBoundingClientRect()
    const mid = itemRect.top + itemRect.height / 2
    const dist = Math.abs(mid - centerY)
    const value = Number(el.textContent?.trim())
    if (!Number.isFinite(value)) {
      continue
    }
    if (best && dist >= best.dist) {
      continue
    }
    best = { dist, value }
  }
  return best?.value ?? null
}

function onWheelScroll(col: HTMLElement, kind: 'hour' | 'minute'): void {
  if (Date.now() < suppressWheelSnapUntil) {
    return
  }
  const previous = scrollSnapTimers.get(col)
  if (previous) {
    clearTimeout(previous)
  }
  const timer = setTimeout(() => {
    if (Date.now() < suppressWheelSnapUntil) {
      return
    }
    const value = valueAtWheelCenter(col)
    if (value == null) {
      return
    }
    const m = parsed.value?.m ?? 0
    const h = parsed.value?.h ?? 0
    if (kind === 'hour' && value !== selectedH.value) {
      commit(value, m)
      return
    }
    if (kind === 'minute' && value !== selectedM.value) {
      commit(h, value)
    }
  }, 150)
  scrollSnapTimers.set(col, timer)
}

function bindWheelScrollHandlers(): void {
  unbindWheelScrollHandlers()
  const hourCol = hourColEl.value
  const minuteCol = minuteColEl.value
  if (hourCol) {
    hourScrollHandler = () => onWheelScroll(hourCol, 'hour')
    hourCol.addEventListener('scroll', hourScrollHandler, { passive: true })
    hourCol.addEventListener('scrollend', hourScrollHandler, { passive: true })
  }
  if (minuteCol) {
    minuteScrollHandler = () => onWheelScroll(minuteCol, 'minute')
    minuteCol.addEventListener('scroll', minuteScrollHandler, { passive: true })
    minuteCol.addEventListener('scrollend', minuteScrollHandler, { passive: true })
  }
}

function unbindWheelScrollHandlers(): void {
  if (hourColEl.value && hourScrollHandler) {
    hourColEl.value.removeEventListener('scroll', hourScrollHandler)
    hourColEl.value.removeEventListener('scrollend', hourScrollHandler)
  }
  if (minuteColEl.value && minuteScrollHandler) {
    minuteColEl.value.removeEventListener('scroll', minuteScrollHandler)
    minuteColEl.value.removeEventListener('scrollend', minuteScrollHandler)
  }
  hourScrollHandler = null
  minuteScrollHandler = null
}

function place(): void {
  const rect = rootEl.value?.getBoundingClientRect()
  if (!rect) {
    return
  }
  const height = popupEl.value?.offsetHeight || 200
  const width = popupEl.value?.offsetWidth || 168
  const spaceBelow = window.innerHeight - rect.bottom
  const top = spaceBelow >= height + 8 ? rect.bottom + 6 : Math.max(8, rect.top - height - 6)
  const left = Math.min(Math.max(8, rect.left), window.innerWidth - width - 8)
  popupStyle.value = { top: `${top}px`, left: `${left}px` }
}

function scrollSelectionIntoView(): void {
  suppressWheelSnapUntil = Date.now() + 280
  for (const col of [hourColEl.value, minuteColEl.value]) {
    if (!col) {
      continue
    }
    const selected = col.querySelector('.ui-time__wheel-item--selected')
    selected?.scrollIntoView({ block: 'center' })
  }
}

function toggle(): void {
  open.value = !open.value
  if (!open.value) {
    return
  }
  if (!parsed.value && model.value) {
    syncFromModel(model.value)
  }
  if (!parsed.value) {
    commit(0, 0)
  }
  void nextTick(() => {
    place()
    scrollSelectionIntoView()
    bindWheelScrollHandlers()
  })
}

function onPointerDown(event: MouseEvent): void {
  const target = event.target as Node
  if (!open.value || rootEl.value?.contains(target) || popupEl.value?.contains(target)) {
    return
  }
  open.value = false
}

watch(model, (value) => {
  syncFromModel(value)
}, { immediate: true })

watch(open, (value) => {
  if (value) {
    window.addEventListener('resize', place)
    window.addEventListener('scroll', place, true)
    document.addEventListener('mousedown', onPointerDown)
    void nextTick(bindWheelScrollHandlers)
    return
  }
  unbindWheelScrollHandlers()
  window.removeEventListener('resize', place)
  window.removeEventListener('scroll', place, true)
  document.removeEventListener('mousedown', onPointerDown)
})

onBeforeUnmount(() => {
  unbindWheelScrollHandlers()
  window.removeEventListener('resize', place)
  window.removeEventListener('scroll', place, true)
  document.removeEventListener('mousedown', onPointerDown)
})
</script>

<style scoped>
.ui-time {
  align-items: center;
  background: #fff;
  border: 1px solid #cdd0e6;
  border-radius: 9px;
  box-sizing: border-box;
  display: flex;
  height: var(--lm-control-height);
  min-width: 0;
  position: relative;
  width: 100%;
}

.ui-time--open,
.ui-time:focus-within {
  border-color: #9486ff;
  box-shadow: 0 0 0 3px rgb(148 134 255 / 38%);
}

.ui-time--invalid {
  border-color: #d391a6;
}

.ui-time__input {
  background: transparent;
  border: 0;
  border-radius: 8px;
  color: var(--lm-foreground);
  flex: 1;
  font: inherit;
  font-size: 13px;
  font-variant-numeric: tabular-nums;
  height: 100%;
  min-width: 0;
  padding: 0 34px 0 12px;
}

.ui-time__input:focus {
  outline: none;
}

.ui-time__toggle {
  align-items: center;
  appearance: none;
  background: transparent;
  border: 0;
  border-radius: 8px;
  color: #343b69;
  cursor: pointer;
  display: flex;
  flex: 0 0 32px;
  height: 32px;
  justify-content: center;
  margin-right: 4px;
  padding: 0;
}

.ui-time__toggle:hover {
  background: var(--lm-lavender);
}

.ui-time__popup {
  background: #fff;
  border: 1px solid var(--lm-border);
  border-radius: 12px;
  box-shadow: 0 8px 24px #27206414, var(--lm-shadow);
  padding: 6px;
  position: fixed;
  width: 168px;
  z-index: 4000;
}

.ui-time__wheel-panel {
  position: relative;
}

.ui-time__wheel-columns {
  column-gap: 2px;
  display: grid;
  grid-template-columns: 1fr 1fr;
  grid-template-rows: auto 1fr;
}

.ui-time__wheel-title {
  color: var(--lm-muted);
  font-size: 12px;
  font-weight: 600;
  margin: 0 0 4px;
  text-align: center;
}

.ui-time__wheel-title--hour {
  grid-column: 1;
  grid-row: 1;
}

.ui-time__wheel-title--min {
  grid-column: 2;
  grid-row: 1;
}

.ui-time__wheel-col {
  grid-row: 2;
  min-width: 0;
}

.ui-time__wheel-col--min {
  border-left: 1px solid #eceef6;
  padding-left: 2px;
}

.ui-time__wheel-mask {
  --ui-time-item-h: 34px;
  --ui-time-viewport-h: 156px;
  height: var(--ui-time-viewport-h);
  -webkit-mask-image: linear-gradient(
    to bottom,
    transparent 0%,
    #000 14%,
    #000 86%,
    transparent 100%
  );
  mask-image: linear-gradient(
    to bottom,
    transparent 0%,
    #000 14%,
    #000 86%,
    transparent 100%
  );
  overflow: hidden;
  position: relative;
}

.ui-time__wheel-scroll {
  align-items: stretch;
  box-sizing: border-box;
  display: flex;
  flex-direction: column;
  gap: 2px;
  height: 100%;
  overflow-y: auto;
  overscroll-behavior: contain;
  padding-inline: 6px;
  scroll-snap-type: y mandatory;
  scrollbar-width: none;
}

.ui-time__wheel-scroll::-webkit-scrollbar {
  display: none;
}

.ui-time__wheel-pad {
  flex-shrink: 0;
  height: calc((var(--ui-time-viewport-h) - var(--ui-time-item-h)) / 2);
}

.ui-time__wheel-item {
  align-items: center;
  appearance: none;
  background: transparent;
  border: 2px solid transparent;
  border-radius: 8px;
  color: var(--lm-foreground);
  cursor: pointer;
  display: flex;
  flex-shrink: 0;
  font: inherit;
  font-size: 13px;
  font-variant-numeric: tabular-nums;
  height: var(--ui-time-item-h);
  justify-content: center;
  margin: 0;
  padding: 0;
  scroll-snap-align: center;
  width: 100%;
}

.ui-time__wheel-item:hover:not(.ui-time__wheel-item--selected) {
  background: var(--lm-lavender);
}

.ui-time__wheel-item:focus-visible {
  background: var(--lm-lavender);
  outline: 2px solid var(--lm-primary);
  outline-offset: 1px;
}

.ui-time__wheel-item--selected {
  border-color: var(--lm-primary);
}

@media (max-width: 768px) {
  .ui-time__popup {
    width: 184px;
  }

  .ui-time__wheel-mask {
    --ui-time-item-h: 44px;
    --ui-time-viewport-h: 172px;
  }

  .ui-time__toggle {
    flex: 0 0 40px;
    height: 40px;
  }
}
</style>