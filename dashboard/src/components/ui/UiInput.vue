<template>
  <input
    v-bind="$attrs"
    class="ui-input"
    :type="type"
    :value="modelValue ?? ''"
    :disabled="disabled"
    :placeholder="placeholder"
    @input="onInput"
  >
</template>

<script setup lang="ts">
withDefaults(defineProps<{
  modelValue?: string | number | null
  type?: string
  placeholder?: string
  disabled?: boolean
}>(), {
  modelValue: '',
  type: 'text',
  placeholder: '',
  disabled: false,
})

const emit = defineEmits<{
  'update:modelValue': [value: string]
}>()

const onInput = (event: Event) => {
  emit('update:modelValue', (event.target as HTMLInputElement).value)
}
</script>

<style scoped>
/* Prototype input:not([type=checkbox]) */
.ui-input {
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

.ui-input::placeholder {
  color: var(--lm-muted);
  opacity: 0.8;
}

.ui-input:focus,
.ui-input:focus-visible {
  border-color: #9486ff;
  box-shadow: 0 0 0 3px rgb(148 134 255 / 38%);
  outline: none;
}

.ui-input:disabled {
  cursor: not-allowed;
  opacity: 0.52;
}
</style>
