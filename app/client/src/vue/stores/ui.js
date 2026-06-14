import { defineStore } from 'pinia'
import { ref } from 'vue'

export const useUiStore = defineStore('ui', () => {
  const navStyle = ref(localStorage.getItem('navStyle') || 'AppMenu--default')

  function setNavStyle(style) {
    navStyle.value = style
    localStorage.setItem('navStyle', style)
  }

  return { navStyle, setNavStyle }
})
