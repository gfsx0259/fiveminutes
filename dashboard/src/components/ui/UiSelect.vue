<template>
  <v-menu
    v-model="open"
    :close-on-content-click="!multiple && !searchable"
    content-class="ui-select-menu"
    location="bottom start"
    :min-width="Math.max(menuWidth, menuMinWidth)"
    offset="6"
  >
    <template #activator="{ props: menuProps }">
      <button
        v-bind="{ ...attrs, ...menuProps }"
        type="button"
        class="ui-select"
        :class="{
          'ui-select--conceal-icon': concealFieldIcon && hasValue,
          'ui-select--multiple': multiple,
        }"
        :disabled="disabled"
        @mousedown="measure"
        @paste="emit('paste', $event)"
      >
        <span class="ui-select__value" :class="{ 'ui-select__value--placeholder': !hasValue }">
          <slot name="value" :items="chosen">
            <span class="ui-select__text">{{ hasValue ? summary : placeholder }}</span>
          </slot>
        </span>
        <v-progress-circular v-if="loading" indeterminate size="16" width="2" />
        <span
          v-else-if="clearable && hasValue"
          class="ui-select__clear"
          role="button"
          tabindex="-1"
          aria-label="Clear"
          @mousedown.stop
          @click.stop="clear"
        >
          <v-icon icon="mdi-close" size="16" />
        </span>
        <v-icon class="ui-select__chevron" icon="mdi-chevron-down" size="18" />
      </button>
    </template>

    <div class="ui-select__menu" :style="menuStyle">
      <input
        v-if="searchable"
        ref="searchEl"
        v-model="query"
        class="ui-select__search"
        type="text"
        :placeholder="searchPlaceholder"
        :aria-label="searchPlaceholder"
        @mousedown.stop
        @paste="emit('paste', $event)"
        @keydown.enter.prevent
      >
      <div class="ui-select__options" role="listbox" :aria-multiselectable="multiple || undefined">
        <button
          v-if="emptyLabel"
          type="button"
          class="ui-select__option"
          :class="{ 'ui-select__option--selected': !hasValue }"
          role="option"
          :aria-selected="!hasValue"
          @click="clearAll"
        >
          <slot name="empty">
            <span>{{ emptyLabel }}</span>
          </slot>
          <v-icon v-if="!hasValue" class="ui-select__check" icon="mdi-check" size="16" />
        </button>
        <button
          v-for="item in visible"
          :key="String(item.value)"
          type="button"
          class="ui-select__option"
          :class="{ 'ui-select__option--selected': selected(item.value) }"
          role="option"
          :aria-selected="selected(item.value)"
          :disabled="item.disabled"
          @click="choose(item.value)"
        >
          <slot name="option" :item="item">
            <span>{{ item.title }}</span>
          </slot>
          <v-icon v-if="selected(item.value)" class="ui-select__check" icon="mdi-check" size="16" />
        </button>
        <p v-if="visible.length === 0" class="ui-select__empty">No options</p>
      </div>
    </div>
  </v-menu>
</template>

<script setup lang="ts">
import { computed, nextTick, ref, useAttrs, watch } from 'vue'

defineOptions({ inheritAttrs: false })

type SelectValue = string | number

const props = withDefaults(defineProps<{
  items?: readonly unknown[]
  multiple?: boolean
  placeholder?: string
  searchable?: boolean
  searchPlaceholder?: string
  emptyLabel?: string
  menuMinWidth?: number
  clearable?: boolean
  /** Hide the parent field icon once a value is selected. */
  concealFieldIcon?: boolean
  disabled?: boolean
  loading?: boolean
  itemTitle?: string
  itemValue?: string
}>(), {
  items: () => [],
  multiple: false,
  placeholder: '',
  searchable: false,
  searchPlaceholder: 'Search',
  emptyLabel: '',
  menuMinWidth: 0,
  clearable: false,
  concealFieldIcon: false,
  disabled: false,
  loading: false,
  itemTitle: 'title',
  itemValue: 'value',
})

const model = defineModel<any>({ default: null })
const emit = defineEmits<{
  paste: [event: ClipboardEvent]
}>()
const attrs = useAttrs()

const open = ref(false)
const query = ref('')
const menuWidth = ref(0)
const searchEl = ref<HTMLInputElement | null>(null)

const options = computed(() => props.items.map((item) => {
  if (item === null || typeof item !== 'object') {
    return { title: String(item), value: item as SelectValue, disabled: false }
  }
  const record = item as Record<string, unknown>
  const value = record[props.itemValue]
  return {
    title: String(record[props.itemTitle] ?? value ?? ''),
    value: (typeof value === 'number' ? value : String(value ?? '')) as SelectValue,
    disabled: Boolean(record.disabled),
  }
}))

const same = (left: unknown, right: unknown) =>
  left === right || (left != null && right != null && String(left) === String(right))

const selected = (value: SelectValue) => {
  if (Array.isArray(model.value)) return model.value.some(item => same(item, value))
  return same(model.value, value)
}

const chosen = computed(() => options.value.filter(item => selected(item.value)))
const hasValue = computed(() => chosen.value.length > 0)
const summary = computed(() => chosen.value.map(item => item.title).join(', '))
const menuStyle = computed(() => {
  const width = Math.max(menuWidth.value, props.menuMinWidth)
  return width > 0 ? { width: `${width}px` } : undefined
})
const visible = computed(() => {
  const needle = query.value.trim().toLowerCase()
  if (!needle) return options.value
  return options.value.filter(item =>
    item.title.toLowerCase().includes(needle) || String(item.value).toLowerCase().includes(needle),
  )
})

watch(open, async (value) => {
  if (!value) {
    query.value = ''
    return
  }
  if (!props.searchable) return
  await nextTick()
  searchEl.value?.focus()
})

const measure = (event: MouseEvent) => {
  menuWidth.value = (event.currentTarget as HTMLElement).offsetWidth
}

const choose = (value: SelectValue) => {
  if (!props.multiple) {
    model.value = value
    open.value = false
    return
  }
  const current = Array.isArray(model.value) ? [...model.value] : []
  const index = current.findIndex(item => same(item, value))
  if (index >= 0) current.splice(index, 1)
  else current.push(value)
  model.value = current
}

const clear = () => {
  model.value = props.multiple ? [] : null
}

const clearAll = () => {
  clear()
  open.value = false
}
</script>

<style scoped>
.ui-select {
  align-items: center;
  background: #fff;
  border: 1px solid #cdd0e6;
  border-radius: 9px;
  box-shadow: 0 2px 3px #22225504;
  color: var(--lm-foreground);
  cursor: pointer;
  display: flex;
  font: inherit;
  font-size: 13px;
  gap: 6px;
  height: var(--lm-control-height);
  min-width: 0;
  padding: 0 10px 0 12px;
  text-align: left;
  width: 100%;
}

.ui-select--multiple {
  height: auto;
  min-height: var(--lm-control-height);
  padding-block: 5px;
}

.ui-select--multiple .ui-select__value {
  flex-wrap: wrap;
  gap: 6px;
  row-gap: 4px;
}

.ui-select:disabled {
  cursor: not-allowed;
  opacity: 0.52;
}

.ui-select[aria-expanded='true'],
.ui-select:focus-visible {
  border-color: #9486ff;
  outline: none;
}

.ui-select__value {
  align-items: center;
  display: flex;
  flex: 1;
  min-width: 0;
  overflow: hidden;
}

.ui-select__text {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.ui-select__value--placeholder {
  color: var(--lm-muted);
  opacity: 0.8;
}

.ui-select__chevron,
.ui-select__clear {
  color: #343b69;
  flex: 0 0 auto;
}

.ui-select__clear {
  align-items: center;
  display: flex;
}

.ui-select__menu {
  background: #fff;
  border: 1px solid var(--lm-border);
  border-radius: 12px;
  box-shadow: 0 8px 24px #27206414, var(--lm-shadow);
  display: flex;
  flex-direction: column;
  max-height: 320px;
  padding: 6px;
}

.ui-select__search {
  background: #fff;
  border: 1px solid #cdd0e6;
  border-radius: 9px;
  color: var(--lm-foreground);
  flex: 0 0 auto;
  font: inherit;
  font-size: 13px;
  height: 36px;
  margin-bottom: 6px;
  padding: 0 12px;
  width: 100%;
}

.ui-select__search:focus {
  border-color: #9486ff;
  outline: none;
}

.ui-select__options {
  display: flex;
  flex-direction: column;
  gap: 3px;
  min-height: 0;
  overflow: auto;
  scrollbar-color: #c7bffc transparent;
  scrollbar-width: thin;
}

.ui-select__option {
  align-items: center;
  appearance: none;
  background: transparent;
  border: 0;
  border-radius: 8px;
  color: var(--lm-foreground);
  cursor: pointer;
  display: flex;
  font: inherit;
  font-size: 13px;
  font-weight: 400;
  gap: 9px;
  line-height: 20px;
  min-height: 36px;
  padding: 7px 9px;
  text-align: left;
  transition: background-color 160ms ease-out, color 160ms ease-out;
  width: 100%;
}

.ui-select__option > span,
.ui-select__option :slotted(span) {
  flex: 1;
  min-width: 0;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.ui-select__check {
  color: var(--lm-primary);
  flex: 0 0 auto;
  margin-left: auto;
}

.ui-select__option:hover,
.ui-select__option:focus-visible {
  background: var(--lm-lavender);
  color: var(--lm-primary-deep);
  outline: none;
}

.ui-select__option--selected {
  background: var(--lm-lavender);
  color: var(--lm-primary-deep);
  font-weight: 600;
}

.ui-select__option:disabled {
  cursor: not-allowed;
  opacity: 0.4;
}

.ui-select__empty {
  color: var(--lm-muted);
  font-size: 13px;
  margin: 0;
  padding: 12px 9px;
}
</style>

<style>
.ui-select-menu.v-overlay__content {
  background: transparent;
  box-shadow: none;
}
</style>
