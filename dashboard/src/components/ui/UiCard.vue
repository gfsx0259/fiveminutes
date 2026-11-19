<template>
  <section class="ui-card">
    <header v-if="title || $slots.actions" class="ui-card__head">
      <div>
        <h2>{{ title }}</h2>
        <p v-if="subtitle" class="ui-card__subtitle">{{ subtitle }}</p>
      </div>
      <div v-if="$slots.actions" class="ui-card__actions">
        <slot name="actions" />
      </div>
    </header>
    <slot />
  </section>
</template>

<script setup lang="ts">
defineProps<{
  title?: string
  subtitle?: string
}>()
</script>

<style scoped>
.ui-card {
  background: #fff;
  border: 1px solid var(--lm-border);
  border-radius: var(--lm-radius);
  box-shadow: var(--lm-shadow);
  box-sizing: border-box;
  min-width: 0;
  padding: 16px;
}

.ui-card__head {
  align-items: flex-start;
  display: flex;
  flex-wrap: wrap;
  gap: 12px;
  justify-content: space-between;
  margin-bottom: 14px;
}

.ui-card__head h2 {
  color: #101024;
  font-size: 18px;
  font-weight: 700;
  letter-spacing: -0.4px;
  margin: 0;
}

.ui-card__subtitle {
  color: var(--lm-muted);
  font-size: 12px;
  margin: 3px 0 0;
}

.ui-card__actions {
  align-items: center;
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}
</style>
