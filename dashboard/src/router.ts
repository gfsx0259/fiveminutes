import { documentTitle } from '@/pageTitle'
import { useSessionStore } from '@/stores/session'
import { createRouter, createWebHistory } from 'vue-router'
import Caps from '@/pages/Caps.vue'
import Dashboard from '@/pages/Dashboard.vue'
import Home from '@/pages/Home.vue'
import Leads from '@/pages/Leads.vue'
import Offers from '@/pages/Offers.vue'
import Orders from '@/pages/Orders.vue'
import Performance from '@/pages/Performance.vue'
import PresetPage from '@/pages/PresetPage.vue'
import Reports from '@/pages/Reports.vue'
import Stock from '@/pages/Stock.vue'
import Transactions from '@/pages/Transactions.vue'
import Users from '@/pages/Users.vue'
import Account from '@/pages/Account.vue'

export const router = createRouter({
  history: createWebHistory(),
  routes: [
    { path: '/', component: Home },
    { path: '/dashboard', component: Dashboard },
    { path: '/reports', component: Reports },
    { path: '/transactions', component: Transactions },
    { path: '/leads', component: Leads },
    { path: '/send-lead', redirect: '/leads' },
    { path: '/presets', component: PresetPage },
    { path: '/orders', component: Orders },
    { path: '/stock', component: Stock },
    { path: '/caps', component: Caps },
    { path: '/offers', component: Offers },
    { path: '/performance', component: Performance },
    { path: '/users', component: Users },
    { path: '/account', component: Account },
  ],
})

router.afterEach((to) => {
  const role = useSessionStore().payload?.user?.role
  document.title = documentTitle(to.path, role)
})
