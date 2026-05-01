<template>
  <div class="section section--LoginPage">
    <AppHeader title="Login" description="Melde dich mit deinen Zugangsdaten an." />

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
        </form>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@stores/auth'
import AppHeader from '@components/AppHeader.vue'

const router = useRouter()
const authStore = useAuthStore()

const email = ref('')
const password = ref('')

async function handleLogin() {
  const success = await authStore.login(email.value, password.value)

  if (success) {
    router.push({ name: 'Dashboard' })
  }
}
</script>

<style scoped>
.login-form {
  max-width: 400px;
  margin: 0 auto;
}

.form-group {
  margin-bottom: 1.5rem;
}

.form-group label {
  display: block;
  margin-bottom: 0.5rem;
  font-weight: 500;
}

.form-control {
  width: 100%;
  padding: 0.75rem;
  border: 1px solid #ddd;
  border-radius: 4px;
  font-size: 1rem;
}

.form-control:disabled {
  background-color: #f5f5f5;
  cursor: not-allowed;
}

.error-message {
  padding: 1rem;
  margin-bottom: 1rem;
  background-color: #fee;
  border: 1px solid #fcc;
  border-radius: 4px;
  color: #c33;
}

.button--primary {
  width: 100%;
  padding: 1rem;
  font-size: 1.1rem;
}
</style>
