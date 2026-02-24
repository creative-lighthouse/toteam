import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import { apiGet, apiPost } from '@utils/api'

export const useAuthStore = defineStore('auth', () => {
  // State
  const user = ref(null)
  const isAuthenticated = ref(false)
  const loading = ref(false)
  const error = ref(null)
  
  // Getters
  const currentUser = computed(() => user.value)
  const userName = computed(() => {
    if (!user.value) return ''
    return `${user.value.FirstName} ${user.value.Surname}`
  })
  
  // Actions
  async function checkAuth() {
    try {
      loading.value = true
      error.value = null
      
      const response = await apiGet('/auth/check', false) // Don't cache auth check
      
      if (response.authenticated) {
        user.value = response.user
        isAuthenticated.value = true
      } else {
        user.value = null
        isAuthenticated.value = false
      }
    } catch (err) {
      console.error('Auth check failed:', err)
      error.value = err.message
      user.value = null
      isAuthenticated.value = false
    } finally {
      loading.value = false
    }
  }
  
  async function login(email, password) {
    try {
      loading.value = true
      error.value = null
      
      const response = await apiPost('/auth/login', { email, password })
      
      if (response.success) {
        user.value = response.user
        isAuthenticated.value = true
        return true
      } else {
        error.value = response.message || 'Login failed'
        return false
      }
    } catch (err) {
      console.error('Login failed:', err)
      error.value = err.message
      return false
    } finally {
      loading.value = false
    }
  }
  
  async function logout() {
    try {
      loading.value = true
      await apiPost('/auth/logout')
    } catch (err) {
      console.error('Logout failed:', err)
    } finally {
      user.value = null
      isAuthenticated.value = false
      loading.value = false
    }
  }
  
  return {
    // State
    user,
    isAuthenticated,
    loading,
    error,
    // Getters
    currentUser,
    userName,
    // Actions
    checkAuth,
    login,
    logout
  }
})
