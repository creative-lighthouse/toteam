<template>
  <div class="section section--FoodPage" :class="{ 'has-food-nav': canPlan }">
    <div class="section_content">

      <!-- ── Übersicht ─────────────────────────────────────────────────────── -->
      <template v-if="activeTab === 'overview'">
        <div class="food-toolbar">
          <select v-model="filter" class="input" aria-label="Mahlzeiten filtern">
            <option value="all">Alle Mahlzeiten</option>
            <option value="accepted">Nur zugesagte</option>
          </select>
          <div class="food-toolbar_actions">
            <AppButton v-if="myFoods.length" variant="secondary" @click="suggestionsModal?.open()">
              Meine Vorschläge ({{ myFoods.length }})
            </AppButton>
            <AppButton v-if="suggestEvents.length" variant="primary" @click="suggestModal?.open()">
              + Vorschlagen
            </AppButton>
          </div>
        </div>

        <div v-if="loading && !loaded" class="section_infobox"><p>Lade Essensplan…</p></div>

        <div v-else-if="loadError" class="section_infobox error">
          <p>Fehler: {{ loadError }}</p>
          <AppButton variant="primary" @click="load">Erneut versuchen</AppButton>
        </div>

        <template v-else>
          <section class="food-group">
            <h2 class="hl3 food-group_title">
              Anstehende Mahlzeiten
              <span class="food-group_count">{{ filteredUpcoming.length }}</span>
            </h2>

            <p v-if="!filteredUpcoming.length" class="food-group_empty">
              {{ filter === 'accepted' ? 'Du hast noch keiner anstehenden Mahlzeit zugesagt.' : 'Keine anstehenden Mahlzeiten.' }}
            </p>

            <div v-for="day in upcomingDays" :key="day.date" class="food-day">
              <h3 class="food-day_title">{{ formatMealDay(day.date) }}</h3>
              <ul class="food-list">
                <li v-for="meal in day.meals" :key="meal.id">
                  <MealCard :meal="meal" :hide-org-logo="!authStore.hasMultipleOrganizations" />
                </li>
              </ul>
            </div>
          </section>


          <AppCollapse
            v-if="filteredPast.length"
            :title="`Vergangene Mahlzeiten (${filteredPast.length})`"
            class="food-archive"
          >
            <div v-for="day in pastDays" :key="day.date" class="food-day">
              <h3 class="food-day_title">{{ formatMealDay(day.date) }}</h3>
              <ul class="food-list">
                <li v-for="meal in day.meals" :key="meal.id">
                  <MealCard :meal="meal" :hide-org-logo="!authStore.hasMultipleOrganizations" />
                </li>
              </ul>
            </div>
          </AppCollapse>
        </template>
      </template>

      <!-- ── Essen planen ──────────────────────────────────────────────────── -->
      <FoodPlanner
        v-else
        ref="planner"
        :events="planEvents"
        :initial-event-id="initialPlanEventId"
        @changed="load"
        @add-food="eventId => suggestModal?.open({ eventId, mode: 'organization', lockEvent: true })"
      />
    </div>

    <FoodEventSuggestModal ref="suggestModal" :events="suggestEvents" :plan-events="planEvents" @saved="onSaved" />
    <FoodSuggestionsModal ref="suggestionsModal" :foods="myFoods" />

    <!-- ── Tabs unten (nur für Essensplaner) ─────────────────────────────── -->
    <nav v-if="canPlan" class="food-tab-nav" aria-label="Essen">
      <button
        v-for="tab in TABS"
        :key="tab.id"
        type="button"
        class="food-tab-nav_item"
        :class="{ 'is-active': activeTab === tab.id }"
        :aria-current="activeTab === tab.id ? 'page' : null"
        @click="selectTab(tab.id)"
      >
        <span class="food-tab-nav_icon" :style="tab.iconStyle" aria-hidden="true"></span>
        {{ tab.label }}
      </button>
    </nav>

    <Transition name="fade">
      <div v-if="statusMessage" :class="['status-message', `status-message--${statusMessage.type}`]" role="status">
        {{ statusMessage.text }}
      </div>
    </Transition>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { usePageHeaderStore } from '@stores/pageHeader'
import { useAuthStore } from '@stores/auth'
import { apiGet } from '@utils/api'
import { formatMealDay, groupMealsByDay } from '@utils/food'
import AppButton from '@components/ui/AppButton.vue'
import AppCollapse from '@components/ui/AppCollapse.vue'
import MealCard from '@components/food/MealCard.vue'
import FoodPlanner from '@components/food/FoodPlanner.vue'
import FoodEventSuggestModal from '@components/food/FoodEventSuggestModal.vue'
import FoodSuggestionsModal from '@components/food/FoodSuggestionsModal.vue'
import FoodIcon from '../../../icons/actions/action_food.svg'
import PlanFoodIcon from '../../../icons/actions/action_planfood.svg'

usePageHeaderStore().setHeader('Essen', '')

const route = useRoute()
const router = useRouter()
const authStore = useAuthStore()

const maskStyle = icon => ({ maskImage: `url("${icon}")`, WebkitMaskImage: `url("${icon}")` })
const TABS = [
  { id: 'overview', label: 'Übersicht', iconStyle: maskStyle(FoodIcon) },
  { id: 'plan', label: 'Planen', iconStyle: maskStyle(PlanFoodIcon) },
]


// ── Daten ────────────────────────────────────────────────────────────────────

const upcomingMeals = ref([])
const pastMeals = ref([])
const myFoods = ref([])
const suggestEvents = ref([])
const planEvents = ref([])
const loading = ref(false)
const loaded = ref(false)
const loadError = ref(null)

const canPlan = computed(() => planEvents.value.length > 0)

async function load() {
  loading.value = true
  loadError.value = null
  try {
    const data = await apiGet('/food', false)
    if (data?.success === false) {
      loadError.value = data.error || 'Essensplan konnte nicht geladen werden.'
      return
    }
    upcomingMeals.value = data.upcomingMeals ?? []
    pastMeals.value = data.pastMeals ?? []
    myFoods.value = data.myFoods ?? []
    suggestEvents.value = data.suggestEvents ?? []
    planEvents.value = data.planEvents ?? []
    loaded.value = true
  } catch (err) {
    loadError.value = err.message
  } finally {
    loading.value = false
  }
}

// ── Tabs (in der URL, damit Links aus Benachrichtigungen direkt zum Planer führen) ──

const activeTab = computed(() => (route.query.tab === 'plan' && canPlan.value ? 'plan' : 'overview'))
const initialPlanEventId = route.query.event ? Number(route.query.event) : null

function selectTab(tab) {
  const { tab: _tab, event: _event, ...rest } = route.query
  router.replace({ query: tab === 'plan' ? { ...rest, tab } : rest })
}

// ── Übersicht ────────────────────────────────────────────────────────────────

const filter = ref('all')
const applyFilter = meals => (filter.value === 'accepted' ? meals.filter(m => m.userResponse === 'Accept') : meals)

const filteredUpcoming = computed(() => applyFilter(upcomingMeals.value))
const filteredPast = computed(() => applyFilter(pastMeals.value))
const upcomingDays = computed(() => groupMealsByDay(filteredUpcoming.value))
const pastDays = computed(() => groupMealsByDay(filteredPast.value))


// ── Vorschlagen ──────────────────────────────────────────────────────────────

const suggestModal = ref(null)
const suggestionsModal = ref(null)
const planner = ref(null)
const statusMessage = ref(null)

function onSaved({ asOrganization, food }) {
  const text = asOrganization
    ? (food.mealId ? `„${food.title}“ ist eingeplant.` : `„${food.title}“ liegt bei den offenen Vorschlägen.`)
    : 'Danke! Dein Vorschlag ist bei der Essensplanung.'
  statusMessage.value = { text, type: 'success' }
  setTimeout(() => { statusMessage.value = null }, 3000)
  load()
  planner.value?.reload()
}

onMounted(load)
</script>
