<template>
  <!-- Legende für Essenspräferenz-Icons und Allergie-Labels -->
  <ul v-if="preferences || allergies" class="food-preference-legend" aria-label="Legende Essenspräferenz und Allergien">
    <template v-if="preferences">
      <li><img :src="VeganIcon" alt="" aria-hidden="true"> Vegan</li>
      <li><img :src="VegetarianIcon" alt="" aria-hidden="true"> Vegetarisch</li>
    </template>
    <li v-if="allergies"><span class="allergy-pill" aria-hidden="true">XXX</span> Allergie</li>
  </ul>
</template>

<script setup>
import VeganIcon from '../../../../icons/states/food_vegan.svg'
import VegetarianIcon from '../../../../icons/states/food_vegetarian.svg'

defineProps({
  preferences: { type: Boolean, default: false },
  allergies: { type: Boolean, default: false },
})
</script>
