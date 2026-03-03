<template>
    <div class="calendar-week">
        <div class="calendar-week__number">
            {{ weekNumber }}
        </div>
        <!-- 7 Days for a full week -->
        <slot></slot>
    </div>
</template>

<script setup>
const props = defineProps({
    weekNumber: {
        type: Number,
        required: true
    }
})
</script>

<style scoped>
    .calendar-week {
        display: grid;
        grid-template-columns: 15px repeat(7, 1fr);
        height: 100%;
        gap: 0;
    }

    .calendar-week__number {
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 0.75rem;
        color: var(--color-text-secondary, #b3b3b3);
        padding: 5px;
    }
</style>
