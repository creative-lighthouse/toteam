<template>
  <!-- Essensplaner: offene Vorschläge eines Events per Drag & Drop auf seine Mahlzeiten verteilen -->
  <div class="food-planner">
    <div v-if="events.length" class="food-planner_toolbar">
      <select v-if="events.length > 1" v-model="eventId" class="input" aria-label="Event wählen">
        <option v-for="event in events" :key="event.ID" :value="event.ID">
          {{ event.Title }}<template v-if="showOrg"> ({{ event.OrganizationTitle }})</template>
        </option>
      </select>
      <h2 v-else class="hl3 food-planner_event">{{ events[0].Title }}</h2>
      <AppButton variant="primary" :disabled="!eventId" @click="$emit('add-food', eventId)">+ Gericht</AppButton>
    </div>

    <div v-if="!events.length" class="section_infobox">
      <p>Es gibt gerade kein Event mit anstehenden Mahlzeiten. Ordne Termine beim Bearbeiten einem Event zu, dann kannst du hier das Essen planen.</p>
    </div>

    <div v-else-if="loading && !plan" class="section_infobox"><p>Lade Essensplanung…</p></div>

    <div v-else-if="loadError" class="section_infobox error">
      <p>{{ loadError }}</p>
      <AppButton variant="primary" @click="load">Erneut versuchen</AppButton>
    </div>

    <div v-else-if="plan" class="food-planner_layout">
      <section
        class="food-planner_pool"
        :class="{ 'is-drop-target': dragging, 'is-over': overZone === 'pool' }"
        data-drop-zone="pool"
        aria-labelledby="food-planner-pool-title"
      >
        <h2 id="food-planner-pool-title" class="hl3 food-planner_title">
          Offene Vorschläge
          <span class="food-planner_count">{{ plan.pool.length }}</span>
        </h2>
        <p class="food-planner_hint">Zieh einen Vorschlag auf die Mahlzeit, für die er mitgebracht werden soll.</p>

        <ul v-if="plan.pool.length" class="food-planner_list">
          <FoodPlanCard
            v-for="food in plan.pool"
            :key="food.id"
            :food="food"
            current-zone="pool"
            :days="dayOptions"
            :suggested-for="suggestedFor(food)"
            :dragging="dragging?.id === food.id"
            v-context-menu="food.canEdit ? (e => openFoodMenu(e, food)) : null"
            @pointerdown="startDrag($event, food)"
            @move="zone => move(food.id, zone)"
            @reject="reject(food)"
          />
        </ul>
        <p v-else class="food-planner_empty">Keine offenen Vorschläge.</p>
      </section>

      <div class="food-planner_days">
        <section v-for="day in plan.days" :key="day.date" class="food-planner_day">
          <h3 class="food-planner_day-title">{{ formatMealDay(day.date) }}</h3>

          <!-- Mahlzeit bearbeiten per Rechtsklick bzw. langem Drücken (nur mit Rechten) -->
          <div
            v-for="meal in day.meals"
            :key="meal.id"
            v-context-menu="meal.canEdit ? (e => openMealMenu(e, meal)) : null"
            class="food-planner_meal"
            :class="{
              'is-drop-target': dragging,
              'is-over': overZone === `meal-${meal.id}`,
              'food-planner_meal--editable': meal.canEdit,
            }"
            :data-drop-zone="`meal-${meal.id}`"
          >
            <div class="food-planner_meal-header">
              <p class="food-planner_meal-title">
                <span class="food-planner_meal-time">{{ meal.time }} Uhr</span>
                <router-link :to="`/food/meal/${meal.id}`" class="food-planner_link">{{ meal.title }}</router-link>
              </p>
              <span class="food-planner_meal-count" :title="`${meal.acceptCount} Zusagen`">
                <img :src="AcceptIcon" alt="Zusagen">{{ meal.acceptCount }}
              </span>
            </div>
            <p class="food-planner_meal-context">
              <router-link
                :to="{ name: 'Calendar', query: { date: day.date, eventID: meal.appointmentId } }"
                class="food-planner_link"
              >{{ meal.appointmentTitle }}</router-link>
            </p>

            <ul v-if="meal.foods.length" class="food-planner_list">
              <FoodPlanCard
                v-for="food in meal.foods"
                :key="food.id"
                :food="food"
                :current-zone="`meal-${meal.id}`"
                :days="dayOptions"
                :dragging="dragging?.id === food.id"
                v-context-menu="food.canEdit ? (e => openFoodMenu(e, food)) : null"
                @pointerdown="startDrag($event, food)"
                @move="zone => move(food.id, zone)"
              />
            </ul>
            <p v-else class="food-planner_empty">Noch nichts geplant.</p>
          </div>
        </section>
      </div>
    </div>

    <FoodPreferenceLegend v-if="plan && !loadError" :preferences="hasFoodPreferences" />

    <ContextMenu ref="mealMenu" />
    <FoodEditModal ref="foodModal" @saved="onFoodSaved" @deleted="onFoodDeleted" />
    <MealFormModal
      ref="mealModal"
      deletable
      @saved="onMealSaved"
      @deleted="onMealDeleted"
    />

    <div class="food-planner_status" role="status" aria-live="polite">
      <Transition name="fade">
        <p v-if="statusMessage" :class="['status-message', `status-message--${statusMessage.type}`]">{{ statusMessage.text }}</p>
      </Transition>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, watch } from 'vue'
import { apiGet, apiPut } from '@utils/api'
import { usePointerDrag } from '@utils/pointerDrag'
import { formatMealDay, formatMealDayShort } from '@utils/food'
import AppButton from '@components/ui/AppButton.vue'
import FoodPlanCard from '@components/food/FoodPlanCard.vue'
import FoodPreferenceLegend from '@components/food/FoodPreferenceLegend.vue'
import MealFormModal from '@components/food/MealFormModal.vue'
import FoodEditModal from '@components/food/FoodEditModal.vue'
import ContextMenu from '@components/ui/ContextMenu.vue'
import { vContextMenu } from '@utils/contextMenu'
import AcceptIcon from '../../../../icons/states/participation_accept.svg'

const props = defineProps({
  // Events, deren Vorschläge man zuordnen darf: [{ ID, Title, OrganizationID, OrganizationTitle }]
  events: { type: Array, default: () => [] },
  initialEventId: { type: Number, default: null },
})

const emit = defineEmits(['changed', 'add-food'])

const eventId = ref(pickEvent(props.initialEventId))
const plan = ref(null)
const loading = ref(false)
const loadError = ref(null)
const statusMessage = ref(null)

// Legende nur, wenn tatsächlich ein Gericht vegan/vegetarisch markiert ist
const hasFoodPreferences = computed(() =>
  [...(plan.value?.pool ?? []), ...(plan.value?.days ?? []).flatMap(d => d.meals.flatMap(m => m.foods))]
    .some(f => f.preference === 'Vegan' || f.preference === 'Vegetarian')
)

const showOrg = computed(() => new Set(props.events.map(e => e.OrganizationID)).size > 1)

function pickEvent(id) {
  return props.events.some(e => e.ID === id) ? id : (props.events[0]?.ID ?? null)
}

watch(() => props.events, () => {
  if (!props.events.some(e => e.ID === eventId.value)) eventId.value = pickEvent(null)
})

watch(eventId, load, { immediate: true })

async function load() {
  if (!eventId.value) {
    plan.value = null
    return
  }
  loading.value = true
  loadError.value = null
  try {
    const response = await apiGet(`/food/planner/${eventId.value}`, false)
    if (response?.success === false) {
      loadError.value = response.error || 'Essensplanung konnte nicht geladen werden.'
      return
    }
    plan.value = response
  } catch (err) {
    loadError.value = err.message || 'Essensplanung konnte nicht geladen werden.'
  } finally {
    loading.value = false
  }
}

// Auswahl-Optionen fürs Zuordnen per Tastatur/Auswahlfeld
const dayOptions = computed(() => (plan.value?.days ?? []).map(day => ({
  date: day.date,
  label: formatMealDayShort(day.date),
  meals: day.meals,
})))

const mealsById = computed(() => {
  const map = new Map()
  for (const day of plan.value?.days ?? []) {
    for (const meal of day.meals) map.set(meal.id, { ...meal, date: day.date })
  }
  return map
})

function suggestedFor(food) {
  const meal = food.suggestedMealId ? mealsById.value.get(food.suggestedMealId) : null
  return meal ? `${meal.title}, ${formatMealDayShort(meal.date)}` : null
}

function showStatus(text, type = 'success') {
  statusMessage.value = { text, type }
  setTimeout(() => { statusMessage.value = null }, 3000)
}

// ── Verschieben ──────────────────────────────────────────────────────────────

function findFood(foodId) {
  const inPool = plan.value.pool.findIndex(f => f.id === foodId)
  if (inPool !== -1) return { list: plan.value.pool, index: inPool, zone: 'pool' }
  for (const day of plan.value.days) {
    for (const meal of day.meals) {
      const index = meal.foods.findIndex(f => f.id === foodId)
      if (index !== -1) return { list: meal.foods, index, zone: `meal-${meal.id}` }
    }
  }
  return null
}

function listForZone(zone) {
  if (zone === 'pool') return plan.value.pool
  const meal = mealsById.value.get(Number(zone.replace('meal-', '')))
  if (!meal) return null
  for (const day of plan.value.days) {
    const found = day.meals.find(m => m.id === meal.id)
    if (found) return found.foods
  }
  return null
}

async function move(foodId, zone) {
  const source = findFood(foodId)
  const target = listForZone(zone)
  if (!source || !target || source.zone === zone) return

  // Sofort umsortieren, bei einem Fehler wird neu geladen
  const [food] = source.list.splice(source.index, 1)
  target.push(food)
  if (zone !== 'pool') target.sort((a, b) => a.title.localeCompare(b.title, 'de'))

  const mealId = zone === 'pool' ? null : Number(zone.replace('meal-', ''))
  const response = await apiPut(`/food/assign/${foodId}`, { mealId }).catch(err => ({ success: false, error: err.message }))
  if (!response?.success) {
    showStatus(response?.error || 'Zuordnung konnte nicht gespeichert werden.', 'error')
    await load()
    return
  }
  const meal = mealId ? mealsById.value.get(mealId) : null
  showStatus(meal ? `„${food.title}“ ist jetzt bei ${meal.title}, ${formatMealDayShort(meal.date)}.` : `„${food.title}“ ist wieder offen.`)
  emit('changed')
}

async function reject(food) {
  if (!confirm(`Vorschlag „${food.title}“ ablehnen?`)) return
  const response = await apiPut(`/food/foodStatus/${food.id}`, { status: 'Rejected' }).catch(err => ({ success: false, error: err.message }))
  if (!response?.success) {
    showStatus(response?.error || 'Vorschlag konnte nicht abgelehnt werden.', 'error')
    return
  }
  plan.value.pool = plan.value.pool.filter(f => f.id !== food.id)
  showStatus(`„${food.title}“ wurde abgelehnt.`)
  emit('changed')
}

// ── Mahlzeit bearbeiten ──────────────────────────────────────────────────────

const mealMenu = ref(null)
const mealModal = ref(null)

function openMealMenu(event, meal) {
  // Bearbeitbare Gerichte in der Mahlzeit haben ihr eigenes Menü
  if (event.target?.closest?.('.food-plan-card--editable')) return
  mealMenu.value?.open(event, [
    {
      label: 'Mahlzeit bearbeiten',
      onClick: () => mealModal.value?.open({
        id: meal.id,
        title: meal.title,
        time: meal.time,
        description: meal.description,
        acceptsContributions: meal.acceptsContributions,
      }),
    },
  ])
}

// ── Gericht bearbeiten ───────────────────────────────────────────────────────

const foodModal = ref(null)

function openFoodMenu(event, food) {
  mealMenu.value?.open(event, [
    { label: 'Gericht bearbeiten', onClick: () => foodModal.value?.open(food) },
  ])
}

function onFoodSaved(updated) {
  const found = findFood(updated.id)
  if (found) Object.assign(found.list[found.index], updated)
  showStatus('Gericht gespeichert')
  emit('changed')
}

function onFoodDeleted(foodId) {
  const found = findFood(foodId)
  if (found) found.list.splice(found.index, 1)
  showStatus('Gericht gelöscht')
  emit('changed')
}

async function onMealSaved() {
  showStatus('Mahlzeit aktualisiert')
  await load()
  emit('changed')
}

async function onMealDeleted() {
  showStatus('Mahlzeit gelöscht')
  await load()
  emit('changed')
}

// ── Drag & Drop ──────────────────────────────────────────────────────────────

// Menü und Tab-Leiste sind unten fest — dort ebenfalls mitscrollen
const bottomInset = () => {
  const nav = document.querySelector('.food-tab-nav')
  return nav ? window.innerHeight - nav.getBoundingClientRect().top : 0
}

const { start, dragging, overZone } = usePointerDrag({
  onDrop: (food, zone) => move(food.id, zone),
  bottomInset,
})

function startDrag(event, food) {
  if (food.isOrderable) return
  start(event, food)
}

defineExpose({ reload: load })
</script>
