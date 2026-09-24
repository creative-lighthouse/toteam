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
              <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
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
            @select="respond"
          />
        </div>

        <!-- Teilnehmer -->
        <div v-if="hasAnyParticipants" class="section_infobox participants-section">
          <h3 class="event-participation_title">Teilnehmer</h3>
          <div class="participants-list">
            <template v-if="groupedAttendees.Accept.length">
              <h5 class="participant-group_title">Zugesagt <span>({{ groupedAttendees.Accept.length }})</span></h5>
              <ParticipantCard
                v-for="p in groupedAttendees.Accept"
                :key="p.ID"
                :participation="p"
                @contextmenu="onAttendeeContextMenu($event, p)"
              />
            </template>

            <template v-if="groupedAttendees.Decline.length">
              <h5 class="participant-group_title">Abgesagt <span>({{ groupedAttendees.Decline.length }})</span></h5>
              <ParticipantCard
                v-for="p in groupedAttendees.Decline"
                :key="p.ID"
                :participation="p"
                @contextmenu="onAttendeeContextMenu($event, p)"
              />
            </template>

            <template v-if="groupedAttendees.Pending.length">
              <h5 class="participant-group_title">Ohne Antwort <span>({{ groupedAttendees.Pending.length }})</span></h5>
              <ParticipantCard
                v-for="p in groupedAttendees.Pending"
                :key="p.ID"
                :participation="p"
                @contextmenu="onAttendeeContextMenu($event, p)"
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
                @click="foodCreateModal?.open(meal.id)"
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
              <div v-if="editingFoodId === item.id" class="meal-food-edit-form">
                <input
                  v-model="editFoodTitle"
                  type="text"
                  class="form-control"
                  placeholder="Titel"
                  aria-label="Titel des Gerichts"
                  @keyup.enter="saveFoodEdit(item.id)"
                />
                <label class="checkbox-label">
                  <input type="checkbox" v-model="editFoodOrderable" aria-label="Bestellbar (Menge pro Person begrenzbar)" />
                  Bestellbar (Menge pro Person begrenzbar)
                </label>
                <div v-if="editFoodOrderable" class="meal-product-add-row">
                  <label>
                    Max. pro Person (0 = unbegrenzt)
                    <input
                      v-model.number="editFoodMax"
                      type="number"
                      min="0"
                      class="form-control"
                      aria-label="Max. pro Person"
                    />
                  </label>
                </div>
                <div class="meal-product-add-row">
                  <AppButton
                    variant="primary"
                    :disabled="!editFoodTitle.trim() || editFoodSaving"
                    @click="saveFoodEdit(item.id)"
                  >{{ editFoodSaving ? '…' : 'Speichern' }}</AppButton>
                  <AppButton variant="secondary" :disabled="editFoodSaving" @click="cancelFoodEdit">Abbrechen</AppButton>
                </div>
              </div>
              <MealCard
                v-else
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
                  ></span>
                </template>

                <template v-if="item.isOrderable && meal.canManage" #trailing>
                  <AppIconButton
                    variant="primary"
                    aria-label="Gericht bearbeiten"
                    @click="startFoodEdit(item)"
                  >
                    <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                  </AppIconButton>
                  <AppIconButton
                    variant="danger"
                    aria-label="Produkt löschen"
                    :disabled="deletingProductId === item.id"
                    @click="deleteProduct(item.id)"
                  >×</AppIconButton>
                </template>
                <template v-else-if="!item.isOrderable && !(item.status === 'New' && meal.canApprove) && meal.canManage" #trailing>
                  <AppIconButton
                    variant="primary"
                    aria-label="Gericht bearbeiten"
                    @click="startFoodEdit(item)"
                  >
                    <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                  </AppIconButton>
                  <AppIconButton
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
              </MealCard>
            </li>
          </ul>
          <p v-else class="meal-card_empty">Noch keine Gerichte geplant.</p>
        </div>

      </template>
    </div>

    <FoodSuggestModal ref="suggestModal" @suggested="onFoodSuggested" />
    <FoodCreateModal ref="foodCreateModal" @created="onFoodCreated" />
    <MealFormModal ref="editModal" @saved="onMealSaved" />
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
import { useRoute } from 'vue-router'
import { usePageHeaderStore } from '@stores/pageHeader'
import { apiGet, apiPost, apiPut, apiDelete } from '@utils/api'
import AppButton from '@components/ui/AppButton.vue'
import AppLinkifiedText from '@components/ui/AppLinkifiedText.vue'
import AppIconButton from '@components/ui/AppIconButton.vue'
import AppButtonGroup from '@components/ui/AppButtonGroup.vue'
import MealCard from '@components/food/MealCard.vue'
import AppOrgLogo from '@components/ui/AppOrgLogo.vue'
import ContextMenu from '@components/ui/ContextMenu.vue'
import ParticipantCard from '@components/calendar/ParticipantCard.vue'
import MealFormModal from '@components/food/MealFormModal.vue'
import FoodSuggestModal from '@components/food/FoodSuggestModal.vue'
import FoodCreateModal from '@components/food/FoodCreateModal.vue'
import HistoryModal from '@components/history/HistoryModal.vue'
import actionHistory from '../../../icons/actions/action_history.svg'
import actionAddFood from '../../../icons/actions/action_addfood.svg'

const route = useRoute()
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
const foodCreateModal   = ref(null)
const deletingProductId     = ref(null)
const decidingFoodId        = ref(null)
const editingFoodId       = ref(null)
const editFoodTitle       = ref('')
const editFoodOrderable   = ref(false)
const editFoodMax         = ref(0)
const editFoodSaving      = ref(false)
const editModal = ref(null)
const historyModal = ref(null)
const historyIconStyle = { maskImage: `url("${actionHistory}")`, WebkitMaskImage: `url("${actionHistory}")` }
const addFoodIconStyle = { maskImage: `url("${actionAddFood}")`, WebkitMaskImage: `url("${actionAddFood}")` }

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
    ProfileImageURL: a.avatarUrl,
    Type: type,
    Allergies: a.allergies,
  }
}

const groupedAttendees = computed(() => ({
  Accept: (meal.value?.attendees ?? []).map(a => toParticipation(a, 'Accept')),
  Decline: (meal.value?.declinedAttendees ?? []).map(a => toParticipation(a, 'Decline')),
  Pending: (meal.value?.pendingAttendees ?? []).map(a => toParticipation(a, 'Pending')),
}))

const hasAnyParticipants = computed(() => {
  const g = groupedAttendees.value
  return g.Accept.length > 0 || g.Decline.length > 0 || g.Pending.length > 0
})

function onAttendeeContextMenu(event, participation) {
  if (!meal.value.canRecordRsvp) return

  const options = [
    { value: 'Accept', label: 'Zusagen' },
    { value: 'Decline', label: 'Absagen' },
  ].filter(o => o.value !== participation.Type)

  const menuItems = options.map(o => ({
    label: o.label,
    onClick: () => respondFor(participation.MemberID, o.value),
  }))

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

function startFoodEdit(item) {
  editingFoodId.value     = item.id
  editFoodTitle.value     = item.title
  editFoodOrderable.value = item.isOrderable
  editFoodMax.value       = item.maxQuantity ?? 0
}

function cancelFoodEdit() {
  editingFoodId.value = null
}

async function saveFoodEdit(foodId) {
  if (!editFoodTitle.value.trim() || editFoodSaving.value) return
  editFoodSaving.value = true
  try {
    await apiPut(`/food/mealProduct/${foodId}`, {
      title: editFoodTitle.value.trim(),
      isOrderable: editFoodOrderable.value,
      maxQuantity: editFoodMax.value ?? 0,
    })
    editingFoodId.value = null
    await load()
  } catch (e) {
    alert('Fehler: ' + e.message)
  } finally {
    editFoodSaving.value = false
  }
}

onMounted(load)
</script>
