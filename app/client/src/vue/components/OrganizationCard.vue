<template>
  <div class="organization-card" :class="{ 'organization-card--clickable': org.Username }" @click="navigateToDetail">
    <div class="organization-card_cover">
      <img
        v-if="org.CoverURL"
        :src="org.CoverURL"
        :alt="`${org.Title} Cover`"
        class="organization-card_cover-img"
      >
      <div v-else class="organization-card_cover-placeholder" />

      <div class="organization-card_logo-wrap">
        <img
          v-if="org.LogoURL"
          :src="org.LogoURL"
          :alt="`${org.Title} Logo`"
          class="organization-card_logo"
        >
        <div v-else class="organization-card_logo-placeholder">
          {{ org.Title?.charAt(0) ?? '?' }}
        </div>
      </div>
    </div>

    <div class="organization-card_body">
      <div class="organization-card_meta">
        <h3 class="hl3 organization-card_title">{{ org.Title }}</h3>
        <span class="organization-card_count">{{ org.MemberCount }} {{ org.MemberCount === 1 ? 'Mitglied' : 'Mitglieder' }}</span>
      </div>

      <p v-if="org.Description" class="organization-card_description">{{ org.Description }}</p>

      <div class="organization-card_footer">
        <span
          v-if="!org.MembershipStatus || org.JoinMode === 'open'"
          class="organization-card_badge"
          :class="`organization-card_badge--${org.JoinMode}`"
        >
          {{ joinModeLabel }}
        </span>

        <button
          v-if="actionLabel"
          class="button organization-card_action"
          :class="{ 'button--secondary': org.MembershipStatus === 'applicant' }"
          :disabled="!!org.MembershipStatus || joining"
          @click.stop="handleJoin"
        >
          {{ joining ? '…' : actionLabel }}
        </button>

        <button
          v-if="org.MembershipStatus === 'admin' || org.MembershipStatus === 'moderator'"
          class="button button--secondary organization-card_manage"
          @click.stop="$emit('manage-applicants', org)"
        >
          Bewerber
          <span v-if="org.ApplicantCount" class="organization-card_badge-count">{{ org.ApplicantCount }}</span>
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue'
import { useRouter } from 'vue-router'

const props = defineProps({
  org: {
    type: Object,
    required: true,
  },
})

const emit = defineEmits(['joined', 'manage-applicants'])
const joining = ref(false)
const router = useRouter()

function navigateToDetail() {
  if (props.org.Username) {
    router.push(`/organizations/${props.org.Username}`)
  }
}

const joinModeLabel = computed(() => {
  switch (props.org.JoinMode) {
    case 'open':         return 'Offen'
    case 'application':  return 'Bewerbung erforderlich'
    case 'invite_only':  return 'Nur auf Einladung'
    default:             return ''
  }
})

const actionLabel = computed(() => {
  if (props.org.MembershipStatus === 'member')    return 'Mitglied'
  if (props.org.MembershipStatus === 'moderator') return 'Moderator'
  if (props.org.MembershipStatus === 'admin')     return 'Admin'
  if (props.org.MembershipStatus === 'applicant') return 'Bewerbung ausstehend'
  if (props.org.JoinMode === 'open')              return 'Beitreten'
  if (props.org.JoinMode === 'application')       return 'Bewerben'
  return null
})

async function handleJoin() {
  if (props.org.MembershipStatus || joining.value) return
  joining.value = true
  try {
    emit('joined', props.org.ID)
  } finally {
    joining.value = false
  }
}
</script>
