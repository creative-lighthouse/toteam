<template>
  <div class="section section--EventDetailPage">
    <div class="section_content">

      <div v-if="loading" class="section_infobox">
        <p>Lade Event…</p>
      </div>

      <div v-else-if="error" class="section_infobox error">
        <p>{{ error }}</p>
        <template v-if="!authStore.isAuthenticated">
          <p>Interne Events sehen nur Mitglieder der Organisation.</p>
          <AppButton variant="primary" @click="login">Anmelden</AppButton>
        </template>
      </div>

      <template v-else-if="event">
        <!-- Eckdaten: sieht jeder, der das Event sehen darf (bei öffentlichen Events auch ohne Anmeldung) -->
        <article class="event-page_card">
          <img v-if="event.ImageURL" :src="event.ImageURL" :alt="event.Title" class="event-page_image">

          <div class="event-page_body">
            <div class="event-page_head">
              <AppOrgLogo
                v-if="event.OrganizationTitle"
                :src="event.OrganizationLogoURL"
                :alt="event.OrganizationTitle"
                :name="event.OrganizationTitle"
                :size="28"
              />
              <span class="event-page_org">{{ event.OrganizationTitle }}</span>
              <span v-if="isInternal" class="event-page_badge">{{ event.IsPublic ? 'Öffentlich' : 'Intern' }}</span>
            </div>

            <dl v-if="facts.length" class="event-page_facts">
              <div v-for="fact in facts" :key="fact.label">
                <dt>{{ fact.label }}</dt>
                <dd>{{ fact.value }}</dd>
              </div>
            </dl>
          </div>

          <template v-if="event.Prices?.length">
            <h2 class="hl3 event-page_subtitle">Preise</h2>
            <table class="event-page_prices">
              <tbody>
                <tr v-for="price in event.Prices" :key="price.ID">
                  <td>{{ price.Title }}</td>
                  <td>{{ formatPrice(price.Price) }}</td>
                </tr>
              </tbody>
            </table>
          </template>

          <template v-if="event.Gallery?.length">
            <h2 class="hl3 event-page_subtitle">Galerie</h2>
            <ul class="event-page_gallery">
              <li v-for="(img, index) in event.Gallery" :key="img.ID">
                <a :href="img.URL" target="_blank" rel="noopener" @click.prevent="lightbox?.open(event.Gallery, index)">
                  <img :src="img.Thumbnail" :alt="img.Name" loading="lazy">
                </a>
              </li>
            </ul>
          </template>

          <div class="event-page_actions">
            <AppButton variant="secondary" @click="copyLink">{{ linkCopied ? 'Link kopiert ✓' : 'Link kopieren' }}</AppButton>
            <template v-if="event.CanManage">
              <AppButton variant="secondary" @click="formModal?.openForEdit(event)">Bearbeiten</AppButton>
              <AppButton variant="danger" :disabled="deleting" @click="remove">Löschen</AppButton>
            </template>
          </div>
          <p v-if="actionError" class="event-page_error">{{ actionError }}</p>
        </article>

        <!-- Interner Bereich: nur für Mitglieder der Organisation -->
        <template v-if="isInternal">
          <section class="event-page_section">
            <h2 class="hl3 event-page_subtitle">Termine</h2>
            <OrgEventAppointmentList :appointments="appointments" />
          </section>
        </template>

        <div v-else-if="!authStore.isAuthenticated" class="event-page_login">
          <p>Mitglieder sehen nach der Anmeldung auch die Termine des Events.</p>
          <AppButton variant="secondary" @click="login">Anmelden</AppButton>
        </div>
      </template>

    </div>

    <AppLightbox ref="lightbox" />
    <OrgEventFormModal v-if="event?.CanManage" ref="formModal" @saved="load" />
  </div>
</template>

<script setup>
import { ref, computed, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '@stores/auth'
import { useOrgEventsStore } from '@stores/orgEvents'
import { usePageHeaderStore } from '@stores/pageHeader'
import { formatOrgEventRange, formatPrice } from '@utils/orgEvents'
import AppButton from '@components/ui/AppButton.vue'
import AppLightbox from '@components/ui/AppLightbox.vue'
import AppOrgLogo from '@components/ui/AppOrgLogo.vue'
import OrgEventAppointmentList from '@components/events/OrgEventAppointmentList.vue'
import OrgEventFormModal from '@components/events/OrgEventFormModal.vue'

const route = useRoute()
const router = useRouter()
const authStore = useAuthStore()
const orgEventsStore = useOrgEventsStore()
const pageHeader = usePageHeaderStore()

const event = ref(null)
const appointments = ref([])
const isInternal = ref(false)
const loading = ref(true)
const error = ref(null)
const deleting = ref(false)
const actionError = ref(null)
const linkCopied = ref(false)
const lightbox = ref(null)
const formModal = ref(null)

const facts = computed(() => {
  const e = event.value
  if (!e) return []
  return [
    { label: 'Wann', value: e.RangeStart ? formatOrgEventRange(e) : '' },
    { label: 'Wo', value: e.Location },
    { label: 'Art', value: e.TypeTitle },
    { label: 'Empfohlen für', value: (e.AgeGroups ?? []).map(g => g.Title).join(', ') },
  ].filter(fact => fact.value)
})

async function load() {
  loading.value = !event.value
  error.value = null
  try {
    const res = await orgEventsStore.fetchEvent(route.params.segment)
    event.value = res.event
    appointments.value = res.appointments ?? []
    isInternal.value = !!res.isInternal
    pageHeader.setHeader(event.value.Title, event.value.OrganizationTitle ?? 'Event')
  } catch (e) {
    event.value = null
    error.value = e.message === 'Event nicht gefunden'
      ? 'Dieses Event gibt es nicht oder es ist nicht öffentlich.'
      : e.message
    pageHeader.setHeader('Event', '')
  } finally {
    loading.value = false
  }
}

function login() {
  router.push({ name: 'Login', query: { redirect: route.fullPath } })
}

async function copyLink() {
  try {
    await navigator.clipboard.writeText(window.location.origin + event.value.Link)
    linkCopied.value = true
    setTimeout(() => { linkCopied.value = false }, 2000)
  } catch {
    window.prompt('Link zum Event:', window.location.origin + event.value.Link)
  }
}

async function remove() {
  if (!confirm(`Event „${event.value.Title}“ wirklich löschen? Die Termine bleiben im Kalender erhalten.`)) return
  deleting.value = true
  actionError.value = null
  try {
    await orgEventsStore.deleteEvent(event.value.ID)
    router.push({ name: 'Events' })
  } catch (e) {
    actionError.value = e.message
  } finally {
    deleting.value = false
  }
}

watch(() => route.params.segment, segment => {
  if (!segment) return
  event.value = null
  load()
}, { immediate: true })
</script>
