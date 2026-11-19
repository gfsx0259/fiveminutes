<template>
  <v-btn
    class="ui-btn"
    :class="`ui-btn--${variant}`"
    :prepend-icon="icon"
    :loading="loading"
    :disabled="disabled"
    variant="flat"
    @click="emit('click', $event)"
  >
    <slot />
  </v-btn>
</template>

<script setup lang="ts">
withDefaults(defineProps<{
  variant?: 'primary' | 'outline' | 'ghost'
  icon?: string
  loading?: boolean
  disabled?: boolean
}>(), {
  variant: 'primary',
  loading: false,
  disabled: false,
})

const emit = defineEmits<{
  click: [event: MouseEvent]
}>()
</script>

<style scoped>
.ui-btn {
  border-radius: 10px;
  font-size: 13px;
  font-weight: 600;
  height: var(--lm-control-height);
  letter-spacing: 0;
  padding-inline: 14px;
  text-transform: none;
}

.ui-btn :deep(.v-btn__content) {
  align-items: center;
  line-height: 1.2;
}

.ui-btn :deep(.v-btn__prepend) {
  align-self: center;
  margin-inline-end: 6px;
}

.ui-btn :deep(.v-btn__prepend .v-icon) {
  font-size: calc(1em + 3px);
  height: calc(1em + 3px);
  width: calc(1em + 3px);
}

.ui-btn--primary {
  background: linear-gradient(120deg, #6650ee, #5339e5) !important;
  box-shadow: 0 2px 3px #4933bf12;
  color: #fff !important;
}

.ui-btn--outline {
  background: #fff !important;
  border: 1px solid #9685ff !important;
  color: #3512bc !important;
}

.ui-btn--outline :deep(.v-btn__overlay) {
  opacity: 0 !important;
}

.ui-btn--outline:hover {
  filter: brightness(0.98);
}

.ui-btn--outline.v-btn--disabled {
  opacity: 0.52;
}

.ui-btn--ghost {
  background: transparent !important;
  color: #3512bc !important;
}

.ui-btn--ghost:hover {
  background: #efecff !important;
}
</style>
