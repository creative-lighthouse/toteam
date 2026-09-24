<!--
  Gibt Freitext (z. B. Beschreibungen) aus und macht darin enthaltene Links
  klickbar. Ohne v-html: Der Text wird in Text- und Link-Stücke zerlegt und als
  normale Textknoten gerendert — eingefügtes HTML bleibt also reiner Text.
  Nur http(s)://… und www.… werden verlinkt, Links öffnen in einem neuen Tab.

    <p class="task-detail_description"><AppLinkifiedText :text="task.Description" /></p>

  Nicht innerhalb klickbarer Elemente (Karten, Buttons, Router-Links) verwenden —
  verschachtelte Links sind ungültiges HTML.
-->
<template>
  <template v-for="(part, index) in parts" :key="index">
    <a
      v-if="part.href"
      :href="part.href"
      class="linkified-text_link"
      target="_blank"
      rel="noopener noreferrer"
      @click.stop
    >{{ part.text }}</a>
    <template v-else>{{ part.text }}</template>
  </template>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  text: { type: String, default: '' },
})

// Kandidaten: alles ab http(s):// bzw. www. bis zum nächsten Leerraum
const URL_PATTERN = /\b(?:https?:\/\/|www\.)[^\s<>"]+/gi
// Satzzeichen am Ende gehören meist zum Satz, nicht zur URL ("Siehe https://x.de.")
const TRAILING_CHARS = '.,;:!?\'"»“”)]'

function count(str, char) {
  return str.split(char).length - 1
}

function splitUrl(raw) {
  let url = raw
  let trailing = ''
  while (url.length && TRAILING_CHARS.includes(url[url.length - 1])) {
    const ch = url[url.length - 1]
    // Schließende Klammer behalten, wenn sie zu einer Klammer in der URL gehört (z. B. Wikipedia)
    if (ch === ')' && count(url, '(') >= count(url, ')')) break
    trailing = ch + trailing
    url = url.slice(0, -1)
  }
  return [url, trailing]
}

const parts = computed(() => {
  const text = props.text || ''
  const result = []
  let last = 0
  for (const match of text.matchAll(URL_PATTERN)) {
    const [url, trailing] = splitUrl(match[0])
    if (match.index > last) result.push({ text: text.slice(last, match.index) })
    const href = /^https?:\/\//i.test(url) ? url : `https://${url}`
    result.push({ text: url, href })
    if (trailing) result.push({ text: trailing })
    last = match.index + match[0].length
  }
  if (last < text.length) result.push({ text: text.slice(last) })
  return result
})
</script>
