<template>
  <div class="offer-integration">
    <h3>Integration (Lead Delivery)</h3>
    <p class="offer-integration__intro">
      Deliver leads for this offer with your API key. The key is shared by all your offers; leads are matched to an offer by country, traffic source and age.
    </p>

    <div class="offer-integration__tabs" role="tablist" aria-label="Lead delivery method">
      <button
        v-for="(item, index) in methods"
        :id="`offer-method-${item.id}`"
        :key="item.id"
        type="button"
        role="tab"
        :aria-selected="method === item.id"
        :aria-controls="`offer-panel-${item.id}`"
        :tabindex="method === item.id ? 0 : -1"
        @click="method = item.id"
        @keydown="onTabKey($event, index)"
      >
        <v-icon :icon="item.icon" size="24" />
        <span>{{ item.title }}</span>
        <small>{{ item.hint }}</small>
      </button>
    </div>

    <div
      :id="`offer-panel-${method}`"
      class="offer-integration__panel"
      role="tabpanel"
      :aria-labelledby="`offer-method-${method}`"
    >
      <div v-if="!apiKey" class="offer-integration__key-missing">
        <p>You have no API key yet. Generate one to deliver leads.</p>
        <UiButton :loading="busy" icon="mdi-key-outline" @click="generate">Generate API key</UiButton>
      </div>

      <template v-if="method === 'api'">
        <div class="offer-field">
          <span>Endpoint</span>
          <div class="offer-copy">
            <input aria-label="Endpoint" readonly :value="endpoint">
            <button type="button" aria-label="Copy endpoint" @click="copy(endpoint)"><v-icon icon="mdi-content-copy" size="16" /></button>
          </div>
        </div>
        <div class="offer-method-row">Method <span class="offer-badge">POST</span></div>
        <div class="offer-field">
          <span>Authorization</span>
          <div class="offer-copy">
            <input aria-label="Authorization" readonly :value="apiKey ? `Bearer ${maskedKey}` : ''" placeholder="Generate an API key first">
            <button type="button" aria-label="Copy authorization" :disabled="!apiKey" @click="copy(`Bearer ${apiKey}`)"><v-icon icon="mdi-content-copy" size="16" /></button>
          </div>
        </div>
        <div class="offer-field">
          <span>cURL</span>
          <div class="offer-copy">
            <input aria-label="cURL" readonly :value="curlMasked">
            <button type="button" aria-label="Copy cURL" :disabled="!apiKey" @click="copy(curl)"><v-icon icon="mdi-content-copy" size="16" /></button>
          </div>
        </div>

        <details class="offer-json">
          <summary>
            Request Example (JSON)
            <v-icon icon="mdi-chevron-down" size="18" />
          </summary>
          <textarea v-model="body" aria-label="Request body" spellcheck="false" />
          <div class="offer-json__actions">
            <UiButton variant="outline" @click="resetBody">Reset</UiButton>
            <UiButton :loading="sending" :disabled="!apiKey" icon="mdi-send-outline" @click="sendTest">Send test lead</UiButton>
          </div>
        </details>
      </template>

      <template v-else-if="method === 'postback'">
        <div class="offer-field">
          <span>Postback URL</span>
          <div class="offer-copy">
            <input aria-label="Postback URL" readonly :value="endpoint">
            <button type="button" aria-label="Copy postback URL" @click="copy(endpoint)"><v-icon icon="mdi-content-copy" size="16" /></button>
          </div>
        </div>
        <div class="offer-method-row">HTTP Method <span class="offer-badge">GET</span></div>
        <div class="offer-field">
          <span>Example parameters</span>
          <div class="offer-copy">
            <input aria-label="Example parameters" readonly :value="apiKey ? parametersMasked : ''" placeholder="Generate an API key first">
            <button type="button" aria-label="Copy example parameters" :disabled="!apiKey" @click="copy(parameters)"><v-icon icon="mdi-content-copy" size="16" /></button>
          </div>
        </div>
        <p class="offer-note">
          Append the parameters to the URL in your tracker and replace the sample values with its macros. The key can also be sent as a Bearer header. Encode a leading <code>+</code> in the phone as <code>%2B</code>.
        </p>
      </template>

      <template v-else>
        <p class="offer-note offer-note--lead">
          Upload a UTF-8 CSV file with a header row and one lead per row (up to {{ csvMaxRows }} leads, 1 MB).
        </p>
        <div class="offer-csv-columns">
          <span>Columns</span>
          <code>{{ csvHeader }}</code>
        </div>
        <button type="button" class="offer-link" @click="downloadTemplate">
          <v-icon icon="mdi-download-outline" size="16" />
          Download CSV template
        </button>
        <form class="offer-csv" @submit.prevent="uploadCsv">
          <label for="offer-csv-file">CSV file</label>
          <input
            id="offer-csv-file"
            ref="fileInput"
            type="file"
            accept=".csv,text/csv"
            :aria-invalid="csvError !== ''"
            @change="onFile"
          >
          <p class="offer-note" aria-live="polite" :class="{ 'offer-note--error': csvError }">
            {{ csvError || (csvFile ? `Selected: ${csvFile.name}` : 'No file selected.') }}
          </p>
          <UiButton type="submit" :loading="uploading" :disabled="!csvFile || !apiKey" icon="mdi-send-outline">Send</UiButton>
        </form>
        <div v-if="csvResult" class="offer-csv-result" aria-live="polite">
          <strong>{{ csvResult.accepted }} accepted, {{ csvResult.rejected.length }} rejected</strong>
          <ul v-if="csvResult.rejected.length > 0">
            <li v-for="item in csvResult.rejected.slice(0, 5)" :key="item.line">
              Line {{ item.line }}: {{ Object.values(item.errors).join('; ') }}
            </li>
          </ul>
          <small v-if="csvResult.rejected.length > 5">…and {{ csvResult.rejected.length - 5 }} more.</small>
        </div>
      </template>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import UiButton from '@/components/ui/UiButton.vue'
import { useBuyerApiKey } from '@/composables/useBuyerApiKey'
import { showErrorToast, showToast } from '@/composables/useToast'
import { copyText } from '@/copyText'
import { apiOrigin } from '@/host'

const props = defineProps<{
  geo: string
  trafficSource: string
  age: string
  gender: string
  vertical: string
}>()

type Method = 'api' | 'postback' | 'csv'
const methods: { id: Method; title: string; icon: string; hint: string }[] = [
  { id: 'api', title: 'API', icon: 'mdi-code-json', hint: '(Recommended)' },
  { id: 'postback', title: 'Postback', icon: 'mdi-link-variant', hint: '\u00a0' },
  { id: 'csv', title: 'CSV', icon: 'mdi-file-delimited-outline', hint: '\u00a0' },
]
const method = ref<Method>('api')

const { apiKey, busy, load, generate } = useBuyerApiKey()
onMounted(() => { void load() })

const csvMaxRows = 1000
const endpoint = computed(() => `${apiOrigin(window.location.hostname)}/v1/lead`)
const csvEndpoint = computed(() => `${apiOrigin(window.location.hostname)}/v1/leads/csv`)
const maskedKey = computed(() => (apiKey.value ? `${apiKey.value.slice(0, 3)}${'•'.repeat(16)}` : ''))

/** Empty, *, any, and all are the open preset value: the lead field must be absent to match it. */
function isOpen(value: string): boolean {
  const normalized = value.trim().toLowerCase()
  return normalized === '' || normalized === '*' || normalized === 'all' || normalized === 'any'
}

/** A single age inside the offer's bracket ("25-34" → 25). An open age stays off the lead. */
function sampleAge(age: string): string {
  if (isOpen(age)) return ''
  const first = age.match(/\d+/)
  return first ? first[0] : ''
}

const sample = computed(() => {
  const lead: Record<string, string> = {
    phone: '+4915112345678',
    email: 'ada@example.com',
    first_name: 'Ada',
    last_name: 'Lovelace',
    country_code: props.geo || 'DE',
    ip: '192.0.2.10',
    aff_sub: props.vertical ? `aff-${props.vertical}` : 'demo',
    traffic_source: props.trafficSource || 'facebook',
  }
  const age = sampleAge(props.age)
  if (age !== '') lead.age = age
  if (props.gender && !isOpen(props.gender)) {
    lead.gender = props.gender
  }
  return lead
})

const body = ref('')
watch(sample, (value) => { body.value = JSON.stringify(value, null, 2) }, { immediate: true })

function resetBody(): void {
  body.value = JSON.stringify(sample.value, null, 2)
}

function curlWith(key: string): string {
  return `curl -X POST ${endpoint.value} -H 'Authorization: Bearer ${key}' -H 'Content-Type: application/json' -d '${JSON.stringify(sample.value)}'`
}
const curl = computed(() => curlWith(apiKey.value))
const curlMasked = computed(() => (apiKey.value ? curlWith(maskedKey.value) : ''))

function query(key: string): string {
  return `api_key=${key}&${new URLSearchParams(sample.value).toString()}`
}
const parameters = computed(() => query(apiKey.value))
const parametersMasked = computed(() => query(maskedKey.value))

async function copy(value: string): Promise<void> {
  if (!value) return
  showToast((await copyText(value)) ? 'Copied' : 'Copy unavailable')
}

function onTabKey(event: KeyboardEvent, index: number): void {
  const last = methods.length - 1
  const next = event.key === 'ArrowRight' ? (index + 1) % methods.length
    : event.key === 'ArrowLeft' ? (index + last) % methods.length
      : event.key === 'Home' ? 0
        : event.key === 'End' ? last : -1
  if (next < 0) return
  event.preventDefault()
  method.value = methods[next].id
  document.getElementById(`offer-method-${methods[next].id}`)?.focus()
}

function failure(decoded: unknown, status: number): string {
  if (decoded && typeof decoded === 'object') {
    const record = decoded as { error?: unknown; errors?: Record<string, unknown> }
    if (record.errors) return Object.values(record.errors).map(String).join(' ')
    if (typeof record.error === 'string' && record.error !== '') return record.error
  }
  return `The lead was not accepted: ${status}`
}

const sending = ref(false)

async function sendTest(): Promise<void> {
  let payload: unknown
  try {
    payload = JSON.parse(body.value)
  } catch {
    showErrorToast('The JSON does not parse')
    return
  }
  if (payload === null || typeof payload !== 'object' || Array.isArray(payload)) {
    showErrorToast('The body must be a JSON object')
    return
  }
  sending.value = true
  try {
    const response = await fetch(endpoint.value, {
      method: 'POST',
      headers: {
        Accept: 'application/json',
        'Content-Type': 'application/json',
        Authorization: `Bearer ${apiKey.value}`,
      },
      body: JSON.stringify(payload),
    })
    const decoded = await response.json().catch(() => null)
    if (!response.ok) {
      showErrorToast(failure(decoded, response.status))
      return
    }
    const uuid = decoded && typeof decoded === 'object' && 'uuid' in decoded ? String(decoded.uuid) : ''
    showToast(uuid ? `Lead accepted: ${uuid}` : 'Lead accepted')
  } catch {
    showErrorToast('The lead was not sent')
  } finally {
    sending.value = false
  }
}

const csvColumns = ['phone', 'email', 'first_name', 'last_name', 'country_code', 'ip', 'aff_sub', 'traffic_source', 'age', 'gender']
const csvHeader = csvColumns.join(',')
const csvFile = ref<File | null>(null)
const csvError = ref('')
const fileInput = ref<HTMLInputElement | null>(null)
const uploading = ref(false)
type CsvResult = { accepted: number; rejected: { line: number; errors: Record<string, string> }[] }
const csvResult = ref<CsvResult | null>(null)

function onFile(event: Event): void {
  const file = (event.target as HTMLInputElement).files?.[0] ?? null
  csvResult.value = null
  csvError.value = ''
  csvFile.value = null
  if (!file) return
  if (!/\.csv$/i.test(file.name)) {
    csvError.value = 'Please choose a CSV file.'
  } else if (file.size === 0) {
    csvError.value = 'The selected file is empty.'
  } else if (file.size > 1048576) {
    csvError.value = 'The file must be 1 MB or smaller.'
  } else {
    csvFile.value = file
  }
}

function csvCell(value: string): string {
  return /[",\n]/.test(value) ? `"${value.replace(/"/g, '""')}"` : value
}

function downloadTemplate(): void {
  const row = csvColumns.map((column) => csvCell(sample.value[column] ?? ''))
  const text = `\uFEFF${csvHeader}\r\n${row.join(',')}\r\n`
  const url = URL.createObjectURL(new Blob([text], { type: 'text/csv;charset=utf-8' }))
  const link = document.createElement('a')
  link.href = url
  link.download = 'leads-template.csv'
  link.click()
  URL.revokeObjectURL(url)
}

async function uploadCsv(): Promise<void> {
  if (!csvFile.value || uploading.value) return
  const form = new FormData()
  form.append('file', csvFile.value)
  uploading.value = true
  csvResult.value = null
  try {
    const response = await fetch(csvEndpoint.value, {
      method: 'POST',
      headers: { Accept: 'application/json', Authorization: `Bearer ${apiKey.value}` },
      body: form,
    })
    const decoded = await response.json().catch(() => null)
    if (!response.ok) {
      csvError.value = failure(decoded, response.status)
      return
    }
    const result = decoded as CsvResult
    csvResult.value = result
    showToast(`CSV processed: ${result.accepted} accepted`)
    if (result.rejected.length === 0) {
      csvFile.value = null
      if (fileInput.value) fileInput.value.value = ''
    }
  } catch {
    csvError.value = 'The file was not sent.'
  } finally {
    uploading.value = false
  }
}
</script>

<style scoped>
.offer-integration {
  background: #fff;
  border: 1px solid var(--lm-border);
  border-radius: var(--lm-radius);
  box-shadow: var(--lm-shadow);
  min-width: 0;
  padding: 18px;
}

.offer-integration h3 {
  font-size: 18px;
  letter-spacing: -0.5px;
  line-height: 1.35;
  margin: 0 0 8px;
}

.offer-integration__intro {
  color: var(--lm-muted);
  font-size: 13px;
  line-height: 1.6;
  margin: 0 0 12px;
}

.offer-integration__tabs {
  display: grid;
  gap: 6px;
  grid-template-columns: repeat(3, minmax(0, 1fr));
}

.offer-integration__tabs button {
  align-items: center;
  background: var(--lm-bg);
  border: 1px solid var(--lm-border);
  border-radius: 8px;
  color: var(--lm-foreground);
  cursor: pointer;
  display: flex;
  flex-direction: column;
  font-size: 13px;
  gap: 4px;
  justify-content: center;
  min-height: 92px;
  padding: 8px;
}

.offer-integration__tabs button small {
  color: var(--lm-muted);
  font-size: 11px;
}

.offer-integration__tabs button[aria-selected='true'] {
  background: var(--lm-lavender);
  border-color: var(--lm-primary);
  color: var(--lm-primary);
}

.offer-integration__panel {
  display: grid;
  font-size: 13px;
  gap: 14px;
  line-height: 1.5;
  margin-top: 16px;
}

.offer-integration__key-missing {
  align-items: center;
  background: var(--lm-lavender);
  border-radius: 10px;
  display: grid;
  gap: 10px;
  justify-items: start;
  padding: 12px;
}

.offer-integration__key-missing p {
  color: var(--lm-primary-deep);
  margin: 0;
}

.offer-field {
  display: grid;
  gap: 4px;
}

.offer-copy {
  align-items: center;
  background: var(--lm-bg);
  border: 1px solid var(--lm-border);
  border-radius: 8px;
  display: flex;
  overflow: hidden;
}

.offer-copy input {
  background: transparent;
  border: 0;
  color: var(--lm-foreground);
  flex: 1;
  font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
  font-size: 12px;
  height: 38px;
  min-width: 0;
  outline: none;
  padding: 0 10px;
  text-overflow: ellipsis;
}

.offer-copy button {
  align-items: center;
  background: transparent;
  border: 0;
  color: var(--lm-primary);
  cursor: pointer;
  display: grid;
  flex-shrink: 0;
  height: 38px;
  place-items: center;
  width: 38px;
}

.offer-copy button:disabled {
  color: #b5b3d1;
  cursor: default;
}

.offer-method-row {
  align-items: center;
  display: flex;
  gap: 12px;
}

.offer-badge {
  background: var(--lm-lavender);
  border-radius: 8px;
  color: var(--lm-primary);
  font-size: 12px;
  font-weight: 600;
  padding: 4px 10px;
}

.offer-json {
  border-top: 1px solid var(--lm-border);
  padding-top: 8px;
}

.offer-json summary {
  align-items: center;
  cursor: pointer;
  display: flex;
  font-weight: 600;
  gap: 8px;
  justify-content: space-between;
  list-style: none;
  padding: 4px 0;
}

.offer-json summary::-webkit-details-marker {
  display: none;
}

.offer-json[open] summary .v-icon {
  transform: rotate(180deg);
}

.offer-json textarea {
  background: var(--lm-bg);
  border: 1px solid var(--lm-border);
  border-radius: 8px;
  box-sizing: border-box;
  color: #303861;
  font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
  font-size: 12px;
  line-height: 1.5;
  margin-top: 8px;
  min-height: 220px;
  padding: 10px;
  resize: vertical;
  width: 100%;
}

.offer-json textarea:focus {
  border-color: #9486ff;
  outline: none;
}

.offer-json__actions {
  display: flex;
  gap: 8px;
  justify-content: flex-end;
  margin-top: 10px;
}

.offer-note {
  color: var(--lm-muted);
  font-size: 12px;
  line-height: 1.6;
  margin: 0;
}

.offer-note--lead {
  font-size: 13px;
}

.offer-note--error {
  color: #b82732;
}

.offer-note code,
.offer-csv-columns code {
  background: var(--lm-bg);
  border-radius: 6px;
  font-size: 12px;
  padding: 1px 5px;
}

.offer-csv-columns {
  display: grid;
  gap: 6px;
}

.offer-csv-columns code {
  overflow-wrap: anywhere;
  padding: 10px;
}

.offer-link {
  align-items: center;
  background: transparent;
  border: 0;
  color: var(--lm-primary);
  cursor: pointer;
  display: inline-flex;
  font-size: 13px;
  font-weight: 600;
  gap: 6px;
  justify-self: start;
  padding: 0;
}

.offer-link:hover {
  text-decoration: underline;
}

.offer-csv {
  display: grid;
  gap: 8px;
  justify-items: start;
}

.offer-csv label {
  font-weight: 600;
}

.offer-csv input[type='file'] {
  background: var(--lm-bg);
  border: 1px solid var(--lm-border);
  border-radius: 8px;
  box-sizing: border-box;
  font-size: 12px;
  padding: 8px;
  width: 100%;
}

.offer-csv input::file-selector-button {
  background: var(--lm-lavender);
  border: 0;
  border-radius: 6px;
  color: var(--lm-primary);
  cursor: pointer;
  font: inherit;
  margin-right: 8px;
  padding: 4px 10px;
}

.offer-csv-result {
  background: var(--lm-bg);
  border-radius: 8px;
  display: grid;
  gap: 4px;
  padding: 10px 12px;
}

.offer-csv-result ul {
  color: #b82732;
  font-size: 12px;
  margin: 0;
  padding-left: 18px;
}
</style>
