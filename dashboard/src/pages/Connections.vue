<template>
  <div class="connections-page">
    <div class="page-head">
      <div>
        <h1>Connections</h1>
        <p>Generate the API key, copy a request, and send a test lead.</p>
      </div>
    </div>

    <section class="ui-filter-panel key-panel">
      <div class="key-panel__head">
        <div>
          <h2>API key</h2>
          <p>The buyer posts a lead with this key. A new key replaces the previous one.</p>
        </div>
        <UiButton :loading="keyBusy" @click="generateKey">
          {{ apiKey ? 'Replace key' : 'Generate' }}
        </UiButton>
      </div>
      <div v-if="apiKey" class="key-panel__value">
        <input class="ui-input" :value="apiKey" readonly>
        <UiButton variant="outline" @click="copyKey">Copy</UiButton>
      </div>
      <pre v-if="apiKey" class="key-panel__example">{{ example }}</pre>
      <p v-else class="key-panel__hint">Generate a key to see the request example.</p>
    </section>

    <v-alert v-if="!apiKey" type="warning" variant="tonal" class="connections-alert">
      Create an API key before sending a test lead.
    </v-alert>

    <section class="ui-filter-panel">
      <h2>Test lead</h2>
      <p class="send-lead__hint">
        The lead is posted with your API key. Change the JSON and send it. <code>gender</code> is optional.
      </p>
      <label class="send-lead__label" for="lead-body">Body</label>
      <textarea id="lead-body" v-model="body" class="send-lead__body" spellcheck="false" />
      <div class="send-lead__actions">
        <UiButton variant="outline" :disabled="sending" @click="resetBody">Reset</UiButton>
        <UiButton :loading="sending" :disabled="!apiKey" @click="sendLead">Send test lead</UiButton>
      </div>
    </section>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { api } from '@/api'
import UiButton from '@/components/ui/UiButton.vue'
import { showErrorToast, showToast } from '@/composables/useToast'
import { apiOrigin } from '@/host'
import { sampleLead, sampleLeadJson } from '@/leadBody'

const apiKey = ref('')
const keyBusy = ref(false)
const keyError = ref('')
const body = ref(sampleLeadJson())
const sending = ref(false)
const sendError = ref('')
const sendResult = ref('')

watch(keyError, (message) => {
  if (message) showErrorToast(message)
})
watch(sendError, (message) => {
  if (message) showErrorToast(message)
})
watch(sendResult, (message) => {
  if (message) showToast(message)
})

const example = computed(() => {
  const origin = apiOrigin(window.location.hostname)
  return [
    `curl -X POST ${origin}/v1/lead \\`,
    `  -H 'Authorization: Bearer ${apiKey.value}' \\`,
    `  -H 'Content-Type: application/json' \\`,
    `  -d '${JSON.stringify(sampleLead)}'`,
  ].join('\n')
})

async function loadKey(): Promise<void> {
  const response = await api('/api-key')
  if (!response.ok) {
    keyError.value = `Ключ не прочитался: ${response.status}`
    return
  }
  const payload = await response.json() as { apiKey?: string | null }
  apiKey.value = payload.apiKey ?? ''
}

async function generateKey(): Promise<void> {
  keyBusy.value = true
  keyError.value = ''
  try {
    const response = await api('/api-key', { method: 'POST' })
    if (!response.ok) {
      keyError.value = `Ключ не создался: ${response.status}`
      return
    }
    const payload = await response.json() as { apiKey?: string }
    apiKey.value = payload.apiKey ?? ''
  } catch {
    keyError.value = 'Ключ не создался'
  } finally {
    keyBusy.value = false
  }
}

function resetBody(): void {
  body.value = sampleLeadJson()
  sendError.value = ''
  sendResult.value = ''
}

async function sendLead(): Promise<void> {
  sendError.value = ''
  sendResult.value = ''
  let payload: unknown
  try {
    payload = JSON.parse(body.value)
  } catch {
    sendError.value = 'The JSON does not parse'
    return
  }
  if (payload === null || typeof payload !== 'object' || Array.isArray(payload)) {
    sendError.value = 'The body must be a JSON object'
    return
  }

  sending.value = true
  try {
    const response = await fetch(`${apiOrigin(window.location.hostname)}/v1/lead`, {
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
      sendError.value = sendFailure(decoded, response.status)
      return
    }
    const uuid = decoded && typeof decoded === 'object' && 'uuid' in decoded ? String(decoded.uuid) : ''
    sendResult.value = uuid ? `Lead accepted: ${uuid}` : 'Lead accepted'
  } catch {
    sendError.value = 'The lead was not sent'
  } finally {
    sending.value = false
  }
}

function sendFailure(decoded: unknown, status: number): string {
  if (decoded && typeof decoded === 'object') {
    const record = decoded as { error?: unknown; errors?: Record<string, unknown> }
    if (record.errors) {
      return Object.values(record.errors).map(String).join(' ')
    }
    if (typeof record.error === 'string' && record.error !== '') {
      return record.error
    }
  }
  return `The lead was not accepted: ${status}`
}

async function copyKey(): Promise<void> {
  if (!apiKey.value) return
  try {
    await navigator.clipboard.writeText(apiKey.value)
    showToast('API key copied')
  } catch {
    showToast('Copy unavailable')
  }
}

onMounted(() => {
  void loadKey()
})
</script>

<style scoped>
.connections-page {
  display: grid;
  gap: 16px;
}

.key-panel__head {
  align-items: flex-start;
  display: flex;
  gap: 16px;
  justify-content: space-between;
}

.key-panel__head h2,
.connections-page h2 {
  font-size: 20px;
  letter-spacing: -0.5px;
  margin: 0 0 4px;
}

.key-panel__head p,
.key-panel__hint {
  color: var(--lm-muted);
  font-size: 14px;
  margin: 4px 0 0;
}

.key-panel__value {
  display: grid;
  gap: 8px;
  grid-template-columns: minmax(0, 1fr) auto;
  margin-top: 16px;
}

.key-panel__value input {
  background: #fff;
  border: 1px solid #cdd0e6;
  border-radius: 9px;
  box-sizing: border-box;
  color: var(--lm-foreground);
  font: inherit;
  font-size: 13px;
  height: var(--lm-control-height);
  min-width: 0;
  padding: 0 12px;
  width: 100%;
}

.key-panel__example {
  background: #f7f7ff;
  border-radius: 10px;
  color: #303861;
  font-size: 12px;
  margin: 12px 0 0;
  overflow-x: auto;
  padding: 12px;
  white-space: pre-wrap;
}

.connections-alert {
  margin: 0;
}

.send-lead__hint {
  color: var(--lm-muted);
  font-size: 14px;
  margin: 0 0 16px;
}

.send-lead__label {
  color: var(--lm-foreground);
  display: block;
  font-size: 13px;
  font-weight: 600;
  margin-bottom: 8px;
}

.send-lead__body {
  background: #f7f7ff;
  border: 1px solid #cdd0e6;
  border-radius: 10px;
  box-sizing: border-box;
  color: #303861;
  font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
  font-size: 13px;
  line-height: 1.5;
  min-height: 280px;
  padding: 12px;
  resize: vertical;
  width: 100%;
}

.send-lead__body:focus {
  border-color: #9486ff;
  outline: none;
}

.send-lead__actions {
  display: flex;
  gap: 8px;
  justify-content: flex-end;
  margin-top: 16px;
}

@media (max-width: 767px) {
  .key-panel__head,
  .key-panel__value {
    display: grid;
    grid-template-columns: minmax(0, 1fr);
  }
}
</style>
