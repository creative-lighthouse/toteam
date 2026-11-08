<!--
  Ein "Ordner" in der Foto-Galerie (Organisation, Event oder "Ohne Event"): Ordner-Symbol
  in der Farbe der Organisation, optional mit ihrem Logo vorne drauf, darunter Titel und Zeile.

    <PhotoFolder :to="…" title="Ottos Halloweenhaus" subtitle="25 Fotos" :color="'#35497d'" logo :logo-url="…" />
-->
<template>
  <router-link :to="to" class="photo-folder" :class="{ 'photo-folder--muted': muted }" :style="color ? { '--FolderColor': color } : null">
    <span class="photo-folder_icon" aria-hidden="true">
      <svg viewBox="0 0 120 96" class="photo-folder_svg">
        <path class="photo-folder_back" d="M8 4h34c2.4 0 4.6 1.1 6.1 3l6.8 8.6c.8 1 1.9 1.4 3.1 1.4H112a8 8 0 0 1 8 8v63a8 8 0 0 1-8 8H8a8 8 0 0 1-8-8V12a8 8 0 0 1 8-8Z" />
        <path class="photo-folder_paper" d="M10 20h100v40H10z" />
        <rect class="photo-folder_front" x="0" y="28" width="120" height="68" rx="8" />
      </svg>
      <span v-if="logo" class="photo-folder_logo">
        <AppOrgLogo :src="logoUrl" :alt="''" :name="title" class="photo-folder_logo-image" />
      </span>
      <span v-else-if="label" class="photo-folder_label">{{ label }}</span>
    </span>
    <span class="photo-folder_title">{{ title }}</span>
    <span v-if="subtitle" class="photo-folder_subtitle">{{ subtitle }}</span>
  </router-link>
</template>

<script setup>
import AppOrgLogo from '@components/ui/AppOrgLogo.vue'

defineProps({
  to: { type: [String, Object], required: true },
  title: { type: String, required: true },
  subtitle: { type: String, default: '' },
  // CSS-Farbe des Ordners, ohne Angabe die Primärfarbe
  color: { type: String, default: null },
  // Logo der Organisation vorne auf dem Ordner (ohne Bild: Platzhalter mit Initiale)
  logo: { type: Boolean, default: false },
  logoUrl: { type: String, default: null },
  // Kurzer Text vorne auf dem Ordner statt Logo (z.B. das Jahr eines Events)
  label: { type: String, default: '' },
  // Zurückhaltend grau (z.B. "Ohne Event")
  muted: { type: Boolean, default: false },
})
</script>
