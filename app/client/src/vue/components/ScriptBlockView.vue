<template>
  <NodeViewWrapper class="script-block" :class="{ 'script-block--nested': !isTopLevel }">
    <span v-if="isTopLevel && node.type.name !== 'heading'" class="script-block_gutter" contenteditable="false">
      <ScriptRoleDropdown
        :roles="scriptRoles"
        :model-value="node.attrs.roleIds || []"
        :direction="!!node.attrs.isDirection"
        @update:model-value="onRolesChange"
        @update:direction="onDirectionChange"
      />
      <span class="script-block_line">{{ lineNumber }}</span>
    </span>
    <NodeViewContent
      :as="contentTag"
      class="script-block_content"
      :class="{ 'script-block_content--direction': node.attrs.isDirection }"
    />
  </NodeViewWrapper>
</template>

<script setup>
import { computed, inject, ref } from 'vue'
import { NodeViewWrapper, NodeViewContent, nodeViewProps } from '@tiptap/vue-3'
import ScriptRoleDropdown from '@components/ScriptRoleDropdown.vue'

const props = defineProps(nodeViewProps)

// Provided by ScriptEditor.vue, which hosts this NodeView within its own Vue
// app context — see @tiptap/vue-3's EditorContent, which forwards the host
// component's provides into every mounted node view.
const scriptRoles = inject('scriptRoles', ref([]))
const docVersion = inject('scriptDocVersion', ref(0))

const BLOCK_TYPES = ['paragraph', 'heading', 'bulletList', 'orderedList']

const contentTag = computed(() => {
  if (props.node.type.name === 'heading') return `h${props.node.attrs.level}`
  if (props.node.type.name === 'bulletList') return 'ul'
  if (props.node.type.name === 'orderedList') return 'ol'
  return 'p'
})

// The "paragraph" node type is also used for paragraphs nested inside list
// items (list schemas typically define listItem content as "paragraph
// block*") — those must render as plain paragraphs, without a gutter number
// or role dropdown of their own, since they're part of their parent list's
// single "Absatz".
const isTopLevel = computed(() => {
  docVersion.value // eslint-disable-line no-unused-expressions
  return props.editor.state.doc.resolve(props.getPos()).depth === 0
})

// A "Regieanweisung" (stage direction) or a heading is skipped in the running
// line count entirely — both for itself (returns null below) and for every
// other block, which doesn't count it while numbering.
function countsTowardsNumbering(node) {
  return BLOCK_TYPES.includes(node.type.name) && !node.attrs.isDirection && node.type.name !== 'heading'
}

const lineNumber = computed(() => {
  docVersion.value // eslint-disable-line no-unused-expressions
  if (!isTopLevel.value || !countsTowardsNumbering(props.node)) return null
  let count = 0
  let result = 0
  props.editor.state.doc.forEach((node, pos) => {
    if (!countsTowardsNumbering(node)) return
    count++
    if (pos === props.getPos()) result = count
  })
  return result
})

function onRolesChange(roleIds) {
  props.updateAttributes({ roleIds })
}

function onDirectionChange(isDirection) {
  props.updateAttributes(isDirection ? { isDirection, roleIds: [] } : { isDirection })
}
</script>
