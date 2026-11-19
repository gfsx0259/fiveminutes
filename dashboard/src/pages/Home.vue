<template>
  <span />
</template>

<script setup lang="ts">
import { useRouter } from 'vue-router'
import { useSessionStore } from '@/stores/session'

const session = useSessionStore()

void useRouter().replace(session.payload?.user?.role === 'partner' ? '/dashboard' : '/leads')
</script>
