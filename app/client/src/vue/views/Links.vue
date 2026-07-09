<template>
  <div class="section section--LinksPage">
    <div class="section_content">

      <!-- Type filter tabs -->
      <div v-if="usedTypes.length > 0" class="section_filter">
        <button
          class="button"
          :class="{ active: selectedType === null }"
          @click="selectedType = null"
        >
          Alle
        </button>
        <button
          v-for="type in usedTypes"
          :key="type.ID"
          class="button"
          :class="{ active: selectedType?.ID === type.ID }"
          @click="selectedType = type"
        >
          {{ type.Title }}
        </button>
      </div>

      <!-- Search + Add button row -->
      <div class="links-toolbar">
        <div class="links-search">
          <input
            v-model="search"
            type="search"
            placeholder="Links durchsuchen…"
          />
        </div>
        <AppButton
          v-if="adminOrgIDs.length > 0"
          size="small"
          variant="primary"
          @click="openAddModal"
        >
          + Link hinzufügen
        </AppButton>
      </div>

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
                <span v-if="link.TypeTitle" class="link-item__type-badge">
                  {{ link.TypeTitle }}
                </span>
              </div>
            </div>
          </a>

          <!-- Edit / Delete actions (only for admins/mods) -->
          <div v-if="adminOrgIDs.includes(link.OrgID)" class="link-item__actions">
            <AppIconButton
              variant="primary"
              size="small"
              aria-label="Bearbeiten"
              @click="openEditModal(link)"
            >
              <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
            </AppIconButton>
            <AppIconButton
              variant="danger"
              size="small"
              aria-label="Löschen"
              @click="deleteLink(link)"
            >
              <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/></svg>
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
    <dialog ref="modalEl" class="link-modal">
      <div class="link-modal__content">
        <div class="link-modal__header">
          <h3 class="link-modal__title">
            {{ editingLink ? 'Link bearbeiten' : 'Link hinzufügen' }}
          </h3>
          <AppIconButton variant="ghost" aria-label="Schließen" @click="closeModal">✕</AppIconButton>
        </div>

        <form class="link-modal__body" @submit.prevent="submitModal">

          <!-- Title -->
          <div class="link-modal__field">
            <label for="link-title">Titel *</label>
            <input
              id="link-title"
              v-model="form.title"
              type="text"
              required
              placeholder="z.B. Vereinssatzung"
            />
          </div>

          <!-- Org selector (only when adding) -->
          <div v-if="!editingLink" class="link-modal__field">
            <label for="link-org">Organisation *</label>
            <select id="link-org" v-model="form.orgId" required>
              <option value="" disabled>Bitte wählen…</option>
              <option v-for="org in adminOrgs" :key="org.ID" :value="org.ID">
                {{ org.Title }}
              </option>
            </select>
          </div>

          <!-- Type selector -->
          <div class="link-modal__field">
            <label for="link-type">Typ (optional)</label>
            <select id="link-type" v-model="form.typeId">
              <option :value="null">— Kein Typ —</option>
              <option v-for="type in types" :key="type.ID" :value="type.ID">
                {{ type.Title }}
              </option>
            </select>
          </div>

          <!-- Kind toggle (only when adding) -->
          <div v-if="!editingLink" class="link-modal__kind-toggle">
            <button
              type="button"
              class="button"
              :class="{ active: form.kind === 'external' }"
              @click="form.kind = 'external'"
            >
              Externe URL
            </button>
            <button
              type="button"
              class="button"
              :class="{ active: form.kind === 'file' }"
              @click="form.kind = 'file'"
            >
              Datei hochladen
            </button>
          </div>

          <!-- URL input (when external or editing an external link) -->
          <div
            v-if="form.kind === 'external'"
            class="link-modal__field"
          >
            <label for="link-url">URL *</label>
            <input
              id="link-url"
              v-model="form.url"
              type="url"
              placeholder="https://…"
              :required="form.kind === 'external' && !editingLink"
            />
          </div>

          <!-- Open in new tab (for external links) -->
          <div v-if="form.kind === 'external'" class="link-modal__checkbox-row">
            <input
              id="link-openinnew"
              v-model="form.openInNew"
              type="checkbox"
            />
            <label for="link-openinnew">In neuem Tab öffnen</label>
          </div>

          <!-- File input (when adding a file) -->
          <div v-if="!editingLink && form.kind === 'file'" class="link-modal__field">
            <label for="link-file">Datei *</label>
            <input
              id="link-file"
              ref="fileInputEl"
              type="file"
              @change="onFileChange"
            />
          </div>

          <!-- File links: note about editing -->
          <div v-if="editingLink && editingLink.LinkKind === 'file'" class="section_infobox" style="margin: 0; font-size: 13px;">
            <p style="margin: 0;">Datei-Links können nicht geändert werden. Bitte lösche diesen Link und erstelle einen neuen.</p>
          </div>

          <!-- Error -->
          <p v-if="modalError" class="link-modal__error">{{ modalError }}</p>

        </form>

        <div class="link-modal__footer">
          <AppButton variant="secondary" @click="closeModal">
            Abbrechen
          </AppButton>
          <AppButton
            variant="primary"
            :disabled="submitting"
            @click="submitModal"
          >
            {{ submitting ? 'Speichern…' : 'Speichern' }}
          </AppButton>
        </div>
      </div>
    </dialog>

  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { usePageHeaderStore } from '@stores/pageHeader'
import { apiGet, apiPost, apiPut, apiDelete, apiPostForm, clearCacheForEndpoint } from '@utils/api'
import AppButton from '@components/AppButton.vue'
import AppIconButton from '@components/AppIconButton.vue'

usePageHeaderStore().setHeader('Links', 'Wichtige Links und Ressourcen für dein Team.')

// ── State ──────────────────────────────────────────────
const links = ref([])
const types = ref([])
const adminOrgIDs = ref([])
const adminOrgs = ref([])
const loading = ref(false)
const loadError = ref(null)

const search = ref('')
const selectedType = ref(null)

const showModal = ref(false)
const editingLink = ref(null)
const submitting = ref(false)
const modalError = ref(null)

const modalEl = ref(null)
const fileInputEl = ref(null)

const form = ref({
  title: '',
  orgId: '',
  typeId: null,
  kind: 'external',
  url: '',
  openInNew: false,
  file: null,
})

// ── Computed ───────────────────────────────────────────
const usedTypes = computed(() => {
  const usedTypeIDs = new Set(links.value.map((l) => l.TypeID).filter(Boolean))
  return types.value.filter((t) => usedTypeIDs.has(t.ID))
})

const filteredLinks = computed(() => {
  let result = links.value

  if (selectedType.value) {
    result = result.filter((l) => l.TypeID === selectedType.value.ID)
  }

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
    types.value = data.types || []
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
    orgId: adminOrgs.value.length === 1 ? adminOrgs.value[0].ID : '',
    typeId: null,
    kind: 'external',
    url: '',
    openInNew: false,
    file: null,
  }
  modalError.value = null
  modalEl.value?.showModal()
}

function openEditModal(link) {
  editingLink.value = link
  form.value = {
    title: link.Title,
    orgId: link.OrgID,
    typeId: link.TypeID || null,
    kind: link.LinkKind || 'external',
    url: link.URL || '',
    openInNew: link.OpenInNew || false,
    file: null,
  }
  modalError.value = null
  modalEl.value?.showModal()
}

function closeModal() {
  modalEl.value?.close()
  editingLink.value = null
  modalError.value = null
}

function onFileChange(e) {
  form.value.file = e.target.files[0] || null
}

async function submitModal() {
  modalError.value = null

  if (!form.value.title.trim()) {
    modalError.value = 'Bitte gib einen Titel ein.'
    return
  }

  submitting.value = true
  try {
    if (editingLink.value) {
      // Edit existing link
      const payload = {
        title: form.value.title,
        typeId: form.value.typeId,
      }
      if (editingLink.value.LinkKind !== 'file') {
        payload.url = form.value.url
        payload.openInNew = form.value.openInNew
      }
      await apiPut(`/links/update/${editingLink.value.ID}`, payload)
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
        if (form.value.typeId) fd.append('typeId', form.value.typeId)
        fd.append('openInNew', form.value.openInNew ? '1' : '0')
        fd.append('file', form.value.file)
        await apiPostForm('/links', fd)
      } else {
        if (!form.value.url.trim()) {
          modalError.value = 'Bitte gib eine URL ein.'
          return
        }
        await apiPost('/links', {
          title: form.value.title,
          orgId: form.value.orgId,
          typeId: form.value.typeId,
          url: form.value.url,
          openInNew: form.value.openInNew,
        })
      }
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
