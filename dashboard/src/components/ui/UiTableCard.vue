<template>
  <section class="ui-table-card">
    <header v-if="title || $slots.actions" class="ui-table-card__head">
      <h2>{{ title }}</h2>
      <div class="ui-table-card__actions">
        <slot name="actions" />
      </div>
    </header>
    <slot />
  </section>
</template>

<script setup lang="ts">
defineProps<{
  title?: string
}>()
</script>

<style scoped>
.ui-table-card {
  background: #fff;
  border: 1px solid var(--lm-border);
  border-radius: var(--lm-radius);
  box-shadow: var(--lm-shadow);
  box-sizing: border-box;
  min-width: 0;
  padding: 14px 16px;
  width: 100%;
}

.ui-table-card__head {
  align-items: center;
  display: flex;
  flex-wrap: wrap;
  gap: 12px;
  justify-content: space-between;
  margin-bottom: 14px;
}

.ui-table-card__head h2 {
  color: #101024;
  font-size: var(--lm-font-section);
  font-weight: 700;
  letter-spacing: -0.5px;
  margin: 0;
}

.ui-table-card__actions {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  margin-left: auto;
}

@media (max-width: 600px) {
  .ui-table-card {
    padding: 12px;
  }

  .ui-table-card__actions {
    margin-left: 0;
    width: 100%;
  }

  .ui-table-card__actions :deep(.v-btn) {
    flex: 1 1 0;
  }
}
</style>
