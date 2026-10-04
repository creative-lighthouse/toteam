<template>
  <!-- Tagesordnung: Tagesordnungspunkte und Mahlzeiten in einer Liste, nach Uhrzeit sortiert -->
  <div v-if="visible" class="agenda-section">
    <div class="section-feature-header">
      <h3 class="event-agenda_title">Tagesordnung</h3>
      <AppIconButton
        v-if="canEditAgenda"
        variant="primary"
        aria-label="Tagesordnung bearbeiten"
        @click="agendaModal?.open()"
      >
        <span class="icon-mask" :style="editIconStyle"></span>
      </AppIconButton>
      <AppIconButton
        v-if="canAddMeal"
        variant="primary"
        aria-label="Mahlzeit hinzufügen"
        @click="mealModal?.open()"
      >
        <span class="icon-mask" :style="addFoodIconStyle"></span>
      </AppIconButton>
    </div>

    <div v-if="items.length" class="agenda-list">
      <template v-for="item in items" :key="`${item.kind}-${item.data.ID}`">
        <EventAgendaMeal
          v-if="item.kind === 'meal'"
          :meal="item.data"
          :can-manage-content="canManageContent"
          @food-changed="(...args) => $emit('food-changed', ...args)"
          @show-status="$emit('show-status', $event)"
          @open-menu="openMealMenu"
          @open-food-menu="openFoodMenu"
        />
        <div v-else class="agenda-point">
          <span class="agenda-point_time">{{ item.data.RenderTime }}</span>
          <div class="agenda-point_content">
            <strong class="agenda-point_title">{{ item.data.Title }}</strong>
            <p v-if="item.data.Description" class="agenda-point_desc agenda-point_desc--pre">
              <AppLinkifiedText :text="item.data.Description" />
            </p>
          </div>
        </div>
      </template>
    </div>

    <p v-else class="event-section-empty">Noch nichts geplant.</p>

    <ContextMenu ref="mealMenu" />

    <EventAgendaModal
      v-if="canEditAgenda"
      ref="agendaModal"
      :event="event"
      @show-status="$emit('show-status', $event)"
    />

    <MealFormModal
      v-if="canAddMeal"
      ref="mealModal"
      :appointment-id="event.ID"
      @saved="m => $emit('show-status', { text: m.isNew ? 'Mahlzeit hinzugefügt' : 'Mahlzeit aktualisiert', type: 'success' })"
      @deleted="$emit('show-status', { text: 'Mahlzeit gelöscht', type: 'success' })"
    />

    <FoodFormModal
      ref="foodModal"
      allow-orderable
      @saved="onFoodSaved"
      @deleted="onFoodDeleted"
    />
  </div>
</template>

<script setup>
import { ref, computed } from 'vue'
import AppIconButton from '@components/ui/AppIconButton.vue'
import AppLinkifiedText from '@components/ui/AppLinkifiedText.vue'
import ContextMenu from '@components/ui/ContextMenu.vue'
import MealFormModal from '@components/food/MealFormModal.vue'
import FoodFormModal from '@components/food/FoodFormModal.vue'
import { useEventsStore } from '@stores/events'
import EventAgendaModal from './EventAgendaModal.vue'
import EventAgendaMeal from './EventAgendaMeal.vue'
import EditIcon from '../../../../../icons/actions/action_edit.svg'
import AddFoodIcon from '../../../../../icons/actions/action_addfood.svg'

const editIconStyle = { maskImage: `url("${EditIcon}")`, WebkitMaskImage: `url("${EditIcon}")` }
const addFoodIconStyle = { maskImage: `url("${AddFoodIcon}")`, WebkitMaskImage: `url("${AddFoodIcon}")` }

const props = defineProps({
  event: { type: Object, required: true },
  canManageContent: { type: Boolean, default: false }
})

const emit = defineEmits(['food-changed', 'show-status'])

const agendaModal = ref(null)
const mealModal = ref(null)
const mealMenu = ref(null)
const foodModal = ref(null)
const eventsStore = useEventsStore()

const agendaPoints = computed(() => props.event.EnableAgenda ? (props.event.AgendaPoints ?? []) : [])
const meals = computed(() => props.event.EnableMeals ? (props.event.Meals ?? []) : [])

const canEditAgenda = computed(() => props.canManageContent && props.event.EnableAgenda)
const canAddMeal = computed(() => props.canManageContent && props.event.EnableMeals)

// Admins/Mods sehen den Teil, sobald Tagesordnung oder Mahlzeiten aktiviert sind; Mitglieder nur mit Inhalt
const visible = computed(() => canEditAgenda.value || canAddMeal.value || items.value.length > 0)

// Zeiten kommen als "HH:mm:ss" und lassen sich daher als String vergleichen.
// Punkte ohne Startzeit sortieren über ihre Endzeit, ganztägige ganz nach oben.
// Bei gleicher Uhrzeit steht der Tagesordnungspunkt vor der Mahlzeit.
const items = computed(() => [
  ...agendaPoints.value.map(p => ({ kind: 'point', time: p.StartTime || p.EndTime || '', order: 0, data: p })),
  ...meals.value.map(m => ({ kind: 'meal', time: m.Time || '', order: 1, data: m })),
].sort((a, b) => a.time.localeCompare(b.time) || a.order - b.order))

function openMealMenu(event, meal) {
  mealMenu.value?.open(event, [
    { label: 'Mahlzeit bearbeiten', onClick: () => openMealModal(meal) },
  ])
}

// Gerichte aus der Kalender-API (PascalCase) ins Format des FoodFormModal bringen
function openFoodMenu(event, food) {
  mealMenu.value?.open(event, [
    {
      label: 'Gericht bearbeiten',
      onClick: () => foodModal.value?.open({
        id: food.ID,
        title: food.Title,
        preference: food.Preference,
        supplier: food.Supplier,
        supplierId: food.SupplierID,
        organizationId: food.OrganizationID,
        isOrderable: food.Orderable,
        maxQuantity: food.MaxQuantity ?? 0,
      }),
    },
  ])
}

async function onFoodSaved(food) {
  await eventsStore.applyMealFoodSaved(food)
  emit('show-status', { text: 'Gericht gespeichert', type: 'success' })
}

async function onFoodDeleted(foodId) {
  await eventsStore.removeMealFood(foodId)
  emit('show-status', { text: 'Gericht gelöscht', type: 'success' })
}

// Mahlzeiten aus der Kalender-API (PascalCase) ins Format des MealFormModal bringen
function openMealModal(meal) {
  mealModal.value?.open({
    id: meal.ID,
    title: meal.Title,
    time: meal.RenderTime,
    description: meal.Description,
    acceptsContributions: meal.AcceptsContributions,
  })
}
</script>
