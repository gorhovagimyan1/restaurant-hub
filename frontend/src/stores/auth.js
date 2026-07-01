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

  return {
    token,
    user,
    loading,
    isAuthenticated,
    roles,
    permissions,
    login,
    fetchMe,
    logout,
  }
})
