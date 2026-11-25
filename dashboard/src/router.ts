import { documentTitle } from '@/pageTitle'
import { useSessionStore } from '@/stores/session'
import { createRouter, createWebHistory } from 'vue-router'
import Connections from '@/pages/Connections.vue'
import Dashboard from '@/pages/Dashboard.vue'
import Demands from '@/pages/Demands.vue'
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
    { path: '/connections', component: Connections },
    { path: '/send-lead', redirect: '/connections' },
    { path: '/presets', component: PresetPage },
    { path: '/orders', component: Orders },
    { path: '/stock', component: Stock },
    { path: '/demands', component: Demands },
    { path: '/caps', redirect: '/demands' },
    {
      path: '/offers',
      component: Offers,
      beforeEnter: () => {
        if (useSessionStore().payload?.user?.role === 'buyer') {
          return '/demands'
        }
      },
    },
    { path: '/performance', component: Performance },
    { path: '/users', component: Users },
    { path: '/account', component: Account },
  ],
})

router.afterEach((to) => {
  const role = useSessionStore().payload?.user?.role
  document.title = documentTitle(to.path, role)
})
