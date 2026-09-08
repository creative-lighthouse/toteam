<template>
  <div
    :id="`paragraph-${paragraph.ID}`"
    class="script-paragraph"
    :class="{
      'script-paragraph--dimmed': dimmed,
      'script-paragraph--highlighted': highlighted,
    }"
  >
    <span v-if="!paragraph.IsDirection && !isHeading" class="script-paragraph_gutter">
      <button
        v-if="assignedRoles.length"
        type="button"
        class="script-paragraph_role-badge"
        :title="`${roleTitles.join(', ')} — Klicken für Details`"
        @click="$emit('open-roles', assignedRoles)"
      >{{ roleTitles.join(', ') }}</button>
      <span v-else class="script-paragraph_role-badge script-paragraph_role-badge--empty"></span>
      <span class="script-paragraph_line">{{ paragraph.LineNumber }}</span>
    </span>

    <div class="script-paragraph_body">
      <div
        class="script-paragraph_content"
        :class="{
          'script-paragraph_content--direction': paragraph.IsDirection,
          'script-paragraph_content--blurred': blurred && !revealed,
        }"
        :title="blurred ? (revealed ? 'Klicken zum Verbergen' : 'Klicken zum Aufdecken') : undefined"
        @click="blurred && (revealed = !revealed)"
        v-html="paragraph.Content"
      />
    </div>
  </div>
</template>

<script setup>
import { ref, computed, watch } from 'vue'

const props = defineProps({
  paragraph: { type: Object, required: true },
  roles: { type: Array, default: () => [] },
  dimmed: { type: Boolean, default: false },
  highlighted: { type: Boolean, default: false },
  blurred: { type: Boolean, default: false },
})
defineEmits(['open-roles'])

const revealed = ref(false)
watch(() => props.blurred, () => { revealed.value = false })

const assignedRoles = computed(() =>
  props.roles.filter(r => props.paragraph.RoleIDs?.includes(r.ID))
)
const roleTitles = computed(() => assignedRoles.value.map(r => r.Title))

// A heading can't carry a role or a line number, so it gets no gutter at all
// (unlike a Regieanweisung, which still needs one to be toggled off again).
const isHeading = computed(() => /^\s*<h[234]\b/i.test(props.paragraph.Content || ''))
</script>
