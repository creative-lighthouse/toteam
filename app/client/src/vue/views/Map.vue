<template>
  <div class="section section--MapPage">
    <div class="section_content">
      <div class="section_infobox">
        <p>Die Karten-Ansicht wird hier implementiert.</p>
        <p>Hier werden Maps und POIs aus dem Backend angezeigt.</p>
      </div>
    </div>
  </div>
</template>

<script setup>
import { usePageHeaderStore } from '@stores/pageHeader'
usePageHeaderStore().setHeader('Karte', 'Finde Orte und POIs auf der Karte.')
</script>
