<template>
  <!-- Person über ihren Benutzernamen in die Organisation einladen (Organisation verwalten).
       Die Person sieht die Einladung auf dem Dashboard und nimmt sie an oder lehnt ab. -->
  <AppModal ref="modal" class="org-member-invite-modal" title="Person einladen" @close="close">
    <form :id="formId" class="modalform" @submit.prevent="submit">
      <div class="field">
        <label :for="`${formId}-username`">Benutzername *</label>
        <div class="org-member-invite-modal_input">
          <span class="org-member-invite-modal_at" aria-hidden="true">@</span>
          <input
            :id="`${formId}-username`"
            v-model="username"
            type="text"
            autocomplete="off"
            autocapitalize="off"
            spellcheck="false"
            placeholder="benutzername"
            required
            autofocus
            @input="onInput"
          >
        </div>
        <span class="org-member-invite-modal_hint">Den Benutzernamen findet die Person in ihrem Profil. Du kannst auch nach dem Namen suchen.</span>
      </div>

      <!-- Vorschläge (Benutzername, Vor- oder Nachname) — ohne Mitglieder und bereits Eingeladene -->
      <ul v-if="suggestions.length" class="field org-member-invite-modal_suggestions">
        <li v-for="s in suggestions" :key="s.ID">
          <button type="button" class="org-member-invite-modal_suggestion" @click="pick(s)">
            <AppAvatar :src="s.Avatar" :alt="s.Name" img-class="org-member-invite-modal_avatar" />
            <span class="org-member-invite-modal_name">{{ s.Name }}</span>
            <span v-if="s.IsApplicant" class="org-member-invite-modal_badge">hat sich beworben</span>
            <span class="org-member-invite-modal_username">@{{ s.Username }}</span>
          </button>
        </li>
      </ul>

      <div v-if="error" class="field app-modal_error">{{ error }}</div>
    </form>

    <template #actions>
      <AppButton variant="secondary" :disabled="saving" @click="close">Abbrechen</AppButton>
      <AppButton type="submit" :form="formId" variant="primary" :disabled="saving || !cleanUsername">
        {{ saving ? 'Wird eingeladen…' : 'Einladen' }}
      </AppButton>
    </template>
  </AppModal>
</template>

<script setup>
import { ref, computed } from 'vue'
import { apiGet } from '@utils/api'
import { useOrganizationsStore } from '@stores/organizations'
import AppButton from '@components/ui/AppButton.vue'
import AppModal from '@components/ui/AppModal.vue'
import AppAvatar from '@components/ui/AppAvatar.vue'

const props = defineProps({
  organizationId: { type: Number, required: true },
})

// (Meldung) — z.B. "Max wurde eingeladen"
const emit = defineEmits(['invited'])

const store = useOrganizationsStore()
const formId = `org-member-invite-${Math.random().toString(36).slice(2)}`

const modal = ref(null)
const username = ref('')
const suggestions = ref([])
const saving = ref(false)
const error = ref(null)
let searchTimer = null
let searchRun = 0

const cleanUsername = computed(() => username.value.trim().replace(/^@/, ''))

// Vorschläge (Benutzername, Vor- oder Nachname) — der Server lässt Mitglieder und
// bereits Eingeladene weg
function onInput() {
  error.value = null
  clearTimeout(searchTimer)
  const q = cleanUsername.value
  if (q.length < 2) {
    suggestions.value = []
    return
  }
  searchTimer = setTimeout(async () => {
    const run = ++searchRun
    try {
      const response = await apiGet(`/organizations/inviteSearch/${props.organizationId}?q=${encodeURIComponent(q)}`, false)
      if (run !== searchRun) return
      // Exakter Treffer allein muss nicht nochmal vorgeschlagen werden
      const members = response.members || []
      suggestions.value = members.length === 1 && members[0].Username?.toLowerCase() === q.toLowerCase() ? [] : members
    } catch {
      suggestions.value = []
    }
  }, 250)
}

function pick(suggestion) {
  username.value = suggestion.Username
  suggestions.value = []
}

function open() {
  username.value = ''
  suggestions.value = []
  error.value = null
  saving.value = false
  modal.value?.open()
}

function close() {
  modal.value?.close()
}

async function submit() {
  if (!cleanUsername.value || saving.value) return
  saving.value = true
  error.value = null
  try {
    const response = await store.inviteMember(props.organizationId, cleanUsername.value)
    if (!response.success) {
      error.value = response.error || 'Einladung fehlgeschlagen.'
      return
    }
    emit('invited', response.message)
    close()
  } catch (err) {
    error.value = err.message || 'Einladung fehlgeschlagen.'
  } finally {
    saving.value = false
  }
}

defineExpose({ open, close })
</script>
