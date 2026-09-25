<!--
  Schadensmeldungen (Ausleihe, Objekt- und Raum-Detail): offene rot hervorgehoben,
  behobene blasser mit Datum. Fotos öffnen in der Lightbox; wer darf, kann einen
  Schaden als behoben markieren.

    <InventoryDamageList :damages="rental.Damages" show-target @changed="reload" />
-->
<template>
  <ul class="inventory-damage-list">
    <li
      v-for="damage in damages"
      :key="damage.ID"
      class="inventory-damage-list_item"
      :class="{ 'inventory-damage-list_item--resolved': !damage.IsOpen }"
    >
      <div class="inventory-damage-list_head">
        <strong v-if="showTarget" class="inventory-damage-list_target">{{ damage.TargetTitle }}</strong>
        <span v-if="damage.MakesUnusable && damage.IsOpen" class="inventory-damage-list_unusable">unbenutzbar</span>
        <span v-if="!damage.IsOpen" class="inventory-damage-list_resolved">behoben am {{ formatDate(damage.ResolvedAt) }}</span>
      </div>

      <p class="inventory-damage-list_description">{{ damage.Description }}</p>

      <ul v-if="damage.Images.length" class="inventory-damage-list_images">
        <li v-for="(img, index) in damage.Images" :key="img.ID">
          <a :href="img.URL" target="_blank" rel="noopener" @click.prevent="lightbox?.open(damage.Images, index)">
            <img :src="img.Thumbnail" :alt="img.Name" loading="lazy">
          </a>
        </li>
      </ul>

      <p class="inventory-damage-list_meta">
        {{ formatDateTime(damage.OccurredAt) }}
        <template v-if="damage.ReportedBy"> · gemeldet von {{ damage.ReportedBy.Name }}</template>
        <template v-if="showRental && damage.RentalMember"> · Ausleihe von {{ damage.RentalMember.Name }}</template>
      </p>
      <p v-if="!damage.IsOpen && damage.ResolutionNote" class="inventory-damage-list_note">„{{ damage.ResolutionNote }}“</p>

      <div v-if="damage.CanResolve" class="inventory-damage-list_actions">
        <AppButton variant="secondary" size="small" @click="openResolve(damage)">Als behoben markieren</AppButton>
      </div>
    </li>
  </ul>

  <AppLightbox ref="lightbox" />

  <AppModal ref="resolveModal" class="inventory-damage-resolve-modal" title="Schaden behoben" @close="resolveModal?.close()">
    <form v-if="resolving" :id="resolveFormId" class="modalform" @submit.prevent="resolve">
      <p class="field inventory-damage-resolve-modal_target">{{ resolving.TargetTitle }}: {{ resolving.Description }}</p>
      <label class="field field--3">
        Behoben am *
        <input v-model="resolveForm.ResolvedAt" type="date" :max="todayIso()" required>
      </label>
      <label class="field">
        Notiz
        <textarea v-model="resolveForm.Note" rows="2" placeholder="z.B. repariert, ersetzt, Kosten übernommen …" />
      </label>
      <AppToggle
        v-if="resolving.ChangedTarget"
        v-model="resolveForm.Restore"
        :label="resolving.TargetType === 'room' ? 'Raum wieder reservierbar machen' : 'Wieder als einsatzbereit markieren'"
      />
      <div v-if="resolveError" class="app-modal_error">{{ resolveError }}</div>
    </form>
    <template #actions>
      <AppButton variant="secondary" :disabled="saving" @click="resolveModal?.close()">Abbrechen</AppButton>
      <AppButton type="submit" :form="resolveFormId" variant="primary" :disabled="saving">{{ saving ? 'Speichern…' : 'Behoben' }}</AppButton>
    </template>
  </AppModal>
</template>

<script setup>
import { ref, reactive } from 'vue'
import { useInventoryStore } from '@stores/inventory'
import { formatDate, todayIso } from '@utils/inventory'
import AppButton from '@components/ui/AppButton.vue'
import AppLightbox from '@components/ui/AppLightbox.vue'
import AppModal from '@components/ui/AppModal.vue'
import AppToggle from '@components/ui/AppToggle.vue'

defineProps({
  damages: { type: Array, required: true },
  // Betroffenes Objekt/Raum anzeigen (in der Ausleihe mit mehreren Objekten)
  showTarget: { type: Boolean, default: false },
  // Wer ausgeliehen hatte (im Objekt-/Raum-Detail)
  showRental: { type: Boolean, default: false },
})

const emit = defineEmits(['changed'])
const store = useInventoryStore()

const lightbox = ref(null)
const resolveModal = ref(null)
const resolveFormId = `inventory-damage-resolve-${Math.random().toString(36).slice(2)}`
const resolving = ref(null)
const saving = ref(false)
const resolveError = ref(null)
const resolveForm = reactive({ ResolvedAt: '', Note: '', Restore: true })

const dateTimeFormat = new Intl.DateTimeFormat('de-DE', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' })
function formatDateTime(value) {
  if (!value) return ''
  const date = new Date(value.replace(' ', 'T'))
  return Number.isNaN(date.getTime()) ? value : `${dateTimeFormat.format(date)} Uhr`
}

function openResolve(damage) {
  resolving.value = damage
  Object.assign(resolveForm, { ResolvedAt: todayIso(), Note: '', Restore: true })
  resolveError.value = null
  resolveModal.value?.open()
}

async function resolve() {
  saving.value = true
  resolveError.value = null
  try {
    const response = await store.resolveDamage(resolving.value.ID, { ...resolveForm })
    if (response.success) {
      resolveModal.value?.close()
      emit('changed', response.data.damage)
    } else {
      resolveError.value = response.error || 'Konnte nicht gespeichert werden.'
    }
  } finally {
    saving.value = false
  }
}
</script>
