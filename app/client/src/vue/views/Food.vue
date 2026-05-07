<template>
  <div class="section section--FoodPage">
    <div class="section_content">
      <div class="section_infobox">
        <p>Die Essensplan-Ansicht wird hier implementiert.</p>
        <p>Hier werden Mahlzeiten aus dem Backend angezeigt.</p>
      </div>
    </div>
  </div>
</template>

<script setup>
import { usePageHeaderStore } from '@stores/pageHeader'
usePageHeaderStore().setHeader('Essensplan', 'Hier findest du den Essensplan und kannst deine Präferenzen angeben.')
</script>
