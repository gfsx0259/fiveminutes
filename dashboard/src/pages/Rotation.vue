<template>
  <div>
    <div class="page-head">
      <div>
        <h1>Rotation</h1>
        <p>Grouping of presets and their active orders. Each preset keeps its own pool.</p>
      </div>
    </div>

    <p v-if="!isAdmin" class="rotation__hint">Rotation is an admin page.</p>

    <section v-if="isAdmin" class="ui-filter-panel rotation__panel">
      <h2 class="rotation__title">Grouping</h2>
      <p class="rotation__hint">
        Folders follow this order. A lead matches the folder of each field it carries. Payment model is not on the lead, so those folders are tried from the top: CPL, then CPA.
      </p>
      <ol class="rotation__axes">
        <li v-for="(axis, index) in draft" :key="axis">
          <span class="rotation__axis-index">{{ index + 1 }}</span>
          <span class="rotation__axis-copy">
            <span class="rotation__axis-name">{{ rotationAxisLabel(axis) }}</span>
            <span v-if="!rotationAxisOnLead(axis)" class="rotation__axis-note">Not on the lead. Order is automatic: CPL, then CPA.</span>
          </span>
          <span class="rotation__axis-actions">
            <button type="button" :disabled="index === 0" aria-label="Move up" @click="move(index, -1)">
              <v-icon icon="mdi-chevron-up" size="16" />
            </button>
            <button type="button" :disabled="index === draft.length - 1" aria-label="Move down" @click="move(index, 1)">
              <v-icon icon="mdi-chevron-down" size="16" />
            </button>
            <button type="button" :disabled="draft.length < 2" aria-label="Remove" @click="remove(index)">
              <v-icon icon="mdi-close" size="16" />
            </button>
          </span>
        </li>
      </ol>
      <div v-if="unused.length" class="rotation__add">
        <span>Add</span>
        <UiButton
          v-for="axis in unused"
          :key="axis.value"
          variant="outline"
          @click="add(axis.value)"
        >
          {{ axis.title }}
        </UiButton>
      </div>
      <div class="rotation__save">
        <UiButton variant="outline" :loading="saving" :disabled="unchanged" @click="save">Save grouping</UiButton>
      </div>
      <p v-if="error" class="rotation__error">{{ error }}</p>
    </section>

    <section v-if="isAdmin" class="ui-filter-panel rotation__panel">
      <h2 class="rotation__title">Presets and active orders</h2>
      <p v-if="loading" class="rotation__hint">Loading…</p>
      <p v-else-if="nodes.length === 0" class="rotation__hint">No presets yet.</p>
      <RotationBranch v-else :nodes="nodes" :axes="saved" />
    </section>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { api } from '@/api'
import RotationBranch from '@/components/RotationBranch.vue'
import UiButton from '@/components/ui/UiButton.vue'
import { rotationAxisLabel, rotationAxisOnLead, rotationAxisOptions, type RotationNode } from '@/rotation'
import { useSessionStore } from '@/stores/session'

const session = useSessionStore()
const isAdmin = computed(() => session.payload?.user?.role === 'admin')

const draft = ref<string[]>(['vertical', 'country', 'payment_model'])
const saved = ref<string[]>(['vertical', 'country', 'payment_model'])
const nodes = ref<RotationNode[]>([])
const loading = ref(true)
const saving = ref(false)
const error = ref('')

const unused = computed(() => rotationAxisOptions.filter((axis) => !draft.value.includes(axis.value)))
const unchanged = computed(() => draft.value.join('\0') === saved.value.join('\0'))

function move(index: number, delta: number): void {
  const target = index + delta
  if (target < 0 || target >= draft.value.length) {
    return
  }
  const next = draft.value.slice()
  const [item] = next.splice(index, 1)
  if (item === undefined) {
    return
  }
  next.splice(target, 0, item)
  draft.value = next
}

function remove(index: number): void {
  if (draft.value.length < 2) {
    return
  }
  draft.value = draft.value.filter((_, item) => item !== index)
}

function add(axis: string): void {
  if (draft.value.includes(axis)) {
    return
  }
  draft.value = [...draft.value, axis]
}

function apply(body: { axes?: string[]; nodes?: RotationNode[] }): void {
  if (body.axes?.length) {
    saved.value = body.axes.slice()
    draft.value = body.axes.slice()
  }
  nodes.value = body.nodes ?? []
}

async function load(): Promise<void> {
  loading.value = true
  error.value = ''
  try {
    const response = await api('/organization/rotation')
    const body = await response.json() as { error?: string; axes?: string[]; nodes?: RotationNode[] }
    if (!response.ok) {
      error.value = body.error ?? 'Could not load the rotation'
      return
    }
    apply(body)
  } catch {
    error.value = 'Could not load the rotation'
  } finally {
    loading.value = false
  }
}

async function save(): Promise<void> {
  saving.value = true
  error.value = ''
  try {
    const response = await api('/organization/rotation', {
      method: 'POST',
      body: JSON.stringify({ axes: draft.value }),
    })
    const body = await response.json() as { error?: string; axes?: string[]; nodes?: RotationNode[] }
    if (!response.ok) {
      error.value = body.error ?? 'Could not save grouping'
      return
    }
    apply(body)
  } catch {
    error.value = 'Could not save grouping'
  } finally {
    saving.value = false
  }
}

onMounted(() => {
  if (isAdmin.value) {
    void load()
  } else {
    loading.value = false
  }
})
</script>

<style scoped>
.rotation__panel {
  margin-bottom: 16px;
}

.rotation__title {
  color: #101024;
  font-size: var(--lm-font-section);
  font-weight: 700;
  letter-spacing: -0.5px;
  margin: 0 0 8px;
}

.rotation__hint {
  color: var(--lm-muted);
  font-size: 13px;
  margin: 0 0 16px;
}

.rotation__axes {
  display: flex;
  flex-direction: column;
  gap: 8px;
  list-style: none;
  margin: 0 0 16px;
  padding: 0;
}

.rotation__axes li {
  align-items: flex-start;
  display: flex;
  gap: 10px;
  max-width: 36rem;
}

.rotation__axis-index {
  color: var(--lm-muted);
  font-size: 12px;
  font-weight: 700;
  padding-top: 3px;
  width: 1rem;
}

.rotation__axis-copy {
  display: flex;
  flex: 1;
  flex-direction: column;
  gap: 2px;
  min-width: 0;
}

.rotation__axis-name {
  font-size: 14px;
  font-weight: 650;
}

.rotation__axis-note {
  color: var(--lm-muted);
  font-size: 12px;
  font-weight: 500;
  line-height: 1.35;
}

.rotation__axis-actions {
  display: flex;
  gap: 4px;
}

.rotation__axis-actions button {
  align-items: center;
  appearance: none;
  background: transparent;
  border: 0;
  border-radius: 6px;
  color: var(--lm-primary-deep);
  cursor: pointer;
  display: flex;
  height: 28px;
  justify-content: center;
  width: 28px;
}

.rotation__axis-actions button:hover:not(:disabled) {
  background: var(--lm-lavender);
}

.rotation__axis-actions button:disabled {
  cursor: default;
  opacity: 0.35;
}

.rotation__add,
.rotation__save {
  align-items: center;
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}

.rotation__add {
  margin-bottom: 16px;
}

.rotation__add > span {
  color: var(--lm-muted);
  font-size: 13px;
  margin-right: 4px;
}

.rotation__error {
  color: rgb(var(--v-theme-error));
  font-size: 13px;
  margin: 8px 0 0;
}
</style>
