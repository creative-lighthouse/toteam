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
          <div v-if="meal.organizationLogoUrl" class="meal-detail-hero_logo">
            <img :src="meal.organizationLogoUrl" :alt="meal.organizationTitle" />
          </div>
          <div class="meal-detail-hero_info">
            <p class="meal-detail-hero_date">{{ formatDate(meal.date) }} • {{ meal.time }} Uhr</p>
            <h2 class="meal-detail-hero_title">{{ meal.title }}</h2>
            <p class="meal-detail-hero_sub">
              {{ meal.appointmentTitle }}
              <span v-if="meal.organizationTitle"> • {{ meal.organizationTitle }}</span>
            </p>
          </div>
        </div>

        <!-- Description -->
        <div v-if="meal.description || meal.canManage" class="section_infobox meal-description">
          <div v-if="!descriptionEditing" class="meal-description_view">
            <p v-if="meal.description" class="meal-description_text">{{ meal.description }}</p>
            <p v-else class="meal-card_empty">Keine Beschreibung vorhanden.</p>
            <AppButton
              v-if="meal.canManage"
              size="small"
              variant="secondary"
              @click="startDescriptionEdit"
            >Bearbeiten</AppButton>
          </div>
          <div v-else class="meal-description_edit">
            <textarea
              v-model="descriptionDraft"
              class="form-control meal-description_textarea"
              rows="4"
              placeholder="Beschreibung der Mahlzeit…"
            ></textarea>
            <div class="meal-description_edit-actions">
              <AppButton variant="primary" :disabled="descriptionSaving" @click="saveDescription">
                {{ descriptionSaving ? '…' : 'Speichern' }}
              </AppButton>
              <AppButton variant="secondary" @click="cancelDescriptionEdit">Abbrechen</AppButton>
            </div>
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

        <!-- Attendees -->
        <div v-if="meal.attendees.length" class="section_infobox">
          <h3 class="hl3">Wer ist dabei ({{ meal.attendees.length }})</h3>
          <ul class="meal-attendee-list">
            <li v-for="a in meal.attendees" :key="a.id" class="meal-attendee">
              <img v-if="a.avatarUrl" :src="a.avatarUrl" :alt="a.name" class="meal-attendee_avatar" />
              <span v-else class="meal-attendee_avatar meal-attendee_avatar--placeholder">{{ a.name[0] }}</span>
              <span class="meal-attendee_name">{{ a.name }}</span>
              <span
                v-for="allergy in a.allergies"
                :key="allergy"
                class="allergy-pill"
              >{{ allergy }}</span>
            </li>
          </ul>
        </div>

        <!-- Geplante Gerichte (orderable + regular combined) -->
        <div class="section_infobox">
          <div class="meal-detail-block_heading-row">
            <h3 class="hl3">Geplante Gerichte ({{ meal.foods.length }})</h3>
            <div class="meal-detail-heading-actions">
              <AppButton
                v-if="meal.canManage"
                size="small"
                variant="secondary"
                @click="addProductOpen = !addProductOpen"
              >{{ addProductOpen ? '× Abbrechen' : '+ Gericht' }}</AppButton>
              <AppButton
                v-if="meal.acceptsContributions"
                size="small"
                variant="secondary"
                @click="modalOpen = true"
              >+ Vorschlagen</AppButton>
            </div>
          </div>

          <!-- Add product form (admin/mod) -->
          <div v-if="addProductOpen" class="meal-product-add-form">
            <input
              v-model="addProductTitle"
              type="text"
              placeholder="Bezeichnung (z.B. Nudelsalat)"
              class="form-control"
              @keyup.enter="submitProduct"
            />
            <label class="checkbox-label">
              <input type="checkbox" v-model="addProductOrderable" aria-label="Bestellbar (Menge pro Person begrenzbar)" />
              Bestellbar (Menge pro Person begrenzbar)
            </label>
            <div v-if="addProductOrderable" class="meal-product-add-row">
              <label>
                Max. pro Person (0 = unbegrenzt)
                <input
                  v-model.number="addProductMax"
                  type="number"
                  min="0"
                  placeholder="0 = unbegrenzt"
                  class="form-control"
                />
              </label>
            </div>
            <div class="meal-product-add-row">
              <AppButton
                variant="primary"
                :disabled="!addProductTitle.trim() || addProductSaving"
                @click="submitProduct"
              >{{ addProductSaving ? '…' : 'Speichern' }}</AppButton>
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
                  >✎</AppIconButton>
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
                  >✎</AppIconButton>
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

        <div class="section_infobox_footer">
          <router-link to="/food">← Zum Essensplan</router-link>
        </div>

      </template>
    </div>

    <!-- Gericht vorschlagen Modal -->
    <Transition name="food-modal">
      <div v-if="modalOpen" class="food-modal-overlay" @click.self="modalOpen = false">
        <div class="food-modal" role="dialog" aria-modal="true">
          <div class="food-modal_header">
            <h3>Gericht vorschlagen</h3>
            <AppIconButton variant="ghost" aria-label="Schließen" @click="modalOpen = false">
              <svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor">
                <path d="M3.72 3.72a.75.75 0 011.06 0L8 6.94l3.22-3.22a.75.75 0 111.06 1.06L9.06 8l3.22 3.22a.75.75 0 11-1.06 1.06L8 9.06l-3.22 3.22a.75.75 0 01-1.06-1.06L6.94 8 3.72 4.78a.75.75 0 010-1.06z"/>
              </svg>
            </AppIconButton>
          </div>
          <div class="food-modal_body">
            <div class="edit-field">
              <label for="mealDetailTitle">Name des Gerichts *</label>
              <input
                id="mealDetailTitle"
                v-model="modalForm.title"
                type="text"
                class="form-control"
                placeholder="z.B. Nudelsalat"
                @keyup.enter="submitSuggestion"
              />
            </div>
            <div class="edit-field">
              <label for="mealDetailPref">Essenspräferenz</label>
              <select id="mealDetailPref" v-model="modalForm.preference" class="form-control">
                <option value="None">Keine Angabe</option>
                <option value="Vegetarian">🥗 Vegetarisch</option>
                <option value="Vegan">🌱 Vegan</option>
              </select>
            </div>
          </div>
          <div class="food-modal_actions">
            <AppButton variant="secondary" @click="modalOpen = false">Abbrechen</AppButton>
            <AppButton
              variant="primary"
              :disabled="!modalForm.title.trim() || modalForm.submitting"
              @click="submitSuggestion"
            >{{ modalForm.submitting ? 'Wird eingereicht…' : 'Vorschlagen' }}</AppButton>
          </div>
        </div>
      </div>
    </Transition>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import { usePageHeaderStore } from '@stores/pageHeader'
import { apiGet, apiPost, apiPut, apiDelete } from '@utils/api'
import AppButton from '@components/AppButton.vue'
import AppIconButton from '@components/AppIconButton.vue'
import AppButtonGroup from '@components/AppButtonGroup.vue'
import MealCard from '@components/EventDialog/MealCard.vue'

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
const modalOpen = ref(false)
const modalForm = ref({ title: '', preference: 'None', submitting: false })

// Products
const userOrders        = ref({})
const ordersSaving      = ref(false)
const ordersSaveTimer   = ref(null)
const addProductOpen    = ref(false)
const addProductTitle   = ref('')
const addProductOrderable = ref(false)
const addProductMax     = ref(0)
const addProductSaving  = ref(false)
const deletingProductId     = ref(null)
const decidingFoodId        = ref(null)
const editingFoodId       = ref(null)
const editFoodTitle       = ref('')
const editFoodOrderable   = ref(false)
const editFoodMax         = ref(0)
const editFoodSaving      = ref(false)
const descriptionEditing    = ref(false)
const descriptionDraft      = ref('')
const descriptionSaving     = ref(false)

function startDescriptionEdit() {
  descriptionDraft.value  = meal.value.description ?? ''
  descriptionEditing.value = true
}

function cancelDescriptionEdit() {
  descriptionEditing.value = false
}

async function saveDescription() {
  if (descriptionSaving.value) return
  descriptionSaving.value = true
  try {
    await apiPut(`/food/mealDescription/${meal.value.id}`, { description: descriptionDraft.value })
    meal.value.description   = descriptionDraft.value
    descriptionEditing.value = false
  } catch (e) {
    alert('Fehler: ' + e.message)
  } finally {
    descriptionSaving.value = false
  }
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

async function submitSuggestion() {
  const form = modalForm.value
  if (!form.title.trim() || form.submitting) return
  form.submitting = true
  try {
    const result = await apiPost(`/food/suggest/${meal.value.id}`, {
      title: form.title.trim(), preference: form.preference,
    })
    meal.value.foods.push(result.data.food)
    modalOpen.value  = false
    form.title       = ''
    form.preference  = 'None'
  } catch (e) {
    alert('Fehler: ' + e.message)
  } finally {
    form.submitting = false
  }
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

async function submitProduct() {
  if (!addProductTitle.value.trim() || addProductSaving.value) return
  addProductSaving.value = true
  try {
    const result = await apiPost(`/food/mealProduct/${meal.value.id}`, {
      title: addProductTitle.value.trim(),
      isOrderable: addProductOrderable.value,
      maxQuantity: addProductMax.value ?? 0,
    })
    meal.value.foods.push(result.data.product)
    if (result.data.product.isOrderable) {
      userOrders.value = { ...userOrders.value, [result.data.product.id]: 0 }
    }
    addProductTitle.value = ''
    addProductOrderable.value = false
    addProductMax.value   = 0
    addProductOpen.value  = false
  } catch (e) {
    alert('Fehler: ' + e.message)
  } finally {
    addProductSaving.value = false
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
