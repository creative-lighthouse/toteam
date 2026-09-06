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

            <!-- Canvas-Editor -->
            <div v-if="editorSrc" class="image-editor">
              <p class="image-editor_hint">Ziehen zum Verschieben · Scrollen oder Schieberegler zum Zoomen</p>
              <canvas
                ref="canvasEl"
                class="image-editor_canvas"
                width="280"
                height="280"
                @wheel.prevent="onWheel"
                @mousedown.prevent="onMouseDown"
                @mousemove="onMouseMove"
                @mouseup="onMouseUp"
                @mouseleave="onMouseUp"
                @touchstart.prevent="onTouchStart"
                @touchmove.prevent="onTouchMove"
                @touchend="onTouchEnd"
              ></canvas>

              <div class="zoom-controls">
                <AppIconButton variant="neutral" aria-label="Verkleinern" @click="adjustZoom(-0.15)">−</AppIconButton>
                <input
                  type="range"
                  class="zoom-slider"
                  min="1"
                  :max="maxZoom"
                  step="0.01"
                  :value="zoom"
                  @input="onZoomSlider"
                >
                <AppIconButton variant="neutral" aria-label="Vergrößern" @click="adjustZoom(0.15)">+</AppIconButton>
              </div>

              <AppButton
                variant="primary"
                :disabled="savingImage"
                @click="saveCroppedImage"
              >
                {{ savingImage ? 'Wird gespeichert …' : 'Bild speichern' }}
              </AppButton>
            </div>
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
                <img v-if="org.LogoURL" :src="org.LogoURL" class="org-item_logo" alt="">
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
</template>

<script setup>
import { ref, reactive, computed, nextTick } from 'vue'
import { apiGet, apiPost, apiPostForm, apiPut } from '@utils/api'
import { useAuthStore } from '@stores/auth'
import AppButton from '@components/AppButton.vue'
import AppIconButton from '@components/AppIconButton.vue'
import AppAvatar from '@components/AppAvatar.vue'

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
  resetEditor()
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

// ── Image editor ───────────────────────────────────
const fileInputEl  = ref(null)
const canvasEl     = ref(null)
const editorSrc    = ref(null)
const editorImg    = ref(null)
const zoom         = ref(1)
const maxZoom      = ref(8)
const panX         = ref(0)
const panY         = ref(0)
const fileError    = ref(null)
const savingImage  = ref(false)
const imageSaved   = ref(false)

// drag
const isDragging     = ref(false)
const dragStartX     = ref(0)
const dragStartY     = ref(0)
const dragStartPanX  = ref(0)
const dragStartPanY  = ref(0)

// pinch
const lastPinchDist = ref(null)

const currentAvatarUrl = computed(() => authStore.user?.Avatar ?? '')

function onFileSelected(e) {
  fileError.value  = null
  imageSaved.value = false
  const file = e.target.files[0]
  if (!file) return

  if (!['image/jpeg', 'image/png'].includes(file.type)) {
    fileError.value = 'Nur PNG und JPEG sind erlaubt.'
    return
  }
  if (file.size > 2 * 1024 * 1024) {
    fileError.value = 'Das Bild darf maximal 2 MB groß sein.'
    return
  }

  if (editorSrc.value) URL.revokeObjectURL(editorSrc.value)
  editorSrc.value = URL.createObjectURL(file)

  const img = new Image()
  img.onload = () => {
    editorImg.value = img
    zoom.value      = 1
    panX.value      = img.width  / 2
    panY.value      = img.height / 2
    nextTick(redraw)
  }
  img.src = editorSrc.value
}

function redraw() {
  const canvas = canvasEl.value
  const img    = editorImg.value
  if (!canvas || !img) return

  const ctx       = canvas.getContext('2d')
  const size      = canvas.width
  const shortSide = Math.min(img.width, img.height)
  const viewSize  = shortSide / zoom.value
  const halfView  = viewSize / 2

  const cx = Math.max(halfView, Math.min(img.width  - halfView, panX.value))
  const cy = Math.max(halfView, Math.min(img.height - halfView, panY.value))

  ctx.clearRect(0, 0, size, size)
  ctx.drawImage(img, cx - halfView, cy - halfView, viewSize, viewSize, 0, 0, size, size)
}

function adjustZoom(delta) {
  zoom.value = Math.max(1, Math.min(maxZoom.value, zoom.value + delta * zoom.value))
  redraw()
}

function onZoomSlider(e) {
  zoom.value = parseFloat(e.target.value)
  redraw()
}

function onWheel(e) {
  adjustZoom(e.deltaY < 0 ? 0.1 : -0.1)
}

function onMouseDown(e) {
  isDragging.value    = true
  dragStartX.value    = e.clientX
  dragStartY.value    = e.clientY
  dragStartPanX.value = panX.value
  dragStartPanY.value = panY.value
}

function onMouseMove(e) {
  if (!isDragging.value || !editorImg.value) return
  const shortSide = Math.min(editorImg.value.width, editorImg.value.height)
  const viewSize  = shortSide / zoom.value
  const scale     = viewSize / canvasEl.value.width
  panX.value = dragStartPanX.value - (e.clientX - dragStartX.value) * scale
  panY.value = dragStartPanY.value - (e.clientY - dragStartY.value) * scale
  redraw()
}

function onMouseUp() {
  isDragging.value = false
}

function pinchDist(e) {
  const dx = e.touches[0].clientX - e.touches[1].clientX
  const dy = e.touches[0].clientY - e.touches[1].clientY
  return Math.sqrt(dx * dx + dy * dy)
}

function onTouchStart(e) {
  if (e.touches.length === 2) {
    lastPinchDist.value = pinchDist(e)
    isDragging.value    = false
  } else {
    isDragging.value    = true
    dragStartX.value    = e.touches[0].clientX
    dragStartY.value    = e.touches[0].clientY
    dragStartPanX.value = panX.value
    dragStartPanY.value = panY.value
  }
}

function onTouchMove(e) {
  if (e.touches.length === 2 && lastPinchDist.value !== null) {
    const dist  = pinchDist(e)
    const ratio = dist / lastPinchDist.value
    zoom.value  = Math.max(1, Math.min(maxZoom.value, zoom.value * ratio))
    lastPinchDist.value = dist
    redraw()
  } else if (e.touches.length === 1 && isDragging.value && editorImg.value) {
    const shortSide = Math.min(editorImg.value.width, editorImg.value.height)
    const viewSize  = shortSide / zoom.value
    const scale     = viewSize / canvasEl.value.width
    panX.value = dragStartPanX.value - (e.touches[0].clientX - dragStartX.value) * scale
    panY.value = dragStartPanY.value - (e.touches[0].clientY - dragStartY.value) * scale
    redraw()
  }
}

function onTouchEnd(e) {
  if (e.touches.length < 2) lastPinchDist.value = null
  if (e.touches.length === 0) isDragging.value   = false
}

async function saveCroppedImage() {
  if (!editorImg.value) return
  savingImage.value = true
  fileError.value   = null

  try {
    const img       = editorImg.value
    const shortSide = Math.min(img.width, img.height)
    const viewSize  = shortSide / zoom.value
    // Output at most 800×800; never upscale beyond source pixels
    const outputSize = Math.min(800, Math.round(viewSize))
    const halfView   = viewSize / 2
    const cx = Math.max(halfView, Math.min(img.width  - halfView, panX.value))
    const cy = Math.max(halfView, Math.min(img.height - halfView, panY.value))

    const out = document.createElement('canvas')
    out.width = out.height = outputSize
    out.getContext('2d').drawImage(img, cx - halfView, cy - halfView, viewSize, viewSize, 0, 0, outputSize, outputSize)

    const blob = await new Promise(resolve => out.toBlob(resolve, 'image/jpeg', 0.92))
    const fd   = new FormData()
    fd.append('image', blob, 'profile.jpg')

    const result = await apiPostForm('/profile/uploadImage', fd)

    if (result.success && result.data?.Avatar) {
      authStore.updateUser({ Avatar: result.data.Avatar })
      resetEditor()
      imageSaved.value = true
      emit('updated')
    } else {
      fileError.value = result.error ?? 'Bild konnte nicht gespeichert werden.'
    }
  } catch (err) {
    console.error('Bild-Upload fehlgeschlagen:', err)
    fileError.value = 'Hochladen fehlgeschlagen.'
  } finally {
    savingImage.value = false
  }
}

function resetEditor() {
  if (editorSrc.value) {
    URL.revokeObjectURL(editorSrc.value)
    editorSrc.value = null
  }
  editorImg.value = null
  zoom.value      = 1
  if (fileInputEl.value) fileInputEl.value.value = ''
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
