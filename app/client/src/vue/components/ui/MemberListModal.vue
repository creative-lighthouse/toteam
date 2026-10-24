<!--
  Liste von Personen mit Zeitpunkt, z.B. wer einem Feedback +1 gegeben oder ein Event
  als "Ich bin dabei" markiert hat — geöffnet über die Zahl an einem AppCountButton.

    <MemberListModal ref="listModal" />
    listModal.value.open(people, { title: '+1 von', empty: 'Noch niemand hat +1 gegeben.' })

  people: { ID, Name, Avatar?, Date }[]
-->
<template>
  <AppModal ref="modal" class="member-list-modal" :title="title" @close="close">
    <p v-if="loading" class="member-list-modal_empty">Lädt…</p>
    <ul v-else-if="people.length" class="member-list-modal_list">
      <li v-for="person in people" :key="person.ID" class="member-list-modal_item">
        <AppAvatar
          :src="person.Avatar"
          :alt="person.Name || ''"
          :name="person.Name || '?'"
          img-class="member-list-modal_avatar"
        />
        <span class="member-list-modal_name">{{ person.Name }}</span>
        <time v-if="person.Date" class="member-list-modal_date" :datetime="person.Date">{{ formatDate(person.Date) }}</time>
      </li>
    </ul>
    <p v-else class="member-list-modal_empty">{{ empty }}</p>
  </AppModal>
</template>

<script setup>
import { ref } from 'vue'
import AppModal from '@components/ui/AppModal.vue'
import AppAvatar from '@components/ui/AppAvatar.vue'

const modal = ref(null)
const people = ref([])
const title = ref('')
const empty = ref('')
const loading = ref(false)

const dateTimeFormat = new Intl.DateTimeFormat('de-DE', { dateStyle: 'medium', timeStyle: 'short' })

function formatDate(value) {
  const date = new Date(String(value).replace(' ', 'T'))
  return Number.isNaN(date.getTime()) ? '' : dateTimeFormat.format(date)
}

/**
 * Öffnet die Liste. `list` darf auch ein Promise sein (z.B. eine API-Anfrage) —
 * dann öffnet das Modal sofort und zeigt solange "Lädt…".
 */
async function open(list, { title: newTitle = 'Personen', empty: newEmpty = 'Noch niemand.' } = {}) {
  title.value = newTitle
  empty.value = newEmpty
  people.value = []
  modal.value?.open()
  loading.value = true
  try {
    people.value = (await list) || []
  } finally {
    loading.value = false
  }
}

function close() {
  modal.value?.close()
}

defineExpose({ open, close })
</script>
