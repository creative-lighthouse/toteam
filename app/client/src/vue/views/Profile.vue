<template>
  <div class="section section--ProfilePage">
    <IntroBar title="Profil" description="Verwalte dein Profil und deine Einstellungen." />
    
    <div class="section_content">
      <div class="section_infobox" v-if="authStore.user">
        <h2 class="hl2">{{ authStore.userName }}</h2>
        <p><b>E-Mail:</b> {{ authStore.user.Email }}</p>
        
        <div class="profile-image">
          <img 
            v-if="authStore.user.ProfileImage" 
            :src="authStore.user.ProfileImage.URL" 
            :alt="`Profilbild von ${authStore.user.FirstName}`"
          >
          <img 
            v-else 
            :src="authStore.user.Gravatar" 
            alt="Standard Profilbild"
          >
        </div>
        
        <button @click="authStore.logout()" class="button">
          Abmelden
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { useAuthStore } from '@stores/auth'
import IntroBar from '@components/IntroBar.vue'

const authStore = useAuthStore()
</script>

<style scoped>
.profile-image {
  margin: 1.5rem 0;
}

.profile-image img {
  max-width: 200px;
  border-radius: 50%;
}
</style>
