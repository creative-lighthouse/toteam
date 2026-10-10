import { ref, onMounted, onUnmounted } from 'vue'

/** Reaktives matchMedia, z. B. useMediaQuery('(max-width: 700px)') passend zum `max-medium`-Breakpoint */
export function useMediaQuery(query) {
  const mediaQuery = window.matchMedia(query)
  const matches = ref(mediaQuery.matches)
  const onChange = e => { matches.value = e.matches }

  onMounted(() => mediaQuery.addEventListener('change', onChange))
  onUnmounted(() => mediaQuery.removeEventListener('change', onChange))

  return matches
}
