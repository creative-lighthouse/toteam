<template>
    <section class="section--LoginPage">
        <div class="section_content">
            <div class="section_infobox">

                <!-- Step 1: email -->
                <form v-if="step === 'email'" @submit.prevent="handleRequestCode" class="login-form">
                    <div v-if="authStore.error" class="error-message">
                        {{ authStore.error }}
                    </div>

                    <p class="login-intro">
                        Gib deine E-Mail-Adresse ein — wir schicken dir einen Anmeldecode.
                        Hast du noch kein Konto, wird eins automatisch für dich angelegt.
                    </p>

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

                    <AppButton
                        type="submit"
                        variant="primary"
                        class="button--primary"
                        :disabled="authStore.loading"
                    >
                        {{ authStore.loading ? 'Sende Code…' : 'Code anfordern' }}
                    </AppButton>

                    <p class="login-register-link">
                        <button type="button" class="link-button" @click="step = 'password'">
                            Stattdessen mit Passwort anmelden
                        </button>
                    </p>
                </form>

                <!-- Alternative: email + password -->
                <form v-else-if="step === 'password'" @submit.prevent="handlePasswordLogin" class="login-form">
                    <div v-if="authStore.error" class="error-message">
                        {{ authStore.error }}
                    </div>

                    <div class="form-group">
                        <label for="pw-email">E-Mail</label>
                        <input
                        id="pw-email"
                        v-model="email"
                        type="email"
                        required
                        :disabled="authStore.loading"
                        class="form-control"
                        >
                    </div>

                    <div class="form-group">
                        <label for="pw-password">Passwort</label>
                        <input
                        id="pw-password"
                        v-model="password"
                        type="password"
                        required
                        :disabled="authStore.loading"
                        class="form-control"
                        >
                    </div>

                    <AppButton
                        type="submit"
                        variant="primary"
                        class="button--primary"
                        :disabled="authStore.loading"
                    >
                        {{ authStore.loading ? 'Anmelden…' : 'Anmelden' }}
                    </AppButton>

                    <p class="login-register-link">
                        <button type="button" class="link-button" @click="step = 'email'">
                            Stattdessen per Anmeldecode
                        </button>
                        ·
                        <button type="button" class="link-button" @click="startForgotPassword">
                            Passwort vergessen?
                        </button>
                    </p>
                </form>

                <!-- Forgot password, step 1: email -->
                <form v-else-if="step === 'forgot'" @submit.prevent="handleRequestPasswordReset" class="login-form">
                    <div v-if="authStore.error" class="error-message">
                        {{ authStore.error }}
                    </div>

                    <p class="login-intro">
                        Gib deine E-Mail-Adresse ein — falls dazu ein Konto existiert, schicken wir dir
                        einen Code, mit dem du ein neues Passwort setzen kannst.
                    </p>

                    <div class="form-group">
                        <label for="forgot-email">E-Mail</label>
                        <input
                        id="forgot-email"
                        v-model="email"
                        type="email"
                        required
                        :disabled="authStore.loading"
                        class="form-control"
                        >
                    </div>

                    <AppButton
                        type="submit"
                        variant="primary"
                        class="button--primary"
                        :disabled="authStore.loading"
                    >
                        {{ authStore.loading ? 'Sende Code…' : 'Code anfordern' }}
                    </AppButton>

                    <p class="login-register-link">
                        <button type="button" class="link-button" @click="step = 'password'">Zurück</button>
                    </p>
                </form>

                <!-- Forgot password, step 2: code + new password -->
                <form v-else-if="step === 'forgot-reset'" @submit.prevent="handleResetPassword" class="login-form">
                    <div v-if="authStore.error" class="error-message">
                        {{ authStore.error }}
                    </div>

                    <p class="login-intro">
                        Falls ein Konto mit <strong>{{ email }}</strong> existiert, haben wir einen Code geschickt.
                    </p>

                    <div class="form-group">
                        <label for="forgot-code">Code</label>
                        <input
                        id="forgot-code"
                        v-model="code"
                        type="text"
                        inputmode="numeric"
                        autocomplete="one-time-code"
                        maxlength="6"
                        required
                        :disabled="authStore.loading"
                        class="form-control"
                        >
                    </div>

                    <div class="form-group">
                        <label for="forgot-new-password">Neues Passwort</label>
                        <input
                        id="forgot-new-password"
                        v-model="newPassword"
                        type="password"
                        minlength="8"
                        required
                        :disabled="authStore.loading"
                        class="form-control"
                        >
                    </div>

                    <AppButton
                        type="submit"
                        variant="primary"
                        class="button--primary"
                        :disabled="authStore.loading || code.length !== 6"
                    >
                        {{ authStore.loading ? 'Speichere…' : 'Passwort setzen & anmelden' }}
                    </AppButton>

                    <p class="login-register-link">
                        <button type="button" class="link-button" :disabled="resendCooldown > 0" @click="handleRequestPasswordReset">
                            {{ resendCooldown > 0 ? `Code erneut senden (${resendCooldown}s)` : 'Code erneut senden' }}
                        </button>
                        ·
                        <button type="button" class="link-button" @click="step = 'forgot'">Andere E-Mail-Adresse</button>
                    </p>
                </form>

                <!-- Step 2: code -->
                <form v-else-if="step === 'code'" @submit.prevent="handleVerifyCode" class="login-form">
                    <div v-if="authStore.error" class="error-message">
                        {{ authStore.error }}
                    </div>

                    <p class="login-intro">
                        Wir haben einen 6-stelligen Code an <strong>{{ email }}</strong> geschickt.
                    </p>

                    <div class="form-group">
                        <label for="code">Anmeldecode</label>
                        <input
                        id="code"
                        v-model="code"
                        type="text"
                        inputmode="numeric"
                        autocomplete="one-time-code"
                        maxlength="6"
                        required
                        :disabled="authStore.loading"
                        class="form-control"
                        >
                    </div>

                    <AppButton
                        type="submit"
                        variant="primary"
                        class="button--primary"
                        :disabled="authStore.loading || code.length !== 6"
                    >
                        {{ authStore.loading ? 'Prüfe Code…' : 'Anmelden' }}
                    </AppButton>

                    <p class="login-register-link">
                        <button type="button" class="link-button" :disabled="resendCooldown > 0" @click="handleRequestCode">
                            {{ resendCooldown > 0 ? `Code erneut senden (${resendCooldown}s)` : 'Code erneut senden' }}
                        </button>
                        ·
                        <button type="button" class="link-button" @click="step = 'email'">Andere E-Mail-Adresse</button>
                    </p>
                </form>

                <!-- Step 3: new account needs a name -->
                <form v-else-if="step === 'profile'" @submit.prevent="handleVerifyCode" class="login-form">
                    <div v-if="authStore.error" class="error-message">
                        {{ authStore.error }}
                    </div>

                    <p class="login-intro">
                        Diese E-Mail-Adresse ist neu bei ToTeam — wie dürfen wir dich nennen?
                    </p>

                    <div class="form-group">
                        <label for="firstName">Vorname</label>
                        <input id="firstName" v-model="firstName" type="text" required :disabled="authStore.loading" class="form-control">
                    </div>
                    <div class="form-group">
                        <label for="surname">Nachname</label>
                        <input id="surname" v-model="surname" type="text" required :disabled="authStore.loading" class="form-control">
                    </div>

                    <AppButton
                        type="submit"
                        variant="primary"
                        class="button--primary"
                        :disabled="authStore.loading"
                    >
                        {{ authStore.loading ? 'Erstelle Konto…' : 'Konto erstellen' }}
                    </AppButton>
                </form>

            </div>
        </div>
    </section>
</template>

<script setup>
import { ref, onBeforeUnmount } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@stores/auth'
import { usePageHeaderStore } from '@stores/pageHeader'
import AppButton from '@components/ui/AppButton.vue'

const router = useRouter()
const authStore = useAuthStore()
usePageHeaderStore().setHeader('Login', 'Melde dich per E-Mail-Code an.')

const step = ref('email') // 'email' | 'password' | 'code' | 'profile' | 'forgot' | 'forgot-reset'
const email = ref('')
const password = ref('')
const code = ref('')
const firstName = ref('')
const surname = ref('')
const newPassword = ref('')

const resendCooldown = ref(0)
let resendTimer = null

function startResendCooldown() {
  resendCooldown.value = 60
  clearInterval(resendTimer)
  resendTimer = setInterval(() => {
    resendCooldown.value--
    if (resendCooldown.value <= 0) clearInterval(resendTimer)
  }, 1000)
}

onBeforeUnmount(() => clearInterval(resendTimer))

async function handleRequestCode() {
  const response = await authStore.requestCode(email.value)
  if (response.success) {
    code.value = ''
    step.value = 'code'
    startResendCooldown()
  }
}

async function handleVerifyCode() {
  const response = await authStore.verifyCode(email.value, code.value, {
    firstName: firstName.value,
    surname: surname.value,
  })

  if (response.success) {
    router.push({ name: 'Dashboard' })
    return
  }

  if (response.needsProfile) {
    step.value = 'profile'
  }
}

async function handlePasswordLogin() {
  const response = await authStore.loginWithPassword(email.value, password.value)
  if (response.success) {
    router.push({ name: 'Dashboard' })
  }
}

function startForgotPassword() {
  step.value = 'forgot'
  code.value = ''
  newPassword.value = ''
}

async function handleRequestPasswordReset() {
  const response = await authStore.requestPasswordReset(email.value)
  if (response.success) {
    step.value = 'forgot-reset'
    startResendCooldown()
  }
}

async function handleResetPassword() {
  const response = await authStore.resetPassword(email.value, code.value, newPassword.value)
  if (response.success) {
    router.push({ name: 'Dashboard' })
  }
}
</script>
