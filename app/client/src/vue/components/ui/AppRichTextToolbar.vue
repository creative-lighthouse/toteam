<!--
  Formatierungsleiste für einen TipTap-Editor: fett/kursiv/unterstrichen,
  Überschriften H2–H4 und Listen — dieselbe Formatierung wie im Skript.
  Mit `align` zusätzlich links/zentriert/rechts (Editor braucht die TextAlign-Extension).

    <AppRichTextToolbar :editor="editor" align />
-->
<template>
  <div class="app-rich-text-toolbar">
    <button
      type="button"
      class="app-rich-text-toolbar_btn"
      :class="{ 'app-rich-text-toolbar_btn--active': editor.isActive('bold') }"
      title="Fett"
      @click="editor.chain().focus().toggleBold().run()"
    ><strong>F</strong></button>
    <button
      type="button"
      class="app-rich-text-toolbar_btn"
      :class="{ 'app-rich-text-toolbar_btn--active': editor.isActive('italic') }"
      title="Kursiv"
      @click="editor.chain().focus().toggleItalic().run()"
    ><em>K</em></button>
    <button
      type="button"
      class="app-rich-text-toolbar_btn"
      :class="{ 'app-rich-text-toolbar_btn--active': editor.isActive('underline') }"
      title="Unterstrichen"
      @click="editor.chain().focus().toggleUnderline().run()"
    ><u>U</u></button>

    <span class="app-rich-text-toolbar_sep" />

    <button
      v-for="level in [2, 3, 4]"
      :key="level"
      type="button"
      class="app-rich-text-toolbar_btn"
      :class="{ 'app-rich-text-toolbar_btn--active': editor.isActive('heading', { level }) }"
      :title="`Überschrift ${level}`"
      @click="editor.chain().focus().toggleHeading({ level }).run()"
    >H{{ level }}</button>

    <span class="app-rich-text-toolbar_sep" />

    <button
      type="button"
      class="app-rich-text-toolbar_btn"
      :class="{ 'app-rich-text-toolbar_btn--active': editor.isActive('bulletList') }"
      title="Aufzählungsliste"
      @click="editor.chain().focus().toggleBulletList().run()"
    >•</button>
    <button
      type="button"
      class="app-rich-text-toolbar_btn"
      :class="{ 'app-rich-text-toolbar_btn--active': editor.isActive('orderedList') }"
      title="Nummerierte Liste"
      @click="editor.chain().focus().toggleOrderedList().run()"
    >1.</button>

    <template v-if="align">
      <span class="app-rich-text-toolbar_sep" />

      <button
        v-for="opt in ALIGNMENTS"
        :key="opt.value"
        type="button"
        class="app-rich-text-toolbar_btn app-rich-text-toolbar_btn--align"
        :class="{ 'app-rich-text-toolbar_btn--active': isAligned(opt.value) }"
        :title="opt.title"
        :aria-label="opt.title"
        @click="editor.chain().focus().setTextAlign(opt.value).run()"
      >
        <span class="app-rich-text-toolbar_align-icon" :class="`app-rich-text-toolbar_align-icon--${opt.value}`" aria-hidden="true">
          <span /><span /><span />
        </span>
      </button>
    </template>
  </div>
</template>

<script setup>
const props = defineProps({
  // TipTap-Editor (useEditor) mit StarterKit
  editor: { type: Object, required: true },
  // Buttons für die Ausrichtung zeigen
  align: { type: Boolean, default: false },
})

const ALIGNMENTS = [
  { value: 'left', title: 'Linksbündig' },
  { value: 'center', title: 'Zentriert' },
  { value: 'right', title: 'Rechtsbündig' },
]

// Ohne gesetzte Ausrichtung gilt linksbündig
function isAligned(value) {
  if (props.editor.isActive({ textAlign: value })) return true
  return value === 'left' && !ALIGNMENTS.some(a => a.value !== 'left' && props.editor.isActive({ textAlign: a.value }))
}
</script>
