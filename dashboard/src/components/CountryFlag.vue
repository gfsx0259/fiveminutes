<template>
  <img
    v-if="!hasError"
    :src="src"
    :alt="code"
    :width="size"
    :height="size"
    class="country-flag"
    @error="hasError = true"
  />
  <v-icon v-else :size="size" color="primary" icon="mdi-flag" />
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue'

const props = withDefaults(defineProps<{
  code: string
  size?: number
}>(), {
  size: 18,
})

const hasError = ref(false)

watch(() => props.code, () => {
  hasError.value = false
})

const src = computed(() =>
  `https://hatscripts.github.io/circle-flags/flags/${props.code.toLowerCase()}.svg`,
)
</script>

<style scoped>
.country-flag {
  border-radius: 50%;
  display: block;
  flex-shrink: 0;
  object-fit: cover;
}
</style>
