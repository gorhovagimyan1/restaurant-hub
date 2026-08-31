import axios from 'axios'

export const TOKEN_KEY = 'rh_token'

// Base URL is proxied to the Laravel backend in dev (see vite.config.js).
// Override with VITE_API_URL for other environments.
const http = axios.create({
  baseURL: import.meta.env.VITE_API_URL || '/api',
  headers: {
    Accept: 'application/json',
  },
})

// Attach the bearer token (if any) to every request.
http.interceptors.request.use((config) => {
  const token = localStorage.getItem(TOKEN_KEY)
  if (token) {
    config.headers.Authorization = `Bearer ${token}`
  }
  return config
})

// On 401, drop the token and bounce to the login screen. On 402, the
// restaurant's trial or subscription has lapsed — send them to checkout. On 409
// from the public QR flow, the table's dining session has ended (bill settled)
// — clear it and send the guest to the "scan again" screen.
http.interceptors.response.use(
  (response) => response,
  async (error) => {
    const status = error?.response?.status
    const url = error?.config?.url || ''

    if (status === 401) {
      localStorage.removeItem(TOKEN_KEY)
      const { default: router } = await import('@/router')
      if (router.currentRoute.value.name !== 'login') {
        router.push({ name: 'login' })
      }
    }

    // Payment Required: every gated dashboard endpoint answers this once the
    // trial runs out, so catching it here means no screen has to check.
    if (status === 402) {
      const { default: router } = await import('@/router')
      if (router.currentRoute.value.name !== 'checkout') {
        router.push({ name: 'checkout' })
      }
    }

    if (status === 409 && url.includes('/public/tables/')) {
      const { useDiningStore } = await import('@/stores/dining')
      useDiningStore().end()
      const { default: router } = await import('@/router')
      if (router.currentRoute.value.name !== 'scan-required') {
        router.push({ name: 'scan-required' })
      }
    }

    return Promise.reject(error)
  },
)

export default http
