<template>
  <v-snackbar v-model="open" location="bottom center" :timeout="2500">
    {{ message }}
  </v-snackbar>
</template>

<script setup lang="ts">
import { useToastState } from '@/composables/useToast'

const { open, message } = useToastState()
</script>