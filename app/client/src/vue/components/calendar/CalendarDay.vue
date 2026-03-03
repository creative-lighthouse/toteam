<template>
    <div
        class="calendar-day"
        :class="{
            'calendar-day--today': isToday,
            'calendar-day--even-month': isEvenMonth
        }"
        :data-date="props.date.toISOString().split('T')[0]"
    >
        <div class="calendar-day__number">
            {{ props.dayNumber }}
        </div>
        <!-- Events -->
        <div v-for="event in events" :key="event.id">
            <CalendarEvent :event="event" />
        </div>
    </div>
</template>

<script setup>
    import CalendarEvent from './CalendarEvent.vue'

    const props = defineProps({
        dayNumber: {
            type: Number,
            required: true
        },
        date: {
            type: Date,
            default: null
        },
        isToday: {
            type: Boolean,
            default: false
        },
        events: {
            type: Array,
            default: () => []
        }
    })

    // Compute if the date is in an even or a odd month for styling
    const isEvenMonth = props.date.getMonth() % 2 === 0;
</script>

<style scoped>
    .calendar-day {
        display: flex;
        flex-direction: column;
        outline: 1px solid var(--color-border, #e7e7e7);
        min-height: 100px;
        padding: 0.5rem;
        background-color: white;
    }

    .calendar-day__number {
        font-size: 0.75rem;
        color: var(--color-text-secondary, #505050);
        margin-bottom: 0.5rem;
        font-weight: 500;
    }

    /* Tage außerhalb des aktuellen Monats */
    .calendar-day--even-month {
        background-color: var(--color-background-muted, #f5f5f5);
    }

    .calendar-day--odd-month .calendar-day__number {
        color: var(--color-text-muted, #999);
    }

    /* Heute hervorheben */
    .calendar-day--today {
        background-color: var(--color-primary-light, #e3f2fd);
    }

    .calendar-day--today .calendar-day__number {
        color: var(--color-primary, #1976d2);
        font-weight: 700;
    }


</style>
