import { createRouter, createWebHistory } from 'vue-router'
import { DEFAULT_RESTAURANT_SLUG } from '@/config'
import { TOKEN_KEY } from '@/services/http'
import CustomerLayout from '@/layouts/CustomerLayout.vue'
import DashboardLayout from '@/layouts/DashboardLayout.vue'

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes: [
    {
      path: '/',
      redirect: `/r/${DEFAULT_RESTAURANT_SLUG}`,
    },
    {
      path: '/login',
      name: 'login',
      component: () => import('@/views/dashboard/LoginView.vue'),
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
      ],
    },
    {
      path: '/r/:slug',
      component: CustomerLayout,
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
      redirect: `/r/${DEFAULT_RESTAURANT_SLUG}`,
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
    return { name: 'dashboard-menu' }
  }
  return true
})

export default router
