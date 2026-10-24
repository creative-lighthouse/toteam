<!--
  Einfacher Rich-Text-Editor (TipTap) mit der Formatierung des Skripts plus Ausrichtung
  (links/zentriert/rechts, als style="text-align: …"), v-model als HTML.
  Der Server muss den Inhalt trotzdem selbst bereinigen (Allowlist).

    <AppRichTextEditor v-model="form.Content" placeholder="Was möchtest du sagen?" />
-->
<template>
  <div class="app-rich-text-editor" :class="{ 'app-rich-text-editor--empty': editor?.isEmpty }" :style="{ '--placeholder': JSON.stringify(placeholder) }">
    <AppRichTextToolbar v-if="editor" :editor="editor" align />
    <EditorContent :editor="editor" class="app-rich-text-editor_text" />
  </div>
</template>

<script setup>
import { watch } from 'vue'
import { useEditor, EditorContent } from '@tiptap/vue-3'
import StarterKit from '@tiptap/starter-kit'
import TextAlign from '@tiptap/extension-text-align'
import AppRichTextToolbar from '@components/ui/AppRichTextToolbar.vue'

const props = defineProps({
  modelValue: { type: String, default: '' },
  placeholder: { type: String, default: '' },
  // Name für Screenreader, z.B. "Text"
  label: { type: String, default: '' },
})

const emit = defineEmits(['update:modelValue'])

const editor = useEditor({
  content: props.modelValue || '',
  extensions: [
    StarterKit.configure({
      heading: { levels: [2, 3, 4] },
      blockquote: false,
      codeBlock: false,
      code: false,
      horizontalRule: false,
      strike: false,
      link: false,
    }),
    // Ausrichtung je Absatz/Überschrift; linksbündig ist der Normalfall und wird nicht gespeichert
    TextAlign.configure({
      types: ['heading', 'paragraph'],
      alignments: ['left', 'center', 'right'],
      defaultAlignment: 'left',
    }),
  ],
  editorProps: {
    attributes: () => ({
      role: 'textbox',
      'aria-multiline': 'true',
      ...(props.label ? { 'aria-label': props.label } : {}),
    }),
  },
  onUpdate: ({ editor }) => {
    emit('update:modelValue', editor.isEmpty ? '' : editor.getHTML())
  },
})

// Von außen gesetzter Inhalt (z.B. Formular zurückgesetzt oder zum Bearbeiten geöffnet)
watch(() => props.modelValue, (value) => {
  if (!editor.value) return
  const current = editor.value.isEmpty ? '' : editor.value.getHTML()
  if ((value || '') !== current) {
    editor.value.commands.setContent(value || '', { emitUpdate: false })
  }
})
</script>
