<template>
    <div class="calendar-event">
        <div class="calendar-event__number">
            {{ event.title }}
        </div>
    </div>
</template>

<script setup>
const props = defineProps({
    event: {
        type: Object,
        required: true
    }
})
</script>

<style scoped>
</style>
