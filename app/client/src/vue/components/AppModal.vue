<template>
  <Teleport to="body">
    <dialog ref="dialogEl" v-bind="$attrs" class="event-modal app-modal" @cancel.prevent="$emit('close')">
      <div class="dialog-content app-modal_content" @click.stop>

        <div class="app-modal_header">
          <slot name="header">
            <h2 class="hl2">{{ title }}</h2>
          </slot>
          <AppIconButton variant="ghost" aria-label="Schließen" @click="$emit('close')">✕</AppIconButton>
        </div>

        <div v-if="tabs.length > 1" class="app-modal_tabs">
          <button
            v-for="t in tabs"
            :key="t.id"
            type="button"
            class="app-modal_tab"
            :class="{ 'app-modal_tab--active': t.id === tab }"
            @click="$emit('update:tab', t.id)"
          >{{ t.label }}</button>
        </div>

        <div class="app-modal_body">
          <slot />
        </div>

        <div v-if="$slots.actions" class="app-modal_actions">
          <slot name="actions" />
        </div>

      </div>
    </dialog>
  </Teleport>
</template>

<script setup>
import { ref } from 'vue'
import AppIconButton from '@components/AppIconButton.vue'

// Root ist ein <Teleport>, kein normales DOM-Element — Vues automatisches
// Attribute-/Class-Fallthrough greift dabei nicht (landet ansonsten ins
// Leere), deshalb wird $attrs (v.a. die vom Aufrufer übergebene Modifier-
// Klasse wie "money-account-modal") hier manuell auf das <dialog> gebunden.
defineOptions({ inheritAttrs: false })

defineProps({
  title: { type: String, default: '' },
  // Optionale Tabs { id, label }[]. Die Tableiste wird nur angezeigt, wenn
  // mehr als ein Tab übergeben wird — bei 0 oder 1 Tab(s) bleibt sie
  // ausgeblendet (z.B. wenn beim Bearbeiten nur noch ein Tab relevant ist).
  tabs: { type: Array, default: () => [] },
  tab: { type: [String, Number], default: null },
})

defineEmits(['close', 'update:tab'])

const dialogEl = ref(null)

function open() {
  dialogEl.value?.showModal()
}

function close() {
  dialogEl.value?.close()
}

defineExpose({ open, close })
</script>
