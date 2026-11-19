<template>
  <div class="ui-field" :class="{ 'ui-field--icon': icon }">
    <span class="ui-field__label">{{ label }}</span>
    <div class="ui-field__control">
      <v-icon v-if="icon" class="ui-field__icon" :icon="icon" size="18" />
      <slot />
    </div>
  </div>
</template>

<script setup lang="ts">
defineProps<{
  label: string
  icon?: string
}>()
</script>

<style scoped>
.ui-field {
  display: flex;
  flex-direction: column;
  gap: 6px;
  min-width: 0;
  overflow: visible;
  padding: 2px 1px;
}

.ui-field__label {
  color: #5c6078;
  font-size: 12px;
  font-weight: 600;
  line-height: 1.2;
}

.ui-field__control {
  min-width: 0;
  position: relative;
  width: 100%;
}

.ui-field__icon {
  color: var(--lm-primary-deep);
  left: 12px;
  pointer-events: none;
  position: absolute;
  top: 50%;
  transform: translateY(-50%);
  z-index: 2;
}

.ui-field :deep(.v-input) {
  display: block;
}

.ui-field :deep(.v-field) {
  --v-field-input-padding-bottom: 0px;
  --v-field-input-padding-top: 0px;
  --v-field-padding-bottom: 0px;
  --v-field-padding-top: 0px;
  background: #fff;
  border: 1px solid #cdd0e6;
  border-radius: 9px;
  box-shadow: 0 2px 3px #22225504;
  contain: none;
  font-size: 13px;
  min-height: var(--lm-control-height);
  overflow: visible;
}

.ui-field :deep(.v-field--focused) {
  border-color: #9486ff;
}

.ui-field :deep(.v-field__outline) {
  display: none;
}

.ui-field :deep(.v-field-label),
.ui-field :deep(.v-label) {
  display: none !important;
}

.ui-field :deep(.v-field__input) {
  align-items: center;
  font-size: 13px;
  min-height: var(--lm-control-height);
  padding-bottom: 0;
  padding-top: 0;
}

.ui-field :deep(.v-field__input > input) {
  height: 100%;
  line-height: var(--lm-control-height);
  top: 0;
}

.ui-field--icon :deep(.v-field__input) {
  padding-inline-start: 38px;
}

.ui-field--icon :deep(.ui-select) {
  padding-inline-start: 38px;
}

.ui-field--icon :deep(.ui-input) {
  padding-inline-start: 38px;
}

.ui-field :deep(.v-field__append-inner),
.ui-field :deep(.v-field__clearable) {
  align-items: center;
  padding-top: 0;
}

.ui-field :deep(input::placeholder) {
  color: var(--lm-muted);
  opacity: 0.8;
}

.ui-field :deep(.ui-date) {
  box-shadow: 0 2px 3px #22225504;
}

.ui-field--icon :deep(.ui-date > button) {
  display: none;
}

.ui-field--icon :deep(.ui-date input) {
  padding-inline-start: 38px;
}

.ui-field :deep(.ui-time) {
  box-shadow: 0 2px 3px #22225504;
}

.ui-field--icon :deep(.ui-time__input) {
  padding-inline-start: 38px;
}
</style>

<style>
.ui-field--icon:has(.ui-select--conceal-icon) .ui-field__icon {
  display: none;
}

.ui-field--icon:has(.ui-select--conceal-icon) > .ui-field__control > .ui-field__icon {
  display: none;
}

.ui-field--icon:has(.ui-select--conceal-icon) .ui-select {
  padding-inline-start: 12px;
}
</style>