<template>
  <div v-if="canManageContent" class="event-admin-section">
    <hr>
    <div class="event-manage-actions">
      <button type="button" class="button event-manage-button" @click="$emit('edit-appointment', event)">
        <img :src="EditIcon" alt="Bearbeiten" />
      </button>
    </div>
  </div>
</template>

<script setup>
import EditIcon from '../../../../icons/actions/action_edit.svg'

defineProps({
  event: { type: Object, required: true },
  canManageContent: { type: Boolean, required: true }
})

defineEmits(['edit-appointment'])
</script>
