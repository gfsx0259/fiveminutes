import { createRouter, createWebHistory } from 'vue-router'
import Caps from '@/pages/Caps.vue'
import Leads from '@/pages/Leads.vue'
import Offers from '@/pages/Offers.vue'
import Orders from '@/pages/Orders.vue'
import Performance from '@/pages/Performance.vue'
import PresetPage from '@/pages/PresetPage.vue'
import Stock from '@/pages/Stock.vue'

export const router = createRouter({
  history: createWebHistory(),
  routes: [
    { path: '/', redirect: '/leads' },
    { path: '/leads', component: Leads },
    { path: '/send-lead', redirect: '/leads' },
    { path: '/presets', component: PresetPage },
    { path: '/orders', component: Orders },
    { path: '/stock', component: Stock },
    { path: '/caps', component: Caps },
    { path: '/offers', component: Offers },
    { path: '/performance', component: Performance },
  ],
})
