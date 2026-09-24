<template>
  <AppModal ref="modal" class="org-role-edit-modal" :title="isEdit ? 'Rolle bearbeiten' : 'Neue Rolle'" @close="close">
    <form id="org-role-edit-form" class="modalform" @submit.prevent="submit">

      <label class="field">
        Titel *
        <input id="role-title" v-model="form.Title" type="text" placeholder="z.B. Kassenwart" required />
      </label>

      <div v-for="(permissions, category) in orgRolesStore.categories" :key="category" class="org-role-edit-modal_category">
        <h3 class="org-role-edit-modal_category-title">{{ category }}</h3>
        <div class="org-role-edit-modal_permissions">
          <label v-for="(label, code) in permissions" :key="code" class="form-checkbox">
            <input type="checkbox" :value="code" v-model="form.Permissions" />
            {{ label }}
          </label>
        </div>
      </div>

      <div v-if="error" class="app-modal_error">{{ error }}</div>

    </form>

    <template #actions>
      <AppButton variant="secondary" :disabled="saving" @click="close">Abbrechen</AppButton>
      <AppButton type="submit" form="org-role-edit-form" variant="primary" :disabled="saving || !form.Title.trim()">
        {{ saving ? 'Speichern…' : (isEdit ? 'Speichern' : 'Erstellen') }}
      </AppButton>
    </template>
  </AppModal>
</template>

<script setup>
import { ref, reactive, computed, watch, onMounted } from 'vue'
import { useOrgRolesStore } from '@stores/orgRoles'
import AppButton from '@components/ui/AppButton.vue'
import AppModal from '@components/ui/AppModal.vue'

const props = defineProps({
  organizationId: { type: Number, required: true },
  role: { type: Object, default: null },
})
const emit = defineEmits(['saved'])
const orgRolesStore = useOrgRolesStore()

const modal = ref(null)
const saving = ref(false)
const error = ref(null)

const isEdit = computed(() => !!props.role)

const defaultForm = () => ({
  Title: '',
  Permissions: [],
})

const form = reactive(defaultForm())

function fillFromRole(role) {
  if (!role) return
  form.Title = role.Title
  form.Permissions = [...(role.Permissions || [])]
}

watch(() => props.role, fillFromRole)

function open() {
  Object.assign(form, defaultForm())
  fillFromRole(props.role)
  error.value = null
  modal.value?.open()
}

function close() {
  modal.value?.close()
}

async function submit() {
  if (!form.Title.trim()) return

  saving.value = true
  error.value = null

  const payload = {
    Title: form.Title.trim(),
    Permissions: form.Permissions,
  }

  try {
    const response = isEdit.value
      ? await orgRolesStore.updateRole(props.role.ID, payload)
      : await orgRolesStore.createRole({ ...payload, OrganizationID: props.organizationId })

    if (response.success) {
      emit('saved', response.data.role)
      close()
    } else {
      error.value = response.error || 'Fehler beim Speichern der Rolle.'
    }
  } catch (err) {
    error.value = err.message || 'Unbekannter Fehler.'
  } finally {
    saving.value = false
  }
}

onMounted(() => orgRolesStore.fetchCatalogue())

defineExpose({ open, close })
</script>
