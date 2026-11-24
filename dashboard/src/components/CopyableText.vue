<template>
  <span
    class="copyable"
    :class="{ 'copyable--truncate': truncate }"
    :style="truncate ? { maxWidth } : undefined"
    :title="truncate && text ? text : copyTitle"
    role="button"
    tabindex="0"
    @click.stop="handleCopy"
    @keydown.enter.prevent="handleCopy"
  ><slot>{{ text }}</slot></span>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { copyText } from '@/copyText'
import { showToast } from '@/composables/useToast'

const props = withDefaults(defineProps<{
  text?: string | null
  maxWidth?: string
  truncate?: boolean
  toastLabel?: string
}>(), {
  text: '',
  maxWidth: '150px',
  truncate: false,
  toastLabel: 'Copied',
})

const copyTitle = computed(() => {
  const value = props.text ?? ''
  return value ? 'Click to copy' : undefined
})

async function handleCopy(): Promise<void> {
  const value = props.text ?? ''
  if (!value) return
  if (!await copyText(value)) {
    showToast('Copy unavailable')
    return
  }
  showToast(props.toastLabel)
}
</script>

<style scoped>
.copyable {
  cursor: pointer;
}

.copyable--truncate {
  display: inline-block;
  overflow: hidden;
  text-overflow: ellipsis;
  vertical-align: bottom;
  white-space: nowrap;
}

.copyable:not(.copyable--truncate) {
  word-break: break-all;
}

.copyable:hover {
  color: #3512bc;
}
</style>