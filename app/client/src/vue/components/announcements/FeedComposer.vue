<template>
  <!-- Neuer Feed-Beitrag: großes Textfeld (mit @-Vorschlägen), darunter wer postet und
       für wen er sichtbar ist -->
  <form class="feed-composer" @submit.prevent="submit">
    <div class="feed-composer_input">
      <!-- Leichte Formatierung wie im Skript-Editor -->
      <div v-if="editor" class="feed-composer_toolbar" role="toolbar" aria-label="Formatierung">
        <button
          type="button"
          class="feed-composer_toolbar-btn"
          :class="{ 'feed-composer_toolbar-btn--active': editor.isActive('bold') }"
          :aria-pressed="editor.isActive('bold')"
          title="Fett (Strg+B)"
          @click="editor.chain().focus().toggleBold().run()"
        ><strong>F</strong></button>
        <button
          type="button"
          class="feed-composer_toolbar-btn"
          :class="{ 'feed-composer_toolbar-btn--active': editor.isActive('italic') }"
          :aria-pressed="editor.isActive('italic')"
          title="Kursiv (Strg+I)"
          @click="editor.chain().focus().toggleItalic().run()"
        ><em>K</em></button>
        <button
          type="button"
          class="feed-composer_toolbar-btn"
          :class="{ 'feed-composer_toolbar-btn--active': editor.isActive('underline') }"
          :aria-pressed="editor.isActive('underline')"
          title="Unterstrichen (Strg+U)"
          @click="editor.chain().focus().toggleUnderline().run()"
        ><u>U</u></button>
      </div>

      <div class="feed-composer_field">
        <EditorContent
          :editor="editor"
          class="feed-composer_editor"
          :class="{ 'feed-composer_editor--empty': isEmpty }"
          :style="{ '--placeholder': JSON.stringify(placeholder) }"
        />
        <!-- Zeichenzähler unten rechts im Textfeld -->
        <span
          class="feed-composer_count"
          :class="{ 'feed-composer_count--near': textLength > MAX_LENGTH * 0.9, 'feed-composer_count--over': textLength > MAX_LENGTH }"
          aria-live="polite"
        >{{ textLength }} / {{ MAX_LENGTH }}</span>
      </div>

      <!-- Vorschläge für @-Markierungen -->
      <ul v-if="suggestions.length" class="feed-composer_suggestions" role="listbox" aria-label="Personen markieren">
        <li v-for="(m, i) in suggestions" :key="m.ID" role="option" :aria-selected="i === activeSuggestion">
          <button
            type="button"
            class="feed-composer_suggestion"
            :class="{ 'feed-composer_suggestion--active': i === activeSuggestion }"
            @mousedown.prevent="insertMention(m)"
          >
            <AppAvatar :src="m.Avatar" :alt="m.Name" :name="m.Name" img-class="feed-composer_suggestion-avatar" />
            <span class="feed-composer_suggestion-name">{{ m.Name }}</span>
            <span class="feed-composer_suggestion-username">@{{ m.Username }}</span>
          </button>
        </li>
      </ul>
    </div>

    <!-- Wer postet und für wen — eingeklappt, über das Auge in der Fußzeile einblendbar -->
    <div v-show="showOptions" :id="`${uid}-options`" class="modalform feed-composer_options">
      <AppSegmentedToggle
        v-if="postAsOrganizations.length"
        v-model="postAs"
        label="Posten als"
        class="field--3"
        :options="[
          { value: 'person', label: 'Persönlich' },
          { value: 'organization', label: 'Organisation' },
        ]"
      />
      <AppSegmentedToggle
        v-model="visibility"
        label="Sichtbarkeit"
        :class="postAsOrganizations.length ? 'field--3' : ''"
        :options="[
          { value: 'Public', label: 'Öffentlich', disabled: eventIsInternal },
          { value: 'Internal', label: 'Intern', disabled: !organizations.length },
        ]"
      />

      <label v-if="postAs === 'organization'" class="field">
        Organisation
        <select v-model="organizationId">
          <option v-for="o in postAsOrganizations" :key="o.ID" :value="o.ID">{{ o.Title }}</option>
        </select>
      </label>
      <label v-else-if="visibility === 'Internal'" class="field">
        Intern für
        <select v-model="internalOrganizationId">
          <option v-for="o in organizations" :key="o.ID" :value="o.ID">{{ o.Title }}</option>
        </select>
      </label>
    </div>

    <!-- Zeitraum: später veröffentlichen und/oder automatisch ausblenden (wie bei Mitteilungen) —
         eingeklappt, über die Uhr in der Fußzeile einblendbar -->
    <div v-show="showSchedule" :id="`${uid}-schedule`" class="modalform feed-composer_options">
      <label class="field field--3">
        Veröffentlichen am
        <input v-model="releaseDate" type="datetime-local" :min="minReleaseDate" />
      </label>
      <label class="field field--3">
        Ausblenden am
        <input v-model="expiryDate" type="datetime-local" :min="releaseDate || minReleaseDate" />
      </label>
      <p class="feed-composer_schedule-hint">
        Leer lassen, um sofort zu posten bzw. den Beitrag dauerhaft zu zeigen. Vor der Veröffentlichung
        siehst nur du ihn, nach dem Ausblenden steht er unter „Vergangene Mitteilungen“.
      </p>
    </div>

    <p v-if="error" class="feed-composer_error">{{ error }}</p>

    <!-- Wer den Beitrag sieht, Einstellungen ein-/ausblenden und (auf dem Desktop daneben) Posten -->
    <div class="feed-composer_footer">
      <p class="feed-composer_hint">{{ audienceHint }}</p>
      <AppIconButton
        v-if="!inline"
        :variant="showOptions ? 'primary' : 'ghost'"
        :aria-label="showOptions ? 'Sichtbarkeit und Absender ausblenden' : 'Sichtbarkeit und Absender einstellen'"
        :title="showOptions ? 'Einstellungen ausblenden' : 'Wer postet, wer sieht es?'"
        :aria-expanded="showOptions"
:aria-controls="`${uid}-options`"
        @click="showOptions = !showOptions"
      >
        <span class="icon-mask" :style="eyeIconStyle" aria-hidden="true" />
      </AppIconButton>
      <AppIconButton
        v-if="!inline"
        :variant="showSchedule || isScheduled || expiryDate ? 'primary' : 'ghost'"
        :aria-label="showSchedule ? 'Zeitraum ausblenden' : 'Zeitraum festlegen'"
        :title="showSchedule ? 'Zeitraum ausblenden' : 'Planen und Ablaufdatum'"
        :aria-expanded="showSchedule"
:aria-controls="`${uid}-schedule`"
        @click="showSchedule = !showSchedule"
      >
        <span class="icon-mask" :style="timeIconStyle" aria-hidden="true" />
      </AppIconButton>
      <AppButton type="submit" variant="primary" class="feed-composer_submit" :disabled="saving || (isEmpty && !event) || textLength > MAX_LENGTH">
        {{ saving ? 'Wird gespeichert…' : submitLabel }}
      </AppButton>
    </div>
  </form>
</template>

<script setup>
import { ref, computed, watch, onBeforeUnmount } from 'vue'
import { useEditor, EditorContent } from '@tiptap/vue-3'
import StarterKit from '@tiptap/starter-kit'
import { useAnnouncementsStore } from '@stores/announcements'
import AppAvatar from '@components/ui/AppAvatar.vue'
import AppButton from '@components/ui/AppButton.vue'
import AppIconButton from '@components/ui/AppIconButton.vue'
import actionEye from '../../../../icons/actions/action_eye.svg'
import actionTime from '../../../../icons/actions/action_time.svg'
import AppSegmentedToggle from '@components/ui/AppSegmentedToggle.vue'

const props = defineProps({
  // Geteiltes Event (Summary bzw. öffentliche Daten) — der Text ist dann optional
  event: { type: Object, default: null },
  // Im Modal: Einstellungen und Zeitraum direkt ausgeklappt, ohne Umschalt-Buttons
  inline: { type: Boolean, default: false },
})

const placeholder = computed(() => (props.event
  ? 'Ein paar Worte zum Event (optional) — mit @Benutzername markierst du jemanden.'
  : 'Was gibt\'s Neues? Mit @Benutzername markierst du jemanden.'))

// Eindeutige IDs für aria-controls (Feed und Modal können gleichzeitig existieren)
const uid = `feed-composer-${Math.random().toString(36).slice(2, 8)}`

// Interne Events lassen sich nur intern für ihre Organisation teilen (prüft auch das Backend)
const eventIsInternal = computed(() => !!props.event && !props.event.IsPublic)

const eyeIconStyle = { maskImage: `url("${actionEye}")`, WebkitMaskImage: `url("${actionEye}")` }
const timeIconStyle = { maskImage: `url("${actionTime}")`, WebkitMaskImage: `url("${actionTime}")` }

// Wie FeedPost::MAX_LENGTH im Backend (Zeichen ohne Formatierung)
const MAX_LENGTH = 2000

const emit = defineEmits(['posted'])
const store = useAnnouncementsStore()

// Nur Absätze, Zeilenumbrüche, fett, kursiv, unterstrichen — mehr lässt das Backend nicht durch
const editor = useEditor({
  extensions: [
    StarterKit.configure({
      heading: false,
      bulletList: false,
      orderedList: false,
      listItem: false,
      listKeymap: false,
      blockquote: false,
      codeBlock: false,
      code: false,
      horizontalRule: false,
      strike: false,
      link: false,
    }),
  ],
  editorProps: {
    attributes: { 'aria-label': 'Neuer Beitrag', 'aria-multiline': 'true', role: 'textbox' },
    handleKeyDown: (_view, event) => onKeydown(event),
  },
  onUpdate: () => {
    updateTextStats()
    updateMentionQuery()
  },
  onSelectionUpdate: () => updateMentionQuery(),
  onBlur: () => closeSuggestions(),
})

const isEmpty = ref(true)
const textLength = ref(0)
function updateTextStats() {
  isEmpty.value = !editor.value || editor.value.isEmpty || !editor.value.getText().trim()
  textLength.value = editor.value?.getText({ blockSeparator: '\n' }).length ?? 0
}
const postAs = ref('person')
const visibility = ref('Public')
const organizationId = ref(null)
const internalOrganizationId = ref(null)
const saving = ref(false)

watch(eventIsInternal, internal => { if (internal) visibility.value = 'Internal' }, { immediate: true })

const submitLabel = computed(() => {
  if (isScheduled.value) return 'Planen'
  return props.event ? 'Im Feed teilen' : 'Posten'
})
// "Posten als", "Sichtbarkeit" und Organisationsauswahl sind standardmäßig eingeklappt
const showOptions = ref(props.inline)

// Planen: "YYYY-MM-DDTHH:MM" (Ortszeit) oder leer = sofort; ebenfalls eingeklappt
const showSchedule = ref(props.inline)
const releaseDate = ref('')
const expiryDate = ref('')

function toLocalInput(date) {
  const pad = n => String(n).padStart(2, '0')
  return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}`
}
const minReleaseDate = computed(() => toLocalInput(new Date()))
const isScheduled = computed(() => !!releaseDate.value && new Date(releaseDate.value) > new Date())
const dateTimeFormat = new Intl.DateTimeFormat('de-DE', { day: '2-digit', month: '2-digit', year: '2-digit', hour: '2-digit', minute: '2-digit' })
const scheduleText = computed(() => [
  isScheduled.value && `Geplant für ${dateTimeFormat.format(new Date(releaseDate.value))}.`,
  expiryDate.value && `Sichtbar bis ${dateTimeFormat.format(new Date(expiryDate.value))}.`,
].filter(Boolean).join(' ') || null)
const error = ref(null)

const organizations = computed(() => (eventIsInternal.value
  ? store.feedOrganizations.filter(o => o.ID === props.event.OrganizationID)
  : store.feedOrganizations))
const postAsOrganizations = computed(() => (eventIsInternal.value
  ? store.postAsOrganizations.filter(o => o.ID === props.event.OrganizationID)
  : store.postAsOrganizations))

// Erste Organisation vorauswählen, sobald die Auswahl geladen ist
watch(postAsOrganizations, list => {
  if (!list.some(o => o.ID === organizationId.value)) organizationId.value = list[0]?.ID ?? null
}, { immediate: true })
watch(organizations, list => {
  if (!list.some(o => o.ID === internalOrganizationId.value)) internalOrganizationId.value = list[0]?.ID ?? null
}, { immediate: true })

// Auch bei eingeklappten Einstellungen sichtbar: wer postet und wer es sieht
const audienceHint = computed(() => {
  const asOrg = postAs.value === 'organization'
    ? postAsOrganizations.value.find(o => o.ID === organizationId.value)?.Title
    : null
  const prefix = asOrg ? `Als ${asOrg} · ` : ''
  const suffix = scheduleText.value ? ` ${scheduleText.value}` : ''
  if (visibility.value === 'Public') return `${prefix}Öffentlich: sichtbar für alle in ToTeam.${suffix}`
  const orgId = asOrg ? organizationId.value : internalOrganizationId.value
  const title = [...postAsOrganizations.value, ...organizations.value].find(o => o.ID === orgId)?.Title
  return `${prefix}Intern: nur für Mitglieder von ${title ?? 'der gewählten Organisation'} sichtbar.${suffix}`
})

// ── @-Markierungen ────────────────────────────────────────────────────────────
const suggestions = ref([])
const activeSuggestion = ref(0)
// Bereich von "@abc" im Dokument (ProseMirror-Positionen)
let mentionRange = null
let searchTimer = null
let searchToken = 0

// Steht direkt vor dem Cursor "@abc" (am Absatzanfang oder nach Leerraum), Vorschläge dazu suchen
function updateMentionQuery() {
  const ed = editor.value
  if (!ed) return
  const { $from, empty } = ed.state.selection
  clearTimeout(searchTimer)
  const before = empty ? $from.parent.textBetween(0, $from.parentOffset, undefined, '\ufffc') : ''
  const match = before.match(/(?:^|\s)@([A-Za-z0-9._-]*)$/)
  if (!match || !match[1]) {
    closeSuggestions()
    return
  }
  mentionRange = { from: $from.pos - match[1].length - 1, to: $from.pos }
  const token = ++searchToken
  searchTimer = setTimeout(async () => {
    try {
      const result = await store.searchMentions(match[1])
      if (token !== searchToken) return
      suggestions.value = result
      activeSuggestion.value = 0
    } catch {
      suggestions.value = []
    }
  }, 200)
}

function closeSuggestions() {
  searchToken++
  suggestions.value = []
}

function insertMention(member) {
  if (!mentionRange) return
  editor.value.chain().focus().insertContentAt(mentionRange, `@${member.Username} `).run()
  closeSuggestions()
}

// Aus dem Editor (handleKeyDown) — true heißt: Taste ist erledigt
function onKeydown(e) {
  if (suggestions.value.length) {
    if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
      const n = suggestions.value.length
      activeSuggestion.value = (activeSuggestion.value + (e.key === 'ArrowDown' ? 1 : n - 1)) % n
      return true
    }
    if (e.key === 'Enter' || e.key === 'Tab') {
      insertMention(suggestions.value[activeSuggestion.value])
      return true
    }
    if (e.key === 'Escape') {
      closeSuggestions()
      return true
    }
  }
  // Strg/⌘ + Enter postet
  if (e.key === 'Enter' && (e.ctrlKey || e.metaKey)) {
    submit()
    return true
  }
  return false
}

async function submit() {
  updateTextStats()
  if (saving.value || (isEmpty.value && !props.event) || textLength.value > MAX_LENGTH) return
  saving.value = true
  error.value = null
  try {
    const post = await store.createPost({
      Content: editor.value.getHTML(),
      PostAs: postAs.value,
      OrganizationID: postAs.value === 'organization' ? organizationId.value : null,
      Visibility: visibility.value,
      InternalOrganizationID: visibility.value === 'Internal' && postAs.value === 'person' ? internalOrganizationId.value : null,
      ReleaseDate: isScheduled.value ? releaseDate.value : null,
      ExpiryDate: expiryDate.value || null,
      EventID: props.event?.ID ?? null,
    })
    editor.value.commands.clearContent(true)
    releaseDate.value = ''
    expiryDate.value = ''
    showSchedule.value = false
    emit('posted', post)
  } catch (e) {
    error.value = e.message
  } finally {
    saving.value = false
  }
}
onBeforeUnmount(() => clearTimeout(searchTimer))
</script>
