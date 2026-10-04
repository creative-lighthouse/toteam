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
        <!-- Ansicht umschalten: nur für Mitglieder der Organisation, "Bearbeiten" nur mit Rechten -->
        <div v-if="viewModes.length > 1" class="event-page_view-switch">
          <AppSegmentedToggle :model-value="viewMode" :options="viewModes" label="Ansicht" @update:model-value="selectMode" />
          <p v-if="hiddenPublicly" class="event-page_view-hint">
            Dieses Event ist nicht öffentlich — Außenstehende können die Seite gar nicht aufrufen.
          </p>
        </div>

        <!-- Auf dem Desktop zweispaltig im Masonry-Prinzip (useMasonry): jede Karte rutscht
             in die kürzere Spalte; die Eckdaten stehen immer oben links -->
        <div ref="layoutEl" class="event-page_layout" :class="{ 'event-page_layout--split': hasAside }">
          <!-- Eckdaten: sieht jeder, der das Event sehen darf (bei öffentlichen Events auch ohne Anmeldung) -->
          <!-- Nicht öffentliche Events gibt es für Außenstehende gar nicht — die Vorschau zeigt dann nur den Hinweis -->
          <article v-if="!hiddenPublicly" class="event-page_card">
            <img v-if="event.ImageURL" :src="event.ImageURL" :alt="event.Title" class="event-page_image">
            <!-- Ohne Hauptbild wie auf der Karte: aus dem Titel generiertes Muster -->
            <AppPatternImage v-else :seed="event.Title" class="event-page_image" />

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
                <span v-if="showInternal" class="event-page_badge">{{ event.IsPublic ? 'Öffentlich' : 'Intern' }}</span>

                <div class="event-page_actions">
                  <span v-if="linkCopied" class="event-page_copied" role="status">Link kopiert</span>
                  <span v-if="sharedToFeed" class="event-page_copied" role="status">
                    Im Feed geteilt · <router-link :to="{ name: 'AnnouncementDetail', params: { id: sharedToFeed.ID } }">ansehen</router-link>
                  </span>
                  <AppIconButton
                    v-if="authStore.isAuthenticated"
                    variant="neutral"
                    aria-label="Im Feed teilen"
                    title="Im Feed teilen"
                    @click="feedShareModal?.open()"
                  >
                    <span class="icon-mask" :style="iconStyle(actionAddMessage)" aria-hidden="true" />
                  </AppIconButton>
                  <AppIconButton variant="neutral" aria-label="Teilen" title="Teilen" @click="share">
                    <span class="icon-mask" :style="iconStyle(actionShare)" aria-hidden="true" />
                  </AppIconButton>
                  <template v-if="editing && event.CanManage">
                    <AppIconButton variant="neutral" aria-label="Bearbeiten" title="Bearbeiten" @click="formModal?.openForEdit(event)">
                      <span class="icon-mask" :style="iconStyle(actionEdit)" aria-hidden="true" />
                    </AppIconButton>
                    <AppIconButton variant="danger" aria-label="Löschen" title="Löschen" :disabled="deleting" @click="remove">
                      <span class="icon-mask" :style="iconStyle(actionTrash)" aria-hidden="true" />
                    </AppIconButton>
                  </template>
                </div>
              </div>

              <dl v-if="facts.length" class="event-page_facts">
                <div v-for="fact in facts" :key="fact.label">
                  <dt>{{ fact.label }}</dt>
                  <dd>{{ fact.value }}</dd>
                </div>
              </dl>

              <!-- "Interessiert" / "Ich bin dabei" — für alle, die das Event sehen (ohne Anmeldung → Login) -->
              <OrgEventInterestButtons :event="event" @update="Object.assign(event, $event)" />

              <!-- Nur mit Koordinaten und wenn der Ort in den selbst gehosteten Kartendaten liegt -->
              <OrgEventMap
                v-if="event.Map"
                :key="event.ID"
                :latitude="event.Latitude"
                :longitude="event.Longitude"
                :config="event.Map"
                :title="event.Location || event.Title"
              />
            </div>

            <template v-if="event.PriceMode === 'Tiered' && event.Prices?.length">
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

            <p v-if="actionError" class="event-page_error">{{ actionError }}</p>
          </article>

          <!-- Interner Bereich: nur für Mitglieder der Organisation. Jede Karte erscheint,
               sobald das Event Inhalte dafür hat; neue Inhalte kommen über "Hinzufügen" -->
          <template v-if="showInternal">
            <section v-if="appointments.length" class="event-page_section">
              <h2 class="hl3 event-page_subtitle">Termine</h2>
              <OrgEventAppointmentList :appointments="appointments" @open="openAppointment" />
            </section>

            <section v-if="moneyState?.accounts.length" class="event-page_section">
              <h2 class="hl3 event-page_subtitle">{{ moneyState.accounts.length === 1 ? 'Kasse' : 'Kassen' }}</h2>
              <OrgEventMoney :event="event" :state="visibleMoneyState" @update="moneyState = $event" />
            </section>

            <section v-if="scriptState?.scripts.length" class="event-page_section">
              <h2 class="hl3 event-page_subtitle">{{ scriptState.scripts.length === 1 ? 'Skript' : 'Skripte' }}</h2>
              <OrgEventScripts :event="event" :state="visibleScriptState" @update="scriptState = $event" />
            </section>
          </template>

          <!-- In der öffentlichen Vorschau so, wie es nicht angemeldete Besucher sehen -->
          <div v-else-if="showLogin" class="event-page_login">
            <p>Mitglieder sehen nach der Anmeldung auch die Termine des Events.</p>
            <AppButton variant="secondary" :disabled="viewMode === 'public'" @click="login">Anmelden</AppButton>
          </div>

          <!-- Unter beiden Spalten über die volle Breite -->
          <OrgEventAddActions
            v-if="showInternal && editing && addActions.length"
            :actions="addActions"
            class="event-page_section event-page_add"
            @select="onAddAction"
          />
        </div>
      </template>

    </div>

    <AppLightbox ref="lightbox" />
    <OrgEventFeedShareModal v-if="authStore.isAuthenticated" ref="feedShareModal" :event="event" @shared="onSharedToFeed" />
    <OrgEventFormModal v-if="event?.CanManage" ref="formModal" @saved="load" />
    <CalendarEntryCreateModal
      v-if="event?.CanManage"
      ref="appointmentModal"
      @appointment-created="load"
      @appointment-updated="onAppointmentSaved"
      @appointment-deleted="onAppointmentSaved"
      @closed="onAppointmentModalClosed"
    />

    <!-- Termin-Dialog wie im Kalender (Daten aus dem events-Store) -->
    <EventDialog
      v-if="selectedAppointment"
      :event="selectedAppointment"
      @close="closeAppointment"
      @edit-appointment="editAppointment"
    />
    <EventDialogSkeleton v-if="appointmentLoadingId" @close="appointmentLoadingId = null" />
    <OrgEventMoneyAddModal
      v-if="canAddMoney"
      ref="moneyAddModal"
      :event-id="event.ID"
      :available-accounts="moneyState.availableAccounts"
      :can-create="moneyState.CanCreate"
      @saved="moneyState = $event"
    />
    <OrgEventScriptAddModal
      v-if="scriptState?.CanManageScripts"
      ref="scriptAddModal"
      :event-id="event.ID"
      :available-scripts="scriptState.availableScripts"
      :can-create="scriptState.CanCreateScripts"
      @saved="scriptState = $event"
    />
  </div>
</template>

<script setup>
import { ref, computed, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '@stores/auth'
import { useOrgEventsStore } from '@stores/orgEvents'
import { useSkriptStore } from '@stores/skript'
import { usePageHeaderStore } from '@stores/pageHeader'
import { formatOrgEventRange, formatPrice, formatFixedPrice, formatAddress, PRICE_MODE_LABELS } from '@utils/orgEvents'
import AppButton from '@components/ui/AppButton.vue'
import AppIconButton from '@components/ui/AppIconButton.vue'
import AppLightbox from '@components/ui/AppLightbox.vue'
import AppOrgLogo from '@components/ui/AppOrgLogo.vue'
import AppPatternImage from '@components/ui/AppPatternImage.vue'
import AppSegmentedToggle from '@components/ui/AppSegmentedToggle.vue'
import OrgEventAppointmentList from '@components/events/OrgEventAppointmentList.vue'
import OrgEventAddActions from '@components/events/OrgEventAddActions.vue'
import OrgEventScripts from '@components/events/OrgEventScripts.vue'
import OrgEventScriptAddModal from '@components/events/OrgEventScriptAddModal.vue'
import OrgEventMoney from '@components/events/OrgEventMoney.vue'
import OrgEventMoneyAddModal from '@components/events/OrgEventMoneyAddModal.vue'
import { useMoneyStore } from '@stores/money'
import CalendarEntryCreateModal from '@components/calendar/CalendarEntryCreateModal.vue'
import EventDialog from '@components/calendar/event-dialog/EventDialog.vue'
import EventDialogSkeleton from '@components/calendar/event-dialog/EventDialogSkeleton.vue'
import { useEventsStore } from '@stores/events'
import { morphIntoModal } from '@utils/viewTransition'
import { useMasonry } from '@utils/useMasonry'
import OrgEventFormModal from '@components/events/OrgEventFormModal.vue'
import OrgEventMap from '@components/events/OrgEventMap.vue'
import OrgEventInterestButtons from '@components/events/OrgEventInterestButtons.vue'
import actionShare from '../../../icons/actions/action_share.svg'
import actionAddMessage from '../../../icons/actions/action_addmessage.svg'
import OrgEventFeedShareModal from '@components/events/OrgEventFeedShareModal.vue'
import actionEdit from '../../../icons/actions/action_edit.svg'
import actionTrash from '../../../icons/actions/action_trash.svg'

const iconStyle = icon => ({ maskImage: `url("${icon}")`, WebkitMaskImage: `url("${icon}")` })

const route = useRoute()
const router = useRouter()
const authStore = useAuthStore()
const orgEventsStore = useOrgEventsStore()
const skriptStore = useSkriptStore()
const eventsStore = useEventsStore()
const moneyStore = useMoneyStore()
const pageHeader = usePageHeaderStore()

const event = ref(null)
const appointments = ref([])
const isInternal = ref(false)
const loading = ref(true)
const error = ref(null)
const deleting = ref(false)
const actionError = ref(null)
const linkCopied = ref(false)
// Nach dem Teilen im Feed kurz anzeigen (mit Link zum Beitrag)
const sharedToFeed = ref(null)
const feedShareModal = ref(null)

function onSharedToFeed(post) {
  sharedToFeed.value = post
  setTimeout(() => { sharedToFeed.value = null }, 5000)
}
const lightbox = ref(null)
const formModal = ref(null)
const appointmentModal = ref(null)
const scriptAddModal = ref(null)
// Skripte, Rollen und Rollenzuteilung (GET /skript/eventScripts/{id}) — null, solange nicht geladen
const scriptState = ref(null)
// Kassen des Events (GET /money/eventAccounts/{id}) — null, solange nicht geladen
const moneyState = ref(null)
const moneyAddModal = ref(null)

// Kasse hinzufügen: eine vorhandene zuordnen oder eine neue anlegen
const canAddMoney = computed(() => !!(moneyState.value?.CanCreate || moneyState.value?.availableAccounts.length))

// Ohne Bearbeiten-Modus lassen sich Kassen nicht vom Event lösen
const visibleMoneyState = computed(() => {
  const state = moneyState.value
  if (!state || editing.value) return state
  return { ...state, accounts: state.accounts.map(a => ({ ...a, CanUnlink: false })) }
})

// Bearbeiten darf, wer das Event verwalten, Skripte verknüpfen, Rollen zuteilen, ein Skript
// bearbeiten oder Kassen zuordnen darf
const canEdit = computed(() => !!(
  event.value?.CanManage
  || scriptState.value?.CanManageScripts
  || scriptState.value?.CanAssign
  || scriptState.value?.scripts.some(s => s.CanEdit)
  || canAddMoney.value
  || moneyState.value?.accounts.some(a => a.CanUnlink)
))

const viewModes = computed(() => (isInternal.value ? [
  { value: 'public', label: 'Öffentlich' },
  { value: 'internal', label: 'Intern' },
  canEdit.value && { value: 'edit', label: 'Bearbeiten' },
].filter(Boolean) : []))

// 'public' | 'internal' | 'edit' — steht in der URL (?view=public|edit), damit die
// Ansicht ein Neuladen übersteht; Standard ist "Intern". Eine Ansicht, die (noch)
// nicht erlaubt ist, zeigt "Intern" — die Bearbeiten-Rechte kommen teils erst mit
// den Skripten, danach greift ?view=edit von selbst.
const viewMode = computed(() => {
  const requested = route.query.view
  return viewModes.value.some(m => m.value === requested) ? requested : 'internal'
})

function selectMode(mode) {
  const { view: _old, ...query } = route.query
  router.replace({ query: mode === 'internal' ? query : { ...query, view: mode } })
}

// Öffentliche Vorschau eines nicht öffentlichen Events
const hiddenPublicly = computed(() => viewMode.value === 'public' && !event.value?.IsPublic)

const showInternal = computed(() => isInternal.value && viewMode.value !== 'public')
const editing = computed(() => viewMode.value === 'edit')

// Anmelde-Hinweis wie für nicht angemeldete Besucher (auch in der öffentlichen Vorschau)
const showLogin = computed(() =>
  !showInternal.value && !hiddenPublicly.value && (!authStore.isAuthenticated || viewMode.value === 'public')
)

// Zweispaltig nur, wenn es neben den Eckdaten etwas gibt — sonst bleibt die Seite einspaltig schmal
const hasAside = computed(() => showLogin.value || (showInternal.value && !!(
  appointments.value.length || scriptState.value?.scripts.length || moneyState.value?.accounts.length
)))

const layoutEl = ref(null)
useMasonry(layoutEl, { enabled: () => hasAside.value })

// Ohne Bearbeiten-Modus sieht auch, wer Rechte hat, die Skripte nur lesend
const visibleScriptState = computed(() => {
  const state = scriptState.value
  if (!state || editing.value) return state
  return {
    ...state,
    CanManageScripts: false,
    CanCreateScripts: false,
    CanAssign: false,
    scripts: state.scripts.map(s => ({ ...s, CanEdit: false })),
  }
})

// Was der Nutzer dem Event hinzufügen darf; weitere Arten kommen hier dazu
const addActions = computed(() => [
  event.value?.CanManage && {
    key: 'appointment',
    label: 'Termin',
    hint: 'z.B. Aufbau, Probe, Show oder Abbau',
  },
  canAddMoney.value && {
    key: 'money',
    label: 'Kasse',
    hint: 'Kontostand, Ziel und Budget einer Kasse auf der Event-Seite',
  },
  scriptState.value?.CanManageScripts && {
    key: 'script',
    label: 'Skript',
    hint: 'Neues oder vorhandenes Skript mit Rollen und Rollenzuteilung',
  },
].filter(Boolean))

function onAddAction(key) {
  if (key === 'appointment') {
    appointmentModal.value?.openForEvent(event.value, { roleCasting: !!scriptState.value?.scripts.length })
  }
  if (key === 'script') scriptAddModal.value?.open()
  if (key === 'money') moneyAddModal.value?.open()
}

// ── Termin-Dialog ─────────────────────────────────────────────────────────────
// Der Dialog arbeitet wie im Kalender auf dem events-Store (Zu-/Absagen, Mahlzeiten
// usw. aktualisieren dort direkt); wir merken uns nur die ID.
const selectedAppointmentId = ref(null)
const appointmentLoadingId = ref(null)
const selectedAppointment = computed(() =>
  selectedAppointmentId.value ? eventsStore.getEventById(selectedAppointmentId.value) ?? null : null
)
// Nach "Bearbeiten" den Dialog mit frischen Daten wieder öffnen
let reopenAppointmentId = null

/** Lädt den Monat des Termins frisch in den events-Store */
async function fetchAppointmentMonth(appt) {
  const [year, month] = appt.DateStart.split('-').map(Number)
  await eventsStore.fetchEvents(year, month, true)
}

async function openAppointment(appt, cardEl = null) {
  if (eventsStore.getEventById(appt.ID)) {
    // Schon im Store (z.B. vom Dashboard): sofort öffnen, im Hintergrund aktualisieren
    morphIntoModal(cardEl, () => { selectedAppointmentId.value = appt.ID })
    fetchAppointmentMonth(appt).catch(() => {})
    return
  }
  appointmentLoadingId.value = appt.ID
  try {
    await fetchAppointmentMonth(appt)
  } catch {
    // ohne Daten bleibt es beim Schließen des Platzhalters
  }
  // Inzwischen geschlossen?
  if (appointmentLoadingId.value !== appt.ID) return
  const skeletonEl = document.querySelector('dialog.event-dialog-skeleton[open]')
  morphIntoModal(skeletonEl, () => {
    appointmentLoadingId.value = null
    if (eventsStore.getEventById(appt.ID)) selectedAppointmentId.value = appt.ID
  })
}

function closeAppointment() {
  selectedAppointmentId.value = null
  // Rückmeldungen (Termin, Mahlzeiten) in der Liste aktualisieren
  load()
}

function editAppointment(appt) {
  selectedAppointmentId.value = null
  reopenAppointmentId = appt.ID
  appointmentModal.value?.openEditAppointment(appt)
}

async function onAppointmentSaved() {
  const id = reopenAppointmentId
  reopenAppointmentId = null
  const appt = appointments.value.find(a => a.ID === id)
  await load()
  if (!appt) return
  await fetchAppointmentMonth(appt).catch(() => {})
  // Gelöscht oder aus dem Event genommen → nicht wieder öffnen
  if (appointments.value.some(a => a.ID === id) && eventsStore.getEventById(id)) selectedAppointmentId.value = id
}

function onAppointmentModalClosed(wasSaved) {
  if (wasSaved || !reopenAppointmentId) return
  // Abgebrochen: Dialog wieder öffnen
  selectedAppointmentId.value = reopenAppointmentId
  reopenAppointmentId = null
}

async function loadMoney() {
  try {
    moneyState.value = await moneyStore.fetchEventAccounts(event.value.ID)
  } catch {
    // Ohne Kassen-Daten bleibt die Seite benutzbar, nur die Kassen-Karte fehlt
    moneyState.value = null
  }
}

async function loadScripts() {
  try {
    scriptState.value = await skriptStore.fetchEventScripts(event.value.ID)
  } catch {
    // Ohne Skript-Daten bleibt die Seite benutzbar, nur die Skript-Karte fehlt
    scriptState.value = null
  }
}

const facts = computed(() => {
  const e = event.value
  if (!e) return []
  return [
    { label: 'Wann', value: e.RangeStart ? formatOrgEventRange(e) : '' },
    { label: 'Wo', value: formatAddress(e) },
    { label: 'Art', value: e.TypeTitle },
    { label: 'Empfohlen für', value: (e.AgeGroups ?? []).map(g => g.Title).join(', ') },
    { label: 'Eintritt', value: PRICE_MODE_LABELS[e.PriceMode] ?? formatFixedPrice(e) },
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
    if (isInternal.value) {
      loadScripts()
      loadMoney()
    }
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

// Auf dem Handy der System-Teilen-Dialog, sonst Link in die Zwischenablage
async function share() {
  const url = window.location.origin + event.value.Link
  if (navigator.share) {
    try {
      await navigator.share({ title: event.value.Title, url })
    } catch {
      // Dialog abgebrochen
    }
    return
  }
  try {
    await navigator.clipboard.writeText(url)
    linkCopied.value = true
    setTimeout(() => { linkCopied.value = false }, 2000)
  } catch {
    window.prompt('Link zum Event:', url)
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
  scriptState.value = null
  moneyState.value = null
  load()
}, { immediate: true })
</script>
