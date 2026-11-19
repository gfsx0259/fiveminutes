<template>
  <div class="ui-labeled-field">
    <span class="ui-labeled-field__label">{{ label }}</span>
    <slot />
  </div>
</template>

<script setup lang="ts">
defineProps<{
  label: string
}>()
</script>

<style scoped>
.ui-labeled-field {
  display: flex;
  flex-direction: column;
  gap: 6px;
  min-width: 0;
}

.ui-labeled-field__label {
  color: #5c6078;
  font-size: 12px;
  font-weight: 600;
  line-height: 1.2;
}

.ui-labeled-field :deep(.ui-select) {
  width: 100%;
}
</style>