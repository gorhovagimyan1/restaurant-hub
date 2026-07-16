import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import http, { TOKEN_KEY } from '@/services/http'

export const useAuthStore = defineStore('auth', () => {
  const token = ref(localStorage.getItem(TOKEN_KEY))
  const user = ref(null)
  const loading = ref(false)

  const isAuthenticated = computed(() => !!token.value)
  const roles = computed(() => user.value?.roles ?? [])
  const permissions = computed(() => user.value?.permissions ?? [])

  function hasRole(role) {
    return roles.value.includes(role)
  }

  function can(permission) {
    return permissions.value.includes(permission)
  }

  // Kitchen/front-of-house staff who should land on the kitchen display rather
  // than the owner dashboard (which they lack permissions for).
  const isKitchenOnly = computed(() => {
    const managerial = ['super-admin', 'restaurant-owner', 'restaurant-manager']
    return roles.value.length > 0 && !roles.value.some((r) => managerial.includes(r))
  })

  // Where a freshly-authenticated user should be sent.
  const homeRoute = computed(() =>
    isKitchenOnly.value ? { name: 'kitchen' } : { name: 'dashboard-overview' },
  )

  function setToken(value) {
    token.value = value
    if (value) {
      localStorage.setItem(TOKEN_KEY, value)
    } else {
      localStorage.removeItem(TOKEN_KEY)
    }
  }

  async function login(credentials) {
    loading.value = true
    try {
      const { data } = await http.post('/auth/login', credentials)
      setToken(data.data.token)
      user.value = data.data.user
      return data.data.user
    } finally {
      loading.value = false
    }
  }

  async function fetchMe() {
    const { data } = await http.get('/auth/me')
    user.value = data.data
    return user.value
  }

  async function logout() {
    try {
      await http.post('/auth/logout')
    } catch {
      // ignore network/expired-token errors on logout
    }
    setToken(null)
    user.value = null
  }

  // Request a password reset email. Returns the API's (deliberately vague)
  // confirmation message.
  async function forgotPassword(email) {
    const { data } = await http.post('/auth/forgot-password', { email })
    return data.message
  }

  // Complete a password reset with the token from the emailed link.
  async function resetPassword(payload) {
    const { data } = await http.post('/auth/reset-password', payload)
    return data.message
  }

  // Update the signed-in user's profile details.
  async function updateProfile(payload) {
    const { data } = await http.put('/auth/profile', payload)
    user.value = data.data
    return user.value
  }

  // Change the signed-in user's password (revokes other sessions server-side).
  async function changePassword(payload) {
    const { data } = await http.put('/auth/password', payload)
    return data.message
  }

  return {
    token,
    user,
    loading,
    isAuthenticated,
    roles,
    permissions,
    hasRole,
    can,
    isKitchenOnly,
    homeRoute,
    login,
    fetchMe,
    logout,
    forgotPassword,
    resetPassword,
    updateProfile,
    changePassword,
  }
})
