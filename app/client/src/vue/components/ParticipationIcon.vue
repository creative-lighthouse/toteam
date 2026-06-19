<template>
    <span
    class="event-card_participation"
    :class="`participation--${participationType}`"
    >
        <img v-if="participationType === 'accept'" class="participation--accept" :src="AcceptIcon" alt="Zugesagt">
        <img v-else-if="participationType === 'maybe'" class="participation--maybe" :src="MaybeIcon" alt="Vielleicht">
        <img v-else-if="participationType === 'decline'" class="participation--decline" :src="DeclinedIcon" alt="Abgelehnt">
        <img v-else class="participation--none" :src="NoneIcon" alt="Keine Teilnahme">
    </span>
</template>

<script setup>
    import AcceptIcon from '../../../icons/states/participation_accept.svg'
    import MaybeIcon from '../../../icons/states/participation_maybe.svg'
    import DeclinedIcon from '../../../icons/states/participation_decline.svg'
    import NoneIcon from '../../../icons/states/participation_unset.svg'

    const props = defineProps({
        participationType: {
            type: String,
            required: true
        }
    })
</script>
