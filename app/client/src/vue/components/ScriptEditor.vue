<template>
  <div class="script-editor">
    <div v-if="editor" class="script-editor_toolbar">
      <button
        type="button"
        class="script-editor_toolbar-btn"
        :class="{ 'script-editor_toolbar-btn--active': editor.isActive('bold') }"
        title="Fett"
        @click="editor.chain().focus().toggleBold().run()"
      ><strong>F</strong></button>
      <button
        type="button"
        class="script-editor_toolbar-btn"
        :class="{ 'script-editor_toolbar-btn--active': editor.isActive('italic') }"
        title="Kursiv"
        @click="editor.chain().focus().toggleItalic().run()"
      ><em>K</em></button>
      <button
        type="button"
        class="script-editor_toolbar-btn"
        :class="{ 'script-editor_toolbar-btn--active': editor.isActive('underline') }"
        title="Unterstrichen"
        @click="editor.chain().focus().toggleUnderline().run()"
      ><u>U</u></button>

      <span class="script-editor_toolbar-sep" />

      <button
        v-for="level in [2, 3, 4]"
        :key="level"
        type="button"
        class="script-editor_toolbar-btn"
        :class="{ 'script-editor_toolbar-btn--active': editor.isActive('heading', { level }) }"
        :title="`Überschrift ${level}`"
        @click="editor.chain().focus().toggleHeading({ level }).run()"
      >H{{ level }}</button>

      <span class="script-editor_toolbar-sep" />

      <button
        type="button"
        class="script-editor_toolbar-btn"
        :class="{ 'script-editor_toolbar-btn--active': editor.isActive('bulletList') }"
        title="Aufzählungsliste"
        @click="editor.chain().focus().toggleBulletList().run()"
      >•</button>
      <button
        type="button"
        class="script-editor_toolbar-btn"
        :class="{ 'script-editor_toolbar-btn--active': editor.isActive('orderedList') }"
        title="Nummerierte Liste"
        @click="editor.chain().focus().toggleOrderedList().run()"
      >1.</button>
    </div>

    <EditorContent :editor="editor" class="script-editor_text" />
  </div>
</template>

<script setup>
import { ref, provide, computed } from 'vue'
import { useEditor, EditorContent } from '@tiptap/vue-3'
import StarterKit from '@tiptap/starter-kit'
import { useSkriptStore } from '@stores/skript'
import {
  ScriptParagraphNode,
  ScriptHeadingNode,
  ScriptBulletListNode,
  ScriptOrderedListNode,
  buildEditorContent,
  extractParagraphs,
} from '@utils/scriptEditorNodes'

const props = defineProps({
  scriptId: { type: Number, required: true },
  initialParagraphs: { type: Array, default: () => [] },
  roles: { type: Array, default: () => [] },
})

const store = useSkriptStore()
const docVersion = ref(0)
let applyingServerUpdate = false
let syncTimer = null

// Consumed by ScriptBlockView (the node view rendering each paragraph/
// heading/list block) to show the role dropdown and recompute gutter line
// numbers — @tiptap/vue-3 forwards this component's provides into every
// mounted node view, so plain inject() works there.
provide('scriptRoles', computed(() => props.roles))
provide('scriptDocVersion', docVersion)

const editor = useEditor({
  content: buildEditorContent(props.initialParagraphs),
  extensions: [
    StarterKit.configure({
      paragraph: false,
      heading: false,
      bulletList: false,
      orderedList: false,
    }),
    ScriptParagraphNode,
    ScriptHeadingNode.configure({ levels: [2, 3, 4] }),
    ScriptBulletListNode,
    ScriptOrderedListNode,
  ],
  onUpdate: () => {
    docVersion.value++
    if (applyingServerUpdate) return
    scheduleSync()
  },
})

function scheduleSync() {
  clearTimeout(syncTimer)
  syncTimer = setTimeout(() => enqueueSync(), 800)
}

// Runs sync() strictly after any currently in-flight call has finished,
// never overlapping with it. Without this, a sync started while a previous
// one is still awaiting its response (network latency) would (a) not yet
// know about the paragraph IDs the in-flight call is about to receive, so it
// re-creates duplicate rows for the same content instead of updating them,
// and (b) whichever response happens to arrive LAST wins and overwrites the
// store — not whichever request was SENT last — silently reverting to an
// older, smaller paragraph list. Queuing guarantees requests are sent and
// resolved in order, and each one reads the doc fresh at the time it
// actually runs (not at schedule time), so nothing typed in between is lost.
let syncChain = Promise.resolve()
function enqueueSync() {
  syncChain = syncChain.then(sync)
  return syncChain
}

async function sync() {
  if (!editor.value) return
  // Snapshot the current document synchronously (before any awaiting) so a
  // component unmount right after this call can't race the extraction.
  const paragraphs = extractParagraphs(editor.value)
  const response = await store.syncParagraphs(props.scriptId, paragraphs)
  if (!editor.value || !response.success || !response.data?.paragraphs) return

  // Write the canonical (real) IDs back onto their matching editor nodes —
  // response order matches the request order 1:1 — without triggering
  // another sync cycle for this purely internal bookkeeping update.
  applyingServerUpdate = true
  const canonical = response.data.paragraphs
  let idx = 0
  const tr = editor.value.state.tr
  editor.value.state.doc.forEach((node, pos) => {
    if (!['paragraph', 'heading', 'bulletList', 'orderedList'].includes(node.type.name)) return
    const match = canonical[idx]
    idx++
    if (match && node.attrs.pid !== match.ID) {
      tr.setNodeMarkup(pos, undefined, { ...node.attrs, pid: match.ID })
    }
  })
  if (tr.docChanged) {
    editor.value.view.dispatch(tr)
  }
  applyingServerUpdate = false
}

// Flushes a pending debounced save immediately. Must be called by the parent
// BEFORE it unmounts this component (e.g. right before switching away from
// edit mode) — useEditor() itself registers an onBeforeUnmount hook that
// destroys the editor, and since that hook is registered before any of ours,
// it always runs first; a flush attempted from this component's own
// onBeforeUnmount would therefore run against an already-destroyed editor.
async function flush() {
  clearTimeout(syncTimer)
  syncTimer = null
  // Queuing (rather than calling sync() directly) ensures this waits for any
  // already in-flight sync to finish first, then runs one more with the doc
  // as it stands right now — see enqueueSync() for why order matters here.
  await enqueueSync()
}

defineExpose({ flush })
</script>
