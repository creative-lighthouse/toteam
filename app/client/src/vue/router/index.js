import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '@stores/auth'

// Views
import Dashboard from '@views/Dashboard.vue'
import Calendar from '@views/Calendar.vue'
import Food from '@views/Food.vue'
import Notices from '@views/Notices.vue'
import Profile from '@views/Profile.vue'
import Map from '@views/Map.vue'
import Links from '@views/Links.vue'
import Login from '@views/Login.vue'

const routes = [
  {
    path: '/',
    name: 'Dashboard',
    component: Dashboard,
    meta: { requiresAuth: true }
  },
  {
    path: '/calendar',
    name: 'Calendar',
    component: Calendar,
    meta: { requiresAuth: true }
  },
  {
    path: '/food',
    name: 'Food',
    component: Food,
    meta: { requiresAuth: true }
  },
  {
    path: '/notices',
    name: 'Notices',
    component: Notices,
    meta: { requiresAuth: true }
  },
  {
    path: '/profile',
    name: 'Profile',
    component: Profile,
    meta: { requiresAuth: true }
  },
  {
    path: '/map',
    name: 'Map',
    component: Map,
    meta: { requiresAuth: true }
  },
  {
    path: '/links',
    name: 'Links',
    component: Links,
    meta: { requiresAuth: true }
  },
  {
    path: '/login',
    name: 'Login',
    component: Login,
    meta: { requiresAuth: false }
  }
]

const router = createRouter({
  history: createWebHistory('/app'),
  routes
})

// Navigation guard for authentication
router.beforeEach((to, from, next) => {
  const authStore = useAuthStore()
  
  if (to.meta.requiresAuth && !authStore.isAuthenticated) {
    next({ name: 'Login' })
  } else if (to.name === 'Login' && authStore.isAuthenticated) {
    next({ name: 'Dashboard' })
  } else {
    next()
  }
})

export default router
