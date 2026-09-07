<template>
  <div class="section section--SkriptPage">
    <div class="section_content">

      <div class="skript-toolbar">
        <AppButton variant="primary" @click="createModal?.open()">
          + Neues Skript
        </AppButton>
      </div>

      <div v-if="store.loading" class="section_infobox">
        <p>Lade Skripte…</p>
      </div>

      <div v-else-if="store.error" class="section_infobox error">
        <p>Fehler: {{ store.error }}</p>
        <AppButton variant="primary" @click="store.fetchScripts(true)">Erneut versuchen</AppButton>
      </div>

      <div v-else-if="store.scripts.length === 0" class="section_infobox">
        <p>Noch keine Skripte vorhanden.</p>
      </div>

      <div v-else class="skript-list">
        <button
          v-for="script in store.scripts"
          :key="script.ID"
          type="button"
          class="skript-list-card"
          @click="openScript(script)"
        >
          <div class="skript-list-card_main">
            <h3 class="hl3 skript-list-card_title">{{ script.Title }}</h3>
            <span v-if="script.Organization" class="skript-list-card_org">{{ script.Organization.Title }}</span>
          </div>
        </button>
      </div>

    </div>

    <ScriptCreateModal ref="createModal" @created="onScriptCreated" />
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useSkriptStore } from '@stores/skript'
import { usePageHeaderStore } from '@stores/pageHeader'
import AppButton from '@components/AppButton.vue'
import ScriptCreateModal from '@components/ScriptCreateModal.vue'

const router = useRouter()
const store = useSkriptStore()
usePageHeaderStore().setHeader('Skript', 'Skripte deiner Organisationen.')

const createModal = ref(null)

function openScript(script) {
  router.push({ name: 'SkriptDetail', params: { hash: script.Hash } })
}

function onScriptCreated(script) {
  router.push({ name: 'SkriptDetail', params: { hash: script.Hash } })
}

onMounted(() => store.fetchScripts())
</script>
