<template>
  <!-- Eigene Events im Profil: zugesagt ("Ich bin dabei") und interessiert — nur laufende
       und kommende (bzw. noch ohne Datum), die vergangenen fallen heraus -->
  <div class="profile-events">
    <h3 class="profile-events_title">Zugesagte Events</h3>
    <ul v-if="going.length" class="profile-events_list">
      <li v-for="orgEvent in going" :key="orgEvent.ID"><OrgEventListItem :event="orgEvent" /></li>
    </ul>
    <p v-else class="profile-events_empty">{{ loading ? 'Wird geladen…' : 'Noch keine Events zugesagt.' }}</p>

    <h3 class="profile-events_title">Interessierte Events</h3>
    <ul v-if="interested.length" class="profile-events_list">
      <li v-for="orgEvent in interested" :key="orgEvent.ID"><OrgEventListItem :event="orgEvent" /></li>
    </ul>
    <p v-else class="profile-events_empty">{{ loading ? 'Wird geladen…' : 'Noch keine Events als interessant markiert.' }}</p>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useOrgEventsStore } from '@stores/orgEvents'
import OrgEventListItem from '@components/events/OrgEventListItem.vue'

const store = useOrgEventsStore()
const loading = ref(true)

const today = new Date().toISOString().slice(0, 10)
const current = computed(() => store.myEvents.filter(e => !e.RangeStart || (e.RangeEnd ?? e.RangeStart) >= today))
const going = computed(() => current.value.filter(e => e.UserInterest === 'Going'))
const interested = computed(() => current.value.filter(e => e.UserInterest === 'Interested'))

onMounted(async () => {
  try {
    await store.fetchMyEvents()
  } catch {
    // Liste bleibt leer
  } finally {
    loading.value = false
  }
})
</script>
