<template>
  <div
    :id="`paragraph-${paragraph.ID}`"
    class="script-paragraph"
    :class="{
      'script-paragraph--dimmed': dimmed,
      'script-paragraph--highlighted': highlighted,
    }"
  >
    <span v-if="!paragraph.IsDirection" class="script-paragraph_gutter">
      <span
        class="script-paragraph_role-badge"
        :class="{ 'script-paragraph_role-badge--empty': roleTitles.length === 0 }"
        :title="roleTitles.join(', ')"
      >{{ roleTitles.join(', ') }}</span>
      <span class="script-paragraph_line">{{ paragraph.LineNumber }}</span>
    </span>

    <div class="script-paragraph_body">
      <div
        v-if="!(blurred && !revealed)"
        class="script-paragraph_content"
        :class="{ 'script-paragraph_content--direction': paragraph.IsDirection }"
        v-html="paragraph.Content"
      />
      <button
        v-else
        type="button"
        class="script-paragraph_reveal"
        @click="revealed = true"
      >
        Aufdecken
      </button>
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

const revealed = ref(false)
watch(() => props.blurred, () => { revealed.value = false })

const roleTitles = computed(() =>
  props.roles.filter(r => props.paragraph.RoleIDs?.includes(r.ID)).map(r => r.Title)
)
</script>
