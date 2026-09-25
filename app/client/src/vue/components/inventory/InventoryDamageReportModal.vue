<template>
  <AppModal ref="modal" class="inventory-damage-report-modal" title="Schaden melden" @close="close">
    <form :id="formId" class="modalform" @submit.prevent="submit">
      <label class="field">
        Was ist beschädigt? *
        <select v-model="form.target" required>
          <option :value="null" disabled>Bitte auswählen…</option>
          <optgroup v-if="items.length" label="Objekte & Fahrzeuge">
            <option v-for="item in items" :key="`item-${item.ID}`" :value="`item-${item.ID}`">
              {{ item.InventoryNumber ? `${item.InventoryNumber} · ` : '' }}{{ item.Title }}
            </option>
          </optgroup>
          <optgroup v-if="rooms.length" label="Räume">
            <option v-for="room in rooms" :key="`room-${room.ID}`" :value="`room-${room.ID}`">{{ room.Title }}</option>
          </optgroup>
        </select>
      </label>

      <label class="field field--3">
        Wann ist es passiert? *
        <input v-model="form.OccurredAt" type="datetime-local" :max="nowLocal" required>
      </label>

      <label class="field">
        Beschreibung *
        <textarea v-model="form.Description" rows="4" placeholder="Was ist kaputt gegangen und wie ist es passiert?" required />
      </label>

      <AppToggle
        v-model="form.MakesUnusable"
        :label="isRoomTarget ? 'Raum ist nicht mehr nutzbar (wird nicht mehr reservierbar)' : 'Nicht mehr benutzbar (wird auf „defekt“ gestellt)'"
      />

      <AppFileUpload
        v-model="form.images"
        label="Fotos"
        multiple
        :max-files="4"
        accept="image/jpeg,image/png,image/webp"
        :max-size="10 * 1024 * 1024"
        hint="Bis zu 4 Fotos (JPG, PNG oder WebP, max. 10 MB)"
      />

      <div v-if="error" class="app-modal_error">{{ error }}</div>
    </form>

    <template #actions>
      <AppButton variant="secondary" :disabled="saving" @click="close">Abbrechen</AppButton>
      <AppButton type="submit" :form="formId" variant="danger" :disabled="saving || !canSubmit">
        {{ saving ? 'Wird gemeldet…' : 'Schaden melden' }}
      </AppButton>
    </template>
  </AppModal>
</template>

<script setup>
import { ref, reactive, computed } from 'vue'
import { useInventoryStore } from '@stores/inventory'
import AppButton from '@components/ui/AppButton.vue'
import AppFileUpload from '@components/ui/AppFileUpload.vue'
import AppModal from '@components/ui/AppModal.vue'
import AppToggle from '@components/ui/AppToggle.vue'

const emit = defineEmits(['reported'])
const store = useInventoryStore()

const formId = `inventory-damage-form-${Math.random().toString(36).slice(2)}`
const modal = ref(null)
const saving = ref(false)
const error = ref(null)
const rentalId = ref(null)
const items = ref([])
const rooms = ref([])

function localNow() {
  const now = new Date()
  const pad = n => String(n).padStart(2, '0')
  return `${now.getFullYear()}-${pad(now.getMonth() + 1)}-${pad(now.getDate())}T${pad(now.getHours())}:${pad(now.getMinutes())}`
}
const nowLocal = ref(localNow())

const form = reactive({
  target: null,
  OccurredAt: '',
  Description: '',
  MakesUnusable: false,
  images: [],
})

const isRoomTarget = computed(() => form.target?.startsWith('room-'))
const canSubmit = computed(() => !!form.target && !!form.OccurredAt && !!form.Description.trim())

/** @param rental Ausleihe mit Items/Rooms */
function open(rental) {
  rentalId.value = rental.ID
  items.value = rental.Items || []
  rooms.value = rental.Rooms || []
  nowLocal.value = localNow()
  const only = items.value.length + rooms.value.length === 1
  Object.assign(form, {
    target: only ? (items.value.length ? `item-${items.value[0].ID}` : `room-${rooms.value[0].ID}`) : null,
    OccurredAt: nowLocal.value,
    Description: '',
    MakesUnusable: false,
    images: [],
  })
  error.value = null
  modal.value?.open()
}

function close() {
  modal.value?.close()
}

async function submit() {
  if (!canSubmit.value) return
  saving.value = true
  error.value = null
  try {
    const [type, id] = form.target.split('-')
    const response = await store.reportDamage(rentalId.value, {
      TargetType: type,
      TargetID: id,
      OccurredAt: form.OccurredAt,
      Description: form.Description.trim(),
      MakesUnusable: form.MakesUnusable,
      images: form.images,
    })
    if (response.data?.rental) emit('reported', response.data.rental)
    if (response.success) {
      close()
    } else {
      error.value = response.error || 'Schaden konnte nicht gemeldet werden.'
    }
  } catch (err) {
    error.value = err.message || 'Unbekannter Fehler.'
  } finally {
    saving.value = false
  }
}

defineExpose({ open, close })
</script>
