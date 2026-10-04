<template>
  <!-- Einzelner Feed-Beitrag, z.B. aus einer Push-Benachrichtigung oder einem geteilten Link -->
  <div class="section section--AnnouncementDetailPage">
    <div class="section_content">
      <div v-if="loading" class="section_infobox">
        <p>Lade Beitrag...</p>
      </div>

      <div v-else-if="!post" class="section_infobox">
        <p>Diesen Beitrag gibt es nicht (mehr) oder er ist nicht für dich sichtbar.</p>
        <router-link :to="{ name: 'Announcements' }">Zum Feed →</router-link>
      </div>

      <template v-else>
        <FeedPostCard :post="post" detail @delete="deletePost" />
        <router-link :to="{ name: 'Announcements' }" class="announcement-detail_back">← Zum Feed</router-link>
      </template>
    </div>
  </div>
</template>

<script setup>
import { ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAnnouncementsStore } from '@stores/announcements'
import { usePageHeaderStore } from '@stores/pageHeader'
import FeedPostCard from '@components/announcements/FeedPostCard.vue'

const route = useRoute()
const router = useRouter()
const store = useAnnouncementsStore()
const pageHeader = usePageHeaderStore()
pageHeader.setHeader('Beitrag', '')

const post = ref(null)
const loading = ref(true)

async function load(id) {
  loading.value = true
  try {
    post.value = await store.fetchPost(Number(id))
    pageHeader.setHeader('Beitrag', post.value.Organization?.Title ?? post.value.Author?.Name ?? '')
  } catch {
    post.value = null
  } finally {
    loading.value = false
  }
}

async function deletePost(p) {
  if (!confirm('Diesen Beitrag wirklich löschen?')) return
  try {
    await store.deletePost(p.ID)
    router.push({ name: 'Announcements' })
  } catch (e) {
    alert(e.message)
  }
}

watch(() => route.params.id, id => { if (id) load(id) }, { immediate: true })
</script>
