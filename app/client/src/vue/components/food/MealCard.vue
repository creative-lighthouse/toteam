<template>
  <!-- Mahlzeit in der Liste des Essens-Totems; führt zur Detailansicht -->
  <router-link :to="`/food/meal/${meal.id}`" class="meal-card">
    <AppOrgLogo
      v-if="!hideOrgLogo"
      :src="meal.organizationLogoUrl"
      :name="meal.organizationTitle"
      :alt="meal.organizationTitle"
      :title="meal.organizationTitle"
      :size="36"
      class="meal-card_logo"
    />

    <div class="meal-card_info">
      <p class="meal-card_context">
        <template v-if="meal.eventTitle">{{ meal.eventTitle }} · </template>{{ meal.appointmentTitle }}
      </p>
      <p class="meal-card_title">
        <span class="meal-card_time">{{ meal.time }} Uhr</span>
        {{ meal.title }}
      </p>
      <p v-if="meal.foods.length" class="meal-card_foods">{{ meal.foods.join(', ') }}</p>
    </div>

    <div class="meal-card_counts">
      <span class="meal-card_count" :title="`${meal.acceptCount} Zusagen`">
        <img :src="AcceptIcon" alt="Zusagen">{{ meal.acceptCount }}
      </span>
      <span class="meal-card_count" :title="`${meal.declineCount} Absagen`">
        <img :src="DeclineIcon" alt="Absagen">{{ meal.declineCount }}
      </span>
    </div>

    <ParticipationIcon
      :participation-type="participationType"
      :title="participationLabel"
      class="meal-card_own"
    />
  </router-link>
</template>

<script setup>
import { computed } from 'vue'
import AppOrgLogo from '@components/ui/AppOrgLogo.vue'
import ParticipationIcon from '@components/calendar/ParticipationIcon.vue'
import AcceptIcon from '../../../../icons/states/participation_accept.svg'
import DeclineIcon from '../../../../icons/states/participation_decline.svg'

const props = defineProps({
  meal: { type: Object, required: true },
  // Wer nur in einer Organisation ist, braucht das Logo nicht (wie bei den Terminkarten)
  hideOrgLogo: { type: Boolean, default: false },
})

const participationType = computed(() => ({ Accept: 'accept', Decline: 'decline' })[props.meal.userResponse] ?? 'none')
const participationLabel = computed(() => ({ Accept: 'Du isst mit', Decline: 'Du isst nicht mit' })[props.meal.userResponse] ?? 'Noch keine Antwort')
</script>
