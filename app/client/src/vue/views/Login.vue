<template>
    <section class="section--LoginPage">
        <div class="section_content">
            <div class="section_infobox">
                <form @submit.prevent="handleLogin" class="login-form">
                    <div v-if="authStore.error" class="error-message">
                        {{ authStore.error }}
                    </div>

                    <div class="form-group">
                        <label for="email">E-Mail</label>
                        <input
                        id="email"
                        v-model="email"
                        type="email"
                        required
                        :disabled="authStore.loading"
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
                        :disabled="authStore.loading"
                        class="form-control"
                        >
                    </div>

                    <button
                        type="submit"
                        class="button button--primary"
                        :disabled="authStore.loading"
                    >
                        {{ authStore.loading ? 'Anmelden...' : 'Anmelden' }}
                    </button>

                    <p class="login-register-link">
                        Noch kein Konto?
                        <RouterLink :to="{ name: 'Register' }">Registrieren →</RouterLink>
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

const router = useRouter()
const authStore = useAuthStore()
usePageHeaderStore().setHeader('Login', 'Melde dich mit deinen Zugangsdaten an.')

const email = ref('')
const password = ref('')

async function handleLogin() {
  const success = await authStore.login(email.value, password.value)

  if (success) {
    router.push({ name: 'Dashboard' })
  }
}
</script>
