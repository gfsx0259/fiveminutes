<template>
  <section class="ui-filter-panel">
    <div class="listing-form__head">
      <div>
        <h2>{{ title }}</h2>
        <p>{{ hint }}</p>
      </div>
      <UiButton :loading="busy" @click="submit">{{ submitLabel }}</UiButton>
    </div>
    <p v-if="error" class="listing-form__error">{{ error }}</p>
    <div class="listing-form">
      <UiField label="Geo">
        <UiInput v-model="geo" placeholder="IT" />
      </UiField>
      <UiField label="Volume">
        <UiInput v-model="volume" type="number" placeholder="10" />
      </UiField>
      <UiField label="Buyer price, USD">
        <UiInput v-model="price" type="number" placeholder="50" />
      </UiField>
      <UiField label="Model">
        <UiSelect v-model="model" :items="models" placeholder="CPL" />
      </UiField>
      <UiField v-if="kind === 'slot'" label="Deadline">
        <UiInput v-model="deadline" type="date" />
      </UiField>
      <template v-else>
        <UiField label="Date">
          <UiInput v-model="serviceOn" type="date" />
        </UiField>
        <UiField label="Opens">
          <UiInput v-model="opensAt" type="time" />
        </UiField>
        <UiField label="Closes">
          <UiInput v-model="closesAt" type="time" />
        </UiField>
      </template>
    </div>
  </section>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import UiButton from '@/components/ui/UiButton.vue'
import UiField from '@/components/ui/UiField.vue'
import UiInput from '@/components/ui/UiInput.vue'
import UiSelect from '@/components/ui/UiSelect.vue'

const props = defineProps<{
  kind: 'slot' | 'demand'
  title: string
  hint: string
  submitLabel: string
  busy: boolean
  error: string
}>()

const emit = defineEmits<{
  submit: [body: Record<string, string | number>]
}>()

const geo = ref('')
const volume = ref('')
const price = ref('')
const model = ref('cpl')
const deadline = ref('')
const serviceOn = ref('')
const opensAt = ref('')
const closesAt = ref('')
const models = [
  { title: 'CPL', value: 'cpl' },
  { title: 'CPA', value: 'cpa' },
]

function submit(): void {
  const body: Record<string, string | number> = {
    geo: geo.value.trim().toUpperCase(),
    volume: Number(volume.value),
    price: Number(price.value),
    pricing_model: model.value,
  }
  if (props.kind === 'slot') {
    body.deadline = deadline.value
  } else {
    body.service_on = serviceOn.value
    body.opens_at = opensAt.value
    body.closes_at = closesAt.value
  }
  emit('submit', body)
}
</script>

<style scoped>
.listing-form__head {
  align-items: flex-start;
  display: flex;
  gap: 16px;
  justify-content: space-between;
}

.listing-form__head h2 {
  font-size: 20px;
  letter-spacing: -0.5px;
  margin: 0;
}

.listing-form__head p,
.listing-form__error {
  color: var(--lm-muted);
  font-size: 14px;
  margin: 4px 0 0;
}

.listing-form__error {
  color: #c42f50;
}

.listing-form {
  display: grid;
  gap: 12px;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  margin-top: 16px;
}

@media (max-width: 900px) {
  .listing-form__head {
    display: grid;
  }

  .listing-form {
    grid-template-columns: 1fr;
  }
}
</style>
