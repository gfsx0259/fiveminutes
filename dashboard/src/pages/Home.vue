<template>
  <span />
</template>

<script setup lang="ts">
import { useRouter } from 'vue-router'
import { useSessionStore } from '@/stores/session'

const session = useSessionStore()

const role = session.payload?.user?.role
const partnerHome = ['/dashboard', '/stock', '/orders'].find((path) => session.canOpen(path)) ?? '/leads'
const home = role === 'partner' ? partnerHome : role === 'buyer' ? '/demands' : '/leads'
void useRouter().replace(home)
</script>
