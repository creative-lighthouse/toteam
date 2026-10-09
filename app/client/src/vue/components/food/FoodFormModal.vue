<template>
  <!-- Gericht anlegen (Mahlzeit-Detailansicht) oder bearbeiten/löschen (auch im Essensplaner).
       Wer es mitbringt (Person oder Organisation) ist unabhängig davon, ob es bestellbar ist;
       bestellbare Gerichte lassen sich zusätzlich pro Person begrenzen.
       Mit `allow-orderable` lässt sich beim Bearbeiten die Art des Gerichts umschalten.

    foodModal.value.create(mealId, organizationId)  // neu → created(product)
    foodModal.value.open(food)                      // bearbeiten → saved(food) / deleted(id)
  -->
  <AppModal ref="modal" class="food-form-modal" :title="isCreate ? 'Gericht hinzufügen' : 'Gericht bearbeiten'" @close="close">
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

      <AppToggle
        v-if="isCreate || allowOrderable"
        v-model="form.isOrderable"
        label="Bestellbar (Menge pro Person begrenzbar)"
      />

      <label v-if="form.isOrderable" class="field">
        Max. pro Person (0 = unbegrenzt)
        <input v-model.number="form.maxQuantity" type="number" min="0">
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
        v-if="!isCreate"
        variant="danger"
        class="food-form-modal_delete"
        aria-label="Gericht löschen"
        title="Löschen"
        :disabled="saving"
        @click="remove"
      >
        <span class="icon-mask" :style="trashIconStyle" />
      </AppIconButton>
      <AppButton variant="secondary" :disabled="saving" @click="close">Abbrechen</AppButton>
      <AppButton type="submit" :form="formId" variant="primary" :disabled="saving || !canSubmit">
        {{ saving ? 'Speichern…' : (isCreate ? 'Hinzufügen' : 'Speichern') }}
      </AppButton>
    </template>
  </AppModal>
</template>

<script setup>
import { ref, computed } from 'vue'
import { apiGet, apiPost, apiPut, apiDelete } from '@utils/api'
import AppButton from '@components/ui/AppButton.vue'
import AppIconButton from '@components/ui/AppIconButton.vue'
import AppModal from '@components/ui/AppModal.vue'
import AppSegmentedToggle from '@components/ui/AppSegmentedToggle.vue'
import MemberPicker from '@components/ui/MemberPicker.vue'
import AppTextField from '@components/ui/AppTextField.vue'
import AppToggle from '@components/ui/AppToggle.vue'
import actionTrash from '../../../../icons/actions/action_trash.svg'

const props = defineProps({
  // Bestellbar an-/ausschalten (Produkt-Endpunkt: Essensplaner oder Mahlzeit-Verwalter)
  allowOrderable: { type: Boolean, default: false },
})

const emit = defineEmits(['created', 'saved', 'deleted'])

const trashIconStyle = { maskImage: `url("${actionTrash}")`, WebkitMaskImage: `url("${actionTrash}")` }

const formId = `food-form-${Math.random().toString(36).slice(2)}`
const modal = ref(null)
const foodId = ref(null)
const mealId = ref(null)          // nur beim Anlegen
const isCreate = computed(() => !foodId.value)
const isOrderable = ref(false)
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

// Der Produkt-Endpunkt kann alles (auch die Art umschalten); ohne Schalter werden
// feste Gerichte über den Endpunkt des Essensplaners gespeichert
const useProductEndpoint = computed(() => props.allowOrderable || isOrderable.value)
const endpoint = computed(() => `/food/${useProductEndpoint.value ? 'mealProduct' : 'plannerFood'}/${foodId.value}`)

function emptyForm() {
  return { title: '', preference: 'None', providedBy: 'organization', supplierId: null, isOrderable: false, maxQuantity: 0 }
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

/** Neues Gericht für eine Mahlzeit; organizationId = Organisation, der es gehören wird */
function create(forMealId, organizationId) {
  foodId.value = null
  mealId.value = forMealId
  isOrderable.value = false
  form.value = emptyForm()
  currentSupplier.value = null
  error.value = null
  loadMembers(organizationId)
  modal.value?.open()
}

/** @param {{ id: number, title: string, preference: string, supplier?: string, supplierId?: number, organizationId?: number, isOrderable?: boolean, maxQuantity?: number }} food */
function open(food) {
  foodId.value = food.id
  mealId.value = null
  isOrderable.value = !!food.isOrderable
  form.value = {
    title: food.title ?? '',
    preference: food.preference ?? 'None',
    providedBy: food.supplierId ? 'member' : 'organization',
    supplierId: food.supplierId ?? null,
    isOrderable: !!food.isOrderable,
    maxQuantity: food.maxQuantity ?? 0,
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
    const body = {
      title: form.value.title.trim(),
      preference: form.value.preference,
      supplierId: form.value.providedBy === 'member' ? form.value.supplierId : null,
      ...(isCreate.value || useProductEndpoint.value
        ? { isOrderable: form.value.isOrderable, maxQuantity: form.value.isOrderable ? (form.value.maxQuantity || 0) : 0 }
        : {}),
    }
    const response = isCreate.value
      ? await apiPost(`/food/mealProduct/${mealId.value}`, body)
      : await apiPut(endpoint.value, body)
    if (!response?.success) {
      error.value = response?.error || 'Gericht konnte nicht gespeichert werden.'
      return
    }
    close()
    if (isCreate.value) emit('created', response.data.product)
    else emit('saved', response.data.food)
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
    const response = await apiDelete(endpoint.value)
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

defineExpose({ create, open, close })
</script>
