<template>
  <div class="integration-page" data-inspect="integration.page">
    <div class="page-head">
      <div>
        <h1>Integration</h1>
        <p>Deliver leads to your offers by API, postback, or CSV. One key covers every offer.</p>
      </div>
    </div>

    <section class="integration-card key-card" aria-labelledby="key-title">
      <header class="integration-card__head">
        <span class="integration-card__icon"><v-icon icon="mdi-key-outline" size="22" /></span>
        <div>
          <h2 id="key-title">API key</h2>
          <p>One key covers every offer. Send it with each lead. Keep it secret.</p>
        </div>
      </header>

      <div v-if="apiKey" class="key-block">
        <div class="key-row">
          <code class="key-row__value">{{ keyShown ? apiKey : maskedKey }}</code>
          <button type="button" class="icon-btn" :aria-label="keyShown ? 'Hide key' : 'Show key'" @click="keyShown = !keyShown">
            <v-icon :icon="keyShown ? 'mdi-eye-off-outline' : 'mdi-eye-outline'" size="18" />
          </button>
          <button type="button" class="icon-btn" aria-label="Copy key" @click="copy(apiKey)">
            <v-icon icon="mdi-content-copy" size="18" />
          </button>
          <UiButton variant="outline" icon="mdi-refresh" :disabled="busy" @click="confirmingRotate = !confirmingRotate">Regenerate</UiButton>
        </div>
        <div v-if="confirmingRotate" class="callout callout--warn" role="alertdialog" aria-labelledby="rotate-title">
          <v-icon icon="mdi-alert-outline" size="20" />
          <div class="callout__body">
            <p id="rotate-title">
              <b>Regenerate the key?</b> The current key stops working at once. Update it in your server,
              tracker and scripts, or leads will be rejected.
            </p>
            <div class="actions">
              <UiButton variant="outline" @click="confirmingRotate = false">Cancel</UiButton>
              <UiButton :loading="busy" icon="mdi-refresh" @click="rotate">Regenerate key</UiButton>
            </div>
          </div>
        </div>
      </div>
      <div v-else class="key-missing">
        <p>You have no API key yet.</p>
        <UiButton :loading="busy" icon="mdi-key-outline" @click="rotate">Generate API key</UiButton>
      </div>
    </section>

    <section v-if="statusLoaded && folded" class="integration-card integration-card--done" aria-labelledby="done-title">
      <header class="integration-card__head">
        <span class="integration-card__icon integration-card__icon--done"><v-icon icon="mdi-check" size="22" /></span>
        <div class="integration-card__text">
          <h2 id="done-title">Integration is complete</h2>
          <p>Your test lead came through on {{ doneAt }}. Send real leads with your key and they are matched to your offers.</p>
        </div>
        <router-link class="link" to="/leads">Open My leads</router-link>
        <UiButton variant="outline" icon="mdi-refresh" aria-label="Integrate again" @click="integrateAgain">Again</UiButton>
      </header>
    </section>

    <section v-else-if="statusLoaded" class="integration-card">
      <header class="integration-card__head">
        <span class="integration-card__icon"><v-icon icon="mdi-code-json" size="22" /></span>
        <div>
          <h2>Send your first lead</h2>
          <p>Pick how you deliver, prepare the fields, and check that the lead arrived.</p>
        </div>
      </header>

      <ol class="steps">
        <li class="step">
          <span class="step__num" :class="{ 'step__num--done': verified }">
            <v-icon v-if="verified" icon="mdi-check" size="16" />
            <template v-else>1</template>
          </span>
          <div class="step__body">
            <h3>Choose how you deliver</h3>
            <div class="options" role="radiogroup" aria-label="Lead delivery method">
              <button
                v-for="(item, index) in methods"
                :id="`method-${item.id}`"
                :key="item.id"
                type="button"
                role="radio"
                class="option"
                :class="{ 'option--on': method === item.id }"
                :aria-checked="method === item.id"
                :tabindex="method === item.id ? 0 : -1"
                @click="method = item.id"
                @keydown="onOptionKey($event, index)"
              >
                <span class="option__icon"><v-icon :icon="item.icon" size="22" /></span>
                <span class="option__text">
                  <strong>{{ item.title }} <em v-if="item.badge">{{ item.badge }}</em></strong>
                  <small>{{ item.text }}</small>
                </span>
              </button>
            </div>
          </div>
        </li>

        <li class="step">
          <span class="step__num" :class="{ 'step__num--done': verified }">
            <v-icon v-if="verified" icon="mdi-check" size="16" />
            <template v-else>2</template>
          </span>
          <div class="step__body">
            <h3>Prepare the lead</h3>
            <p class="hint">Every lead carries these fields. The lead is matched to an offer by vertical, country, traffic source, age and gender.</p>
            <div class="table-wrap">
              <table class="fields">
                <thead>
                  <tr><th>Field</th><th>Required</th><th>Description</th><th>Example</th></tr>
                </thead>
                <tbody>
                  <tr v-for="field in fields" :key="field.name">
                    <td><CopyableText class="field-name" :class="{ 'field-name--match': field.match }" :text="field.name" /></td>
                    <td><span class="pill" :class="field.required ? 'pill--req' : 'pill--opt'">{{ field.required ? 'Required' : 'Optional' }}</span></td>
                    <td>
                      {{ field.text }}
                      <span v-if="field.values" class="values">
                        <CopyableText v-for="value in field.values" :key="value" class="value" :text="value" />
                      </span>
                    </td>
                    <td><code>{{ field.example }}</code></td>
                  </tr>
                </tbody>
              </table>
            </div>
            <div class="callout">
              <v-icon icon="mdi-information-outline" size="20" />
              <p>
                The <span class="field-name field-name--match">highlighted</span> fields must equal the offer. Send age and gender only when the offer sets them;
                an offer with Age <b>Any</b> or Gender <b>*</b> takes leads without those fields.
                The exact values of each offer are in its details on the
                <router-link to="/demands">Demands</router-link> page.
              </p>
            </div>
          </div>
        </li>

        <li class="step">
          <span class="step__num" :class="{ 'step__num--done': verified }">
            <v-icon v-if="verified" icon="mdi-check" size="16" />
            <template v-else>3</template>
          </span>
          <div class="step__body">
            <h3>Send a test lead</h3>
            <div class="callout">
              <v-icon icon="mdi-flask-outline" size="20" />
              <p>
                The sample below is a <b>test lead</b>: <CopyableText class="field-name" text="is_test" /> is
                <CopyableText class="field-name" text="true" />. It is stored and shows in My leads, but it is never sold,
                put on stock or counted in statistics. Leave <code>is_test</code> out for real leads.
              </p>
            </div>

            <template v-if="method === 'api'">
              <div class="endpoint">
                <span class="badge">POST</span>
                <code>{{ endpoint }}</code>
                <button type="button" class="icon-btn" aria-label="Copy endpoint" @click="copy(endpoint)">
                  <v-icon icon="mdi-content-copy" size="18" />
                </button>
              </div>
              <p class="hint">Send the key in the <code>Authorization</code> header as a Bearer token.</p>

              <div class="code">
                <div class="code__head">
                  <span>cURL</span>
                  <button type="button" :disabled="!apiKey" @click="copy(curl)"><v-icon icon="mdi-content-copy" size="14" />Copy</button>
                </div>
                <pre>{{ apiKey ? curlMasked : 'Generate an API key first' }}</pre>
              </div>

              <label class="label" for="request-body">Request body (JSON)</label>
              <textarea id="request-body" v-model="body" class="editor" spellcheck="false" />
              <div class="actions">
                <UiButton variant="outline" @click="resetBody">Reset</UiButton>
                <UiButton :loading="sending" :disabled="!apiKey" icon="mdi-send-outline" @click="sendTest">Send test lead</UiButton>
              </div>
            </template>

            <template v-else-if="method === 'postback'">
              <div class="endpoint">
                <span class="badge">GET</span>
                <code>{{ endpoint }}</code>
                <button type="button" class="icon-btn" aria-label="Copy postback URL" @click="copy(endpoint)">
                  <v-icon icon="mdi-content-copy" size="18" />
                </button>
              </div>
              <div class="code">
                <div class="code__head">
                  <span>Postback URL with parameters</span>
                  <button type="button" :disabled="!apiKey" @click="copy(`${endpoint}?${parameters}`)"><v-icon icon="mdi-content-copy" size="14" />Copy</button>
                </div>
                <pre>{{ apiKey ? `${endpoint}?${parametersMasked}` : 'Generate an API key first' }}</pre>
              </div>
              <p class="hint">
                Paste the URL into your tracker and replace the sample values with its macros. The key goes in the
                <code>api_key</code> parameter, because a postback URL cannot carry headers.
                Encode a leading <code>+</code> in the phone as <code>%2B</code>.
              </p>
            </template>

            <template v-else>
              <p class="hint">
                Upload a UTF-8 CSV with a header row and one lead per row, up to {{ csvMaxRows }} leads and 1 MB.
                The header names are the field names from step 2. The template row is a test lead; drop the <code>is_test</code> column, or set it to <code>false</code>, for real leads.
              </p>
              <div class="code">
                <div class="code__head">
                  <span>Header row</span>
                  <button type="button" @click="copy(csvHeader)"><v-icon icon="mdi-content-copy" size="14" />Copy</button>
                </div>
                <pre>{{ csvHeader }}</pre>
              </div>
              <button type="button" class="link" @click="downloadTemplate">
                <v-icon icon="mdi-download-outline" size="16" />
                Download CSV template
              </button>

              <form class="csv" @submit.prevent="uploadCsv">
                <label class="label" for="csv-file">CSV file</label>
                <input
                  id="csv-file"
                  ref="fileInput"
                  type="file"
                  accept=".csv,text/csv"
                  :aria-invalid="csvError !== ''"
                  @change="onFile"
                >
                <p class="hint" aria-live="polite" :class="{ 'hint--error': csvError }">
                  {{ csvError || (csvFile ? `Selected: ${csvFile.name}` : 'No file selected.') }}
                </p>
                <UiButton type="submit" :loading="uploading" :disabled="!csvFile || !apiKey" icon="mdi-send-outline">Upload</UiButton>
              </form>
            </template>

            <div v-if="result" class="result" :class="result.ok ? 'result--ok' : 'result--bad'" aria-live="polite">
              <strong>{{ result.title }}</strong>
              <ul v-if="result.lines.length">
                <li v-for="line in result.lines" :key="line">{{ line }}</li>
              </ul>
            </div>
          </div>
        </li>

        <li class="step">
          <span class="step__num" :class="{ 'step__num--done': verified }">
            <v-icon v-if="verified" icon="mdi-check" size="16" />
            <template v-else>4</template>
          </span>
          <div class="step__body">
            <h3>Verify the delivery</h3>

            <div v-if="testLead" class="verified" aria-live="polite">
              <strong class="verified__title"><v-icon icon="mdi-check-circle-outline" size="20" />Test lead received</strong>
              <dl class="facts">
                <div v-for="fact in facts" :key="fact.label">
                  <dt>{{ fact.label }}</dt>
                  <dd><CopyableText :text="fact.value" /></dd>
                </div>
              </dl>
              <p class="hint">
                The lead is stored in My leads and marked as a test. It is not sold, not on stock and not counted in statistics.
              </p>
              <router-link class="link" to="/leads">
                Open My leads
                <v-icon icon="mdi-arrow-right" size="16" />
              </router-link>
            </div>

            <div v-else-if="watching === 'stalled'" class="callout callout--warn">
              <v-icon icon="mdi-alert-outline" size="20" />
              <div class="callout__body">
                <p><b>No test lead yet.</b> Check the key and the fields, send the test lead again, then check once more.</p>
                <div class="actions">
                  <UiButton variant="outline" icon="mdi-refresh" @click="watchForTestLead">Check again</UiButton>
                </div>
              </div>
            </div>

            <div v-else class="waiting" aria-live="polite">
              <v-progress-circular indeterminate size="18" width="2" color="primary" />
              <div>
                <strong>Waiting for your test lead…</strong>
                <p class="hint">{{ waiting }} This page checks every few seconds and finishes the setup on its own.</p>
              </div>
            </div>
          </div>
        </li>
      </ol>
    </section>
  </div>
</template>

<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { api } from '@/api'
import CopyableText from '@/components/CopyableText.vue'
import UiButton from '@/components/ui/UiButton.vue'
import { useBuyerApiKey } from '@/composables/useBuyerApiKey'
import { showErrorToast, showToast } from '@/composables/useToast'
import { copyText } from '@/copyText'
import { apiOrigin } from '@/host'
import type { CabinetLead } from '@/stores/leads'
import { useSessionStore } from '@/stores/session'
import { genders, trafficSources, verticals } from '@/marketOptions'

type Method = 'api' | 'postback' | 'csv'

const methods: { id: Method; title: string; icon: string; badge?: string; text: string }[] = [
  { id: 'api', title: 'API', icon: 'mdi-code-json', badge: 'Recommended', text: 'POST each lead as JSON from your server.' },
  { id: 'postback', title: 'Postback', icon: 'mdi-link-variant', text: 'Call a URL from your tracker for every lead.' },
  { id: 'csv', title: 'CSV', icon: 'mdi-file-delimited-outline', text: 'Upload a file with many leads at once.' },
]
const method = ref<Method>('api')

function onOptionKey(event: KeyboardEvent, index: number): void {
  const last = methods.length - 1
  const next = event.key === 'ArrowRight' ? (index + 1) % methods.length
    : event.key === 'ArrowLeft' ? (index + last) % methods.length
      : event.key === 'Home' ? 0
        : event.key === 'End' ? last : -1
  if (next < 0) return
  event.preventDefault()
  method.value = methods[next].id
  document.getElementById(`method-${methods[next].id}`)?.focus()
}

const verticalValues = verticals.map((item) => item.value)
const trafficValues = trafficSources.map((item) => item.value)
const genderValues = genders.filter((item) => item.value !== 'all').map((item) => item.value)

interface Field {
  name: string
  required: boolean
  text: string
  example: string
  values?: string[]
  match?: boolean
}

const fields: Field[] = [
  { name: 'phone', required: true, text: 'Phone in E.164 format, with a leading +.', example: '+4915112345678' },
  { name: 'email', required: true, text: 'Email of the lead.', example: 'ada@example.com' },
  { name: 'first_name', required: true, text: 'First name.', example: 'Ada' },
  { name: 'last_name', required: true, text: 'Last name.', example: 'Lovelace' },
  { name: 'ip', required: true, text: 'IP address of the lead.', example: '192.0.2.10' },
  { name: 'vertical', required: true, match: true, text: 'One of:', values: verticalValues, example: 'crypto' },
  { name: 'country_code', required: true, match: true, text: 'Two-letter ISO country code of the offer.', example: 'DE' },
  { name: 'traffic_source', required: true, match: true, text: 'Source of the traffic. Known values:', values: trafficValues, example: 'facebook' },
  { name: 'age', required: false, text: 'A single age inside the offer bracket. Leave it out when the offer age is Any.', example: '30' },
  { name: 'gender', required: false, text: 'Leave it out when the offer gender is *. One of:', values: genderValues, example: 'm' },
  { name: 'is_test', required: false, text: 'Marks a test lead. It is stored and shown in My leads, never sold, put on stock or counted. Leave it out for real leads.', example: 'true' },
]

const { apiKey, busy, load, generate } = useBuyerApiKey()
onMounted(() => {
  void load()
  void loadStatus()
})
onBeforeUnmount(() => run?.abort())

const keyShown = ref(false)
const confirmingRotate = ref(false)

async function rotate(): Promise<void> {
  await generate()
  confirmingRotate.value = false
  if (apiKey.value) {
    keyShown.value = true
    showToast('New key ready')
  }
}
const maskedKey = computed(() => (apiKey.value ? `${apiKey.value.slice(0, 3)}${'•'.repeat(16)}` : ''))

const csvMaxRows = 1000
const endpoint = computed(() => `${apiOrigin(window.location.hostname)}/v1/lead`)
const csvEndpoint = computed(() => `${apiOrigin(window.location.hostname)}/v1/leads/csv`)

const sample: Record<string, string | boolean> = {
  phone: '+4915112345678',
  email: 'ada@example.com',
  first_name: 'Ada',
  last_name: 'Lovelace',
  ip: '192.0.2.10',
  vertical: 'crypto',
  country_code: 'DE',
  traffic_source: 'facebook',
  age: '30',
  is_test: true,
}

const body = ref(JSON.stringify(sample, null, 2))

function resetBody(): void {
  body.value = JSON.stringify(sample, null, 2)
}

function curlWith(key: string): string {
  return `curl -X POST ${endpoint.value} \\\n  -H 'Authorization: Bearer ${key}' \\\n  -H 'Content-Type: application/json' \\\n  -d '${JSON.stringify(sample)}'`
}
const curl = computed(() => curlWith(apiKey.value))
const curlMasked = computed(() => (apiKey.value ? curlWith(maskedKey.value) : ''))

function query(key: string): string {
  const text = Object.fromEntries(Object.entries(sample).map(([name, value]) => [name, String(value)]))

  return `api_key=${key}&${new URLSearchParams(text).toString()}`
}
const parameters = computed(() => query(apiKey.value))
const parametersMasked = computed(() => query(maskedKey.value))

async function copy(value: string): Promise<void> {
  if (!value) return
  showToast((await copyText(value)) ? 'Copied' : 'Copy unavailable')
}

interface Outcome {
  ok: boolean
  title: string
  lines: string[]
}
const result = ref<Outcome | null>(null)
watch(method, () => { result.value = null })

const waiting = computed(() => {
  if (method.value === 'api') return 'Send the test lead above.'
  if (method.value === 'postback') return 'Fire the postback once from your tracker.'
  return 'Upload the file above.'
})

const session = useSessionStore()
const statusLoaded = ref(false)
const completedAt = ref<string | null>(null)
const reintegrating = ref(false)
const testLead = ref<CabinetLead | null>(null)
const watching = ref<'waiting' | 'stalled'>('waiting')
let since: string | null = null
let run: AbortController | null = null

const folded = computed(() => completedAt.value !== null && !reintegrating.value)
const verified = computed(() => testLead.value !== null)
const doneAt = computed(() => (
  completedAt.value === null
    ? ''
    : new Intl.DateTimeFormat(undefined, { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(completedAt.value))
))

const facts = computed(() => {
  const lead = testLead.value
  if (lead === null) return []
  const received = new Intl.DateTimeFormat(undefined, { dateStyle: 'medium', timeStyle: 'medium' }).format(new Date(lead.createdAt))
  const rows = [
    { label: 'Lead ID', value: lead.uuid },
    { label: 'Received', value: received },
    { label: 'Vertical', value: lead.vertical },
    { label: 'Country', value: lead.country },
    { label: 'Traffic source', value: lead.trafficSource },
    { label: 'Age', value: lead.age || '—' },
    { label: 'Gender', value: lead.gender || '—' },
  ]
  if (!lead.contactsHidden) {
    rows.push({ label: 'Phone', value: lead.phone }, { label: 'Email', value: lead.email || '—' })
  }

  return rows
})

interface StatusBody {
  completedAt: string | null
  now: string
}

async function serverStatus(): Promise<StatusBody | null> {
  try {
    const response = await api('/integration')

    return response.ok ? await response.json() as StatusBody : null
  } catch {
    return null
  }
}

async function loadStatus(): Promise<void> {
  const status = await serverStatus()
  completedAt.value = status?.completedAt ?? null
  if (completedAt.value !== null) session.markIntegrated(completedAt.value)
  statusLoaded.value = true
  if (!folded.value) void watchForTestLead()
}

async function integrateAgain(): Promise<void> {
  // The server clock decides what counts as new, so a skewed browser clock cannot hide the lead.
  since = (await serverStatus())?.now ?? new Date().toISOString()
  testLead.value = null
  result.value = null
  reintegrating.value = true
  void watchForTestLead()
}

function pause(ms: number, signal: AbortSignal): Promise<void> {
  return new Promise((resolve) => {
    const timer = setTimeout(resolve, ms)
    signal.addEventListener('abort', () => {
      clearTimeout(timer)
      resolve()
    }, { once: true })
  })
}

const POLL_INTERVAL_MS = 2000
const POLL_GIVE_UP_MS = 10 * 60 * 1000

/**
 * Asks the server again and again whether a test lead of this buyer has arrived. The server settles the setup
 * when it finds one, so this only has to show the lead.
 */
async function watchForTestLead(): Promise<void> {
  run?.abort()
  const mine = new AbortController()
  run = mine
  watching.value = 'waiting'
  const startedAt = Date.now()
  while (!mine.signal.aborted) {
    try {
      const response = await api(`/integration/test-lead${since === null ? '' : `?since=${encodeURIComponent(since)}`}`, { signal: mine.signal })
      if (response.status === 401 || response.status === 403) return
      if (response.ok) {
        const body = await response.json() as { lead: CabinetLead | null; completedAt: string | null }
        if (body.lead !== null) {
          testLead.value = { ...body.lead, phone: body.lead.phone ?? '', email: body.lead.email ?? '' }
          completedAt.value = body.completedAt
          if (body.completedAt !== null) session.markIntegrated(body.completedAt)
          return
        }
      }
    } catch {
      if (mine.signal.aborted) return
    }
    if (Date.now() - startedAt > POLL_GIVE_UP_MS) {
      watching.value = 'stalled'
      return
    }
    await pause(POLL_INTERVAL_MS, mine.signal)
  }
}

function failure(decoded: unknown, status: number): string[] {
  if (decoded && typeof decoded === 'object') {
    const record = decoded as { error?: unknown; errors?: Record<string, unknown> }
    if (record.errors) return Object.entries(record.errors).map(([field, message]) => `${field}: ${String(message)}`)
    if (typeof record.error === 'string' && record.error !== '') return [record.error]
  }
  return [`The lead was not accepted: ${status}`]
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
      result.value = { ok: false, title: `Rejected (${response.status})`, lines: failure(decoded, response.status) }
      return
    }
    const uuid = decoded && typeof decoded === 'object' && 'uuid' in decoded ? String(decoded.uuid) : ''
    result.value = { ok: true, title: 'Lead accepted', lines: uuid ? [`uuid: ${uuid}`] : [] }
  } catch {
    result.value = { ok: false, title: 'The lead was not sent', lines: ['The API did not answer.'] }
  } finally {
    sending.value = false
  }
}

const csvColumns = ['phone', 'email', 'first_name', 'last_name', 'ip', 'vertical', 'country_code', 'traffic_source', 'age', 'gender', 'is_test']
const csvHeader = csvColumns.join(',')
const csvFile = ref<File | null>(null)
const csvError = ref('')
const fileInput = ref<HTMLInputElement | null>(null)
const uploading = ref(false)
type CsvResult = { accepted: number; rejected: { line: number; errors: Record<string, string> }[] }

function onFile(event: Event): void {
  const file = (event.target as HTMLInputElement).files?.[0] ?? null
  result.value = null
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
  const row = csvColumns.map((column) => csvCell(String(sample[column] ?? '')))
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
  result.value = null
  try {
    const response = await fetch(csvEndpoint.value, {
      method: 'POST',
      headers: { Accept: 'application/json', Authorization: `Bearer ${apiKey.value}` },
      body: form,
    })
    const decoded = await response.json().catch(() => null)
    if (!response.ok) {
      csvError.value = failure(decoded, response.status).join(' ')
      return
    }
    const parsed = decoded as CsvResult
    const rejected = parsed.rejected.slice(0, 5).map((item) => `Line ${item.line}: ${Object.values(item.errors).join('; ')}`)
    if (parsed.rejected.length > 5) rejected.push(`…and ${parsed.rejected.length - 5} more.`)
    result.value = {
      ok: parsed.rejected.length === 0,
      title: `${parsed.accepted} accepted, ${parsed.rejected.length} rejected`,
      lines: rejected,
    }
    if (parsed.rejected.length === 0) {
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
.key-card {
  margin-bottom: 16px;
}

.key-block {
  display: grid;
  gap: 12px;
}

.integration-card {
  box-sizing: border-box;
  width: 100%;
  background: #fff;
  border: 1px solid var(--lm-border);
  border-radius: var(--lm-radius);
  box-shadow: var(--lm-shadow);
  padding: 24px 28px 28px;
}

.integration-card__head {
  align-items: center;
  display: flex;
  gap: 14px;
  margin-bottom: 22px;
}

.integration-card__head h2 {
  font-size: 18px;
  letter-spacing: -0.4px;
  margin: 0;
}

.integration-card__head p {
  color: var(--lm-muted);
  font-size: 13px;
  margin: 2px 0 0;
}

.integration-card__icon {
  background: var(--lm-lavender);
  border-radius: 10px;
  color: var(--lm-primary);
  display: grid;
  flex-shrink: 0;
  height: 40px;
  place-items: center;
  width: 40px;
}

.steps {
  display: grid;
  gap: 0;
  list-style: none;
  margin: 0;
  padding: 0;
}

.step {
  display: grid;
  gap: 16px;
  grid-template-columns: 28px minmax(0, 1fr);
  position: relative;
  padding-bottom: 28px;
}

.step:last-child {
  padding-bottom: 0;
}

.step:not(:last-child)::before {
  background: var(--lm-border);
  bottom: 0;
  content: '';
  left: 13px;
  position: absolute;
  top: 32px;
  width: 2px;
}

.step__num {
  align-items: center;
  background: var(--lm-primary);
  border-radius: 50%;
  color: #fff;
  display: grid;
  font-size: 13px;
  font-weight: 700;
  height: 28px;
  place-items: center;
  width: 28px;
}

.step__num--done {
  background: #1f9d6b;
}

.step__body {
  display: grid;
  gap: 12px;
  min-width: 0;
}

.step__body h3 {
  font-size: 15px;
  letter-spacing: -0.2px;
  line-height: 28px;
  margin: 0;
}

.hint {
  color: var(--lm-muted);
  font-size: 13px;
  line-height: 1.6;
  margin: 0;
}

.hint--error {
  color: #b82732;
}

.hint code,
.endpoint code,
.fields code,
.callout code {
  background: var(--lm-bg);
  border-radius: 6px;
  font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
  font-size: 12px;
  padding: 1px 6px;
}

.key-row,
.endpoint {
  align-items: center;
  background: var(--lm-bg);
  border: 1px solid var(--lm-border);
  border-radius: 10px;
  display: flex;
  gap: 6px;
  min-height: 44px;
  padding: 0 6px 0 14px;
}

.key-row__value,
.endpoint code {
  background: transparent;
  flex: 1;
  font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
  font-size: 13px;
  min-width: 0;
  overflow-wrap: anywhere;
  padding: 0;
}

.endpoint {
  gap: 10px;
}

.icon-btn {
  align-items: center;
  background: transparent;
  border: 0;
  border-radius: 8px;
  color: var(--lm-primary);
  cursor: pointer;
  display: grid;
  flex-shrink: 0;
  height: 34px;
  place-items: center;
  width: 34px;
}

.icon-btn:hover {
  background: var(--lm-lavender);
}

.key-missing {
  align-items: center;
  background: var(--lm-lavender);
  border-radius: 10px;
  display: flex;
  flex-wrap: wrap;
  gap: 12px;
  justify-content: space-between;
  padding: 12px 14px;
}

.key-missing p {
  color: var(--lm-primary-deep);
  font-size: 13px;
  margin: 0;
}

.options {
  display: grid;
  gap: 12px;
  grid-template-columns: repeat(3, minmax(0, 1fr));
}

.option {
  align-items: center;
  background: #fff;
  border: 1px solid var(--lm-border);
  border-radius: 12px;
  color: var(--lm-foreground);
  cursor: pointer;
  display: flex;
  gap: 12px;
  padding: 14px;
  text-align: left;
}

.option:hover {
  border-color: #b9b0ff;
}

.option--on {
  background: var(--lm-lavender);
  border-color: var(--lm-primary);
  box-shadow: 0 0 0 1px var(--lm-primary) inset;
}

.option__icon {
  background: #fff;
  border: 1px solid var(--lm-border);
  border-radius: 10px;
  color: var(--lm-primary);
  display: grid;
  flex-shrink: 0;
  height: 40px;
  place-items: center;
  width: 40px;
}

.option__text {
  display: grid;
  gap: 2px;
  min-width: 0;
}

.option__text strong {
  align-items: center;
  display: flex;
  flex-wrap: wrap;
  font-size: 14px;
  gap: 6px;
}

.option__text em {
  background: var(--lm-lavender);
  border-radius: 6px;
  color: var(--lm-primary);
  font-size: 11px;
  font-style: normal;
  font-weight: 600;
  padding: 1px 7px;
}

.option--on .option__text em {
  background: #fff;
}

.option__text small {
  color: var(--lm-muted);
  font-size: 12px;
  line-height: 1.4;
}

.table-wrap {
  border: 1px solid var(--lm-border);
  border-radius: 10px;
  overflow-x: auto;
}

.fields {
  border-collapse: collapse;
  font-size: 13px;
  width: 100%;
}

.fields th {
  background: var(--lm-bg);
  color: var(--lm-muted);
  font-size: 12px;
  font-weight: 600;
  padding: 10px 14px;
  text-align: left;
  white-space: nowrap;
}

.fields td {
  border-top: 1px solid var(--lm-lavender);
  line-height: 1.5;
  padding: 10px 14px;
  vertical-align: top;
}

.field-name {
  background: var(--lm-bg);
  border-radius: 6px;
  display: inline-block;
  font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
  font-size: 12px;
  padding: 1px 6px;
}

.fields td:first-child .field-name {
  color: var(--lm-primary-deep);
  font-weight: 600;
}

.field-name--match,
.fields td:first-child .field-name--match {
  background: #fff1b8;
  color: #6b4e00;
}

.field-name:hover,
.value:hover {
  filter: brightness(0.95);
}

.field-name:focus-visible,
.value:focus-visible {
  outline: 2px solid var(--lm-primary);
  outline-offset: 1px;
}

.values {
  display: inline-flex;
  flex-wrap: wrap;
  gap: 4px;
  margin-left: 4px;
  vertical-align: middle;
}

.value {
  background: var(--lm-lavender);
  border-radius: 6px;
  color: var(--lm-primary-deep);
  font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
  font-size: 12px;
  padding: 1px 7px;
}

.pill {
  border-radius: 6px;
  display: inline-block;
  font-size: 11px;
  font-weight: 600;
  padding: 2px 8px;
  white-space: nowrap;
}

.pill--req {
  background: var(--lm-lavender);
  color: var(--lm-primary);
}

.pill--opt {
  background: var(--lm-bg);
  color: var(--lm-muted);
}

.callout {
  background: var(--lm-lavender);
  border-radius: 10px;
  color: var(--lm-primary-deep);
  display: flex;
  font-size: 13px;
  gap: 10px;
  line-height: 1.6;
  padding: 12px 14px;
}

.callout--warn {
  background: #fff4e5;
  color: #8a4b00;
}

.callout__body {
  display: grid;
  gap: 10px;
  flex: 1;
}

.callout .v-icon {
  flex-shrink: 0;
  margin-top: 2px;
}

.callout p {
  margin: 0;
}

.callout a {
  color: inherit;
  font-weight: 600;
}

.badge {
  background: var(--lm-lavender);
  border-radius: 8px;
  color: var(--lm-primary);
  font-size: 12px;
  font-weight: 700;
  padding: 4px 10px;
}

.code {
  border: 1px solid var(--lm-border);
  border-radius: 10px;
  overflow: hidden;
}

.code__head {
  align-items: center;
  background: var(--lm-bg);
  border-bottom: 1px solid var(--lm-border);
  color: var(--lm-muted);
  display: flex;
  font-size: 11px;
  font-weight: 600;
  justify-content: space-between;
  letter-spacing: 0.4px;
  padding: 6px 12px;
  text-transform: uppercase;
}

.code__head button {
  align-items: center;
  background: transparent;
  border: 0;
  color: var(--lm-primary);
  cursor: pointer;
  display: inline-flex;
  font: inherit;
  gap: 4px;
  text-transform: none;
}

.code__head button:disabled {
  color: #b5b3d1;
  cursor: default;
}

.code pre {
  color: #303861;
  font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
  font-size: 12px;
  line-height: 1.6;
  margin: 0;
  overflow-x: auto;
  padding: 12px 14px;
  white-space: pre-wrap;
  word-break: break-all;
}

.label {
  font-size: 13px;
  font-weight: 600;
}

.editor {
  background: var(--lm-bg);
  border: 1px solid var(--lm-border);
  border-radius: 10px;
  box-sizing: border-box;
  color: #303861;
  font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
  font-size: 12px;
  line-height: 1.6;
  min-height: 220px;
  padding: 12px 14px;
  resize: vertical;
  width: 100%;
}

.editor:focus {
  border-color: #9486ff;
  outline: none;
}

.actions {
  display: flex;
  gap: 8px;
  justify-content: flex-end;
}

.link {
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
  text-decoration: none;
}

.link:hover {
  text-decoration: underline;
}

.csv {
  display: grid;
  gap: 8px;
  justify-items: start;
}

.csv input[type='file'] {
  background: var(--lm-bg);
  border: 1px solid var(--lm-border);
  border-radius: 10px;
  box-sizing: border-box;
  font-size: 12px;
  padding: 10px;
  width: 100%;
}

.csv input::file-selector-button {
  background: var(--lm-lavender);
  border: 0;
  border-radius: 6px;
  color: var(--lm-primary);
  cursor: pointer;
  font: inherit;
  margin-right: 10px;
  padding: 4px 12px;
}

.result {
  border-radius: 10px;
  display: grid;
  font-size: 13px;
  gap: 4px;
  padding: 12px 14px;
}

.result--ok {
  background: #e7f6ef;
  color: #17694a;
}

.result--bad {
  background: #fdecee;
  color: #b82732;
}

.result ul {
  margin: 0;
  padding-left: 18px;
}

.integration-card--done .integration-card__head {
  margin-bottom: 0;
}

.integration-card__text {
  flex: 1;
  min-width: 0;
}

.integration-card__icon--done {
  background: #e7f6ef;
  color: #17694a;
}

.waiting {
  align-items: flex-start;
  background: var(--lm-bg);
  border: 1px dashed var(--lm-border);
  border-radius: 10px;
  display: flex;
  font-size: 13px;
  gap: 12px;
  padding: 14px;
}

.waiting p {
  margin: 2px 0 0;
}

.verified {
  background: #f1faf6;
  border: 1px solid #bfe6d4;
  border-radius: 10px;
  display: grid;
  gap: 12px;
  padding: 14px 16px;
}

.verified__title {
  align-items: center;
  color: #17694a;
  display: inline-flex;
  font-size: 14px;
  gap: 6px;
}

.facts {
  display: grid;
  gap: 10px 20px;
  grid-template-columns: repeat(auto-fill, minmax(190px, 1fr));
  margin: 0;
}

.facts dt {
  color: var(--lm-muted);
  font-size: 11px;
  font-weight: 600;
  letter-spacing: 0.4px;
  text-transform: uppercase;
}

.facts dd {
  font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
  font-size: 13px;
  margin: 2px 0 0;
  overflow-wrap: anywhere;
}

@media (max-width: 800px) {
  .options {
    grid-template-columns: minmax(0, 1fr);
  }

  .integration-card {
    padding: 18px 16px 22px;
  }
}
</style>
