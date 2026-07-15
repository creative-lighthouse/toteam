<template>
  <Teleport to="body">
    <dialog ref="dialogEl" class="applicants-modal" @cancel.prevent="close">
      <div class="applicants-modal_content" @click.stop>
        <div class="applicants-modal_header">
          <h2 class="hl2 applicants-modal_title">Bewerber – {{ org?.Title }}</h2>
          <AppIconButton variant="ghost" aria-label="Schließen" @click="close">✕</AppIconButton>
        </div>

        <div class="applicants-modal_body">
          <div v-if="loading" class="applicants-modal_state">Lädt...</div>

          <div v-else-if="applicants.length === 0" class="applicants-modal_state">
            Keine offenen Bewerbungen.
          </div>

          <ul v-else class="applicants-list">
            <li v-for="a in applicants" :key="a.MembershipID" class="applicant-item">
              <img
                v-if="a.Gravatar"
                :src="a.Gravatar"
                :alt="`${a.FirstName} ${a.Surname}`"
                class="applicant-avatar"
              >
              <div v-else class="applicant-avatar applicant-avatar--placeholder">
                {{ a.FirstName?.charAt(0) ?? '?' }}
              </div>

              <div class="applicant-info">
                <strong class="applicant-name">{{ a.FirstName }} {{ a.Surname }}</strong>
                <span class="applicant-email">{{ a.Email }}</span>
              </div>

              <div class="applicant-actions">
                <AppButton
                  size="small"
                  variant="primary"
                  :disabled="processingID === a.MembershipID"
                  @click="accept(a.MembershipID)"
                >
                  Annehmen
                </AppButton>
                <AppButton
                  size="small"
                  variant="secondary"
                  :disabled="processingID === a.MembershipID"
                  @click="reject(a.MembershipID)"
                >
                  Ablehnen
                </AppButton>
              </div>
            </li>
          </ul>
        </div>
      </div>
    </dialog>
  </Teleport>
</template>

<script setup>
import { ref } from 'vue'
import { apiGet, apiPost } from '@utils/api'
import AppButton from '@components/AppButton.vue'
import AppIconButton from '@components/AppIconButton.vue'

const emit = defineEmits(['accepted'])

const dialogEl    = ref(null)
const org         = ref(null)
const applicants  = ref([])
const loading     = ref(false)
const processingID = ref(null)

async function open(orgData) {
  org.value = orgData
  applicants.value = []
  dialogEl.value?.showModal()
  await loadApplicants()
}

function close() {
  dialogEl.value?.close()
}



async function loadApplicants() {
  try {
    loading.value = true
    const data = await apiGet(`/organizations/applicants/${org.value.ID}`, false)
    applicants.value = data.applicants ?? []
  } catch (err) {
    console.error('Could not load applicants:', err)
  } finally {
    loading.value = false
  }
}

async function accept(membershipID) {
  processingID.value = membershipID
  try {
    await apiPost(`/organizations/accept/${membershipID}`, {})
    applicants.value = applicants.value.filter(a => a.MembershipID !== membershipID)
    emit('accepted', org.value.ID)
  } catch (err) {
    console.error('Could not accept applicant:', err)
  } finally {
    processingID.value = null
  }
}

async function reject(membershipID) {
  processingID.value = membershipID
  try {
    await apiPost(`/organizations/reject/${membershipID}`, {})
    applicants.value = applicants.value.filter(a => a.MembershipID !== membershipID)
  } catch (err) {
    console.error('Could not reject applicant:', err)
  } finally {
    processingID.value = null
  }
}

defineExpose({ open, close })
</script>
