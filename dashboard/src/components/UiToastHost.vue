<template>
  <v-snackbar v-model="open" location="bottom center" :timeout="timeoutMs">
    <div class="ui-toast" :class="{ 'ui-toast--rich': hasLines }">
      <p class="ui-toast__title">{{ content.title }}</p>
      <p v-for="(line, index) in content.lines" :key="index" class="ui-toast__line">
        {{ line }}
      </p>
    </div>
  </v-snackbar>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { useToastState } from '@/composables/useToast'

const { open, content, timeoutMs } = useToastState()
const hasLines = computed(() => (content.value.lines?.length ?? 0) > 0)
</script>

<style scoped>
.ui-toast {
  text-align: left;
}

.ui-toast__title {
  font-size: 13px;
  font-weight: 600;
  line-height: 1.35;
  margin: 0;
}

.ui-toast--rich .ui-toast__title {
  margin-bottom: 4px;
}

.ui-toast__line {
  color: rgb(255 255 255 / 88%);
  font-size: 12px;
  font-weight: 400;
  line-height: 1.45;
  margin: 0;
}

.ui-toast__line + .ui-toast__line {
  margin-top: 2px;
}
</style>