<template>
  <!-- Gericht im Essensplaner bearbeiten oder löschen -->
  <AppModal ref="modal" class="food-edit-modal" title="Gericht bearbeiten" @close="close">
    <form :id="formId" class="modalform" @submit.prevent="submit">
      <AppTextField v-model="form.title" label="Gericht *" placeholder="z.B. Nudelsalat" required />

      <label class="field">
        Essenspräferenz
        <select v-model="form.preference">
          <option value="None">Keine Angabe</option>
          <option value="Vegetarian">Vegetarisch</option>
          <option value="Vegan">Vegan</option>
        </select>
      </label>

      <AppSegmentedToggle
        v-model="form.providedBy"
        label="Wer bringt es mit?"
        :options="[
          { value: 'member', label: 'Person' },
          { value: 'organization', label: 'Organisation' },
        ]"
      />

      <div v-if="form.providedBy === 'member'" class="field">
        <MemberPicker
          v-model="form.supplierId"
          :members="memberOptions"
          dropdown
          :disabled="loadingMembers"
        />
      </div>

      <div v-if="error" class="app-modal_error">{{ error }}</div>
    </form>

    <template #actions>
      <AppIconButton
        variant="danger"
        class="food-edit-modal_delete"
        aria-label="Gericht löschen"
        title="Löschen"
        :disabled="saving"
        @click="remove"
      >
        <span class="icon-mask" :style="trashIconStyle" />
      </AppIconButton>
      <AppButton variant="secondary" :disabled="saving" @click="close">Abbrechen</AppButton>
      <AppButton type="submit" :form="formId" variant="primary" :disabled="saving || !canSubmit">
        {{ saving ? 'Speichern…' : 'Speichern' }}
      </AppButton>
    </template>
  </AppModal>
</template>

<script setup>
import { ref, computed } from 'vue'
import { apiGet, apiPut, apiDelete } from '@utils/api'
import AppButton from '@components/ui/AppButton.vue'
import AppIconButton from '@components/ui/AppIconButton.vue'
import AppModal from '@components/ui/AppModal.vue'
import AppSegmentedToggle from '@components/ui/AppSegmentedToggle.vue'
import MemberPicker from '@components/ui/MemberPicker.vue'
import AppTextField from '@components/ui/AppTextField.vue'
import actionTrash from '../../../../icons/actions/action_trash.svg'

const emit = defineEmits(['saved', 'deleted'])

const trashIconStyle = { maskImage: `url("${actionTrash}")`, WebkitMaskImage: `url("${actionTrash}")` }

const formId = `food-edit-form-${Math.random().toString(36).slice(2)}`
const modal = ref(null)
const foodId = ref(null)
const form = ref(emptyForm())
const saving = ref(false)
const error = ref(null)

// Mitglieder der Organisation des Gerichts — Auswahl, wer es mitbringt
const members = ref([])
const membersOrgId = ref(null)
const loadingMembers = ref(false)
const currentSupplier = ref(null)

// Die bisherige Person auch dann anbieten, wenn sie nicht (mehr) Mitglied ist
const memberOptions = computed(() => {
  const s = currentSupplier.value
  return s && !members.value.some(m => m.ID === s.ID) ? [...members.value, s] : members.value
})

const canSubmit = computed(() =>
  !!form.value.title.trim() && (form.value.providedBy === 'organization' || !!form.value.supplierId)
)

function emptyForm() {
  return { title: '', preference: 'None', providedBy: 'organization', supplierId: null }
}

async function loadMembers(orgId) {
  if (!orgId || membersOrgId.value === orgId) return
  loadingMembers.value = true
  try {
    const response = await apiGet(`/calendar/members?organizationIds=${orgId}`, false)
    members.value = response?.members ?? []
    membersOrgId.value = orgId
  } catch {
    members.value = []
  } finally {
    loadingMembers.value = false
  }
}

/** @param {{ id: number, title: string, preference: string, supplier?: string, supplierId?: number, organizationId?: number }} food */
function open(food) {
  foodId.value = food.id
  form.value = {
    title: food.title ?? '',
    preference: food.preference ?? 'None',
    providedBy: food.supplierId ? 'member' : 'organization',
    supplierId: food.supplierId ?? null,
  }
  currentSupplier.value = food.supplierId ? { ID: food.supplierId, Name: food.supplier ?? '' } : null
  error.value = null
  loadMembers(food.organizationId)
  modal.value?.open()
}

function close() {
  modal.value?.close()
}

async function submit() {
  saving.value = true
  error.value = null
  try {
    const response = await apiPut(`/food/plannerFood/${foodId.value}`, {
      title: form.value.title.trim(),
      preference: form.value.preference,
      supplierId: form.value.providedBy === 'member' ? form.value.supplierId : null,
    })
    if (!response?.success) {
      error.value = response?.error || 'Gericht konnte nicht gespeichert werden.'
      return
    }
    close()
    emit('saved', response.data.food)
  } catch (err) {
    error.value = err.message || 'Gericht konnte nicht gespeichert werden.'
  } finally {
    saving.value = false
  }
}

async function remove() {
  if (!confirm(`„${form.value.title}“ wirklich löschen?`)) return
  saving.value = true
  error.value = null
  try {
    const response = await apiDelete(`/food/plannerFood/${foodId.value}`)
    if (!response?.success) {
      error.value = response?.error || 'Gericht konnte nicht gelöscht werden.'
      return
    }
    close()
    emit('deleted', foodId.value)
  } catch (err) {
    error.value = err.message || 'Gericht konnte nicht gelöscht werden.'
  } finally {
    saving.value = false
  }
}

defineExpose({ open, close })
</script>
