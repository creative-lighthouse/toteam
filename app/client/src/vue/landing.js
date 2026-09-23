import { createApp } from 'vue'
import LandingApp from './LandingApp.vue'
import router from './public/router.js'

createApp(LandingApp).use(router).mount('#app')
