import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '@stores/auth'

// Views
import Dashboard from '@views/Dashboard.vue'
import Calendar from '@views/Calendar.vue'
import Food from '@views/Food.vue'
import Announcements from '@views/Announcements.vue'
import AnnouncementDetail from '@views/AnnouncementDetail.vue'
import Profile from '@views/Profile.vue'
import MealDetail from '@views/MealDetail.vue'
import Map from '@views/Map.vue'
import MapDetail from '@views/MapDetail.vue'
import MapCreate from '@views/MapCreate.vue'
import MapLayerEdit from '@views/MapLayerEdit.vue'
import Links from '@views/Links.vue'
import Organizations from '@views/Organizations.vue'
import Login from '@views/Login.vue'
import Register from '@views/Register.vue'

const routes = [
  {
    path: '/',
    redirect: to => {
      // Root route redirects to dashboard or login based on auth
      // This will be handled by the beforeEach guard
      return '/dashboard'
    }
  },
  {
    path: '/dashboard',
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
    path: '/food/meal/:id',
    name: 'MealDetail',
    component: MealDetail,
    meta: { requiresAuth: true }
  },
  {
    path: '/food',
    name: 'Food',
    component: Food,
    meta: { requiresAuth: true }
  },
  {
    path: '/announcements',
    name: 'Announcements',
    component: Announcements,
    meta: { requiresAuth: true }
  },
  {
    path: '/announcements/:id',
    name: 'AnnouncementDetail',
    component: AnnouncementDetail,
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
    path: '/map/new',
    name: 'MapCreate',
    component: MapCreate,
    meta: { requiresAuth: true }
  },
  {
    path: '/map/:mapId/layer/:layerId/edit',
    name: 'MapLayerEdit',
    component: MapLayerEdit,
    meta: { requiresAuth: true }
  },
  {
    path: '/map/:id',
    name: 'MapDetail',
    component: MapDetail,
    meta: { requiresAuth: true }
  },
  {
    path: '/links',
    name: 'Links',
    component: Links,
    meta: { requiresAuth: true }
  },
  {
    path: '/organizations',
    name: 'Organizations',
    component: Organizations,
    meta: { requiresAuth: true }
  },
  {
    path: '/login',
    name: 'Login',
    component: Login,
    meta: { requiresAuth: false }
  },
  {
    path: '/register',
    name: 'Register',
    component: Register,
    meta: { requiresAuth: false }
  }
]

const router = createRouter({
  history: createWebHistory('/app'),
  routes
})

// Navigation guard for authentication
router.beforeEach(async (to, from, next) => {
  const authStore = useAuthStore()
  
  // Check auth only once if we don't have user data yet
  if (!authStore.user) {
    await authStore.checkAuth()
  }
  
  // Redirect to login if authentication is required but user is not authenticated
  if (to.meta.requiresAuth && !authStore.isAuthenticated) {
    next({ name: 'Login', query: { redirect: to.fullPath } })
    return
  }
  
  // Redirect authenticated users away from login/register pages
  if ((to.name === 'Login' || to.name === 'Register') && authStore.isAuthenticated) {
    next({ name: 'Dashboard' })
    return
  }
  
  next()
})

export default router
