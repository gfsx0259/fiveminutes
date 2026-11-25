<template>
  <div
    ref="rootEl"
    class="ui-date"
    :class="{
      'ui-date--invalid': (text.length > 0 && !parsed) || isPastSelection,
      'ui-date--open': open,
    }"
  >
    <button type="button" :aria-label="`Choose ${label} date`" @click.stop="toggle">
      <v-icon icon="mdi-calendar-month-outline" size="18" />
    </button>
    <input
      :value="text"
      :aria-label="label"
      type="text"
      inputmode="decimal"
      maxlength="10"
      placeholder="DD.MM.YYYY"
      autocomplete="off"
      @click.stop="reveal"
      @input="onType(($event.target as HTMLInputElement).value)"
    >
  </div>
  <Teleport to="body">
    <div
      v-if="open"
      ref="calendarEl"
      class="ui-date__calendar"
      role="dialog"
      :aria-label="`${label} calendar`"
      :style="popupStyle"
      @mousedown.stop
    >
      <div class="ui-date__caption">
        <strong>{{ monthLabel }}</strong>
        <span>
          <button type="button" aria-label="Previous month" @click="shift(-1)">
            <v-icon icon="mdi-chevron-left" size="18" />
          </button>
          <button type="button" aria-label="Next month" @click="shift(1)">
            <v-icon icon="mdi-chevron-right" size="18" />
          </button>
        </span>
      </div>
      <div class="ui-date__weekdays">
        <span v-for="name in weekdays" :key="name">{{ name }}</span>
      </div>
      <div class="ui-date__days">
        <button
          v-for="day in days"
          :key="day.iso"
          type="button"
          class="ui-date__day"
          :class="{
            'ui-date__day--outside': day.outside,
            'ui-date__day--today': day.iso === today && !day.outside,
            'ui-date__day--selected': day.iso === parsed,
            'ui-date__day--disabled': isBeforeMin(day.iso),
          }"
          :disabled="isBeforeMin(day.iso)"
          @click="choose(day.iso)"
        >
          {{ day.number }}
        </button>
      </div>
    </div>
  </Teleport>
</template>

<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue'
import { monthGrid, monthTitle, shiftMonth, weekdays } from '@/calendar'

const props = withDefaults(defineProps<{
  label?: string
  /** Earliest selectable day (YYYY-MM-DD, UTC). */
  min?: string
}>(), {
  label: 'Date',
  min: '',
})

const model = defineModel<string>({ default: '' })

const rootEl = ref<HTMLElement | null>(null)
let host: HTMLElement | null = null
const calendarEl = ref<HTMLElement | null>(null)
const open = ref(false)
const text = ref('')
const cursor = ref({ year: 2026, month: 0 })
const popupStyle = ref<Record<string, string>>({})

function isIso(value: string): boolean {
  if (!/^\d{4}-\d{2}-\d{2}$/.test(value)) return false
  const date = new Date(`${value}T12:00:00Z`)
  return !Number.isNaN(date.getTime()) && date.toISOString().slice(0, 10) === value
}

function display(iso: string): string {
  return isIso(iso) ? `${iso.slice(8, 10)}.${iso.slice(5, 7)}.${iso.slice(0, 4)}` : ''
}

function parse(value: string): string {
  const match = /^(\d{2})\.(\d{2})\.(\d{4})$/.exec(value)
  if (!match) return ''
  const iso = `${match[3]}-${match[2]}-${match[1]}`
  return isIso(iso) ? iso : ''
}

const parsed = computed(() => parse(text.value))
const today = new Date().toISOString().slice(0, 10)

function isBeforeMin(iso: string): boolean {
  if (!props.min || !isIso(iso)) {
    return false
  }
  return iso < props.min
}

const isPastSelection = computed(() => {
  const iso = parsed.value
  return Boolean(iso) && isBeforeMin(iso)
})

function showMonth(iso: string): void {
  const source = isIso(iso) ? iso : today
  cursor.value = { year: Number(source.slice(0, 4)), month: Number(source.slice(5, 7)) - 1 }
}

const monthLabel = computed(() => monthTitle(cursor.value.year, cursor.value.month))

const days = computed(() => monthGrid(cursor.value.year, cursor.value.month))

function shift(delta: number): void {
  cursor.value = shiftMonth(cursor.value.year, cursor.value.month, delta)
}

function toggle(event: MouseEvent): void {
  host = (event.currentTarget as HTMLElement).closest('.ui-date')
  open.value = !open.value
  if (open.value) void nextTick(place)
}

function reveal(event: MouseEvent): void {
  host = (event.currentTarget as HTMLElement).closest('.ui-date')
  open.value = true
  void nextTick(place)
}

function place(): void {
  const rect = (rootEl.value ?? host)?.getBoundingClientRect()
  if (!rect) return
  const height = calendarEl.value?.offsetHeight || 320
  const width = calendarEl.value?.offsetWidth || 280
  const spaceBelow = window.innerHeight - rect.bottom
  const top = spaceBelow >= height + 8 ? rect.bottom + 6 : Math.max(8, rect.top - height - 6)
  const left = Math.min(Math.max(8, rect.left), window.innerWidth - width - 8)
  popupStyle.value = { top: `${top}px`, left: `${left}px` }
}

function choose(iso: string): void {
  if (isBeforeMin(iso)) {
    return
  }
  text.value = display(iso)
  model.value = iso
  setTimeout(() => {
    open.value = false
  }, 0)
}

function onType(value: string): void {
  if (!/^[\d.]*$/.test(value)) return
  text.value = value
  const iso = parse(value)
  if (iso) {
    showMonth(iso)
    if (!isBeforeMin(iso)) {
      model.value = iso
    }
  } else if (!value) {
    model.value = ''
  }
}

watch(model, (value) => {
  const shown = display(value)
  if (shown !== text.value) text.value = shown
}, { immediate: true })

function onPointerDown(event: MouseEvent): void {
  const target = event.target as Node
  if (!open.value || rootEl.value?.contains(target) || calendarEl.value?.contains(target)) return
  open.value = false
}

watch(open, (value) => {
  if (!value) return
  showMonth(parsed.value)
  requestAnimationFrame(place)
})

watch(open, (value) => {
  if (value) {
    window.addEventListener('resize', place)
    window.addEventListener('scroll', place, true)
    document.addEventListener('mousedown', onPointerDown)
    return
  }
  window.removeEventListener('resize', place)
  window.removeEventListener('scroll', place, true)
  document.removeEventListener('mousedown', onPointerDown)
})

onBeforeUnmount(() => {
  window.removeEventListener('resize', place)
  window.removeEventListener('scroll', place, true)
  document.removeEventListener('mousedown', onPointerDown)
})
</script>

<style scoped>
.ui-date {
  align-items: center;
  background: #fff;
  border: 1px solid #cdd0e6;
  border-radius: 9px;
  box-sizing: border-box;
  display: flex;
  height: var(--lm-control-height);
  min-width: 0;
  width: 100%;
}

.ui-date--open,
.ui-date:focus-within {
  border-color: #9486ff;
  box-shadow: 0 0 0 3px rgb(148 134 255 / 38%);
}

.ui-date--invalid {
  border-color: #d391a6;
}

.ui-date > button {
  align-items: center;
  background: transparent;
  border: 0;
  border-radius: 8px;
  color: var(--lm-primary-deep);
  cursor: pointer;
  display: flex;
  flex: 0 0 36px;
  height: 36px;
  justify-content: center;
  padding: 0;
}

.ui-date > button:hover {
  background: var(--lm-lavender);
}

.ui-date input {
  background: transparent;
  border: 0;
  border-radius: 8px;
  color: var(--lm-foreground);
  flex: 1;
  font: inherit;
  font-size: 13px;
  height: 36px;
  min-width: 0;
  padding: 0 10px 0 2px;
}

.ui-date input:focus {
  outline: none;
}

.ui-date__calendar {
  background: #fff;
  border: 1px solid var(--lm-border);
  border-radius: 12px;
  box-shadow: 0 8px 24px #27206414, var(--lm-shadow);
  padding: 12px;
  position: fixed;
  z-index: 4000;
}

.ui-date__caption {
  align-items: center;
  display: flex;
  justify-content: space-between;
  margin-bottom: 8px;
  min-width: 252px;
}

.ui-date__caption strong {
  font-size: 13px;
  font-weight: 600;
}

.ui-date__caption span {
  display: flex;
  gap: 2px;
}

.ui-date__caption button {
  align-items: center;
  background: transparent;
  border: 0;
  border-radius: 8px;
  color: var(--lm-primary);
  cursor: pointer;
  display: flex;
  height: 30px;
  justify-content: center;
  width: 30px;
}

.ui-date__caption button:hover {
  background: var(--lm-lavender);
}

.ui-date__weekdays,
.ui-date__days {
  display: grid;
  grid-template-columns: repeat(7, 36px);
}

.ui-date__weekdays span {
  color: var(--lm-muted);
  font-size: 12px;
  height: 28px;
  line-height: 28px;
  text-align: center;
}

.ui-date__day {
  appearance: none;
  background: transparent;
  border: 2px solid transparent;
  border-radius: 8px;
  color: var(--lm-foreground);
  cursor: pointer;
  font: inherit;
  font-size: 13px;
  height: 34px;
  margin: 1px;
  padding: 0;
  width: 34px;
}

.ui-date__day:hover {
  background: var(--lm-lavender);
}

.ui-date__day--outside {
  color: var(--lm-muted);
  opacity: 0.75;
}

.ui-date__day--today:not(.ui-date__day--selected) {
  color: var(--lm-primary);
}

.ui-date__day--selected {
  border-color: var(--lm-primary);
  opacity: 1;
}

.ui-date__day--disabled,
.ui-date__day:disabled {
  cursor: not-allowed;
  opacity: 0.35;
}

.ui-date__day--disabled:hover,
.ui-date__day:disabled:hover {
  background: transparent;
}
</style>
