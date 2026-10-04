<template>
  <AppModal ref="modal" class="org-event-form-modal" :title="isEdit ? 'Event bearbeiten' : 'Neues Event'" @close="close">
    <form id="org-event-form" class="modalform" @submit.prevent="submit">
      <div v-if="!isEdit && orgs.length > 1" class="field">
        <label>Organisation *</label>
        <OrganizationPicker v-model="form.organizationId" :orgs="orgs" />
      </div>

      <label class="field">
        Titel *
        <input v-model="form.title" type="text" autocomplete="off" required placeholder="z.B. Halloweenhaus 2026" />
      </label>

      <div class="field">
        <label>Bild</label>
        <div class="org-event-form-modal_image">
          <img v-if="imagePreview" :src="imagePreview" alt="" class="org-event-form-modal_image-preview">
          <div class="org-event-form-modal_image-actions">
            <label class="app-button app-button--secondary app-button--default org-event-form-modal_image-button">
              {{ imagePreview ? 'Bild ändern' : 'Bild wählen' }}
              <input
                ref="imageInputEl"
                type="file"
                accept="image/png,image/jpeg"
                class="org-event-form-modal_image-input"
                @change="onImageSelected"
              >
            </label>
            <AppButton v-if="imagePreview" variant="secondary" @click="clearImage">Entfernen</AppButton>
          </div>
        </div>
      </div>

      <div v-if="existingGallery.length" class="field">
        <label>Galerie</label>
        <ul class="org-event-form-modal_gallery">
          <li v-for="img in existingGallery" :key="img.ID">
            <img :src="img.Thumbnail" :alt="img.Name">
            <AppIconButton variant="ghost" aria-label="Bild entfernen" title="Bild entfernen" @click="removeGalleryImage(img)">✕</AppIconButton>
          </li>
        </ul>
      </div>
      <AppFileUpload
        v-model="newGallery"
        :label="existingGallery.length ? 'Weitere Galeriebilder' : 'Galerie'"
        multiple
        :max-files="20"
        accept="image/jpeg,image/png,image/webp"
        :max-size="10 * 1024 * 1024"
        hint="Zusätzliche Bilder zum Hauptbild – JPG, PNG oder WebP, max. 10 MB"
      />

      <DateTimeRangeField
        :model-value="form"
        @update:model-value="v => Object.assign(form, v)"
        time="toggle"
        :required="false"
        start-label-date="Beginn"
        start-label-time="Beginn"
        end-label-date="Ende"
        end-label-time="Ende"
      />

      <label class="field">
        Veranstaltungsort
        <input v-model="form.location" type="text" autocomplete="off" placeholder="z.B. Gemeindehaus" />
      </label>

      <label class="field">
        Straße und Hausnummer
        <input v-model="form.street" type="text" autocomplete="street-address" placeholder="z.B. Hauptstraße 1" />
      </label>

      <label class="field field--2">
        PLZ
        <input v-model="form.postalCode" type="text" inputmode="numeric" maxlength="10" autocomplete="postal-code" />
      </label>

      <!-- Der Ort erscheint auch auf der Event-Karte -->
      <label class="field field--4">
        Ort
        <input v-model="form.city" type="text" autocomplete="address-level2" placeholder="z.B. Lütjensee" />
      </label>

      <label class="field">
        Art
        <select v-model="form.typeId">
          <option :value="null">Keine Angabe</option>
          <option v-for="type in orgEventsStore.types" :key="type.ID" :value="type.ID">{{ type.Title }}</option>
        </select>
      </label>

      <div v-if="orgEventsStore.ageGroups.length" class="field">
        <label>Empfohlen für</label>
        <AppChipSelect
          v-model="form.ageGroupIds"
          aria-label="Empfohlen für"
          :options="orgEventsStore.ageGroups.map(g => ({ value: g.ID, label: g.Title }))"
        />
      </div>

      <AppToggle v-model="form.isPublic" label="Öffentlich sichtbar" />
      <p class="org-event-form-modal_hint org-event-form-modal_hint--attached">
        {{ form.isPublic ? 'Das Event darf öffentlich angezeigt werden.' : 'Das Event ist nur intern für Mitglieder sichtbar.' }}
      </p>

      <AppSegmentedToggle
        v-model="form.priceMode"
        label="Eintritt"
        :options="[
          { value: 'Fixed', label: 'Festpreis' },
          { value: 'Tiered', label: 'Gestaffelt' },
          { value: 'Free', label: 'Kostenfrei' },
          { value: 'Donation', label: 'Spende' },
        ]"
      />

      <label v-if="form.priceMode === 'Fixed'" class="field">
        Preis
        <span class="org-event-form-modal_price org-event-form-modal_price--fixed">
          <input v-model="form.fixedPrice" type="number" autocomplete="off" step="0.01" min="0" placeholder="0,00">
          €
        </span>
      </label>

      <div v-if="form.priceMode === 'Tiered'" class="field">
        <label>Preise</label>
        <ul v-if="form.prices.length" class="org-event-form-modal_prices">
          <li v-for="(price, index) in form.prices" :key="price.key">
            <input v-model="price.title" type="text" autocomplete="off" placeholder="z.B. Erwachsene" aria-label="Bezeichnung">
            <span class="org-event-form-modal_price">
              <input v-model="price.price" type="number" autocomplete="off" step="0.01" min="0" placeholder="0,00" aria-label="Preis in Euro">
              €
            </span>
            <AppIconButton variant="ghost" aria-label="Preis entfernen" title="Preis entfernen" @click="form.prices.splice(index, 1)">✕</AppIconButton>
          </li>
        </ul>
        <div>
          <AppButton variant="secondary" @click="addPrice">+ Preis hinzufügen</AppButton>
        </div>
      </div>

      <div v-if="error" class="app-modal_error">{{ error }}</div>
    </form>

    <template #actions>
      <AppButton variant="secondary" :disabled="submitting" @click="close">Abbrechen</AppButton>
      <AppButton type="submit" form="org-event-form" variant="primary" :disabled="submitting">
        {{ submitting ? 'Speichern…' : (isEdit ? 'Speichern' : 'Anlegen') }}
      </AppButton>
    </template>
  </AppModal>

  <ImageCropModal
    ref="cropModal"
    title="Bild zuschneiden"
    shape="rect"
    :aspect-ratio="16 / 9"
    :output-size="1280"
    @cropped="onImageCropped"
  />
</template>

<script setup>
import { ref, reactive, computed } from 'vue'
import { useOrgEventsStore } from '@stores/orgEvents'
import AppButton from '@components/ui/AppButton.vue'
import AppChipSelect from '@components/ui/AppChipSelect.vue'
import AppFileUpload from '@components/ui/AppFileUpload.vue'
import AppIconButton from '@components/ui/AppIconButton.vue'
import AppModal from '@components/ui/AppModal.vue'
import AppSegmentedToggle from '@components/ui/AppSegmentedToggle.vue'
import AppToggle from '@components/ui/AppToggle.vue'
import DateTimeRangeField from '@components/ui/DateTimeRangeField.vue'
import ImageCropModal from '@components/ui/ImageCropModal.vue'
import OrganizationPicker from '@components/ui/OrganizationPicker.vue'

const props = defineProps({
  // Organisationen, in denen man Termine verwalten darf (CALENDAR_MANAGE)
  orgs: { type: Array, default: () => [] },
})

const emit = defineEmits(['saved'])

const orgEventsStore = useOrgEventsStore()

const modal = ref(null)
const cropModal = ref(null)
const imageInputEl = ref(null)
const editingId = ref(null)
const submitting = ref(false)
const error = ref(null)

const isEdit = computed(() => editingId.value !== null)

let priceKey = 0
const priceRow = (title = '', price = '') => ({ key: ++priceKey, title, price })

const defaultForm = () => ({
  organizationId: props.orgs.length === 1 ? props.orgs[0].ID : null,
  title: '',
  dateStart: '',
  dateEnd: '',
  timeStart: '',
  timeEnd: '',
  allDay: true,
  location: '',
  street: '',
  postalCode: '',
  city: '',
  typeId: null,
  ageGroupIds: [],
  isPublic: false,
  priceMode: 'Fixed',
  fixedPrice: '',
  prices: [],
})

const form = reactive(defaultForm())

// Bild: bestehendes (URL vom Server) oder neu zugeschnittenes, das erst nach
// dem Speichern hochgeladen wird — ein neues Event hat vorher noch keine ID
const existingImageUrl = ref(null)
const newImage = ref(null)
const newImageUrl = ref(null)
const imagePreview = computed(() => newImageUrl.value ?? existingImageUrl.value)

function resetImage(url = null) {
  if (newImageUrl.value) URL.revokeObjectURL(newImageUrl.value)
  existingImageUrl.value = url
  newImage.value = null
  newImageUrl.value = null
}

function onImageSelected(e) {
  error.value = null
  const file = e.target.files?.[0]
  if (imageInputEl.value) imageInputEl.value.value = ''
  if (!file) return
  if (!['image/png', 'image/jpeg'].includes(file.type)) {
    error.value = 'Nur PNG und JPEG sind erlaubt.'
    return
  }
  if (file.size > 25 * 1024 * 1024) {
    error.value = 'Das Bild darf maximal 25 MB groß sein.'
    return
  }
  cropModal.value?.open(file)
}

function onImageCropped(blob) {
  if (newImageUrl.value) URL.revokeObjectURL(newImageUrl.value)
  newImage.value = blob
  newImageUrl.value = URL.createObjectURL(blob)
}

function clearImage() {
  resetImage(null)
}

// Galerie: vorhandene Bilder werden sofort entfernt, neue nach dem Speichern hochgeladen
const existingGallery = ref([])
const newGallery = ref([])

function resetGallery(images = []) {
  existingGallery.value = [...images]
  newGallery.value = []
}

async function removeGalleryImage(img) {
  if (!confirm(`„${img.Name}“ wirklich aus der Galerie entfernen?`)) return
  error.value = null
  try {
    const event = await orgEventsStore.removeGalleryImage(editingId.value, img.ID)
    existingGallery.value = event.Gallery ?? []
  } catch (e) {
    error.value = e.message
  }
}

// Je nach Preisangabe: ein Betrag, die Tabelle oder nichts (kostenfrei/gegen Spende)
function pricesPayload() {
  if (form.priceMode === 'Fixed') {
    return form.fixedPrice === '' ? [] : [{ title: '', price: Number(form.fixedPrice) }]
  }
  if (form.priceMode === 'Tiered') {
    return form.prices.map(p => ({ title: p.title.trim(), price: p.price === '' ? '' : Number(p.price) }))
  }
  return []
}

function addPrice() {
  form.prices.push(priceRow())
}

function open() {
  editingId.value = null
  Object.assign(form, defaultForm())
  resetImage()
  resetGallery()
  error.value = null
  orgEventsStore.fetchOptions()
  modal.value?.open()
}

// event: Summary von der Event-Seite (GET /calendar/orgEvent/{id})
function openForEdit(event) {
  editingId.value = event.ID
  Object.assign(form, {
    organizationId: event.OrganizationID,
    title: event.Title,
    dateStart: event.DateStart ?? '',
    dateEnd: event.DateEnd ?? '',
    timeStart: event.TimeStart?.slice(0, 5) ?? '',
    timeEnd: event.TimeEnd?.slice(0, 5) ?? '',
    allDay: event.AllDay ?? true,
    location: event.Location ?? '',
    street: event.Street ?? '',
    postalCode: event.PostalCode ?? '',
    city: event.City ?? '',
    typeId: event.TypeID ?? null,
    ageGroupIds: (event.AgeGroups ?? []).map(g => g.ID),
    isPublic: !!event.IsPublic,
    priceMode: event.PriceMode ?? 'Fixed',
    fixedPrice: event.PriceMode === 'Fixed' && event.Prices?.length ? event.Prices[0].Price : '',
    prices: event.PriceMode === 'Tiered' ? (event.Prices ?? []).map(p => priceRow(p.Title, p.Price)) : [],
  })
  resetImage(event.ImageURL ?? null)
  resetGallery(event.Gallery ?? [])
  error.value = null
  orgEventsStore.fetchOptions()
  modal.value?.open()
}

function close() {
  modal.value?.close()
}

async function submit() {
  error.value = null
  if (!form.title.trim()) {
    error.value = 'Bitte gib einen Titel ein.'
    return
  }
  if (!isEdit.value && !form.organizationId) {
    error.value = 'Bitte wähle eine Organisation.'
    return
  }

  const payload = {
    title: form.title.trim(),
    dateStart: form.dateStart || null,
    dateEnd: form.dateEnd || null,
    timeStart: form.allDay ? null : (form.timeStart || null),
    timeEnd: form.allDay ? null : (form.timeEnd || null),
    allDay: form.allDay,
    location: form.location.trim(),
    street: form.street.trim(),
    postalCode: form.postalCode.trim(),
    city: form.city.trim(),
    typeId: form.typeId || 0,
    ageGroupIds: form.ageGroupIds,
    isPublic: form.isPublic,
    priceMode: form.priceMode,
    prices: pricesPayload(),
  }

  submitting.value = true
  try {
    let event = isEdit.value
      ? await orgEventsStore.updateEvent(editingId.value, payload)
      : await orgEventsStore.createEvent({ ...payload, organizationId: form.organizationId })
    // Ab hier existiert das Event — scheitert das Bild, nicht noch einmal anlegen
    editingId.value = event.ID

    if (newImage.value) {
      event = await orgEventsStore.uploadImage(event.ID, newImage.value)
    } else if (!existingImageUrl.value && event.ImageURL) {
      event = await orgEventsStore.removeImage(event.ID)
    }
    resetImage(event.ImageURL)

    if (newGallery.value.length) {
      try {
        event = await orgEventsStore.uploadGallery(event.ID, newGallery.value)
        resetGallery(event.Gallery ?? [])
      } catch (e) {
        // Wie im RoomFormModal: Auswahl leeren, sonst würden bereits hochgeladene
        // Bilder beim nächsten Speichern doppelt angelegt
        const updated = orgEventsStore.events.find(ev => ev.ID === event.ID)
        resetGallery(updated?.Gallery ?? existingGallery.value)
        throw e
      }
    }

    close()
    emit('saved', event)
  } catch (e) {
    error.value = e.message
  } finally {
    submitting.value = false
  }
}

defineExpose({ open, openForEdit, close })
</script>
