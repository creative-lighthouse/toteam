import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import { apiPost, setAccessToken, refreshAccessToken } from '@utils/api'

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

  // Whether a given Totem (feature module, e.g. 'calendar', 'food') is enabled
  // for the current user's organization(s). Defaults to visible when unknown.
  function hasTotem(totemKey) {
    const enabledTotems = user.value?.EnabledTotems
    if (!enabledTotems || !(totemKey in enabledTotems)) return true
    return !!enabledTotems[totemKey]
  }

  // Actions

  /**
   * Called once on app start (router guard). Tries to silently obtain a
   * fresh access token from the httpOnly refresh cookie — if that succeeds
   * the user is still logged in from a previous visit, otherwise they're not.
   */
  async function checkAuth() {
    try {
      loading.value = true
      error.value = null

      // Shared, cross-tab-locked refresh (see utils/api.js) — also stores the
      // new access token, so the first API call doesn't have to refresh again
      const response = await refreshAccessToken()

      if (response?.success) {
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

  /**
   * Step 1 of passwordless login/signup: request a code for `email`.
   * Returns { success, isNewAccount } — the caller shows the code-entry step
   * either way, since the backend never reveals whether the account exists.
   */
  async function requestCode(email) {
    try {
      loading.value = true
      error.value = null

      const response = await apiPost('/auth/requestCode', { email })
      if (!response.success) {
        error.value = response.error || 'Code konnte nicht verschickt werden.'
      }
      return response
    } catch (err) {
      console.error('requestCode failed:', err)
      error.value = err.message
      return { success: false }
    } finally {
      loading.value = false
    }
  }

  /**
   * Step 2: verify the code. `profile` ({firstName, surname}) is only needed
   * when the backend previously responded with needsProfile: true.
   */
  async function verifyCode(email, code, profile = {}) {
    try {
      loading.value = true
      error.value = null

      const response = await apiPost('/auth/verifyCode', {
        email,
        code,
        firstName: profile.firstName,
        surname: profile.surname,
      })

      if (response.success) {
        setAccessToken(response.accessToken)
        user.value = response.user
        isAuthenticated.value = true
      } else if (!response.needsProfile) {
        error.value = response.error || response.message || 'Code ungültig.'
      }

      return response
    } catch (err) {
      console.error('verifyCode failed:', err)
      error.value = err.message
      return { success: false }
    } finally {
      loading.value = false
    }
  }

  /**
   * Alternative to the code flow: email + password, for members who've set
   * one (see EditProfileModal's password section / ProfileApiController::setPassword()).
   */
  async function loginWithPassword(email, password) {
    try {
      loading.value = true
      error.value = null

      const response = await apiPost('/auth/loginPassword', { email, password })

      if (response.success) {
        setAccessToken(response.accessToken)
        user.value = response.user
        isAuthenticated.value = true
      } else {
        error.value = response.error || 'Anmeldung fehlgeschlagen.'
      }

      return response
    } catch (err) {
      console.error('loginWithPassword failed:', err)
      error.value = err.message
      return { success: false }
    } finally {
      loading.value = false
    }
  }

  /**
   * "Forgot password" step 1: request a reset code. Always looks successful
   * to the caller (generic backend response), regardless of whether the
   * account/password actually exists.
   */
  async function requestPasswordReset(email) {
    try {
      loading.value = true
      error.value = null

      const response = await apiPost('/auth/requestPasswordReset', { email })
      if (!response.success) {
        error.value = response.error || 'Code konnte nicht verschickt werden.'
      }
      return response
    } catch (err) {
      console.error('requestPasswordReset failed:', err)
      error.value = err.message
      return { success: false }
    } finally {
      loading.value = false
    }
  }

  /**
   * "Forgot password" step 2: verify the code and set a new password. Logs
   * the member in on success, same as the other login flows.
   */
  async function resetPassword(email, code, newPassword) {
    try {
      loading.value = true
      error.value = null

      const response = await apiPost('/auth/resetPassword', { email, code, newPassword })

      if (response.success) {
        setAccessToken(response.accessToken)
        user.value = response.user
        isAuthenticated.value = true
      } else {
        error.value = response.error || 'Code ungültig.'
      }

      return response
    } catch (err) {
      console.error('resetPassword failed:', err)
      error.value = err.message
      return { success: false }
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
      setAccessToken(null)
      user.value = null
      isAuthenticated.value = false
      loading.value = false
    }
  }

  function updateUser(data) {
    if (user.value) {
      Object.assign(user.value, data)
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
    requestCode,
    verifyCode,
    loginWithPassword,
    requestPasswordReset,
    resetPassword,
    logout,
    updateUser,
    hasTotem
  }
})
