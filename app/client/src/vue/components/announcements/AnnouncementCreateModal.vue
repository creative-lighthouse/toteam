<template>
  <AppModal ref="modal" class="announcement-create-modal" title="Neue Mitteilung" @close="close">
    <form id="announcement-create-form" class="modalform" @submit.prevent="submit">

      <div class="field">
        <label>Organisationen *</label>
        <OrganizationPicker v-model="form.OrganizationIDs" :orgs="store.createOrganizations" multiple />
      </div>

      <label class="field">
        Titel *
        <input
          id="announcement-title"
          v-model="form.Title"
          type="text"
          placeholder="Titel der Mitteilung"
          required
          autofocus
        />
      </label>

      <label v-if="store.categories.length" class="field">
        Kategorie
        <select v-model="form.CategoryID">
          <option :value="0">Keine Kategorie</option>
          <option v-for="category in store.categories" :key="category.ID" :value="category.ID">{{ category.Title }}</option>
        </select>
      </label>

      <DateTimeRangeField
        :model-value="dateField"
        @update:model-value="v => (dateField = v)"
        time="always"
        :required="false"
        start-label-time="Veröffentlichen ab"
        end-label-time="Gültig bis"
      />
      <p class="field announcement-create-modal_hint">
        Leer lassen, um die Mitteilung sofort bzw. unbegrenzt anzuzeigen.
      </p>

      <label class="field">
        Kurztext
        <textarea
          id="announcement-shorttext"
          v-model="form.ShortText"
          rows="2"
          placeholder="Kurze Zusammenfassung für die Übersicht…"
        />
      </label>

      <label class="field">
        Langtext
        <textarea
          id="announcement-longtext"
          v-model="form.LongText"
          rows="8"
          placeholder="Vollständiger Text der Mitteilung…"
        />
      </label>

      <div v-if="error" class="app-modal_error">{{ error }}</div>
    </form>

    <template #actions>
      <AppButton variant="secondary" :disabled="saving" @click="close">Abbrechen</AppButton>
      <AppButton type="submit" form="announcement-create-form" variant="primary" :disabled="saving || !form.Title.trim() || !form.OrganizationIDs.length">
        {{ saving ? 'Speichern…' : 'Veröffentlichen' }}
      </AppButton>
    </template>
  </AppModal>
</template>

<script setup>
import { ref, reactive, computed } from 'vue'
import { useAnnouncementsStore } from '@stores/announcements'
import AppButton from '@components/ui/AppButton.vue'
import AppModal from '@components/ui/AppModal.vue'
import OrganizationPicker from '@components/ui/OrganizationPicker.vue'
import DateTimeRangeField from '@components/ui/DateTimeRangeField.vue'

const emit = defineEmits(['created'])
const store = useAnnouncementsStore()

const modal = ref(null)
const saving = ref(false)
const error = ref(null)

const defaultForm = () => ({
  Title: '',
  ShortText: '',
  LongText: '',
  CategoryID: 0,
  ReleaseDate: { date: '', time: '' },
  ExpiryDate: { date: '', time: '' },
  OrganizationIDs: store.createOrganizations.length === 1 ? [store.createOrganizations[0].ID] : [],
})

const form = reactive(defaultForm())

// { dateStart, timeStart, dateEnd, timeEnd }-Form, die DateTimeRangeField per v-model erwartet/liefert.
const dateField = computed({
  get: () => ({
    dateStart: form.ReleaseDate.date,
    timeStart: form.ReleaseDate.time,
    dateEnd: form.ExpiryDate.date,
    timeEnd: form.ExpiryDate.time,
  }),
  set: (val) => {
    form.ReleaseDate = { date: val.dateStart, time: val.timeStart }
    form.ExpiryDate = { date: val.dateEnd, time: val.timeEnd }
  },
})

function toDateTime({ date, time }) {
  return date ? `${date}T${time || '00:00'}` : null
}

function open() {
  Object.assign(form, defaultForm())
  error.value = null
  modal.value?.open()
}

function close() {
  modal.value?.close()
}

async function submit() {
  if (!form.Title.trim() || !form.OrganizationIDs.length) return

  const releaseDate = toDateTime(form.ReleaseDate)
  const expiryDate = toDateTime(form.ExpiryDate)
  if (releaseDate && expiryDate && expiryDate <= releaseDate) {
    error.value = 'Das Ablaufdatum muss nach dem Veröffentlichungsdatum liegen.'
    return
  }

  saving.value = true
  error.value = null

  try {
    const response = await store.createAnnouncement({
      Title: form.Title.trim(),
      ShortText: form.ShortText.trim(),
      LongText: form.LongText,
      CategoryID: form.CategoryID || 0,
      ReleaseDate: releaseDate,
      ExpiryDate: expiryDate,
      OrganizationIDs: form.OrganizationIDs,
    })

    if (response.success) {
      emit('created', response.data.announcement)
      close()
    } else {
      error.value = response.error || 'Fehler beim Erstellen der Mitteilung.'
    }
  } catch (err) {
    error.value = err.message || 'Unbekannter Fehler.'
  } finally {
    saving.value = false
  }
}

defineExpose({ open, close })
</script>
