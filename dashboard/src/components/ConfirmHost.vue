<template>
  <FormDialog
    :model-value="Boolean(request)"
    :title="request?.title ?? ''"
    :hint="request?.text ?? ''"
    :submit-label="request?.confirmLabel ?? 'Confirm'"
    :submit-icon="request?.danger ? 'mdi-alert-outline' : 'mdi-check'"
    :max-width="460"
    @update:model-value="(open: boolean) => { if (!open) settleConfirm(null) }"
    @submit="settleConfirm(choice || 'yes')"
  >
    <div v-if="request?.choices?.length" class="confirm-choices" role="radiogroup">
      <label v-for="item in request.choices" :key="item.value" class="confirm-choice">
        <input v-model="choice" type="radio" :value="item.value">
        <span>
          <strong>{{ item.label }}</strong>
          <small v-if="item.hint">{{ item.hint }}</small>
        </span>
      </label>
    </div>
  </FormDialog>
</template>

<script setup lang="ts">
import { ref, watch } from 'vue'
import FormDialog from '@/components/FormDialog.vue'
import { settleConfirm, useConfirmState } from '@/composables/useConfirm'

const request = useConfirmState()
const choice = ref('')

watch(request, (value) => {
  choice.value = value?.choices?.[0]?.value ?? ''
})
</script>

<style scoped>
.confirm-choices {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.confirm-choice {
  align-items: flex-start;
  border: 1px solid var(--lm-border);
  border-radius: 10px;
  cursor: pointer;
  display: flex;
  gap: 10px;
  padding: 10px 12px;
}

.confirm-choice input {
  accent-color: var(--lm-primary);
  margin-top: 3px;
}

.confirm-choice span {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.confirm-choice small {
  color: var(--lm-muted);
  font-size: 12px;
}
</style>
