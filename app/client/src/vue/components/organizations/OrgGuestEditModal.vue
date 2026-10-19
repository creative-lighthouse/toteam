<template>
  <!-- Gast-Mitglied anlegen oder bearbeiten (Organisation verwalten): Personen ohne eigenen
       Account, die nur in dieser Organisation existieren. Sie können zu Terminen eingeladen
       werden; ihre Zu-/Absagen tragen andere ein ("RSVP anpassen"). -->
  <AppModal ref="modal" class="org-guest-edit-modal" :title="guestId ? 'Gast bearbeiten' : 'Gast anlegen'" @close="close">
    <form :id="formId" class="modalform" @submit.prevent="submit">
      <p v-if="!guestId" class="field org-guest-edit-modal_hint">
        Gäste haben keinen eigenen Account. Sie tauchen in Listen auf und können zu Terminen eingeladen werden –
        ihre Zu- und Absagen trägst du (oder wer „Zu-/Absagen für andere eintragen“ darf) ein.
        Tritt die Person später ToTeam bei, kann sie ihren Gast übernehmen.
      </p>

      <label class="field field--3">
        Vorname *
        <input v-model="form.FirstName" type="text" maxlength="50" required autofocus>
      </label>
      <label class="field field--3">
        Nachname
        <input v-model="form.Surname" type="text" maxlength="50">
      </label>

      <label class="field">
        Essensvorliebe
        <select v-model="form.FoodPreference">
          <option value="None">Keine Angabe</option>
          <option value="Vegetarian">Vegetarisch</option>
          <option value="Vegan">Vegan</option>
        </select>
      </label>

      <!-- Allergien & Unverträglichkeiten — gleiche Auswahl wie im eigenen Profil, nach Kategorie -->
      <div v-if="allergyGroups.length" class="field">
        <label>Allergien &amp; Unverträglichkeiten</label>
        <div v-for="group in allergyGroups" :key="group.category" class="org-guest-edit-modal_allergy-group">
          <span class="org-guest-edit-modal_allergy-category">{{ group.category }}</span>
          <AppChipSelect
            v-model="form.AllergyIDs"
            :aria-label="`Allergien: ${group.category}`"
            :options="group.items.map(a => ({ value: a.ID, label: a.Title }))"
          />
        </div>
      </div>

      <div v-if="error" class="field app-modal_error">{{ error }}</div>
    </form>

    <template #actions>
      <AppButton variant="secondary" :disabled="saving" @click="close">Abbrechen</AppButton>
      <AppButton type="submit" :form="formId" variant="primary" :disabled="saving || !form.FirstName.trim()">
        {{ saving ? 'Speichern…' : (guestId ? 'Speichern' : 'Anlegen') }}
      </AppButton>
    </template>
  </AppModal>
</template>

<script setup>
import { ref, reactive, computed } from 'vue'
import { apiPost, apiPut } from '@utils/api'
import AppButton from '@components/ui/AppButton.vue'
import AppModal from '@components/ui/AppModal.vue'
import AppChipSelect from '@components/ui/AppChipSelect.vue'

const props = defineProps({
  organizationId: { type: Number, required: true },
  // Wählbare Allergien [{ ID, Title, Category }] (aus organizations/detail)
  allergyOptions: { type: Array, default: () => [] },
})

// (Meldung) — nach dem Speichern
const emit = defineEmits(['saved'])

const formId = `org-guest-edit-${Math.random().toString(36).slice(2)}`

const modal = ref(null)
const guestId = ref(null)
const saving = ref(false)
const error = ref(null)
const form = reactive({ FirstName: '', Surname: '', FoodPreference: 'None', AllergyIDs: [] })

// Kategorien in fester Reihenfolge (Essen zuerst — für Mahlzeiten am wichtigsten)
const CATEGORY_ORDER = ['Essen', 'Tiere', 'Sonstiges']
const allergyGroups = computed(() =>
  CATEGORY_ORDER
    .map(category => ({ category, items: props.allergyOptions.filter(a => (a.Category || 'Sonstiges') === category) }))
    .filter(group => group.items.length)
)

/** Ohne Gast: neu anlegen; mit Gast (Eintrag aus der Mitgliederliste) bearbeiten */
function open(guest = null) {
  guestId.value = guest?.MemberID ?? null
  Object.assign(form, {
    FirstName: guest?.FirstName ?? '',
    Surname: guest?.Surname ?? '',
    FoodPreference: guest?.FoodPreference ?? 'None',
    AllergyIDs: [...(guest?.AllergyIDs ?? [])],
  })
  error.value = null
  saving.value = false
  modal.value?.open()
}

function close() {
  modal.value?.close()
}

async function submit() {
  if (!form.FirstName.trim() || saving.value) return
  saving.value = true
  error.value = null
  try {
    const payload = {
      FirstName: form.FirstName.trim(),
      Surname: form.Surname.trim(),
      FoodPreference: form.FoodPreference,
      AllergyIDs: form.AllergyIDs,
    }
    const response = guestId.value
      ? await apiPut(`/organizations/guestUpdate/${guestId.value}`, payload)
      : await apiPost(`/organizations/guestStore/${props.organizationId}`, payload)
    if (!response.success) {
      error.value = response.error || 'Gast konnte nicht gespeichert werden.'
      return
    }
    emit('saved', response.message)
    close()
  } catch (err) {
    error.value = err.message || 'Gast konnte nicht gespeichert werden.'
  } finally {
    saving.value = false
  }
}

defineExpose({ open, close })
</script>
