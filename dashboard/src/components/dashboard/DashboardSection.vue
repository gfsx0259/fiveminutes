<template>
  <section class="dashboard-section" :data-tone="tone">
    <header class="dashboard-section__head">
      <span class="dashboard-section__icon"><v-icon :icon="icon" size="18" /></span>
      <div class="dashboard-section__text">
        <h2>{{ title }}</h2>
        <p>{{ subtitle }}</p>
      </div>
      <span class="dashboard-section__source">
        <v-icon :icon="sourceIcon" size="14" />
        {{ source }}
      </span>
    </header>
    <slot />
  </section>
</template>

<script setup lang="ts">
withDefaults(defineProps<{
  title: string
  subtitle: string
  icon: string
  source: string
  sourceIcon: string
  tone?: 'market' | 'own'
}>(), {
  tone: 'market',
})
</script>

<style scoped>
.dashboard-section {
  --section-accent: #5b45e8;
  --section-soft: #f1eeff;
  margin-top: 22px;
}

.dashboard-section[data-tone='own'] {
  --section-accent: var(--success-strong);
  --section-soft: var(--success-soft);
}

.dashboard-section__head {
  align-items: center;
  border-bottom: 1px solid var(--lm-border);
  display: flex;
  gap: 12px;
  margin-bottom: 12px;
  padding-bottom: 10px;
}

.dashboard-section__icon {
  align-items: center;
  background: var(--section-soft);
  border-radius: 10px;
  color: var(--section-accent);
  display: inline-flex;
  flex: 0 0 34px;
  height: 34px;
  justify-content: center;
}

.dashboard-section__text {
  flex: 1;
  min-width: 0;
}

.dashboard-section__text h2 {
  color: #101024;
  font-size: 16px;
  font-weight: 700;
  letter-spacing: -0.3px;
  margin: 0;
}

.dashboard-section__text p {
  color: var(--lm-muted);
  font-size: 12px;
  margin: 2px 0 0;
}

.dashboard-section__source {
  align-items: center;
  background: var(--section-soft);
  border-radius: 12px;
  color: var(--section-accent);
  display: inline-flex;
  font-size: 11px;
  font-weight: 500;
  gap: 5px;
  line-height: 1.25;
  padding: 4px 8px;
  white-space: nowrap;
}

@media (max-width: 767px) {
  .dashboard-section__head {
    align-items: flex-start;
    flex-wrap: wrap;
  }

  .dashboard-section__text {
    flex-basis: calc(100% - 46px);
  }

  .dashboard-section__source {
    margin-left: 46px;
  }
}
</style>
