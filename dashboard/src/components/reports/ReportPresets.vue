<template>
  <div class="report-presets">
    <v-menu v-model="open" :close-on-content-click="false" location="bottom start" offset="6">
      <template #activator="{ props: menuProps }">
        <button v-bind="menuProps" type="button" class="report-presets__trigger" :class="{ 'report-presets__trigger--open': open }">
          <v-icon icon="mdi-bookmark-outline" size="18" class="report-presets__trigger-icon" />
          <span v-if="activePreset" class="report-presets__trigger-text">{{ activePreset.label }}</span>
          <span v-else class="report-presets__trigger-text report-presets__trigger-text--empty">Choose preset</span>
          <span v-if="dirty" class="report-presets__edited" title="Grouping, conditions or columns differ from the saved preset">edited</span>
          <v-icon icon="mdi-chevron-down" size="18" class="report-presets__chevron" />
        </button>
      </template>

      <div class="report-presets__menu">
        <section v-for="section in sections" :key="section.title">
          <p class="report-presets__section">{{ section.title }}</p>
          <div
            v-for="preset in section.items"
            :key="preset.key"
            class="report-presets__item"
            :class="{ 'report-presets__item--on': preset.key === active }"
          >
            <button type="button" class="report-presets__apply" @click="pick(preset)">
              <span class="report-presets__name">{{ preset.label }}</span>
              <small>{{ describe(preset) }}</small>
            </button>
            <button type="button" class="report-presets__icon" :aria-label="`Edit ${preset.label}`" title="Edit" @click="openEdit(preset)">
              <v-icon icon="mdi-pencil-outline" size="16" />
            </button>
            <button type="button" class="report-presets__icon report-presets__icon--remove" :aria-label="`Delete ${preset.label}`" title="Delete" @click="remove(preset.key)">
              <v-icon icon="mdi-close" size="16" />
            </button>
          </div>
          <p v-if="section.items.length === 0" class="report-presets__empty">{{ section.empty }}</p>
        </section>
        <footer v-if="changed" class="report-presets__footer">
          <UiButton variant="ghost" icon="mdi-restore" class="report-presets__restore" @click="restore">Restore standard presets</UiButton>
        </footer>
      </div>
    </v-menu>

    <button
      type="button"
      class="report-presets__save"
      :class="{ 'report-presets__save--dirty': dirty }"
      :aria-label="activePreset ? `Save to ${activePreset.label}` : 'Save as preset'"
      :title="activePreset ? 'Save changes or save as a new preset' : 'Save grouping, conditions and columns as a preset'"
      @click="openSave"
    >
      <v-icon icon="mdi-content-save-outline" size="20" />
    </button>

    <FormDialog
      v-model="dialog"
      :title="dialogTitle"
      :error="error"
      :submit-label="submitLabel"
      submit-icon="mdi-content-save-outline"
      :max-width="760"
      @submit="save"
    >
      <UiField label="Name">
        <UiInput v-model="draft.label" placeholder="For example, Countries by source" maxlength="40" />
      </UiField>
      <UiField label="Group by">
        <GroupLevels v-model="draft.groups" />
      </UiField>
      <div class="report-presets__saved">
        <span class="report-presets__saved-title">Conditions</span>
        <span v-if="draft.conditions.length === 0">No conditions, all your leads are counted</span>
        <span v-for="(condition, index) in draft.conditions" :key="index" class="report-presets__condition">
          {{ conditionText(condition) }}
        </span>
        <span class="report-presets__saved-title">Columns</span>
        <span>{{ draft.columns.length }} of {{ metrics.length }} shown</span>
        <button v-if="editing && mode === 'edit'" type="button" @click="takeCurrent">Use the current conditions and columns</button>
      </div>
    </FormDialog>
  </div>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import FormDialog from '@/components/FormDialog.vue'
import GroupLevels from '@/components/reports/GroupLevels.vue'
import UiButton from '@/components/ui/UiButton.vue'
import UiField from '@/components/ui/UiField.vue'
import UiInput from '@/components/ui/UiInput.vue'
import { defaultPresets, dimensionLabel, metrics, nameOf, type PresetState, type ReportCondition, type ReportDimension, type ReportPreset } from '@/reportFields'

const STORAGE_KEY = 'rider:reports:presets'
const ACTIVE_KEY = 'rider:reports:active-preset'

const props = defineProps<{
  groups: ReportDimension[]
  conditions: ReportCondition[]
  columns: string[]
}>()

const emit = defineEmits<{
  apply: [preset: PresetState]
}>()

const list = ref<ReportPreset[]>(load())
const active = ref(stored(ACTIVE_KEY))
const open = ref(false)
const dialog = ref(false)
const mode = ref<'save' | 'edit'>('save')
const editing = ref('')
const error = ref('')
const draft = ref<{ label: string, groups: ReportDimension[], conditions: ReportCondition[], columns: string[] }>({ label: '', groups: [], conditions: [], columns: [] })

const activePreset = computed(() => list.value.find((preset) => preset.key === active.value))

const dirty = computed(() => {
  const preset = activePreset.value
  if (!preset) return false
  if (preset.groups.join('>') !== props.groups.join('>')) return true
  if (signature(preset.conditions ?? []) !== signature(props.conditions)) return true
  return Boolean(preset.columns) && [...(preset.columns ?? [])].sort().join() !== [...props.columns].sort().join()
})

const sections = computed(() => [
  { title: 'Standard', empty: 'All standard presets are deleted', items: list.value.filter((preset) => isDefault(preset.key)) },
  { title: 'My presets', empty: 'Set up grouping and conditions, then press the save icon', items: list.value.filter((preset) => !isDefault(preset.key)) },
])

const changed = computed(() => JSON.stringify(list.value.filter((preset) => isDefault(preset.key))) !== JSON.stringify(defaults()))

const updating = computed(() => mode.value === 'edit'
  || (Boolean(editing.value) && draft.value.label.trim().toLowerCase() === activePreset.value?.label.toLowerCase()))

const dialogTitle = computed(() => (mode.value === 'edit' ? 'Edit preset' : 'Save preset'))
const submitLabel = computed(() => (updating.value ? 'Save changes' : 'Save as new preset'))

watch(list, (value) => keep(STORAGE_KEY, JSON.stringify(value)), { deep: true })
watch(active, (value) => keep(ACTIVE_KEY, value))

function keep(key: string, value: string): void {
  try {
    localStorage.setItem(key, value)
  } catch {
    return
  }
}

function stored(key: string): string {
  try {
    return localStorage.getItem(key) ?? ''
  } catch {
    return ''
  }
}

function defaults(): ReportPreset[] {
  return defaultPresets.map((preset) => ({ ...preset, groups: [...preset.groups], conditions: [] }))
}

function load(): ReportPreset[] {
  try {
    const saved = JSON.parse(stored(STORAGE_KEY) || 'null') as unknown
    if (Array.isArray(saved) && saved.every(valid)) return saved as ReportPreset[]
  } catch {
    return defaults()
  }
  return defaults()
}

function valid(value: unknown): boolean {
  const preset = value as Partial<ReportPreset> | null
  return Boolean(preset) && typeof preset?.key === 'string' && typeof preset.label === 'string'
    && Array.isArray(preset.groups) && preset.groups.length > 0
    && preset.groups.every((group) => typeof group === 'string' && dimensionLabel(group) !== group)
}

function signature(conditions: ReportCondition[]): string {
  return JSON.stringify(conditions.filter((condition) => condition.values.length > 0)
    .map((condition) => [condition.field, condition.operator, [...condition.values].sort()]))
}

function clone(conditions: ReportCondition[]): ReportCondition[] {
  return conditions.map((condition) => ({ ...condition, values: [...condition.values] }))
}

function isDefault(key: string): boolean {
  return defaultPresets.some((preset) => preset.key === key)
}

function path(groups: ReportDimension[]): string {
  return groups.map(dimensionLabel).join(' → ')
}

function describe(preset: ReportPreset): string {
  const conditions = (preset.conditions ?? []).filter((condition) => condition.values.length > 0).length
  return conditions ? `${path(preset.groups)} · ${conditions} ${conditions === 1 ? 'condition' : 'conditions'}` : path(preset.groups)
}

function conditionText(condition: ReportCondition): string {
  const values = condition.values.length ? condition.values.map((value) => nameOf(condition.field, value)).join(', ') : 'any value'
  return `${dimensionLabel(condition.field)} ${condition.operator === 'in' ? 'is' : 'is not'} ${values}`
}

function stateOf(preset: ReportPreset): PresetState {
  return {
    groups: [...preset.groups],
    conditions: clone(preset.conditions ?? []),
    columns: preset.columns ? [...preset.columns] : undefined,
  }
}

function pick(preset: ReportPreset): void {
  active.value = preset.key
  emit('apply', stateOf(preset))
  open.value = false
}

function restore(): void {
  const own = list.value.filter((preset) => !isDefault(preset.key))
  list.value = [...defaults(), ...own]
}

function openSave(): void {
  mode.value = 'save'
  editing.value = activePreset.value?.key ?? ''
  error.value = ''
  draft.value = {
    label: activePreset.value?.label ?? path(props.groups),
    groups: [...props.groups],
    conditions: clone(props.conditions),
    columns: [...props.columns],
  }
  dialog.value = true
}

function openEdit(preset: ReportPreset): void {
  open.value = false
  mode.value = 'edit'
  editing.value = preset.key
  error.value = ''
  draft.value = {
    label: preset.label,
    groups: [...preset.groups],
    conditions: clone(preset.conditions ?? []),
    columns: preset.columns ? [...preset.columns] : [...props.columns],
  }
  dialog.value = true
}

function takeCurrent(): void {
  draft.value = { ...draft.value, conditions: clone(props.conditions), columns: [...props.columns] }
}

function save(): void {
  const label = draft.value.label.trim()
  if (!label) {
    error.value = 'Give the preset a name'
    return
  }
  const key = updating.value ? editing.value : `own-${Date.now().toString(36)}`
  if (list.value.some((preset) => preset.key !== key && preset.label.toLowerCase() === label.toLowerCase())) {
    error.value = 'A preset with this name already exists'
    return
  }
  const saved: ReportPreset = {
    key,
    label,
    groups: [...draft.value.groups],
    conditions: clone(draft.value.conditions),
    columns: [...draft.value.columns],
  }
  list.value = list.value.some((preset) => preset.key === key)
    ? list.value.map((preset) => (preset.key === key ? saved : preset))
    : [...list.value, saved]
  dialog.value = false
  if (mode.value === 'save' || key === active.value) {
    active.value = key
    emit('apply', stateOf(saved))
  }
}

function remove(key: string): void {
  list.value = list.value.filter((preset) => preset.key !== key)
  if (active.value === key) active.value = ''
}
</script>

<style scoped>
.report-presets {
  align-items: center;
  display: flex;
  gap: 6px;
}

.report-presets__trigger {
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
  gap: 8px;
  height: var(--lm-control-height);
  max-width: 100%;
  min-width: 260px;
  padding: 0 10px 0 12px;
}

.report-presets__trigger:hover,
.report-presets__trigger--open {
  border-color: #9486ff;
}

.report-presets__trigger--open {
  box-shadow: 0 0 0 3px rgb(148 134 255 / 30%);
}

.report-presets__trigger-icon {
  color: var(--lm-primary);
}

.report-presets__trigger-text {
  flex: 1;
  font-weight: 600;
  overflow: hidden;
  text-align: left;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.report-presets__trigger-text--empty {
  color: var(--lm-muted);
  font-weight: 400;
}

.report-presets__edited {
  background: #fff4dc;
  border-radius: 999px;
  color: #9a6400;
  font-size: 11px;
  font-weight: 700;
  padding: 2px 8px;
}

.report-presets__chevron {
  color: var(--lm-muted);
}

.report-presets__menu {
  background: #fff;
  border: 1px solid var(--lm-border);
  border-radius: 12px;
  box-shadow: 0 12px 32px #27206424, var(--lm-shadow);
  max-height: 440px;
  min-width: 340px;
  overflow: auto;
  padding: 6px;
}

.report-presets__section {
  color: var(--lm-muted);
  font-size: 11px;
  font-weight: 700;
  letter-spacing: 0.3px;
  margin: 6px 8px 4px;
  text-transform: uppercase;
}

.report-presets__item {
  align-items: center;
  border-radius: 8px;
  display: flex;
  gap: 2px;
  padding-right: 4px;
}

.report-presets__item:hover {
  background: var(--lm-lavender);
}

.report-presets__item--on {
  background: var(--lm-lavender);
}

.report-presets__apply {
  background: none;
  border: 0;
  color: var(--lm-foreground);
  cursor: pointer;
  display: flex;
  flex: 1;
  flex-direction: column;
  font: inherit;
  gap: 1px;
  min-width: 0;
  padding: 7px 10px;
  text-align: left;
}

.report-presets__name {
  font-size: 13px;
  font-weight: 600;
}

.report-presets__item--on .report-presets__name {
  color: var(--lm-primary-deep);
}

.report-presets__apply small {
  color: var(--lm-muted);
  font-size: 12px;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.report-presets__icon {
  align-items: center;
  background: none;
  border: 0;
  border-radius: 7px;
  color: var(--lm-muted);
  cursor: pointer;
  display: inline-flex;
  flex: 0 0 30px;
  height: 30px;
  justify-content: center;
  opacity: 0;
  transition: opacity 0.12s;
}

.report-presets__item:hover .report-presets__icon,
.report-presets__icon:focus-visible {
  opacity: 1;
}

.report-presets__icon:hover {
  background: #fff;
  color: var(--lm-primary-deep);
}

.report-presets__icon--remove:hover {
  color: #c2405f;
}

.report-presets__empty {
  color: var(--lm-muted);
  font-size: 12px;
  margin: 2px 10px 8px;
}

.report-presets__footer {
  border-top: 1px solid var(--lm-border);
  display: flex;
  flex-wrap: wrap;
  gap: 4px;
  margin-top: 6px;
  padding-top: 6px;
}

.report-presets__restore {
  color: var(--lm-muted) !important;
}

.report-presets__save {
  align-items: center;
  background: #fff;
  border: 1px solid #9685ff;
  border-radius: 10px;
  color: #3512bc;
  cursor: pointer;
  display: inline-flex;
  flex: 0 0 auto;
  height: var(--lm-control-height);
  justify-content: center;
  position: relative;
  width: var(--lm-control-height);
}

.report-presets__save:hover {
  background: #efecff;
}

.report-presets__save--dirty::after {
  background: #f0a020;
  border: 2px solid #fff;
  border-radius: 50%;
  content: '';
  height: 10px;
  position: absolute;
  right: -3px;
  top: -3px;
  width: 10px;
}

.report-presets__saved {
  align-items: baseline;
  background: #faf9ff;
  border: 1px solid var(--lm-border);
  border-radius: 10px;
  color: var(--lm-foreground);
  display: flex;
  flex-direction: column;
  font-size: 13px;
  gap: 4px;
  margin: 4px 0 0;
  padding: 10px 12px;
}

.report-presets__saved-title {
  color: var(--lm-muted);
  font-size: 11px;
  font-weight: 700;
  margin-top: 4px;
  text-transform: uppercase;
}

.report-presets__condition {
  background: #fff;
  border: 1px solid var(--lm-border);
  border-radius: 8px;
  padding: 3px 8px;
}

.report-presets__saved button {
  background: none;
  border: 0;
  color: var(--lm-primary-deep);
  cursor: pointer;
  font: inherit;
  font-weight: 600;
  padding: 0;
  text-decoration: underline;
}

@media (hover: none) {
  .report-presets__icon {
    opacity: 1;
  }
}

@media (max-width: 767px) {
  .report-presets {
    width: 100%;
  }

  .report-presets__trigger {
    flex: 1;
    min-width: 0;
  }

  .report-presets__menu {
    min-width: 0;
    width: calc(100vw - 32px);
  }
}
</style>
