<template>
  <Teleport to="body">
    <dialog ref="dialogEl" class="role-edit-modal" @cancel.prevent="close">
      <div class="role-edit-modal_content" @click.stop>

        <div class="role-edit-modal_header">
          <h2 class="hl2 role-edit-modal_title">{{ isEdit ? 'Rolle bearbeiten' : 'Neue Rolle' }}</h2>
          <button class="button--close" aria-label="Schließen" @click="close">✕</button>
        </div>

        <form class="role-edit-modal_body" @submit.prevent="submit">

          <div class="form-field">
            <label class="form-label" for="role-title">Titel *</label>
            <input id="role-title" v-model="form.Title" type="text" class="input" placeholder="z.B. Kassenwart" required />
          </div>

          <div v-for="(permissions, category) in orgRolesStore.categories" :key="category" class="role-edit-modal_category">
            <h3 class="role-edit-modal_category-title">{{ category }}</h3>
            <div class="role-edit-modal_permissions">
              <label v-for="(label, code) in permissions" :key="code" class="form-checkbox">
                <input type="checkbox" :value="code" v-model="form.Permissions" />
                {{ label }}
              </label>
            </div>
          </div>

          <div v-if="error" class="role-edit-modal_error">{{ error }}</div>

          <div class="role-edit-modal_actions">
            <button type="button" class="button" @click="close" :disabled="saving">Abbrechen</button>
            <button type="submit" class="button button--primary" :disabled="saving || !form.Title.trim()">
              {{ saving ? 'Speichern…' : (isEdit ? 'Speichern' : 'Erstellen') }}
            </button>
          </div>

        </form>
      </div>
    </dialog>
  </Teleport>
</template>

<script setup>
import { ref, reactive, computed, watch, onMounted } from 'vue'
import { useOrgRolesStore } from '@stores/orgRoles'

const props = defineProps({
  organizationId: { type: Number, required: true },
  role: { type: Object, default: null },
})
const emit = defineEmits(['saved'])
const orgRolesStore = useOrgRolesStore()

const dialogEl = ref(null)
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
  dialogEl.value?.showModal()
}

function close() {
  dialogEl.value?.close()
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
