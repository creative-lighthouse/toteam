<template>
  <div class="section section--LinksPage">
    <div class="section_content">
      <div class="section_infobox">
        <p>Die Links-Ansicht wird hier implementiert.</p>
        <p>Hier werden Team-Links aus dem Backend angezeigt.</p>
      </div>
    </div>
  </div>
</template>

<script setup>
import { usePageHeaderStore } from '@stores/pageHeader'
usePageHeaderStore().setHeader('Links', 'Wichtige Links und Ressourcen für dein Team.')
</script>
