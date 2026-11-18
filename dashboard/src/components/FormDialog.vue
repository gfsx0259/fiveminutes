<template>
  <v-dialog
    :model-value="modelValue"
    :max-width="maxWidth"
    @update:model-value="emit('update:modelValue', $event)"
  >
    <v-card>
      <v-card-title>{{ title }}</v-card-title>
      <v-card-text>
        <p v-if="hint">{{ hint }}</p>
        <p v-if="error" class="form-dialog__error">{{ error }}</p>
        <form class="ui-form" @submit.prevent="emit('submit')">
          <slot />
        </form>
      </v-card-text>
      <v-card-actions>
        <UiButton variant="outline" :disabled="loading" @click="emit('update:modelValue', false)">
          Cancel
        </UiButton>
        <UiButton :loading="loading" @click="emit('submit')">{{ submitLabel }}</UiButton>
      </v-card-actions>
    </v-card>
  </v-dialog>
</template>

<script setup lang="ts">
import UiButton from '@/components/ui/UiButton.vue'

withDefaults(defineProps<{
  modelValue: boolean
  title: string
  hint?: string
  error?: string
  submitLabel: string
  loading?: boolean
  maxWidth?: number
}>(), {
  hint: '',
  error: '',
  loading: false,
  maxWidth: 520,
})

const emit = defineEmits<{
  'update:modelValue': [value: boolean]
  submit: []
}>()
</script>
