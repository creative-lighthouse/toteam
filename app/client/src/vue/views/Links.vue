<template>
  <div class="section section--LinksPage">
    <div class="section_content">

      <!-- Search + Add button row -->
      <AppSearchBar v-model="search" placeholder="Links durchsuchen…">
        <template v-if="adminOrgIDs.length > 0" #actions>
          <AppButton variant="primary" @click="openAddModal">+ Link hinzufügen</AppButton>
        </template>
      </AppSearchBar>

      <!-- Loading -->
      <div v-if="loading" class="section_infobox">
        <p>Lade Links…</p>
      </div>

      <!-- Error -->
      <div v-else-if="loadError" class="section_infobox error">
        <p>Fehler beim Laden: {{ loadError }}</p>
        <AppButton variant="primary" @click="fetchLinks">Erneut versuchen</AppButton>
      </div>

      <!-- Links list -->
      <div v-else class="links-list">
        <div
          v-for="link in filteredLinks"
          :key="link.ID"
          class="link-item"
        >
          <!-- Clickable area -->
          <a
            class="link-item__anchor"
            :href="link.URL || '#'"
            :target="link.OpenInNew ? '_blank' : '_self'"
            :download="link.LinkKind === 'file' ? (link.FileName || true) : undefined"
            rel="noopener noreferrer"
            @click.prevent="handleLinkClick(link)"
          >
            <span
              class="link-item__icon"
              :class="link.LinkKind === 'file' ? 'link-item__icon--file' : 'link-item__icon--external'"
            >
              <svg v-if="link.LinkKind === 'file'" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
              <svg v-else xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg>
            </span>
            <div class="link-item__body">
              <span class="link-item__title">{{ link.Title }}</span>
              <div class="link-item__meta">
                <router-link
                  v-if="link.OrgUsername"
                  :to="`/organizations/${link.OrgUsername}`"
                  class="link-item__org-badge"
                  @click.stop
                >
                  {{ link.OrgTitle }}
                </router-link>
                <span v-else-if="link.OrgTitle" class="link-item__org-badge">
                  {{ link.OrgTitle }}
                </span>
              </div>
            </div>
          </a>

          <!-- Edit / Delete actions (only for admins/mods) -->
          <div v-if="adminOrgIDs.includes(link.OrgID)" class="link-item__actions">
            <AppIconButton
              variant="primary"
              aria-label="Bearbeiten"
              @click="openEditModal(link)"
            >
              <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
            </AppIconButton>
            <AppIconButton
              variant="danger"
              aria-label="Löschen"
              @click="deleteLink(link)"
            >
              <span class="icon-mask" :style="trashIconStyle" />
            </AppIconButton>
          </div>
        </div>

        <!-- Empty state -->
        <div v-if="filteredLinks.length === 0" class="section_infobox">
          <p>Keine Links gefunden.</p>
        </div>
      </div>

    </div>

    <!-- Add / Edit Modal -->
    <AppModal
      ref="modal"
      class="link-modal"
      :title="editingLink ? 'Link bearbeiten' : 'Link hinzufügen'"
      @close="closeModal"
    >
      <form id="link-form" class="modalform" @submit.prevent="submitModal">

        <!-- Org selector (only when adding) -->
        <div v-if="!editingLink" class="field">
          <label>Organisation *</label>
          <OrganizationPicker v-model="form.orgId" :orgs="adminOrgs" />
        </div>

        <label class="field">
          Titel *
          <input v-model="form.title" type="text" required placeholder="z.B. Vereinssatzung" />
        </label>

        <!-- Kind toggle (only when adding) -->
        <AppSegmentedToggle
          v-if="!editingLink"
          v-model="form.kind"
          label="Art"
          :options="[{ value: 'external', label: 'Externe URL' }, { value: 'file', label: 'Datei hochladen' }]"
        />

        <!-- URL input (when external or editing an external link) -->
        <label v-if="form.kind === 'external'" class="field">
          URL *
          <input
            v-model="form.url"
            type="url"
            placeholder="https://…"
            :required="form.kind === 'external' && !editingLink"
          />
        </label>

        <!-- Open in new tab (for external links) -->
        <AppToggle v-if="form.kind === 'external'" v-model="form.openInNew" label="In neuem Tab öffnen" />

        <!-- File input (when adding a file) -->
        <AppFileUpload
          v-if="!editingLink && form.kind === 'file'"
          v-model="form.file"
          label="Datei"
          required
          :max-size="LINK_FILE_MAX_SIZE"
          hint="Max. 10 MB"
        />

        <!-- File links: note about editing -->
        <p v-if="editingLink && editingLink.LinkKind === 'file'" class="link-modal_file-hint">
          Datei-Links können nicht geändert werden. Bitte lösche diesen Link und erstelle einen neuen.
        </p>

        <!-- Error -->
        <div v-if="modalError" class="app-modal_error">{{ modalError }}</div>

      </form>

      <template #actions>
        <AppButton variant="secondary" @click="closeModal">Abbrechen</AppButton>
        <AppButton type="submit" form="link-form" variant="primary" :disabled="submitting">
          {{ submitting ? 'Speichern…' : 'Speichern' }}
        </AppButton>
      </template>
    </AppModal>

  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { usePageHeaderStore } from '@stores/pageHeader'
import { apiGet, apiPost, apiPut, apiDelete, apiPostForm, clearCacheForEndpoint } from '@utils/api'
import AppButton from '@components/ui/AppButton.vue'
import AppSearchBar from '@components/ui/AppSearchBar.vue'
import AppIconButton from '@components/ui/AppIconButton.vue'
import AppModal from '@components/ui/AppModal.vue'
import AppToggle from '@components/ui/AppToggle.vue'
import AppSegmentedToggle from '@components/ui/AppSegmentedToggle.vue'
import AppFileUpload from '@components/ui/AppFileUpload.vue'
import OrganizationPicker from '@components/ui/OrganizationPicker.vue'
import actionTrash from '../../../icons/actions/action_trash.svg'

const trashIconStyle = { maskImage: `url("${actionTrash}")`, WebkitMaskImage: `url("${actionTrash}")` }

usePageHeaderStore().setHeader('Links', 'Wichtige Links und Ressourcen für dein Team.')

// ── State ──────────────────────────────────────────────
const links = ref([])
const adminOrgIDs = ref([])
const adminOrgs = ref([])
const loading = ref(false)
const loadError = ref(null)

const search = ref('')

const editingLink = ref(null)
const submitting = ref(false)
const modalError = ref(null)

const modal = ref(null)

// Deckt sich mit dem Upload-Limit in LinksApiController
const LINK_FILE_MAX_SIZE = 10 * 1024 * 1024

const form = ref({
  title: '',
  orgId: null,
  kind: 'external',
  url: '',
  openInNew: false,
  file: null,
})

// ── Computed ───────────────────────────────────────────
const filteredLinks = computed(() => {
  let result = links.value

  if (search.value.trim()) {
    const q = search.value.trim().toLowerCase()
    result = result.filter((l) => l.Title && l.Title.toLowerCase().includes(q))
  }

  return result
})

// ── API ────────────────────────────────────────────────
async function fetchLinks() {
  loading.value = true
  loadError.value = null
  try {
    const data = await apiGet('/links', false)
    links.value = data.links || []
    adminOrgIDs.value = data.adminOrgIDs || []
    adminOrgs.value = data.adminOrgs || []
  } catch (e) {
    loadError.value = e.message
  } finally {
    loading.value = false
  }
}

// ── Link click handler ─────────────────────────────────
function handleLinkClick(link) {
  if (!link.URL) return
  if (link.LinkKind === 'file') {
    const a = document.createElement('a')
    a.href = link.URL
    a.download = link.FileName || ''
    a.target = '_blank'
    a.rel = 'noopener noreferrer'
    document.body.appendChild(a)
    a.click()
    document.body.removeChild(a)
  } else {
    window.open(link.URL, link.OpenInNew ? '_blank' : '_self', 'noopener,noreferrer')
  }
}

// ── Modal ──────────────────────────────────────────────
function openAddModal() {
  editingLink.value = null
  form.value = {
    title: '',
    orgId: adminOrgs.value.length === 1 ? adminOrgs.value[0].ID : null,
    kind: 'external',
    url: '',
    openInNew: false,
    file: null,
  }
  modalError.value = null
  modal.value?.open()
}

function openEditModal(link) {
  editingLink.value = link
  form.value = {
    title: link.Title,
    orgId: link.OrgID,
    kind: link.LinkKind || 'external',
    url: link.URL || '',
    openInNew: link.OpenInNew || false,
    file: null,
  }
  modalError.value = null
  modal.value?.open()
}

function closeModal() {
  modal.value?.close()
  editingLink.value = null
  modalError.value = null
}

async function submitModal() {
  modalError.value = null

  if (!form.value.title.trim()) {
    modalError.value = 'Bitte gib einen Titel ein.'
    return
  }

  submitting.value = true
  let response = null
  try {
    if (editingLink.value) {
      // Edit existing link
      const payload = {
        title: form.value.title,
      }
      if (editingLink.value.LinkKind !== 'file') {
        payload.url = form.value.url
        payload.openInNew = form.value.openInNew
      }
      response = await apiPut(`/links/update/${editingLink.value.ID}`, payload)
    } else {
      // Create new link
      if (!form.value.orgId) {
        modalError.value = 'Bitte wähle eine Organisation.'
        return
      }

      if (form.value.kind === 'file') {
        if (!form.value.file) {
          modalError.value = 'Bitte wähle eine Datei aus.'
          return
        }
        const fd = new FormData()
        fd.append('title', form.value.title)
        fd.append('orgId', form.value.orgId)
        fd.append('openInNew', form.value.openInNew ? '1' : '0')
        fd.append('file', form.value.file)
        response = await apiPostForm('/links', fd)
      } else {
        if (!form.value.url.trim()) {
          modalError.value = 'Bitte gib eine URL ein.'
          return
        }
        response = await apiPost('/links', {
          title: form.value.title,
          orgId: form.value.orgId,
          url: form.value.url,
          openInNew: form.value.openInNew,
        })
      }
    }

    // Die API-Helfer werfen bei 4xx nicht, sondern liefern { success: false, error }
    if (!response?.success) {
      modalError.value = response?.error || 'Der Link konnte nicht gespeichert werden.'
      return
    }

    // Success — refresh
    await clearCacheForEndpoint('/links')
    await fetchLinks()
    closeModal()
  } catch (e) {
    modalError.value = e.message || 'Ein Fehler ist aufgetreten.'
  } finally {
    submitting.value = false
  }
}

async function deleteLink(link) {
  if (!confirm(`Link „${link.Title}" wirklich löschen?`)) return
  try {
    await apiDelete(`/links/remove/${link.ID}`)
    await clearCacheForEndpoint('/links')
    await fetchLinks()
  } catch (e) {
    alert('Fehler beim Löschen: ' + e.message)
  }
}

// ── Lifecycle ──────────────────────────────────────────
onMounted(fetchLinks)
</script>
