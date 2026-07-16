import { createRouter, createWebHistory } from 'vue-router'
import { TOKEN_KEY } from '@/services/http'
import { activeDiningSlug } from '@/stores/dining'
import { useAuthStore } from '@/stores/auth'
import CustomerLayout from '@/layouts/CustomerLayout.vue'
import DashboardLayout from '@/layouts/DashboardLayout.vue'
import KitchenLayout from '@/layouts/KitchenLayout.vue'

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes: [
    {
      // Root: continue an active dining session, otherwise ask for a scan.
      path: '/',
      redirect: () => {
        const slug = activeDiningSlug()
        return slug ? `/r/${slug}` : { name: 'scan-required' }
      },
    },
    {
      path: '/scan',
      name: 'scan-required',
      component: () => import('@/views/customer/ScanRequired.vue'),
    },
    {
      path: '/login',
      name: 'login',
      component: () => import('@/views/dashboard/LoginView.vue'),
    },
    {
      path: '/forgot-password',
      name: 'forgot-password',
      component: () => import('@/views/dashboard/ForgotPasswordView.vue'),
    },
    {
      path: '/reset-password',
      name: 'reset-password',
      component: () => import('@/views/dashboard/ResetPasswordView.vue'),
    },
    {
      // Standalone so both managerial and kitchen staff can reach it without
      // tripping the DashboardLayout's kitchen-only redirect.
      path: '/profile',
      name: 'profile',
      component: () => import('@/views/dashboard/ProfileView.vue'),
      meta: { requiresAuth: true },
    },
    {
      path: '/dashboard',
      component: DashboardLayout,
      meta: { requiresAuth: true },
      children: [
        {
          path: '',
          name: 'dashboard-menu',
          component: () => import('@/views/dashboard/MenuManager.vue'),
        },
        {
          path: 'orders',
          name: 'dashboard-orders',
          component: () => import('@/views/dashboard/OrdersBoard.vue'),
        },
        {
          path: 'orders/history',
          name: 'dashboard-orders-history',
          component: () => import('@/views/dashboard/OrdersHistory.vue'),
        },
        {
          path: 'tables',
          name: 'dashboard-tables',
          component: () => import('@/views/dashboard/TablesManager.vue'),
        },
        {
          path: 'team',
          name: 'dashboard-team',
          component: () => import('@/views/dashboard/EmployeesManager.vue'),
        },
        {
          path: 'settings',
          name: 'dashboard-settings',
          component: () => import('@/views/dashboard/RestaurantSettings.vue'),
        },
      ],
    },
    {
      // Kitchen display — minimal full-screen board for kitchen/waiter staff.
      path: '/kitchen',
      component: KitchenLayout,
      meta: { requiresAuth: true },
      children: [
        {
          path: '',
          name: 'kitchen',
          component: () => import('@/views/dashboard/KitchenBoard.vue'),
        },
      ],
    },
    {
      // Landing target of a scanned table QR code — opens a dining session
      // then redirects into the restaurant portal below.
      path: '/t/:token',
      name: 'table-order',
      component: () => import('@/views/customer/TableEntry.vue'),
    },
    {
      // Customer portal. Gated: only reachable with a dining session started
      // by scanning a QR (see the guard below).
      path: '/r/:slug',
      component: CustomerLayout,
      meta: { requiresDining: true },
      children: [
        {
          path: '',
          name: 'restaurant-home',
          component: () => import('@/views/customer/CustomerHome.vue'),
        },
        {
          path: 'menu',
          name: 'restaurant-menu',
          component: () => import('@/views/customer/MenuView.vue'),
        },
      ],
    },
    {
      path: '/:pathMatch(.*)*',
      redirect: { name: 'scan-required' },
    },
  ],
  scrollBehavior(to, from, savedPosition) {
    if (savedPosition) return savedPosition
    // Hash scrolling is handled inside MenuView so the sticky bars are offset.
    if (to.hash) return false
    return { top: 0 }
  },
})

router.beforeEach((to) => {
  const hasToken = !!localStorage.getItem(TOKEN_KEY)
  if (to.meta.requiresAuth && !hasToken) {
    return { name: 'login', query: { redirect: to.fullPath } }
  }
  if (to.name === 'login' && hasToken) {
    // Send already-authenticated users to their role's home (kitchen staff to
    // the kitchen display, everyone else to the owner dashboard).
    return useAuthStore().homeRoute
  }

  // The customer menu portal only opens for a table whose QR was scanned.
  if (to.meta.requiresDining) {
    const slug = activeDiningSlug()
    if (!slug || slug !== to.params.slug) {
      return { name: 'scan-required' }
    }
  }

  return true
})

export default router
