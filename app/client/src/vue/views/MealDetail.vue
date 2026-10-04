<template>
  <div class="section section--MealDetail">
    <div class="section_content">

      <div v-if="loading" class="section_infobox">
        <p>Lade Mahlzeit…</p>
      </div>

      <div v-else-if="error" class="section_infobox error">
        <p>{{ error }}</p>
        <AppButton to="/food" variant="primary">← Zurück</AppButton>
      </div>

      <template v-else-if="meal">

        <!-- Header -->
        <div class="meal-detail-hero">
          <AppOrgLogo
            :src="meal.organizationLogoUrl"
            :alt="meal.organizationTitle"
            :size="56"
            class="meal-detail-hero_logo"
          />
          <div class="meal-detail-hero_info">
            <p class="meal-detail-hero_date">{{ formatDate(meal.date) }} • {{ meal.time }} Uhr</p>
            <h2 class="meal-detail-hero_title">{{ meal.title }}</h2>
            <p class="meal-detail-hero_sub">
              {{ meal.appointmentTitle }}
              <span v-if="meal.organizationTitle"> • {{ meal.organizationTitle }}</span>
            </p>

            <p v-if="meal.description" class="meal-detail-hero_description-text"><AppLinkifiedText :text="meal.description" /></p>
          </div>
          <div class="meal-detail-hero_actions">
            <AppIconButton
              variant="neutral"
              aria-label="Verlauf anzeigen"
              title="Verlauf anzeigen"
              @click="historyModal?.open()"
            >
              <span class="icon-mask" :style="historyIconStyle" />
            </AppIconButton>
            <AppIconButton
              v-if="meal.canManage"
              variant="primary"
              aria-label="Mahlzeit bearbeiten"
              title="Mahlzeit bearbeiten"
              @click="openEditModal"
            >
              <span class="icon-mask" :style="editIconStyle" />
            </AppIconButton>
          </div>
        </div>

        <!-- RSVP -->
        <div class="section_infobox meal-rsvp">
          <div class="meal-rsvp_status">
            Deine Antwort:
            <strong :class="rsvpClass">{{ rsvpLabel }}</strong>
          </div>
          <AppButtonGroup
            :options="rsvpOptions"
            :model-value="meal.userResponse"
            :disabled="responding"
            label="Deine Antwort"
            @select="respond"
          />
        </div>

        <!-- Teilnehmer -->
        <div v-if="hasAnyParticipants" class="section_infobox participants-section">
          <h3 class="event-participation_title">Teilnehmer</h3>
          <!-- Ein Tab-Stopp für die ganze Liste, Pfeiltasten wechseln zwischen den Karten -->
          <div v-roving-focus="{ selector: '.participant', label: 'Teilnehmer' }" class="participants-list">
            <template v-if="groupedAttendees.Accept.length">
              <h5 class="participant-group_title">Zugesagt <span>({{ groupedAttendees.Accept.length }})</span></h5>
              <ParticipantCard
                v-for="p in groupedAttendees.Accept"
                :key="p.ID"
                :participation="p"
                v-context-menu="hasAttendeeMenu(p) ? (e => onAttendeeContextMenu(e, p)) : null"
              />
            </template>

            <template v-if="groupedAttendees.Decline.length">
              <h5 class="participant-group_title">Abgesagt <span>({{ groupedAttendees.Decline.length }})</span></h5>
              <ParticipantCard
                v-for="p in groupedAttendees.Decline"
                :key="p.ID"
                :participation="p"
                v-context-menu="hasAttendeeMenu(p) ? (e => onAttendeeContextMenu(e, p)) : null"
              />
            </template>

            <template v-if="groupedAttendees.Pending.length">
              <h5 class="participant-group_title">Ohne Antwort <span>({{ groupedAttendees.Pending.length }})</span></h5>
              <ParticipantCard
                v-for="p in groupedAttendees.Pending"
                :key="p.ID"
                :participation="p"
                v-context-menu="hasAttendeeMenu(p) ? (e => onAttendeeContextMenu(e, p)) : null"
              />
            </template>
          </div>
        </div>

        <ContextMenu ref="attendeeMenu" />

        <!-- Geplante Gerichte (orderable + regular combined) -->
        <div class="section_infobox meal-foods-section" :class="{ 'meal-foods-section--full': !hasAnyParticipants }">
          <div class="meal-detail-block_heading-row">
            <h3 class="hl3">Geplante Gerichte ({{ meal.foods.length }})</h3>
            <div class="meal-detail-heading-actions">
              <AppIconButton
                v-if="meal.canManage"
                variant="primary"
                aria-label="Gericht hinzufügen"
                title="Gericht hinzufügen"
                @click="foodModal?.create(meal.id, meal.organizationId)"
              >
                <span class="icon-mask" :style="addFoodIconStyle" />
              </AppIconButton>
              <AppButton
                v-if="meal.acceptsContributions"
                size="small"
                variant="secondary"
                @click="suggestModal?.open(meal.id)"
              >+ Vorschlagen</AppButton>
            </div>
          </div>

          <ul v-if="meal.foods.length" class="meal-food-list">
            <li
              v-for="item in meal.foods"
              :key="item.id"
              class="meal-food"
              :class="{ 'meal-food--orderable': item.isOrderable }"
            >
              <MealFoodRow
                :title="item.title"
                :preference="item.preference"
                :supplier="item.supplier"
                :max-quantity="item.maxQuantity"
                :orderable="item.isOrderable"
                :can-order="meal.userResponse === 'Accept'"
                :quantity="userOrders[item.id] ?? 0"
                @increment="changeQty(item, 1)"
                @decrement="changeQty(item, -1)"
              >
                <template v-if="!item.isOrderable" #leading>
                  <span
                    class="food-status-dot"
                    :class="`food-status-dot--${(item.status || 'new').toLowerCase()}`"
                    :title="statusLabel(item.status)"
                    role="img"
                    :aria-label="statusLabel(item.status)"
                  ></span>
                </template>

                <template v-if="item.isOrderable && meal.canManage" #trailing>
                  <AppIconButton
                    variant="primary"
                    aria-label="Gericht bearbeiten"
                    @click="foodModal?.open(item)"
                  >
                    <span class="icon-mask" :style="editIconStyle" />
                  </AppIconButton>
                  <AppIconButton
                    variant="danger"
                    aria-label="Produkt löschen"
                    :disabled="deletingProductId === item.id"
                    @click="deleteProduct(item.id)"
                  >×</AppIconButton>
                </template>
                <template v-else-if="!item.isOrderable && (meal.canApprove || meal.canManage)" #trailing>
                  <AppIconButton
                    variant="primary"
                    aria-label="Gericht bearbeiten"
                    @click="foodModal?.open(item)"
                  >
                    <span class="icon-mask" :style="editIconStyle" />
                  </AppIconButton>
                  <AppIconButton
                    v-if="!(item.status === 'New' && meal.canApprove) && meal.canManage"
                    variant="danger"
                    aria-label="Gericht löschen"
                    :disabled="deletingProductId === item.id"
                    @click="deleteProduct(item.id)"
                  >×</AppIconButton>
                </template>

                <template v-if="item.isOrderable && item.totalOrdered > 0" #footer>
                  <span class="meal-product-item_total">{{ item.totalOrdered }}× bestellt</span>
                  <span
                    v-for="o in item.orders"
                    :key="o.memberId"
                    class="meal-product-item_order"
                  >{{ o.name }} ({{ o.quantity }})</span>
                </template>
                <template v-else-if="!item.isOrderable && item.status === 'New' && meal.canApprove" #footer>
                  <AppButton
                    size="small"
                    variant="primary"
                    :disabled="decidingFoodId === item.id"
                    @click="decideFood(item.id, 'Accepted')"
                  >Bestätigen</AppButton>
                  <AppButton
                    size="small"
                    variant="secondary"
                    :disabled="decidingFoodId === item.id"
                    @click="decideFood(item.id, 'Rejected')"
                  >Ablehnen</AppButton>
                </template>
              </MealFoodRow>
            </li>
          </ul>
          <p v-else class="meal-detail-empty">Noch keine Gerichte geplant.</p>
        </div>

        <FoodPreferenceLegend class="meal-detail-legend" :preferences="hasFoodPreferences" :allergies="hasAllergies" />

      </template>
    </div>

    <FoodSuggestModal ref="suggestModal" @suggested="onFoodSuggested" />
    <FoodFormModal
      ref="foodModal"
      :allow-orderable="meal?.canManage || meal?.canApprove"
      @created="onFoodCreated"
      @saved="onFoodEdited"
      @deleted="onFoodDeleted"
    />
    <MealFormModal ref="editModal" @saved="onMealSaved" @deleted="onMealDeleted" />
    <HistoryModal
      v-if="meal"
      ref="historyModal"
      :endpoint="`/food/mealHistory/${meal.id}`"
      created-label="hat die Mahlzeit erstellt"
    />
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { usePageHeaderStore } from '@stores/pageHeader'
import { apiGet, apiPost, apiPut, apiDelete } from '@utils/api'
import AppButton from '@components/ui/AppButton.vue'
import AppLinkifiedText from '@components/ui/AppLinkifiedText.vue'
import AppIconButton from '@components/ui/AppIconButton.vue'
import AppButtonGroup from '@components/ui/AppButtonGroup.vue'
import MealFoodRow from '@components/food/MealFoodRow.vue'
import AppOrgLogo from '@components/ui/AppOrgLogo.vue'
import ContextMenu from '@components/ui/ContextMenu.vue'
import { vContextMenu, profileMenuItem } from '@utils/contextMenu'
import { vRovingFocus } from '@utils/rovingFocus'
import ParticipantCard from '@components/calendar/ParticipantCard.vue'
import MealFormModal from '@components/food/MealFormModal.vue'
import FoodSuggestModal from '@components/food/FoodSuggestModal.vue'
import FoodFormModal from '@components/food/FoodFormModal.vue'
import FoodPreferenceLegend from '@components/food/FoodPreferenceLegend.vue'
import HistoryModal from '@components/history/HistoryModal.vue'
import actionHistory from '../../../icons/actions/action_history.svg'
import actionAddFood from '../../../icons/actions/action_addfood.svg'
import actionEdit from '../../../icons/actions/action_edit.svg'

const route = useRoute()
const router = useRouter()
usePageHeaderStore().setHeader('Mahlzeit', '')

const rsvpOptions = [
  { value: 'Decline', label: 'Absagen', tone: 'negative' },
  { value: 'Accept', label: 'Zusagen', tone: 'positive' },
]

const meal      = ref(null)
const loading   = ref(true)
const error     = ref(null)
const responding = ref(false)
const attendeeMenu = ref(null)
const suggestModal = ref(null)

// Products
const userOrders        = ref({})
const ordersSaving      = ref(false)
const ordersSaveTimer   = ref(null)
const foodModal         = ref(null)
const deletingProductId     = ref(null)
const decidingFoodId        = ref(null)
const editModal = ref(null)
const historyModal = ref(null)
const historyIconStyle = { maskImage: `url("${actionHistory}")`, WebkitMaskImage: `url("${actionHistory}")` }
const addFoodIconStyle = { maskImage: `url("${actionAddFood}")`, WebkitMaskImage: `url("${actionAddFood}")` }
const editIconStyle = { maskImage: `url("${actionEdit}")`, WebkitMaskImage: `url("${actionEdit}")` }

function openEditModal() {
  editModal.value?.open(meal.value)
}

function onMealSaved({ title, time, description, acceptsContributions }) {
  meal.value.title                = title
  meal.value.time                 = time
  meal.value.description          = description
  meal.value.acceptsContributions = acceptsContributions
  usePageHeaderStore().setHeader(meal.value.title, '')
}

// Die Mahlzeit gibt es nicht mehr → zurück zur Übersicht des Essens-Totems
function onMealDeleted() {
  router.push({ name: 'Food' })
}

async function load() {
  loading.value = true
  error.value   = null
  try {
    const data = await apiGet(`/food/mealdetail/${route.params.id}`, false)
    meal.value  = data.meal
    usePageHeaderStore().setHeader(data.meal.title, '')
    const initial = {}
    for (const f of data.meal.foods ?? []) {
      if (f.isOrderable) initial[f.id] = f.userQuantity
    }
    userOrders.value = initial
  } catch (e) {
    error.value = e.message
  } finally {
    loading.value = false
  }
}

async function respond(type) {
  if (responding.value) return
  responding.value = true
  try {
    await apiPost(`/calendar/participationFood/${meal.value.id}`, { response: type })
    meal.value.userResponse = type
    // Refresh attendees list by reloading
    const data = await apiGet(`/food/mealdetail/${route.params.id}`, false)
    meal.value = data.meal
  } catch (e) {
    alert('Fehler: ' + e.message)
  } finally {
    responding.value = false
  }
}

function toParticipation(a, type) {
  return {
    ID: a.id,
    MemberID: a.id,
    MemberName: a.name,
    Username: a.username,
    ProfileImageURL: a.avatarUrl,
    Type: type,
    Allergies: a.allergies,
    FoodPreference: a.preference,
  }
}

const groupedAttendees = computed(() => ({
  Accept: (meal.value?.attendees ?? []).map(a => toParticipation(a, 'Accept')),
  Decline: (meal.value?.declinedAttendees ?? []).map(a => toParticipation(a, 'Decline')),
  Pending: (meal.value?.pendingAttendees ?? []).map(a => toParticipation(a, 'Pending')),
}))

// Legende nur für das zeigen, was tatsächlich jemand hinterlegt hat
const allAttendees = computed(() => [
  ...(meal.value?.attendees ?? []),
  ...(meal.value?.declinedAttendees ?? []),
  ...(meal.value?.pendingAttendees ?? []),
])
const hasFoodPreferences = computed(() =>
  [...allAttendees.value, ...(meal.value?.foods ?? [])]
    .some(a => a.preference === 'Vegan' || a.preference === 'Vegetarian')
)
const hasAllergies = computed(() =>
  allAttendees.value.some(a => a.allergies?.length)
)

const hasAnyParticipants = computed(() => {
  const g = groupedAttendees.value
  return g.Accept.length > 0 || g.Decline.length > 0 || g.Pending.length > 0
})

// Menü: "Profil ansehen" für alle, darunter Antworten eintragen (nur mit FOOD_RECORD_RSVP)
function hasAttendeeMenu(participation) {
  return !!participation.Username || meal.value.canRecordRsvp
}

function onAttendeeContextMenu(event, participation) {
  const menuItems = [profileMenuItem(router, participation)].filter(Boolean)
  if (!meal.value.canRecordRsvp) {
    if (menuItems.length) attendeeMenu.value?.open(event, menuItems)
    return
  }

  const options = [
    { value: 'Accept', label: 'Zusagen' },
    { value: 'Decline', label: 'Absagen' },
  ].filter(o => o.value !== participation.Type)

  menuItems.push(...options.map(o => ({
    label: o.label,
    onClick: () => respondFor(participation.MemberID, o.value),
  })))

  if (participation.Type !== 'Pending') {
    menuItems.push({
      label: 'Antwort entfernen',
      danger: true,
      onClick: () => respondFor(participation.MemberID, null),
    })
  }

  attendeeMenu.value?.open(event, menuItems)
}

async function respondFor(targetMemberId, type) {
  try {
    await apiPost(`/calendar/participationFood/${meal.value.id}`, { response: type, targetMemberId })
    const data = await apiGet(`/food/mealdetail/${route.params.id}`, false)
    meal.value = data.meal
  } catch (e) {
    alert('Fehler: ' + e.message)
  }
}

function onFoodSuggested({ food }) {
  meal.value.foods.push(food)
}

const rsvpClass = computed(() => {
  if (meal.value?.userResponse === 'Accept')  return 'text-accept'
  if (meal.value?.userResponse === 'Decline') return 'text-decline'
  return 'text-pending'
})

const rsvpLabel = computed(() => {
  if (meal.value?.userResponse === 'Accept')  return 'Zugesagt'
  if (meal.value?.userResponse === 'Decline') return 'Abgesagt'
  return 'Keine Rückmeldung'
})

function statusLabel(status) {
  return { New: 'Neu vorgeschlagen', Accepted: 'Angenommen', Rejected: 'Abgelehnt' }[status] ?? status
}

function formatDate(dateStr) {
  if (!dateStr) return ''
  const date     = new Date(dateStr + 'T00:00:00')
  const today    = new Date(); today.setHours(0, 0, 0, 0)
  const tomorrow = new Date(today); tomorrow.setDate(today.getDate() + 1)
  if (date.getTime() === today.getTime())    return 'Heute'
  if (date.getTime() === tomorrow.getTime()) return 'Morgen'
  return new Intl.DateTimeFormat('de-DE', {
    weekday: 'long', day: '2-digit', month: '2-digit', year: 'numeric',
  }).format(date)
}

function changeQty(product, delta) {
  const current = userOrders.value[product.id] ?? 0
  let next = current + delta
  if (next < 0) next = 0
  if (product.maxQuantity > 0 && next > product.maxQuantity) next = product.maxQuantity
  userOrders.value = { ...userOrders.value, [product.id]: next }

  clearTimeout(ordersSaveTimer.value)
  ordersSaveTimer.value = setTimeout(saveOrders, 1000)
}

async function saveOrders() {
  if (ordersSaving.value) return
  ordersSaving.value = true
  try {
    await apiPut(`/food/mealProductOrder/${meal.value.id}`, { orders: userOrders.value })
    await load()
  } catch (e) {
    alert('Fehler: ' + e.message)
  } finally {
    ordersSaving.value = false
  }
}

function onFoodCreated(product) {
  meal.value.foods.push(product)
  if (product.isOrderable) {
    userOrders.value = { ...userOrders.value, [product.id]: 0 }
  }
}

async function decideFood(foodId, status) {
  if (decidingFoodId.value) return
  decidingFoodId.value = foodId
  try {
    await apiPut(`/food/foodStatus/${foodId}`, { status })
    if (status === 'Accepted') {
      const item = meal.value.foods.find(f => f.id === foodId)
      if (item) item.status = 'Accepted'
    } else {
      meal.value.foods = meal.value.foods.filter(f => f.id !== foodId)
    }
  } catch (e) {
    alert('Fehler: ' + e.message)
  } finally {
    decidingFoodId.value = null
  }
}

async function deleteProduct(productId) {
  if (!confirm('Gericht wirklich löschen?')) return
  deletingProductId.value = productId
  try {
    await apiDelete(`/food/mealProduct/${productId}`)
    meal.value.foods = meal.value.foods.filter(f => f.id !== productId)
    const { [productId]: _, ...rest } = userOrders.value
    userOrders.value = rest
  } catch (e) {
    alert('Fehler: ' + e.message)
  } finally {
    deletingProductId.value = null
  }
}

// Das Modal antwortet je nach Gericht im Format des Essensplaners oder der Produkt-API —
// beide enthalten nur Felder, die auch die Gerichte-Liste kennt. Wurde das Gericht
// bestellbar (oder wieder fest), fehlen Bestellungen bzw. Status → neu laden.
function onFoodEdited(updated) {
  const item = meal.value.foods.find(f => f.id === updated.id)
  if (!item) return
  if (item.isOrderable !== updated.isOrderable) {
    load()
    return
  }
  Object.assign(item, updated)
}

function onFoodDeleted(foodId) {
  meal.value.foods = meal.value.foods.filter(f => f.id !== foodId)
}

onMounted(load)
</script>
