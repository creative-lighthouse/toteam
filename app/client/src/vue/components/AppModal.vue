<template>
  <Teleport to="body">
    <dialog ref="dialogEl" class="event-modal app-modal" @cancel.prevent="$emit('close')">
      <div class="dialog-content app-modal_content" @click.stop>

        <div class="app-modal_header">
          <slot name="header">
            <h2 class="hl2">{{ title }}</h2>
          </slot>
          <AppIconButton variant="ghost" aria-label="Schließen" @click="$emit('close')">✕</AppIconButton>
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

defineProps({
  title: { type: String, default: '' },
})

defineEmits(['close'])

const dialogEl = ref(null)

function open() {
  dialogEl.value?.showModal()
}

function close() {
  dialogEl.value?.close()
}

defineExpose({ open, close })
</script>
