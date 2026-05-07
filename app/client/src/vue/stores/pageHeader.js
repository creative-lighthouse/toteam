import { defineStore } from 'pinia'
import { ref } from 'vue'

export const usePageHeaderStore = defineStore('pageHeader', () => {
  const title = ref('')
  const description = ref('')

  function setHeader(newTitle, newDescription = '') {
    title.value = newTitle
    description.value = newDescription
  }

  function setTitle(newTitle) {
    title.value = newTitle
  }

  return { title, description, setHeader, setTitle }
})
