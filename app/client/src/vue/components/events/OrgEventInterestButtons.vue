<template>
  <!-- "Interessiert" / "Ich bin dabei" eines Events — je mit Zähler; ein erneuter Klick
       auf die eigene Auswahl nimmt sie zurück. Ohne Anmeldung führt ein Klick zum Login. -->
  <div class="org-event-interest-buttons">
    <AppCountButton
      label="Interessiert"
      :count="event.InterestedCount ?? 0"
      count-label="Personen interessiert"
      :active="event.UserInterest === 'Interested'"
      :disabled="busy"
      :count-clickable="!!event.CanSeeInterests"
      count-title="Wer ist interessiert?"
      @click="toggle('Interested')"
      @count-click="showList('Interested')"
    >
      <template #icon><span aria-hidden="true">{{ event.UserInterest === 'Interested' ? '★' : '☆' }}</span></template>
    </AppCountButton>
    <AppCountButton
      label="Ich bin dabei"
      :count="event.GoingCount ?? 0"
      count-label="Personen dabei"
      :active="event.UserInterest === 'Going'"
      :disabled="busy"
      :count-clickable="!!event.CanSeeInterests"
      count-title="Wer ist dabei?"
      @click="toggle('Going')"
      @count-click="showList('Going')"
    >
      <template #icon><span aria-hidden="true">✓</span></template>
    </AppCountButton>
    <p v-if="error" class="org-event-interest-buttons_error">{{ error }}</p>

    <MemberListModal ref="listModal" />
  </div>
</template>

<script setup>
import { ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '@stores/auth'
import { useOrgEventsStore } from '@stores/orgEvents'
import AppCountButton from '@components/ui/AppCountButton.vue'
import MemberListModal from '@components/ui/MemberListModal.vue'

const props = defineProps({
  // Event mit InterestedCount, GoingCount, UserInterest (OrgEvent::interestToApi())
  event: { type: Object, required: true },
})

// Neue Zähler + eigene Markierung: { InterestedCount, GoingCount, UserInterest }
const emit = defineEmits(['update'])

const route = useRoute()
const router = useRouter()
const authStore = useAuthStore()
const store = useOrgEventsStore()
const busy = ref(false)
const error = ref(null)
const listModal = ref(null)

const LISTS = {
  Interested: { title: 'Interessiert', empty: 'Noch niemand ist interessiert.' },
  Going: { title: 'Ich bin dabei', empty: 'Noch niemand ist dabei.' },
}

// Zahl angeklickt: wer hat das Event so markiert, und wann (nur für Mitglieder der Organisation)
async function showList(type) {
  error.value = null
  try {
    await listModal.value?.open(store.fetchInterests(props.event.ID).then(data => data[type]), LISTS[type])
  } catch (e) {
    listModal.value?.close()
    error.value = e.message
  }
}

async function toggle(type) {
  if (!authStore.isAuthenticated) {
    router.push({ name: 'Login', query: { redirect: route.fullPath } })
    return
  }
  busy.value = true
  error.value = null
  try {
    emit('update', await store.setInterest(props.event.ID, props.event.UserInterest === type ? null : type))
  } catch (e) {
    error.value = e.message
  } finally {
    busy.value = false
  }
}
</script>
