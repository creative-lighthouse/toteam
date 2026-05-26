<template>
  <div :class="['participant', `participant--status-${participation.Type}`]">
    <div class="participant-main">
      <div class="participant-avatar">
        <img
          v-if="participation.ProfileImageURL"
          :src="participation.ProfileImageURL"
          :alt="participation.MemberName"
          class="participant-avatar_img"
        >
        <span v-else class="participant-avatar_initials">{{ initials }}</span>
      </div>
      <div class="participant-info">
        <span class="participant-name" :data-me="participation.IsCurrentUser ? 'true' : null">
          {{ participation.MemberName }}
        </span>
        <span class="participant-status" v-if="participation.CustomTimeframe && participation.TimeStart && participation.TimeEnd">
          {{ formatTime(participation.TimeStart) }} – {{ formatTime(participation.TimeEnd) }}
        </span>
      </div>
    </div>
    <p
      v-if="participation.Notes"
      class="participant-note"
      :class="{ 'participant-note--expanded': noteExpanded }"
      @click="$emit('toggle-note')"
    >{{ participation.Notes }}</p>
  </div>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  participation: {
    type: Object,
    required: true
  },
  noteExpanded: {
    type: Boolean,
    default: false
  }
})

defineEmits(['toggle-note'])

const initials = computed(() => {
  const name = props.participation.MemberName || ''
  return name
    .split(' ')
    .filter(Boolean)
    .slice(0, 2)
    .map(w => w[0].toUpperCase())
    .join('')
})

function formatTime(timeStr) {
  if (!timeStr) return ''
  return timeStr.substring(0, 5)
}
</script>
