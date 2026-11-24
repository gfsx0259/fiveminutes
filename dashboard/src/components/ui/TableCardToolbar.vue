<template>
  <UiButton
    variant="outline"
    icon="mdi-refresh"
    :loading="refreshLoading"
    @click="emit('refresh')"
  >
    Refresh
  </UiButton>
  <UiActionMenu :items="menuItems" @select="onMenuSelect">
    <template #activator="{ menuProps }">
      <UiButton v-bind="menuProps" variant="outline" icon="mdi-dots-horizontal">
        More
      </UiButton>
    </template>
  </UiActionMenu>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import UiActionMenu from '@/components/ui/UiActionMenu.vue'
import UiButton from '@/components/ui/UiButton.vue'

const props = defineProps<{
  refreshLoading?: boolean
  exportDisabled?: boolean
  shareDisabled?: boolean
}>()

const emit = defineEmits<{
  refresh: []
  export: []
  share: []
}>()

const menuItems = computed(() => [
  {
    id: 'export',
    title: 'Export',
    icon: 'mdi-download',
    disabled: props.exportDisabled,
  },
  {
    id: 'share',
    title: 'Share',
    icon: 'mdi-share-variant',
    disabled: props.shareDisabled,
  },
])

function onMenuSelect(id: string): void {
  if (id === 'export') {
    emit('export')
    return
  }
  if (id === 'share') {
    emit('share')
  }
}
</script>