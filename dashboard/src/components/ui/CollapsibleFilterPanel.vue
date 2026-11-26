<template>
  <section
    class="ui-filter-panel ui-filter-panel--collapsible"
    :class="{ 'ui-filter-panel--expanded': bodyVisible }"
    v-bind="$attrs"
  >
    <div v-if="collapse" class="ui-filter-panel__mobile-bar">
      <UiButton
        class="ui-filter-panel__toggle"
        variant="outline"
        :icon="bodyVisible ? 'mdi-chevron-up' : 'mdi-tune-variant'"
        @click="toggleBody"
      >
        Filters
        <span v-if="activeCount > 0" class="ui-filter-panel__badge">{{ activeCount }}</span>
      </UiButton>
      <div v-if="$slots['mobile-actions']" class="ui-filter-panel__mobile-actions">
        <slot name="mobile-actions" />
      </div>
    </div>
    <div v-show="bodyVisible" class="ui-filter-panel__body">
      <slot />
    </div>
  </section>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import UiButton from '@/components/ui/UiButton.vue'
import { useFilterPanelCollapse } from '@/viewport'

defineOptions({ inheritAttrs: false })

const props = withDefaults(defineProps<{
  activeCount?: number
}>(), {
  activeCount: 0,
})

const collapse = useFilterPanelCollapse()
const userToggled = ref(false)
const bodyOpen = ref(false)

const bodyVisible = computed(() => {
  if (!collapse.value) {
    return true
  }
  if (!userToggled.value) {
    return false
  }
  return bodyOpen.value
})

function toggleBody(): void {
  if (!userToggled.value) {
    userToggled.value = true
    bodyOpen.value = true
    return
  }
  bodyOpen.value = !bodyOpen.value
}

watch(() => props.activeCount, (count) => {
  if (!collapse.value || userToggled.value) {
    return
  }
  if (count > 0) {
    userToggled.value = true
    bodyOpen.value = true
  }
})
</script>

<style scoped>
.ui-filter-panel__mobile-bar {
  align-items: center;
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  justify-content: space-between;
}

.ui-filter-panel__toggle {
  flex: 1 1 auto;
  justify-content: center;
  min-width: 0;
}

.ui-filter-panel__mobile-actions {
  display: flex;
  flex: 0 0 auto;
  gap: 8px;
}

.ui-filter-panel__badge {
  align-items: center;
  background: #3512bc;
  border-radius: 999px;
  color: #fff;
  display: inline-flex;
  font-size: 11px;
  font-weight: 700;
  justify-content: center;
  line-height: 1;
  margin-left: 6px;
  min-width: 18px;
  padding: 2px 6px;
}

@media (min-width: 1281px) {
  .ui-filter-panel__mobile-bar {
    display: none;
  }
}

@media (max-width: 1280px) {
  .ui-filter-panel--collapsible:not(.ui-filter-panel--expanded) {
    margin-bottom: 8px;
    padding: 10px 12px;
  }

  .ui-filter-panel--collapsible.ui-filter-panel--expanded .ui-filter-panel__body {
    margin-top: 12px;
  }
}
</style>