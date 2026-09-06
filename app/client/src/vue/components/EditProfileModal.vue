<template>
  <Teleport to="body">
    <dialog ref="dialogEl" class="edit-profile-modal" @cancel.prevent="close">
      <div class="edit-profile-modal_content" @click.stop>

        <!-- Header -->
        <div class="edit-profile-modal_header">
          <h2 class="hl2 edit-profile-modal_title">Profil bearbeiten</h2>
          <AppIconButton variant="ghost" aria-label="Schließen" @click="close">✕</AppIconButton>
        </div>

        <div v-if="loading" class="edit-profile-modal_loading">Profil wird geladen …</div>

        <div v-else class="edit-profile-modal_body">

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

            <form class="profile-form" @submit.prevent="saveProfile" novalidate>
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
                <input id="ep-email" type="email" v-model="form.Email" required>
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
              <p v-if="saveSuccess" class="status-text status-text--success">{{ saveSuccess }}</p>

              <AppButton type="submit" variant="primary" :disabled="saving">
                {{ saving ? 'Wird gespeichert …' : 'Speichern' }}
              </AppButton>
            </form>
          </section>

          <!-- ── Section: Allergien ── -->
          <section class="edit-section">
            <h3 class="edit-section_title">Allergien & Unverträglichkeiten</h3>
            <div v-if="allergiesLoading" class="empty-hint">Wird geladen…</div>
            <div v-else-if="allergies.length === 0" class="empty-hint">Keine Allergien hinterlegt.</div>
            <div v-else class="allergy-chips">
              <button
                v-for="a in allergies"
                :key="a.id"
                type="button"
                class="allergy-chip"
                :class="{ 'allergy-chip--selected': a.selected }"
                :disabled="allergiesSaving"
                @click="toggleAllergy(a)"
              >{{ a.title }}</button>
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
              <div class="org-item_info">
                <AppOrgLogo :src="org.LogoURL" :alt="org.Title" :size="36" />
                <div class="org-item_text">
                  <strong>{{ org.Title }}</strong>
                  <span class="org-item_role">{{ roleLabel(org.Role) }}</span>
                </div>
              </div>

              <div class="org-item_actions">
                <template v-if="leaveConfirmId !== org.MembershipID">
                  <AppButton
                    size="small"
                    variant="danger"
                    @click="leaveConfirmId = org.MembershipID"
                  >
                    Organisation verlassen
                  </AppButton>
                </template>
                <template v-else>
                  <div class="leave-confirm">
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
                </template>
              </div>
            </div>
          </section>

        </div>
      </div>
    </dialog>
  </Teleport>

  <ImageCropModal
    ref="cropModal"
    upload-url="/profile/uploadImage"
    response-field="Avatar"
    shape="circle"
    @saved="onImageSaved"
  />
</template>

<script setup>
import { ref, reactive, computed } from 'vue'
import { apiGet, apiPost, apiPut } from '@utils/api'
import { useAuthStore } from '@stores/auth'
import AppButton from '@components/AppButton.vue'
import AppIconButton from '@components/AppIconButton.vue'
import AppAvatar from '@components/AppAvatar.vue'
import ImageCropModal from '@components/ImageCropModal.vue'
import AppOrgLogo from '@components/AppOrgLogo.vue'

const emit = defineEmits(['updated'])

const authStore = useAuthStore()

// ── Modal ──────────────────────────────────────────
const dialogEl = ref(null)

async function open() {
  dialogEl.value?.showModal()
  await Promise.all([loadProfile(), loadAllergies()])
}

function close() {
  dialogEl.value?.close()
  fileError.value  = null
  imageSaved.value = false
}

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
      form.Email          = p.Email          ?? ''
      form.FoodPreference   = p.FoodPreference   ?? 'None'
      form.NameVisibility   = p.NameVisibility   ?? 'full'
      orgs.value            = p.Organizations    ?? []
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

// ── Profile form ───────────────────────────────────
const form = reactive({ FirstName: '', Surname: '', Email: '', FoodPreference: 'None', NameVisibility: 'full' })
const saving      = ref(false)
const saveError   = ref(null)
const saveSuccess = ref(null)

function isValidEmail(email) {
  return /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(email.trim())
}

async function saveProfile() {
  saving.value      = true
  saveError.value   = null
  saveSuccess.value = null

  if (!form.FirstName.trim()) {
    saveError.value = 'Vorname darf nicht leer sein.'
    saving.value    = false
    return
  }
  if (!form.Surname.trim()) {
    saveError.value = 'Nachname darf nicht leer sein.'
    saving.value    = false
    return
  }
  if (!isValidEmail(form.Email)) {
    saveError.value = 'Bitte gib eine gültige E-Mail-Adresse ein.'
    saving.value    = false
    return
  }

  try {
    const result = await apiPost('/profile/update', { ...form })
    if (result.success) {
      authStore.updateUser({
        FirstName:      form.FirstName,
        Surname:        form.Surname,
        Email:          form.Email,
        FoodPreference: form.FoodPreference,
      })
      saveSuccess.value = 'Profil gespeichert.'
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

// ── Organizations ──────────────────────────────────
const orgs          = ref([])
const leaveConfirmId = ref(null)
const leavingOrg    = ref(false)

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
