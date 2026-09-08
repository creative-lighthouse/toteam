<template>
  <div class="section section--SkriptDetailPage">
    <div class="section_content">

      <div v-if="loading" class="section_infobox">
        <p>Lade Skript…</p>
      </div>

      <div v-else-if="!script" class="section_infobox">
        <p>Skript nicht gefunden.</p>
      </div>

      <div v-else class="skript-detail">

        <!-- Toolbar: mode switcher (own centered row) + title/actions (hidden when printing) -->
        <div class="skript-detail_toolbar no-print">
          <div class="skript-detail_modes-row">
            <!-- Desktop/Tablet: Button-Leiste -->
            <div class="skript-detail_modes" role="tablist">
              <button
                type="button"
                class="skript-detail_mode-btn"
                :class="{ 'skript-detail_mode-btn--active': store.activeMode === 'view' }"
                @click="switchMode('view')"
              >Ansicht</button>
              <button
                v-if="script.CanEdit"
                type="button"
                class="skript-detail_mode-btn"
                :class="{ 'skript-detail_mode-btn--active': store.activeMode === 'edit' }"
                @click="switchMode('edit')"
              >Bearbeiten</button>
              <button
                type="button"
                class="skript-detail_mode-btn"
                :class="{ 'skript-detail_mode-btn--active': store.activeMode === 'focus' }"
                @click="switchMode('focus')"
              >Fokus</button>
              <button
                type="button"
                class="skript-detail_mode-btn"
                :class="{ 'skript-detail_mode-btn--active': store.activeMode === 'learn' }"
                @click="switchMode('learn')"
              >Lernen</button>
              <button
                v-if="script.CanManageRoles"
                type="button"
                class="skript-detail_mode-btn"
                :class="{ 'skript-detail_mode-btn--active': store.activeMode === 'roles' }"
                @click="switchMode('roles')"
              >Rollen</button>
            </div>

            <!-- Mobil: die Button-Leiste wird zu breit, daher ein Dropdown -->
            <select
              class="skript-detail_modes-select input"
              :value="store.activeMode"
              aria-label="Ansicht wechseln"
              @change="switchMode($event.target.value)"
            >
              <option value="view">Ansicht</option>
              <option v-if="script.CanEdit" value="edit">Bearbeiten</option>
              <option value="focus">Fokus</option>
              <option value="learn">Lernen</option>
              <option v-if="script.CanManageRoles" value="roles">Rollen</option>
            </select>
          </div>

          <div class="skript-detail_header-row">
            <h2 class="hl2 skript-detail_title">{{ script.Title }}</h2>

            <div class="skript-detail_actions">
              <AppIconButton variant="primary" aria-label="Skript drucken" title="Drucken" @click="printScript">
                <span class="icon-mask" :style="printIconStyle" />
              </AppIconButton>
              <AppIconButton v-if="script.CanDelete" variant="danger" aria-label="Skript löschen" title="Löschen" @click="deleteScript">
                <span class="icon-mask" :style="trashIconStyle" />
              </AppIconButton>
            </div>
          </div>

          <div v-if="store.activeMode === 'focus' || store.activeMode === 'learn'" class="skript-detail_person-row">
            <ScriptMemberPickerDropdown
              v-model="store.focusMemberId"
              :members="orgMembers"
            />
          </div>
        </div>

        <!-- Gliederungs-Navigation: identisch in allen Modi außer Rollen (dort
             gibt es keine Absätze zum Navigieren, der Bereich nutzt die volle Breite) -->
        <div class="skript-detail_layout">
          <ScriptTocSidebar
            v-if="store.activeMode !== 'roles'"
            class="skript-detail_toc no-print"
            :entries="store.tocEntries"
          />

          <div class="skript-detail_main">
            <!-- Bearbeiten-Modus: ein durchgehender Editor, Absätze entstehen automatisch bei Enter -->
            <ScriptEditor
              v-if="store.activeMode === 'edit' && script.CanEdit"
              ref="scriptEditorRef"
              :key="script.ID"
              :script-id="script.ID"
              :initial-paragraphs="script.Paragraphs"
              :roles="script.Roles"
            />

            <!-- Rollen: Akkordeon-Liste zum Anlegen/Bearbeiten der Skript-Rollen -->
            <ScriptRolesView
              v-else-if="store.activeMode === 'roles' && script.CanManageRoles"
              :script="script"
              :org-members="orgMembers"
            />

            <!-- Ansicht / Fokus / Lernen: gerenderte Absätze aus dem zuletzt gespeicherten Stand -->
            <div v-else class="skript-detail_paragraphs">
              <ScriptParagraphRow
                v-for="paragraph in script.Paragraphs"
                :key="paragraph.ID"
                :paragraph="paragraph"
                :roles="script.Roles"
                :dimmed="isDimmed(paragraph)"
                :highlighted="isHighlighted(paragraph)"
                :blurred="isBlurred(paragraph)"
                @open-roles="roleInfoModal?.open($event)"
              />
            </div>
          </div>
        </div>

      </div>
    </div>

    <ScriptRoleInfoModal ref="roleInfoModal" />
  </div>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { useRoute, useRouter, onBeforeRouteLeave } from 'vue-router'
import { useSkriptStore } from '@stores/skript'
import { usePageHeaderStore } from '@stores/pageHeader'
import AppIconButton from '@components/AppIconButton.vue'
import ScriptEditor from '@components/ScriptEditor.vue'
import ScriptParagraphRow from '@components/ScriptParagraphRow.vue'
import ScriptTocSidebar from '@components/ScriptTocSidebar.vue'
import ScriptRolesView from '@components/ScriptRolesView.vue'
import ScriptRoleInfoModal from '@components/ScriptRoleInfoModal.vue'
import ScriptMemberPickerDropdown from '@components/ScriptMemberPickerDropdown.vue'
import actionPrint from '../../../icons/actions/action_print.svg'
import actionTrash from '../../../icons/actions/action_trash.svg'

const printIconStyle = { maskImage: `url("${actionPrint}")`, WebkitMaskImage: `url("${actionPrint}")` }
const trashIconStyle = { maskImage: `url("${actionTrash}")`, WebkitMaskImage: `url("${actionTrash}")` }

const route = useRoute()
const router = useRouter()
const store = useSkriptStore()
const pageHeaderStore = usePageHeaderStore()

pageHeaderStore.setHeader('Skript')

const loading = ref(true)
const orgMembers = ref([])
const scriptEditorRef = ref(null)
const roleInfoModal = ref(null)
const script = computed(() => store.currentScript)

watch(script, val => pageHeaderStore.setTitle(val?.Title ?? 'Skript'))

// Leaving edit mode unmounts ScriptEditor, and Tiptap's own useEditor() hook
// destroys the editor on unmount before ScriptEditor gets a chance to react —
// so any not-yet-saved change (the debounce is 800ms) must be flushed here,
// while the editor is still alive, before the mode actually switches.
async function switchMode(mode) {
  if (store.activeMode === 'edit' && mode !== 'edit') {
    await scriptEditorRef.value?.flush()
  }
  store.setMode(mode)
}

function isHighlighted(paragraph) {
  if (store.activeMode !== 'focus' && store.activeMode !== 'learn') return false
  return store.isParagraphForMember(paragraph, store.focusMemberId)
}

function isDimmed(paragraph) {
  if (store.activeMode !== 'focus' && store.activeMode !== 'learn') return false
  if (!store.focusMemberId) return false
  return !store.isParagraphForMember(paragraph, store.focusMemberId)
}

function isBlurred(paragraph) {
  if (store.activeMode !== 'learn' || !store.focusMemberId) return false
  return store.isParagraphForMember(paragraph, store.focusMemberId)
}

async function deleteScript() {
  if (!script.value || !confirm(`Skript "${script.value.Title}" wirklich löschen?`)) return
  const response = await store.deleteScript(script.value.ID)
  if (response.success) {
    router.push({ name: 'Skript' })
  }
}

function printScript() {
  window.print()
}

async function loadScript(hash) {
  loading.value = true
  await store.fetchScriptByHash(hash)
  loading.value = false

  if (script.value?.Organization?.ID) {
    orgMembers.value = await store.fetchOrgMembers(script.value.Organization.ID)
  }
}

onMounted(() => loadScript(route.params.hash))
watch(() => route.params.hash, async hash => {
  if (!hash) return
  if (store.activeMode === 'edit') await scriptEditorRef.value?.flush()
  loadScript(hash)
})

// Flush unsaved edits before navigating away entirely (e.g. back to the
// script list) — same reasoning as switchMode() above.
onBeforeRouteLeave(async () => {
  if (store.activeMode === 'edit') await scriptEditorRef.value?.flush()
})
</script>
