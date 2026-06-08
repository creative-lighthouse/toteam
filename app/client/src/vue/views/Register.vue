<template>
    <section class="section--LoginPage section--RegisterPage">
        <div class="section_content">
            <div class="section_infobox">
                <form @submit.prevent="handleRegister" class="login-form">
                    <div v-if="error" class="error-message">
                        {{ error }}
                    </div>

                    <div class="form-group">
                        <label for="firstName">Vorname</label>
                        <input
                            id="firstName"
                            v-model="firstName"
                            type="text"
                            required
                            :disabled="loading"
                            class="form-control"
                        >
                    </div>

                    <div class="form-group">
                        <label for="surname">Nachname</label>
                        <input
                            id="surname"
                            v-model="surname"
                            type="text"
                            required
                            :disabled="loading"
                            class="form-control"
                        >
                    </div>

                    <div class="form-group">
                        <label for="email">E-Mail</label>
                        <input
                            id="email"
                            v-model="email"
                            type="email"
                            required
                            :disabled="loading"
                            class="form-control"
                        >
                    </div>

                    <div class="form-group">
                        <label for="password">Passwort</label>
                        <input
                            id="password"
                            v-model="password"
                            type="password"
                            required
                            :disabled="loading"
                            class="form-control"
                        >
                    </div>

                    <div class="form-group">
                        <label for="passwordConfirm">Passwort bestätigen</label>
                        <input
                            id="passwordConfirm"
                            v-model="passwordConfirm"
                            type="password"
                            required
                            :disabled="loading"
                            class="form-control"
                        >
                    </div>

                    <button
                        type="submit"
                        class="button button--primary"
                        :disabled="loading"
                    >
                        {{ loading ? 'Registrieren...' : 'Registrieren' }}
                    </button>

                    <p class="login-register-link">
                        Bereits ein Konto?
                        <RouterLink :to="{ name: 'Login' }">Anmelden →</RouterLink>
                    </p>
                </form>
            </div>
        </div>
    </section>
</template>

<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@stores/auth'
import { usePageHeaderStore } from '@stores/pageHeader'
import { apiPost } from '@utils/api'

const router = useRouter()
const authStore = useAuthStore()
usePageHeaderStore().setHeader('Registrieren', 'Erstelle jetzt dein Konto.')

const firstName = ref('')
const surname = ref('')
const email = ref('')
const password = ref('')
const passwordConfirm = ref('')
const loading = ref(false)
const error = ref(null)

async function handleRegister() {
  error.value = null
  loading.value = true

  try {
    const response = await apiPost('/register', {
      FirstName: firstName.value,
      Surname: surname.value,
      Email: email.value,
      Password: password.value,
      PasswordConfirm: passwordConfirm.value,
    })

    if (response.success) {
      authStore.user = response.user
      authStore.isAuthenticated = true
      router.push({ name: 'Dashboard' })
    } else {
      error.value = response.message || 'Registrierung fehlgeschlagen.'
    }
  } catch (err) {
    error.value = err.message || 'Registrierung fehlgeschlagen.'
  } finally {
    loading.value = false
  }
}
</script>
