<template>
  <v-menu
    v-model="open"
    :close-on-content-click="true"
    content-class="ui-select-menu"
    location="bottom end"
    :min-width="menuMinWidth"
    offset="6"
  >
    <template #activator="{ props: menuProps }">
      <slot name="activator" :menu-props="menuProps" />
    </template>

    <div class="ui-select__menu ui-action-menu__panel" :style="panelStyle">
      <div class="ui-select__options" role="menu">
        <button
          v-for="item in items"
          :key="item.id"
          type="button"
          class="ui-select__option"
          role="menuitem"
          :disabled="item.disabled"
          @click="onPick(item)"
        >
          <v-icon v-if="item.icon" class="ui-action-menu__icon" :icon="item.icon" size="18" />
          <span>{{ item.title }}</span>
        </button>
      </div>
    </div>
  </v-menu>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'

export type UiActionMenuItem = {
  id: string
  title: string
  icon?: string
  disabled?: boolean
}

const props = withDefaults(defineProps<{
  items: UiActionMenuItem[]
  menuMinWidth?: number
}>(), {
  menuMinWidth: 168,
})

const emit = defineEmits<{
  select: [id: string]
}>()

const open = ref(false)

const panelStyle = computed(() => ({
  minWidth: `${props.menuMinWidth}px`,
}))

function onPick(item: UiActionMenuItem): void {
  if (item.disabled) {
    return
  }
  open.value = false
  emit('select', item.id)
}
</script>

<style scoped>
.ui-action-menu__panel {
  width: max-content;
}

.ui-action-menu__icon {
  color: #343b69;
  flex: 0 0 auto;
}

.ui-select__menu {
  background: #fff;
  border: 1px solid var(--lm-border);
  border-radius: 12px;
  box-shadow: 0 8px 24px #27206414, var(--lm-shadow);
  display: flex;
  flex-direction: column;
  max-height: 320px;
  padding: 6px;
}

.ui-select__options {
  display: flex;
  flex-direction: column;
  gap: 3px;
  min-height: 0;
}

.ui-select__option {
  align-items: center;
  appearance: none;
  background: transparent;
  border: 0;
  border-radius: 8px;
  color: var(--lm-foreground);
  cursor: pointer;
  display: flex;
  font: inherit;
  font-size: 13px;
  font-weight: 600;
  gap: 9px;
  line-height: 20px;
  min-height: 36px;
  padding: 7px 9px;
  text-align: left;
  transition: background-color 160ms ease-out, color 160ms ease-out;
  width: 100%;
}

.ui-select__option > span {
  flex: 1;
  min-width: 0;
}

.ui-select__option:hover,
.ui-select__option:focus-visible {
  background: var(--lm-lavender);
  color: var(--lm-primary-deep);
  outline: none;
}

.ui-select__option:disabled {
  cursor: not-allowed;
  opacity: 0.4;
}
</style>

<style>
.ui-select-menu.v-overlay__content {
  background: transparent;
  box-shadow: none;
}
</style>