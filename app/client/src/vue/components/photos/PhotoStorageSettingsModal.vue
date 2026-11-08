<template>
  <AppModal ref="modal" class="photo-storage-settings-modal" title="Foto-Speicher (Bunny)" @close="close">
    <form id="photo-storage-form" class="modalform" autocomplete="off" @submit.prevent="save">

      <div v-if="orgs.length > 1" class="field">
        <label>Organisation *</label>
        <OrganizationPicker v-model="orgId" :orgs="orgs" />
      </div>

      <div v-if="loading" class="photo-storage-settings-modal_loading">Lädt…</div>

      <template v-else-if="orgId && loaded">
        <AppCollapse title="So richtest du Bunny ein" class="photo-storage-settings-modal_help" :default-open="!hasAccessKey">
          <ol class="photo-storage-settings-modal_steps">
            <li>Bei <a href="https://bunny.net" target="_blank" rel="noopener noreferrer">bunny.net</a> ein Konto für die Organisation anlegen – die Abrechnung läuft direkt über euch.</li>
            <li>Unter <em>Storage</em> eine <strong>Storage Zone</strong> anlegen und die Region merken. Unter <em>FTP &amp; API Access</em> steht das <strong>Passwort</strong>.</li>
            <li>In der Storage Zone <em>Connected Pull Zones</em> → eine <strong>Pull Zone</strong> anlegen. Deren Adresse (z.&nbsp;B. <code>https://mein-verein.b-cdn.net</code>) hier eintragen.</li>
            <li>Empfohlen: In der Pull Zone unter <em>Security</em> → <em>Token Authentication</em> einschalten und den Schlüssel hier eintragen. Dann sind die Fotos nur über Links aus ToTeam abrufbar, die nach einigen Stunden ablaufen.</li>
          </ol>
        </AppCollapse>

        <p v-if="usage.Count" class="photo-storage-settings-modal_usage">
          Belegt: <strong>{{ usage.Count }} {{ usage.Count === 1 ? 'Foto' : 'Fotos' }}</strong>, {{ formatBytes(usage.Bytes) }}
        </p>

        <label class="field">
          Storage Zone *
          <input v-model="form.StorageZone" type="text" required placeholder="z.B. mein-verein-fotos" data-1p-ignore>
        </label>

        <label class="field">
          Region der Storage Zone *
          <select v-model="form.StorageRegion" class="input" required>
            <option v-for="(label, host) in regions" :key="host" :value="host">{{ label }}</option>
          </select>
        </label>

        <label class="field">
          Passwort der Storage Zone {{ hasAccessKey ? '' : '*' }}
          <input
            v-model="form.AccessKey"
            type="password"
            autocomplete="new-password"
            data-1p-ignore
            :placeholder="hasAccessKey ? 'Gespeichert – leer lassen zum Beibehalten' : 'FTP & API Access → Password'"
            :required="!hasAccessKey"
          >
        </label>

        <label class="field">
          Adresse der Pull Zone *
          <input v-model="form.PullZoneURL" type="url" required placeholder="https://mein-verein.b-cdn.net">
        </label>

        <label class="field">
          Token-Authentication-Schlüssel
          <input
            v-model="form.TokenKey"
            type="password"
            autocomplete="new-password"
            data-1p-ignore
            :disabled="form.RemoveTokenKey"
            :placeholder="hasTokenKey ? 'Gespeichert – leer lassen zum Beibehalten' : 'Optional, aber empfohlen'"
          >
        </label>
        <AppToggle v-if="hasTokenKey" v-model="form.RemoveTokenKey" label="Token-Schutz entfernen" />
        <p v-if="!hasTokenKey && !form.TokenKey" class="photo-storage-settings-modal_warning">
          Ohne Token-Schutz kann jede:r ein Foto öffnen, die den Link kennt. Die Links sind zufällig und nicht erratbar, laufen aber nie ab.
        </p>

        <p v-if="usage.Count && zoneChanged" class="photo-storage-settings-modal_warning">
          Achtung: Die {{ usage.Count }} vorhandenen Fotos liegen in der bisherigen Storage Zone. Werden sie nicht dorthin mitgenommen, sind sie danach nicht mehr erreichbar.
        </p>
      </template>

      <p v-if="message" class="photo-storage-settings-modal_ok" role="status">{{ message }}</p>
      <div v-if="error" class="field app-modal_error">{{ error }}</div>
    </form>

    <template #actions>
      <AppButton variant="secondary" :disabled="busy" @click="close">Abbrechen</AppButton>
      <AppButton variant="secondary" :disabled="busy || !loaded" @click="test">Verbindung testen</AppButton>
      <AppButton type="submit" form="photo-storage-form" variant="primary" :disabled="busy || !loaded">
        {{ busy ? 'Prüfe…' : 'Speichern' }}
      </AppButton>
    </template>
  </AppModal>
</template>

<script setup>
import { ref, reactive, computed, watch } from 'vue'
import { usePhotosStore } from '@stores/photos'
import { formatBytes } from '@utils/photoUpload'
import AppModal from '@components/ui/AppModal.vue'
import AppButton from '@components/ui/AppButton.vue'
import AppToggle from '@components/ui/AppToggle.vue'
import AppCollapse from '@components/ui/AppCollapse.vue'
import OrganizationPicker from '@components/ui/OrganizationPicker.vue'

const emit = defineEmits(['saved'])
const store = usePhotosStore()

const modal = ref(null)
const orgId = ref(null)
const loading = ref(false)
const loaded = ref(false)
const busy = ref(false)
const error = ref(null)
const message = ref(null)
const regions = ref({})
const usage = ref({ Count: 0, Bytes: 0 })
const hasAccessKey = ref(false)
const hasTokenKey = ref(false)
const savedZone = ref('')

const form = reactive({
  StorageZone: '',
  StorageRegion: 'storage.bunnycdn.com',
  AccessKey: '',
  PullZoneURL: '',
  TokenKey: '',
  RemoveTokenKey: false,
})

const orgs = computed(() => store.organizations.filter(o => o.CanManageStorage))
const zoneChanged = computed(() => !!savedZone.value && form.StorageZone.trim() !== savedZone.value)

function applySettings(settings) {
  Object.assign(form, {
    StorageZone: settings.StorageZone,
    StorageRegion: settings.StorageRegion,
    PullZoneURL: settings.PullZoneURL,
    AccessKey: '',
    TokenKey: '',
    RemoveTokenKey: false,
  })
  hasAccessKey.value = settings.HasAccessKey
  hasTokenKey.value = settings.HasTokenKey
  savedZone.value = settings.StorageZone
}

async function load(id) {
  loaded.value = false
  error.value = null
  message.value = null
  if (!id) return
  loading.value = true
  try {
    const data = await store.fetchSettings(id)
    if (orgId.value !== id) return
    regions.value = data.regions
    usage.value = data.usage
    applySettings(data.settings)
    loaded.value = true
  } catch (err) {
    error.value = err.message
  } finally {
    loading.value = false
  }
}

watch(orgId, load)

function open(id = null) {
  error.value = null
  message.value = null
  const next = id ?? (orgs.value.length === 1 ? orgs.value[0].ID : null)
  // Gleiche Organisation wie beim letzten Mal: trotzdem frisch laden (Schlüsselfelder leeren)
  if (next === orgId.value) load(next)
  else orgId.value = next
  modal.value?.open()
}

function close() {
  modal.value?.close()
}

function payload() {
  return { ...form }
}

async function test() {
  busy.value = true
  error.value = null
  message.value = null
  try {
    message.value = await store.testSettings(orgId.value, payload())
  } catch (err) {
    error.value = err.message
  } finally {
    busy.value = false
  }
}

async function save() {
  busy.value = true
  error.value = null
  message.value = null
  try {
    const data = await store.saveSettings(orgId.value, payload())
    applySettings(data.settings)
    usage.value = data.usage
    emit('saved')
    close()
  } catch (err) {
    error.value = err.message
  } finally {
    busy.value = false
  }
}

defineExpose({ open, close })
</script>
