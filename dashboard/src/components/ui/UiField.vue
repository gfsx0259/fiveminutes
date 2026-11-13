<template>
  <div class="ui-field" :class="{ 'ui-field--icon': icon }">
    <v-icon v-if="icon" class="ui-field__icon" :icon="icon" size="18" />
    <slot />
    <span class="ui-field__label">{{ label }}</span>
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
  display: block;
  min-width: 0;
  overflow: visible;
  position: relative;
}

.ui-field__label {
  background: #fff;
  color: var(--lm-foreground);
  font-size: 11px;
  font-weight: 600;
  left: 12px;
  line-height: 16px;
  padding: 0 5px;
  pointer-events: none;
  position: absolute;
  top: -8px;
  z-index: 4;
}

.ui-field--icon .ui-field__label {
  left: 34px;
}

.ui-field__icon {
  color: var(--lm-primary-deep);
  left: 12px;
  pointer-events: none;
  position: absolute;
  top: 11px;
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

.ui-field :deep(.v-field__append-inner),
.ui-field :deep(.v-field__clearable) {
  align-items: center;
  padding-top: 0;
}

.ui-field :deep(input::placeholder) {
  color: var(--lm-muted);
  opacity: 0.8;
}
</style>

<style>
.ui-field--icon:has(.ui-select--conceal-icon) > .ui-field__icon {
  display: none;
}

.ui-field--icon:has(.ui-select--conceal-icon) > .ui-field__label {
  left: 12px;
}

.ui-field--icon:has(.ui-select--conceal-icon) .ui-select {
  padding-inline-start: 12px;
}
</style>
