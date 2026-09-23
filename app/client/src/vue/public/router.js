import { createRouter, createWebHistory } from 'vue-router'
import Home from './views/Home.vue'
import Legal from './views/Legal.vue'

export default createRouter({
  history: createWebHistory('/'),
  routes: [
    { path: '/', component: Home },
    { path: '/rechtliches', component: Legal },
  ],
})
