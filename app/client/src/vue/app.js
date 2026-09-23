import { createApp } from 'vue'
import { createPinia } from 'pinia'
import App from './App.vue'
import router from './router'

// Create Pinia instance
const pinia = createPinia()

// Create Vue app
const app = createApp(App)

// Use plugins
app.use(pinia)
app.use(router)

// iOS Safari only applies :active styles on touch when a touchstart listener exists
document.addEventListener('touchstart', () => {}, { passive: true })

// Mount app
app.mount('#app')
