<template>
  <AppModal ref="modal" class="app-modal--flush edit-profile-modal" title="Profil bearbeiten" @close="close">
    <div v-if="loading" class="edit-profile-modal_loading">Profil wird geladen …</div>

    <template v-else>

          <!-- ── Section: Profilbild ── -->
          <section class="edit-section">
            <h3 class="edit-section_title">Profilbild</h3>

            <div class="current-avatar">
              <AppAvatar :src="currentAvatarUrl" alt="Aktuelles Profilbild" img-class="current-avatar_img" />
            </div>

            <label class="button file-input-label">
              Bild auswählen
              <input
                ref="fileInputEl"
                type="file"
                accept="image/jpeg,image/png"
                class="file-input-hidden"
                @change="onFileSelected"
              >
            </label>
            <p v-if="fileError" class="status-text status-text--error">{{ fileError }}</p>
            <p v-if="imageSaved" class="status-text status-text--success">Profilbild gespeichert.</p>
          </section>

          <!-- ── Section: Persönliche Daten ── -->
          <section class="edit-section">
            <h3 class="edit-section_title">Persönliche Daten</h3>

            <div class="modalform">
              <div class="field">
                <label for="ep-firstname">Vorname</label>
                <input id="ep-firstname" type="text" v-model="form.FirstName" required>
              </div>
              <div class="field">
                <label for="ep-surname">Nachname</label>
                <input id="ep-surname" type="text" v-model="form.Surname" required>
              </div>
              <div class="field">
                <label for="ep-email">E-Mail</label>
                <div v-if="emailChange.step === 'idle'" class="email-display">
                  <span>{{ authStore.user?.Email }}</span>
                  <button type="button" class="link-button" @click="startEmailChange">Ändern</button>
                </div>
                <form v-else-if="emailChange.step === 'request'" class="email-change-form" @submit.prevent="requestEmailChangeCode">
                  <input id="ep-email" type="email" v-model="emailChange.newEmail" required placeholder="Neue E-Mail-Adresse">
                  <div class="email-change-form_actions">
                    <AppButton size="small" variant="primary" type="submit" :disabled="emailChange.loading">
                      {{ emailChange.loading ? '…' : 'Code senden' }}
                    </AppButton>
                    <AppButton size="small" variant="secondary" type="button" @click="cancelEmailChange">Abbrechen</AppButton>
                  </div>
                </form>
                <form v-else class="email-change-form" @submit.prevent="confirmEmailChangeCode">
                  <p class="status-text">Code an {{ emailChange.newEmail }} geschickt.</p>
                  <input v-model="emailChange.code" type="text" inputmode="numeric" maxlength="6" required placeholder="6-stelliger Code" aria-label="Bestätigungscode">
                  <div class="email-change-form_actions">
                    <AppButton size="small" variant="primary" type="submit" :disabled="emailChange.loading">
                      {{ emailChange.loading ? '…' : 'Bestätigen' }}
                    </AppButton>
                    <AppButton size="small" variant="secondary" type="button" @click="cancelEmailChange">Abbrechen</AppButton>
                  </div>
                </form>
                <p v-if="emailChange.error" class="status-text status-text--error">{{ emailChange.error }}</p>
                <p v-if="emailChange.success" class="status-text status-text--success">{{ emailChange.success }}</p>
              </div>
              <div class="field">
                <label for="ep-food">Essenspräferenz</label>
                <select id="ep-food" v-model="form.FoodPreference">
                  <option value="None">Keine Besonderheiten</option>
                  <option value="Vegetarian">Vegetarisch</option>
                  <option value="Vegan">Vegan</option>
                </select>
              </div>
              <div class="field">
                <label for="ep-namevis">Name öffentlich anzeigen</label>
                <select id="ep-namevis" v-model="form.NameVisibility">
                  <option value="full">Ganzer Name</option>
                  <option value="first">Nur Vorname</option>
                  <option value="username">Nur Benutzername</option>
                </select>
              </div>

              <p v-if="saveError" class="status-text status-text--error">{{ saveError }}</p>
              <p v-else-if="saving" class="status-text">Wird gespeichert …</p>
              <p v-else-if="saveSuccess" class="status-text status-text--success">{{ saveSuccess }}</p>
            </div>
          </section>

          <!-- ── Section: Anmeldung ── -->
          <section class="edit-section">
            <h3 class="edit-section_title">Anmeldung</h3>
            <p class="empty-hint">
              Standardmäßig meldest du dich per E-Mail-Code an. Optional kannst du zusätzlich
              ein Passwort einrichten, um dich auch damit anmelden zu können.
            </p>

            <form class="modalform" @submit.prevent="submitPassword">
              <div v-if="passwordForm.hasPassword" class="field">
                <label for="ep-current-password">Aktuelles Passwort</label>
                <input id="ep-current-password" type="password" v-model="passwordForm.currentPassword" required>
              </div>
              <div class="field">
                <label for="ep-new-password">{{ passwordForm.hasPassword ? 'Neues Passwort' : 'Passwort festlegen' }}</label>
                <input id="ep-new-password" type="password" v-model="passwordForm.newPassword" minlength="8" required>
              </div>

              <AppButton size="small" variant="secondary" type="submit" :disabled="passwordForm.saving">
                {{ passwordForm.saving ? '…' : (passwordForm.hasPassword ? 'Passwort ändern' : 'Passwort festlegen') }}
              </AppButton>

              <p v-if="passwordForm.error" class="status-text status-text--error">{{ passwordForm.error }}</p>
              <p v-if="passwordForm.success" class="status-text status-text--success">{{ passwordForm.success }}</p>
            </form>
          </section>

          <!-- ── Section: Allergien ── -->
          <section class="edit-section">
            <h3 class="edit-section_title">Allergien & Unverträglichkeiten</h3>
            <div v-if="allergiesLoading" class="empty-hint">Wird geladen…</div>
            <div v-else-if="allergies.length === 0" class="empty-hint">Keine Allergien hinterlegt.</div>
            <div v-else>
              <div v-for="group in allergiesByCategory" :key="group.category" class="allergy-group">
                <h4 class="allergy-group_title">{{ group.label }}</h4>
                <div class="allergy-chips">
                  <button
                    v-for="a in group.items"
                    :key="a.id"
                    type="button"
                    class="allergy-chip"
                    :class="{ 'allergy-chip--selected': a.selected }"
                    :disabled="allergiesSaving"
                    @click="toggleAllergy(a)"
                  >{{ a.title }}</button>
                </div>
              </div>
            </div>
            <p v-if="allergiesSaveError" class="status-text status-text--error">{{ allergiesSaveError }}</p>
          </section>

          <!-- ── Section: Organisationen ── -->
          <section class="edit-section">
            <h3 class="edit-section_title">Meine Organisationen</h3>

            <p v-if="orgs.length === 0" class="empty-hint">Keine Organisationsmitgliedschaften vorhanden.</p>

            <div
              v-for="org in orgs"
              :key="org.MembershipID"
              class="org-item"
            >
              <div class="org-item_row">
                <div class="org-item_info">
                  <AppOrgLogo :src="org.LogoURL" :alt="org.Title" :size="36" />
                  <div class="org-item_text">
                    <strong>{{ org.Title }}</strong>
                    <span class="org-item_role">{{ roleLabel(org.Role) }}</span>
                  </div>
                </div>

                <AppIconButton
                  variant="danger"
                  aria-label="Organisation verlassen"
                  title="Organisation verlassen"
                  @click="leaveConfirmId = org.MembershipID"
                >
                  <span class="icon-mask" :style="logoutIconStyle" />
                </AppIconButton>
              </div>

              <div v-if="leaveConfirmId === org.MembershipID" class="leave-confirm">
                <p class="leave-confirm_text">Mitgliedschaft in <strong>{{ org.Title }}</strong> wirklich auflösen?</p>
                <div class="leave-confirm_btns">
                  <AppButton
                    size="small"
                    variant="danger"
                    :disabled="leavingOrg"
                    @click="confirmLeaveOrg(org)"
                  >
                    {{ leavingOrg ? '…' : 'Ja, auflösen' }}
                  </AppButton>
                  <AppButton
                    size="small"
                    variant="secondary"
                    @click="leaveConfirmId = null"
                  >
                    Abbrechen
                  </AppButton>
                </div>
              </div>
            </div>
          </section>

    </template>
  </AppModal>

  <ImageCropModal
    ref="cropModal"
    upload-url="/profile/uploadImage"
    response-field="Avatar"
    shape="circle"
    @saved="onImageSaved"
  />
</template>

<script setup>
import { ref, reactive, computed, watch, nextTick, onBeforeUnmount } from 'vue'
import { apiGet, apiPost, apiPut } from '@utils/api'
import { useAuthStore } from '@stores/auth'
import AppButton from '@components/ui/AppButton.vue'
import AppIconButton from '@components/ui/AppIconButton.vue'
import AppAvatar from '@components/ui/AppAvatar.vue'
import AppModal from '@components/ui/AppModal.vue'
import ImageCropModal from '@components/ui/ImageCropModal.vue'
import AppOrgLogo from '@components/ui/AppOrgLogo.vue'
import actionLogout from '../../../../icons/actions/action_logout.svg'

const emit = defineEmits(['updated'])

const authStore = useAuthStore()

// ── Modal ──────────────────────────────────────────
const modal = ref(null)

async function open() {
  modal.value?.open()
  autosaveEnabled = false
  await Promise.all([loadProfile(), loadAllergies()])
  // Die obigen Zuweisungen an `form` lösen den Autosave-Watcher aus; erst nach
  // dem nächsten Tick (wenn dieser Lauf verarbeitet ist) wieder scharf schalten,
  // damit das Laden des Profils nicht selbst ein Speichern auslöst.
  await nextTick()
  autosaveEnabled = true
}

function close() {
  // Eine noch ausstehende Autosave-Verzögerung sofort ausführen, damit die
  // letzte Änderung beim Schließen nicht verloren geht.
  if (autosaveTimeout) {
    clearTimeout(autosaveTimeout)
    autosaveTimeout = null
    if (autosaveEnabled && !validateForm()) {
      persistProfile()
    }
  }
  clearTimeout(successTimeout)
  autosaveEnabled = false
  modal.value?.close()
  fileError.value   = null
  imageSaved.value  = false
  saveError.value   = null
  saveSuccess.value = null
  emailChange.step = 'idle'
  emailChange.error = null
  emailChange.success = null
  passwordForm.currentPassword = ''
  passwordForm.newPassword = ''
  passwordForm.error = null
  passwordForm.success = null
}

onBeforeUnmount(() => {
  clearTimeout(autosaveTimeout)
  clearTimeout(successTimeout)
})

defineExpose({ open, close })

// ── Profile load ───────────────────────────────────
const loading = ref(false)

async function loadProfile() {
  try {
    loading.value = true
    const data = await apiGet('/profile', false)
    if (data.success && data.profile) {
      const p = data.profile
      form.FirstName      = p.FirstName      ?? ''
      form.Surname        = p.Surname        ?? ''
      form.FoodPreference   = p.FoodPreference   ?? 'None'
      form.NameVisibility   = p.NameVisibility   ?? 'full'
      orgs.value            = p.Organizations    ?? []
      passwordForm.hasPassword = !!p.HasPassword
    }
  } catch (err) {
    console.error('Profil laden fehlgeschlagen:', err)
  } finally {
    loading.value = false
  }
}

// ── Allergies ──────────────────────────────────────
const allergies         = ref([])
const allergiesLoading  = ref(false)
const allergiesSaving   = ref(false)
const allergiesSaveError = ref(null)

const ALLERGY_CATEGORY_LABELS = { Essen: 'Essen', Tiere: 'Tiere', Sonstiges: 'Sonstiges' }
const ALLERGY_CATEGORY_ORDER  = ['Essen', 'Tiere', 'Sonstiges']

const allergiesByCategory = computed(() => {
  return ALLERGY_CATEGORY_ORDER
    .map(category => ({
      category,
      label: ALLERGY_CATEGORY_LABELS[category] ?? category,
      items: allergies.value.filter(a => (a.category ?? 'Sonstiges') === category),
    }))
    .filter(group => group.items.length > 0)
})

async function loadAllergies() {
  allergiesLoading.value = true
  try {
    const data = await apiGet('/profile/allergies', false)
    allergies.value = data.allergies ?? []
  } catch (err) {
    console.error('Allergien laden fehlgeschlagen:', err)
  } finally {
    allergiesLoading.value = false
  }
}

async function toggleAllergy(allergy) {
  allergy.selected = !allergy.selected
  allergiesSaving.value   = true
  allergiesSaveError.value = null
  try {
    const ids = allergies.value.filter(a => a.selected).map(a => a.id)
    await apiPut('/profile/allergies', { allergyIds: ids })
  } catch (err) {
    allergy.selected = !allergy.selected
    allergiesSaveError.value = 'Fehler beim Speichern.'
  } finally {
    allergiesSaving.value = false
  }
}

// ── Profilbild ─────────────────────────────────────
// Die eigentliche Zuschneide-/Zoom-Logik lebt in ImageCropModal, das sich als
// zweites Modal vor diesem öffnet — hier wird nur die Dateiauswahl validiert.
const fileInputEl = ref(null)
const cropModal   = ref(null)
const fileError   = ref(null)
const imageSaved  = ref(false)

const currentAvatarUrl = computed(() => authStore.user?.Avatar ?? '')

function onFileSelected(e) {
  fileError.value  = null
  imageSaved.value = false
  const file = e.target.files[0]
  if (fileInputEl.value) fileInputEl.value.value = ''
  if (!file) return

  if (!['image/jpeg', 'image/png'].includes(file.type)) {
    fileError.value = 'Nur PNG und JPEG sind erlaubt.'
    return
  }
  // Nur eine großzügige Notbremse gegen pathologische Dateien — der Zuschnitt
  // passiert im Browser und das Ergebnis ist immer ein kleines 180×180-JPEG,
  // die tatsächliche Upload-Größe hängt also nicht von der Quelldatei ab.
  if (file.size > 25 * 1024 * 1024) {
    fileError.value = 'Das Bild darf maximal 25 MB groß sein.'
    return
  }

  cropModal.value?.open(file)
}

function onImageSaved(avatarUrl) {
  authStore.updateUser({ Avatar: avatarUrl })
  imageSaved.value = true
  emit('updated')
}

// ── Profile form (Autosave) ─────────────────────────
// Email is deliberately not part of this form — since login is now
// passwordless (email + one-time code), the email address is the login
// credential, so it goes through its own confirmed change flow below
// instead of being autosaved like the other fields.
const form = reactive({ FirstName: '', Surname: '', FoodPreference: 'None', NameVisibility: 'full' })
const saving      = ref(false)
const saveError   = ref(null)
const saveSuccess = ref(null)

const AUTOSAVE_DELAY_MS = 1000
const SUCCESS_MESSAGE_MS = 2000
let autosaveEnabled = false
let autosaveTimeout = null
let successTimeout  = null

function validateForm() {
  if (!form.FirstName.trim()) return 'Vorname darf nicht leer sein.'
  if (!form.Surname.trim()) return 'Nachname darf nicht leer sein.'
  return null
}

async function persistProfile() {
  saving.value    = true
  saveError.value = null

  try {
    const result = await apiPost('/profile/update', { ...form })
    if (result.success) {
      authStore.updateUser({
        FirstName:      form.FirstName,
        Surname:        form.Surname,
        FoodPreference: form.FoodPreference,
      })
      saveSuccess.value = 'Gespeichert.'
      clearTimeout(successTimeout)
      successTimeout = setTimeout(() => { saveSuccess.value = null }, SUCCESS_MESSAGE_MS)
      emit('updated')
    } else {
      saveError.value = result.error ?? 'Speichern fehlgeschlagen.'
    }
  } catch (err) {
    console.error('Profil-Update fehlgeschlagen:', err)
    saveError.value = 'Speichern fehlgeschlagen.'
  } finally {
    saving.value = false
  }
}

function scheduleAutosave() {
  if (!autosaveEnabled) return

  saveSuccess.value = null
  clearTimeout(successTimeout)
  clearTimeout(autosaveTimeout)
  autosaveTimeout = setTimeout(() => {
    const error = validateForm()
    if (error) {
      saveError.value = error
      return
    }
    saveError.value = null
    persistProfile()
  }, AUTOSAVE_DELAY_MS)
}

watch(form, scheduleAutosave, { deep: true })

// ── E-Mail-Änderung (eigener, code-bestätigter Ablauf) ──
const emailChange = reactive({
  step: 'idle', // 'idle' | 'request' | 'verify'
  newEmail: '',
  code: '',
  loading: false,
  error: null,
  success: null,
})

function startEmailChange() {
  emailChange.step = 'request'
  emailChange.newEmail = ''
  emailChange.code = ''
  emailChange.error = null
  emailChange.success = null
}

function cancelEmailChange() {
  emailChange.step = 'idle'
  emailChange.error = null
}

async function requestEmailChangeCode() {
  emailChange.loading = true
  emailChange.error = null
  try {
    const result = await apiPost('/profile/requestEmailChange', { email: emailChange.newEmail })
    if (result.success) {
      emailChange.step = 'verify'
    } else {
      emailChange.error = result.error ?? 'Code konnte nicht verschickt werden.'
    }
  } catch (err) {
    emailChange.error = 'Code konnte nicht verschickt werden.'
  } finally {
    emailChange.loading = false
  }
}

async function confirmEmailChangeCode() {
  emailChange.loading = true
  emailChange.error = null
  try {
    const result = await apiPost('/profile/confirmEmailChange', {
      email: emailChange.newEmail,
      code: emailChange.code,
    })
    if (result.success) {
      authStore.updateUser({ Email: emailChange.newEmail })
      emailChange.step = 'idle'
      emailChange.success = 'E-Mail-Adresse aktualisiert.'
      setTimeout(() => { emailChange.success = null }, SUCCESS_MESSAGE_MS)
      emit('updated')
    } else {
      emailChange.error = result.error ?? 'Code ungültig.'
    }
  } catch (err) {
    emailChange.error = 'Code ungültig.'
  } finally {
    emailChange.loading = false
  }
}

// ── Passwort (optionale Alternative zum Code-Login) ──
const passwordForm = reactive({
  hasPassword: false,
  currentPassword: '',
  newPassword: '',
  saving: false,
  error: null,
  success: null,
})

async function submitPassword() {
  passwordForm.saving = true
  passwordForm.error = null
  passwordForm.success = null
  try {
    const result = await apiPost('/profile/setPassword', {
      currentPassword: passwordForm.currentPassword,
      newPassword: passwordForm.newPassword,
    })
    if (result.success) {
      passwordForm.hasPassword = true
      passwordForm.currentPassword = ''
      passwordForm.newPassword = ''
      passwordForm.success = 'Passwort gespeichert.'
    } else {
      passwordForm.error = result.error ?? 'Passwort konnte nicht gespeichert werden.'
    }
  } catch (err) {
    passwordForm.error = 'Passwort konnte nicht gespeichert werden.'
  } finally {
    passwordForm.saving = false
  }
}

// ── Organizations ──────────────────────────────────
const orgs          = ref([])
const leaveConfirmId = ref(null)
const leavingOrg    = ref(false)
const logoutIconStyle = { maskImage: `url("${actionLogout}")`, WebkitMaskImage: `url("${actionLogout}")` }

function roleLabel(role) {
  return { member: 'Mitglied', moderator: 'Moderator', admin: 'Administrator', applicant: 'Bewerber' }[role] ?? role
}

async function confirmLeaveOrg(org) {
  leavingOrg.value = true
  try {
    const result = await apiPost(`/profile/leaveOrg/${org.MembershipID}`, {})
    if (result.success) {
      orgs.value       = orgs.value.filter(o => o.MembershipID !== org.MembershipID)
      leaveConfirmId.value = null
      emit('updated')
    } else {
      console.error('Auflösen fehlgeschlagen:', result.error)
    }
  } catch (err) {
    console.error('Auflösen fehlgeschlagen:', err)
  } finally {
    leavingOrg.value = false
  }
}
</script>
