<template>
  <div ref="rootEl" class="period-picker" :class="{ 'period-picker--open': open }">
    <button type="button" class="period-picker__button" :aria-expanded="open" @click="toggle">
      <v-icon icon="mdi-calendar-range" size="18" />
      <span class="period-picker__title">{{ title }}</span>
      <span class="period-picker__dates">{{ dates }}</span>
      <v-icon icon="mdi-chevron-down" size="18" class="period-picker__chevron" />
    </button>
    <div v-if="open" class="period-picker__popup" role="dialog" aria-label="Choose period">
      <div class="period-picker__presets">
        <button
          v-for="item in periodItems"
          :key="item.value"
          type="button"
          :class="{ 'period-picker__preset--on': model.key === item.value }"
          @click="pickPreset(item.value)"
        >
          {{ item.title }}
        </button>
        <button type="button" :class="{ 'period-picker__preset--on': model.key === 'custom' }" @click="anchor = ''">
          Custom range
        </button>
      </div>
      <div class="period-picker__calendar">
        <div class="period-picker__caption">
          <button type="button" aria-label="Previous month" @click="cursor = shiftMonth(cursor.year, cursor.month, -1)">
            <v-icon icon="mdi-chevron-left" size="18" />
          </button>
          <strong>{{ monthTitle(cursor.year, cursor.month) }}</strong>
          <button type="button" aria-label="Next month" :disabled="lastMonth" @click="cursor = shiftMonth(cursor.year, cursor.month, 1)">
            <v-icon icon="mdi-chevron-right" size="18" />
          </button>
        </div>
        <div class="period-picker__weekdays">
          <span v-for="name in weekdays" :key="name">{{ name }}</span>
        </div>
        <div class="period-picker__days" @mouseleave="hover = ''">
          <button
            v-for="day in days"
            :key="day.iso"
            type="button"
            class="period-picker__day"
            :class="dayClass(day.iso, day.outside)"
            :disabled="day.iso > today"
            @mouseenter="hover = day.iso"
            @click="pickDay(day.iso)"
          >
            {{ day.number }}
          </button>
        </div>
        <p class="period-picker__hint">{{ anchor ? 'Pick the last day' : 'Pick the first day, then the last' }}</p>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, onBeforeUnmount, ref, watch } from 'vue'
import { monthGrid, monthTitle, shiftMonth, weekdays } from '@/calendar'
import { localDay } from '@/orderBook'
import { choicePeriodOf, periodItems, type PeriodChoice, type PeriodKey } from '@/purchases'

const props = defineProps<{
  now: number
}>()

const model = defineModel<PeriodChoice>({ required: true })

const rootEl = ref<HTMLElement | null>(null)
const open = ref(false)
const anchor = ref('')
const hover = ref('')
const today = computed(() => localDay(props.now))
const period = computed(() => choicePeriodOf(model.value, props.now))
const first = computed(() => localDay(period.value.from))
const last = computed(() => localDay(Math.min(period.value.to, props.now + 1) - 1))
const cursor = ref({ year: 2026, month: 0 })

const days = computed(() => monthGrid(cursor.value.year, cursor.value.month))
const lastMonth = computed(() => `${cursor.value.year}-${String(cursor.value.month + 1).padStart(2, '0')}` >= today.value.slice(0, 7))

const title = computed(() => periodItems.find((item) => item.value === model.value.key)?.title ?? 'Custom range')

const dates = computed(() => {
  const short = (iso: string, year: boolean) => new Date(`${iso}T12:00:00`).toLocaleDateString('en-GB', year
    ? { day: 'numeric', month: 'short', year: 'numeric' }
    : { day: 'numeric', month: 'short' })
  if (first.value === last.value) return short(first.value, true)
  return `${short(first.value, first.value.slice(0, 4) !== last.value.slice(0, 4))} – ${short(last.value, true)}`
})

const selection = computed(() => {
  if (!anchor.value) return [first.value, last.value]
  const end = hover.value || anchor.value
  return anchor.value <= end ? [anchor.value, end] : [end, anchor.value]
})

function dayClass(iso: string, outside: boolean): Record<string, boolean> {
  const [from, to] = selection.value as [string, string]
  return {
    'period-picker__day--outside': outside,
    'period-picker__day--today': iso === today.value,
    'period-picker__day--inside': iso > from && iso < to,
    'period-picker__day--edge': iso === from || iso === to,
  }
}

function pickPreset(key: PeriodKey): void {
  model.value = { key, from: '', to: '' }
  close()
}

function pickDay(iso: string): void {
  if (!anchor.value) {
    anchor.value = iso
    return
  }
  const [from, to] = anchor.value <= iso ? [anchor.value, iso] : [iso, anchor.value]
  model.value = { key: 'custom', from, to }
  close()
}

function toggle(): void {
  if (open.value) {
    close()
    return
  }
  const shown = last.value
  cursor.value = { year: Number(shown.slice(0, 4)), month: Number(shown.slice(5, 7)) - 1 }
  anchor.value = ''
  open.value = true
}

function close(): void {
  open.value = false
  anchor.value = ''
  hover.value = ''
}

function onPointerDown(event: MouseEvent): void {
  if (!rootEl.value?.contains(event.target as Node)) close()
}

function onKey(event: KeyboardEvent): void {
  if (event.key === 'Escape') close()
}

watch(open, (value) => {
  if (value) {
    document.addEventListener('mousedown', onPointerDown)
    document.addEventListener('keydown', onKey)
    return
  }
  document.removeEventListener('mousedown', onPointerDown)
  document.removeEventListener('keydown', onKey)
})

onBeforeUnmount(() => {
  document.removeEventListener('mousedown', onPointerDown)
  document.removeEventListener('keydown', onKey)
})
</script>

<style scoped>
.period-picker {
  display: inline-block;
  position: relative;
}

.period-picker__button {
  align-items: center;
  background: #fff;
  border: 1px solid var(--lm-border);
  border-radius: 11px;
  box-shadow: var(--lm-shadow);
  color: var(--lm-foreground);
  cursor: pointer;
  display: inline-flex;
  font: inherit;
  font-size: 13px;
  gap: 8px;
  height: 40px;
  padding: 0 10px 0 12px;
  white-space: nowrap;
}

.period-picker__button:hover,
.period-picker--open .period-picker__button {
  border-color: #9486ff;
}

.period-picker--open .period-picker__button {
  box-shadow: 0 0 0 3px rgb(148 134 255 / 30%);
}

.period-picker__button > .v-icon:first-child {
  color: var(--lm-primary);
}

.period-picker__title {
  color: var(--lm-primary-deep);
  font-weight: 700;
}

.period-picker__dates {
  color: var(--lm-muted);
  font-variant-numeric: tabular-nums;
}

.period-picker__chevron {
  color: var(--lm-muted);
  transition: transform 0.15s;
}

.period-picker--open .period-picker__chevron {
  transform: rotate(180deg);
}

.period-picker__popup {
  background: #fff;
  border: 1px solid var(--lm-border);
  border-radius: 14px;
  box-shadow: 0 12px 32px #27206424, var(--lm-shadow);
  display: flex;
  margin-top: 6px;
  position: absolute;
  right: 0;
  top: 100%;
  z-index: 40;
}

.period-picker__presets {
  border-right: 1px solid var(--lm-border);
  display: flex;
  flex-direction: column;
  gap: 2px;
  min-width: 150px;
  padding: 10px;
}

.period-picker__presets button {
  background: none;
  border: 0;
  border-radius: 8px;
  color: var(--lm-foreground);
  cursor: pointer;
  font: inherit;
  font-size: 13px;
  height: 34px;
  padding: 0 10px;
  text-align: left;
}

.period-picker__presets button:hover {
  background: var(--lm-lavender);
}

.period-picker__presets .period-picker__preset--on {
  background: var(--lm-lavender);
  color: var(--lm-primary-deep);
  font-weight: 700;
}

.period-picker__calendar {
  padding: 12px;
}

.period-picker__caption {
  align-items: center;
  display: flex;
  justify-content: space-between;
  margin-bottom: 6px;
}

.period-picker__caption strong {
  font-size: 13px;
  font-weight: 700;
}

.period-picker__caption button {
  align-items: center;
  background: none;
  border: 0;
  border-radius: 8px;
  color: var(--lm-primary);
  cursor: pointer;
  display: flex;
  height: 30px;
  justify-content: center;
  width: 30px;
}

.period-picker__caption button:hover:not(:disabled) {
  background: var(--lm-lavender);
}

.period-picker__caption button:disabled {
  cursor: default;
  opacity: 0.3;
}

.period-picker__weekdays,
.period-picker__days {
  display: grid;
  grid-template-columns: repeat(7, 36px);
  row-gap: 2px;
}

.period-picker__weekdays span {
  color: var(--lm-muted);
  font-size: 12px;
  height: 26px;
  line-height: 26px;
  text-align: center;
}

.period-picker__day {
  background: none;
  border: 0;
  color: var(--lm-foreground);
  cursor: pointer;
  font: inherit;
  font-size: 13px;
  font-variant-numeric: tabular-nums;
  height: 34px;
  padding: 0;
}

.period-picker__day:hover:not(:disabled) {
  border-radius: 8px;
  box-shadow: inset 0 0 0 2px #9486ff;
}

.period-picker__day--outside {
  color: var(--lm-muted);
}

.period-picker__day--today {
  color: var(--lm-primary);
  font-weight: 700;
}

.period-picker__day--inside {
  background: var(--lm-lavender);
}

.period-picker__day--edge {
  background: var(--lm-primary);
  border-radius: 8px;
  color: #fff;
  font-weight: 700;
}

.period-picker__day:disabled {
  cursor: default;
  opacity: 0.3;
}

.period-picker__hint {
  color: var(--lm-muted);
  font-size: 12px;
  margin: 8px 2px 0;
}

@media (max-width: 600px) {
  .period-picker,
  .period-picker__button {
    width: 100%;
  }

  .period-picker__dates {
    flex: 1;
    overflow: hidden;
    text-align: left;
    text-overflow: ellipsis;
  }

  .period-picker__popup {
    flex-direction: column;
    left: 0;
    right: auto;
  }

  .period-picker__presets {
    border-bottom: 1px solid var(--lm-border);
    border-right: 0;
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}
</style>
