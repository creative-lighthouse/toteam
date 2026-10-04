<template>
  <!-- Mahlzeiten eines Termins mit der eigenen Rückmeldung, z.B. unter den Termin-Karten
       auf dem Dashboard und der Event-Seite; ein Klick öffnet die Mahlzeit -->
  <ul v-if="meals.length" class="event-meals-list">
    <li v-for="meal in meals" :key="meal.ID">
      <router-link :to="`/food/meal/${meal.ID}`" class="event-meal-link">
        <span class="event-meal-time">{{ meal.RenderTime }} Uhr</span>
        <span class="event-meal-name">{{ meal.Title }}</span>
        <span class="event-meal-response" :class="responseClass(meal.UserResponse)">
          {{ responseLabel(meal.UserResponse) }}
        </span>
        <svg width="12" height="12" viewBox="0 0 16 16" fill="currentColor" style="opacity:.4;flex-shrink:0">
          <path d="M6.22 3.22a.75.75 0 011.06 0l4.25 4.25a.75.75 0 010 1.06l-4.25 4.25a.75.75 0 01-1.06-1.06L9.94 8 6.22 4.28a.75.75 0 010-1.06z"/>
        </svg>
      </router-link>
    </li>
  </ul>
</template>

<script setup>
defineProps({
  // [{ ID, Title, RenderTime, UserResponse }]
  meals: { type: Array, default: () => [] },
})

function responseClass(response) {
  if (response === 'Accept') return 'response--accept'
  if (response === 'Decline') return 'response--decline'
  return 'response--pending'
}

function responseLabel(response) {
  if (response === 'Accept') return '✓ Zugesagt'
  if (response === 'Decline') return '✗ Abgesagt'
  return '?'
}
</script>
