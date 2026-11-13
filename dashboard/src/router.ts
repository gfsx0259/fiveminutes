import { createRouter, createWebHistory } from 'vue-router'
import Leads from '@/pages/Leads.vue'
import Performance from '@/pages/Performance.vue'
import Requests from '@/pages/Requests.vue'
import Slots from '@/pages/Slots.vue'

export const router = createRouter({
  history: createWebHistory(),
  routes: [
    { path: '/', redirect: '/leads' },
    { path: '/leads', component: Leads },
    { path: '/slots', component: Slots },
    { path: '/requests', component: Requests },
    { path: '/performance', component: Performance },
  ],
})
