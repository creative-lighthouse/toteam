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
import { linkifyParts } from '@utils/linkify'

const props = defineProps({
  text: { type: String, default: '' },
})

const parts = computed(() => linkifyParts(props.text))
</script>
