<template>
  <v-dialog
    :model-value="modelValue"
    :max-width="maxWidth"
    @update:model-value="emit('update:modelValue', $event)"
  >
    <v-card>
      <v-card-title class="form-dialog__title">
        <span>{{ title }}</span>
        <UiButton
          v-if="shareable"
          variant="ghost"
          icon="mdi-share-variant"
          class="form-dialog__share"
          title="Copy link with current form"
          @click="emit('share')"
        >
          Share
        </UiButton>
      </v-card-title>
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
        <UiButton :loading="loading" :icon="submitIcon" @click="emit('submit')">{{ submitLabel }}</UiButton>
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
  submitIcon?: string
  shareable?: boolean
}>(), {
  hint: '',
  error: '',
  loading: false,
  maxWidth: 520,
  submitIcon: '',
  shareable: false,
})

const emit = defineEmits<{
  'update:modelValue': [value: boolean]
  submit: []
  share: []
}>()
</script>

<style scoped>
.form-dialog__title {
  align-items: center;
  display: flex;
  gap: 12px;
  justify-content: space-between;
}

.form-dialog__share {
  flex-shrink: 0;
  margin-left: auto;
}
</style>
