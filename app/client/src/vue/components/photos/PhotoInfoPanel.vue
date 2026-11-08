<!--
  Infos zu einem Foto im Betrachter — mit Recht direkt bearbeitbar: Titel und
  Beschreibung zum Anklicken, das Event als Auswahl in einer Zeile, darunter je Art
  (Personen, Charaktere, Schlagworte) die Tags mit "+". Jede Änderung wird sofort
  gespeichert (`change` mit { field, set | add | remove }, siehe PhotoViewer).
  Auf großen Bildschirmen rechts neben dem Foto, auf dem Handy als Blatt von unten.
-->
<template>
  <aside class="photo-info-panel" aria-label="Informationen zum Foto">
    <div class="photo-info-panel_head">
      <PhotoInlineText
        v-if="photo"
        tag="h2"
        class="photo-info-panel_title"
        label="Titel"
        placeholder="Titel hinzufügen"
        :value="photo.Title || ''"
        :editable="photo.CanEdit"
        :disabled="busy"
        @save="emit('change', { field: 'Title', set: $event })"
      />
      <h2 v-if="!photo || (!photo.Title && !photo.CanEdit)" class="photo-info-panel_title">Informationen</h2>
      <button type="button" class="photo-info-panel_close" aria-label="Informationen schließen" @click="emit('close')">✕</button>
    </div>

    <p v-if="!photo" class="photo-info-panel_loading">Lädt…</p>

    <template v-else>
      <PhotoInlineText
        class="photo-info-panel_description"
        label="Beschreibung"
        placeholder="Beschreibung hinzufügen…"
        multiline
        :maxlength="5000"
        :value="photo.Description || ''"
        :editable="photo.CanEdit"
        :disabled="busy"
        @save="emit('change', { field: 'Description', set: $event })"
      />

      <!-- Copyright: Freitext, angezeigt mit vorangestelltem © -->
      <div v-if="photo.CanEdit || photo.Copyright" class="photo-info-panel_copyright">
        <span class="photo-info-panel_copyright-sign" aria-hidden="true">©</span>
        <PhotoInlineText
          class="photo-info-panel_copyright-text"
          label="Copyright"
          placeholder="Copyright hinzufügen"
          :suggestions="options?.copyrights ?? []"
          :value="photo.Copyright || ''"
          :editable="photo.CanEdit"
          :disabled="busy"
          @save="emit('change', { field: 'Copyright', set: $event.replace(/^\s*(©|\(c\))\s*/i, '') })"
        />
      </div>

      <!-- Event: eine Zeile -->
      <div v-if="photo.CanTag.Events || photo.Events.length" class="photo-info-panel_row">
        <span :id="`${uid}-event`" class="photo-info-panel_row-label">Event</span>
        <select
          v-if="photo.CanTag.Events"
          class="input photo-info-panel_event-select"
          :aria-labelledby="`${uid}-event`"
          :value="photo.Events[0]?.ID ?? 0"
          :disabled="busy"
          @change="onEventChange"
        >
          <option :value="0">Kein Event</option>
          <option v-for="ev in eventOptions" :key="ev.ID" :value="ev.ID">{{ ev.Title }}</option>
        </select>
        <router-link
          v-for="ev in (photo.CanTag.Events ? photo.Events.slice(0, 1) : photo.Events)"
          :key="ev.ID"
          :to="{ name: 'EventDetail', params: { segment: ev.URLSegment } }"
          class="photo-info-panel_event-link"
          :title="`Zur Event-Seite von ${ev.Title}`"
          :aria-label="photo.CanTag.Events ? `Zur Event-Seite von ${ev.Title}` : undefined"
        >{{ photo.CanTag.Events ? '→' : ev.Title }}</router-link>
      </div>

      <PhotoTagSection
        v-if="photo.CanTag.People || photo.People.length"
        title="Personen"
        with-avatars
        add-label="Person markieren"
        placeholder="Name suchen…"
        :items="photo.People.map(p => ({ id: p.ID, label: p.Name, avatar: p.Avatar }))"
        :options="(options?.people ?? []).map(p => ({ id: p.ID, label: p.Name, avatar: p.Avatar, hint: p.IsGuest ? 'Gast' : '' }))"
        :can-add="photo.CanTag.People"
        :removable="item => photo.CanTag.People || item.id === authStore.user?.ID"
        :disabled="busy"
        @add="emit('change', { field: 'People', add: $event.id })"
        @remove="removePerson"
      />

      <PhotoTagSection
        v-if="(photo.CanTag.Characters && options?.characters.length) || photo.Characters.length"
        title="Charaktere"
        add-label="Charakter hinzufügen"
        placeholder="Charakter suchen…"
        :items="photo.Characters.map(c => ({ id: c.ID, label: c.Title }))"
        :options="(options?.characters ?? []).map(c => ({ id: c.ID, label: c.Title, hint: c.Script || '' }))"
        :can-add="photo.CanTag.Characters"
        :removable="() => photo.CanTag.Characters"
        :disabled="busy"
        @add="emit('change', { field: 'Characters', add: $event.id })"
        @remove="emit('change', { field: 'Characters', remove: $event.id })"
      />

      <!-- Räume: Inventar-Räume der Organisation und gezeichnete Räume auf den Lageplänen
           des Events (IDs mit "r"/"a" davor, damit sie sich nicht in die Quere kommen) -->
      <PhotoTagSection
        v-if="(photo.CanTag.Rooms && roomOptions.length) || roomItems.length"
        title="Räume"
        add-label="Raum hinzufügen"
        placeholder="Raum suchen…"
        :items="roomItems"
        :options="roomOptions"
        :can-add="photo.CanTag.Rooms"
        :removable="() => photo.CanTag.Rooms"
        :disabled="busy"
        @add="emit('change', roomChange($event.id, 'add'))"
        @remove="emit('change', roomChange($event.id, 'remove'))"
      />

      <PhotoTagSection
        v-if="photo.CanTag.Tags || photo.Tags.length"
        title="Schlagworte"
        add-label="Schlagwort hinzufügen"
        placeholder="Schlagwort suchen oder neu…"
        allow-create
        :items="photo.Tags.map(t => ({ id: t.ID, label: t.Title }))"
        :options="(options?.tags ?? []).map(t => ({ id: t.ID, label: t.Title }))"
        :can-add="photo.CanTag.Tags"
        :removable="() => photo.CanTag.Tags"
        :disabled="busy"
        @add="emit('change', { field: 'Tags', add: $event.create ?? $event.label })"
        @remove="emit('change', { field: 'Tags', remove: $event.label })"
      />

      <ul class="photo-info-panel_facts">
        <li v-if="photo.TakenAt">Aufgenommen am {{ formatDateTime(photo.TakenAt) }}</li>
        <li v-if="photo.UploadedBy">Hochgeladen von {{ photo.UploadedBy.Name }} am {{ formatDateShort(photo.Created) }}</li>
        <li v-if="photo.Width && photo.Height">{{ photo.Width }} × {{ photo.Height }} px</li>
        <li v-if="hasLocation">
          <a :href="osmLink" target="_blank" rel="noopener noreferrer">Aufnahmeort auf OpenStreetMap</a>
        </li>
      </ul>

      <p v-if="error" class="photo-info-panel_error" role="alert">{{ error }}</p>

      <div class="photo-info-panel_actions">
        <AppButton v-if="photo.URL" variant="secondary" size="small" @click="openOriginal">In neuem Tab öffnen</AppButton>
        <AppIconButton
          v-if="photo.CanDelete"
          variant="danger"
          class="photo-info-panel_delete"
          aria-label="Foto löschen"
          title="Löschen"
          :disabled="busy"
          @click="emit('remove')"
        >
          <span class="icon-mask" :style="trashIconStyle" aria-hidden="true" />
        </AppIconButton>
      </div>
    </template>
  </aside>
</template>

<script setup>
import { computed } from 'vue'
import { useAuthStore } from '@stores/auth'
import AppButton from '@components/ui/AppButton.vue'
import AppIconButton from '@components/ui/AppIconButton.vue'
import PhotoInlineText from '@components/photos/PhotoInlineText.vue'
import PhotoTagSection from '@components/photos/PhotoTagSection.vue'
import { formatPhotoDateShort as formatDateShort, formatPhotoDateTime as formatDateTime } from '@utils/photoUpload'
import actionTrash from '../../../../icons/actions/action_trash.svg'

const props = defineProps({
  // Detailform aus GET /photos/detail/{id}, null solange sie lädt
  photo: { type: Object, default: null },
  // Auswahl der Organisation (people, characters, events, tags) aus GET /photos/options
  options: { type: Object, default: null },
  busy: { type: Boolean, default: false },
  error: { type: String, default: null },
})

// change: { field, set | add | remove }; untag: eigene Markierung ohne Bearbeitungsrecht entfernen
const emit = defineEmits(['close', 'change', 'untag', 'remove'])
const authStore = useAuthStore()

const uid = `photo-info-${Math.random().toString(36).slice(2)}`
const trashIconStyle = { maskImage: `url("${actionTrash}")`, WebkitMaskImage: `url("${actionTrash}")` }

// Events der Organisation — das aktuelle bleibt wählbar, auch wenn die Auswahl noch lädt
const eventOptions = computed(() => {
  const list = props.options?.events ?? []
  const current = props.photo?.Events[0]
  return current && !list.some(e => e.ID === current.ID) ? [current, ...list] : list
})

// ── Räume: Inventar-Räume + Räume (Flächen) auf den Lageplänen der Events des Fotos ──
const roomItems = computed(() => [
  ...(props.photo?.Rooms ?? []).map(r => ({ id: `r${r.ID}`, label: r.Title })),
  ...(props.photo?.MapAreas ?? []).map(a => ({ id: `a${a.ID}`, label: a.Title })),
])
const roomOptions = computed(() => {
  const eventIds = new Set((props.photo?.Events ?? []).map(e => e.ID))
  return [
    ...(props.options?.rooms ?? []).map(r => ({ id: `r${r.ID}`, label: r.Title, hint: 'Inventar' })),
    ...(props.options?.areas ?? [])
      .filter(a => eventIds.has(a.EventID))
      .map(a => ({ id: `a${a.ID}`, label: a.Title, hint: a.Plan ? `Lageplan: ${a.Plan}` : 'Lageplan' })),
  ]
})

function roomChange(key, action) {
  const field = key.startsWith('a') ? 'MapAreas' : 'Rooms'
  return { field, [action]: Number(key.slice(1)) }
}

// 0/0 ist kein echter Aufnahmeort (so stehen Fotos ohne GPS in der Datenbank)
const hasLocation = computed(() => {
  const p = props.photo
  return !!p && p.Latitude != null && p.Longitude != null && (p.Latitude !== 0 || p.Longitude !== 0)
})

const osmLink = computed(() => {
  const p = props.photo
  return p ? `https://www.openstreetmap.org/?mlat=${p.Latitude}&mlon=${p.Longitude}#map=17/${p.Latitude}/${p.Longitude}` : ''
})

// Die eigene Markierung darf jede:r entfernen, auch ohne Bearbeitungsrecht
function removePerson(item) {
  if (props.photo.CanTag.People) emit('change', { field: 'People', remove: item.id })
  else emit('untag')
}

// Danach den Fokus abgeben, sonst wechseln die Pfeiltasten das Event statt das Foto
function onEventChange(event) {
  emit('change', { field: 'Events', set: Number(event.target.value) })
  event.target.blur()
}

function openOriginal() {
  window.open(props.photo.URL, '_blank', 'noopener')
}
</script>
