<template>
  <div class="section section--MealDetail">
    <div class="section_content">

      <div v-if="loading" class="section_infobox">
        <p>Lade Mahlzeit…</p>
      </div>

      <div v-else-if="error" class="section_infobox error">
        <p>{{ error }}</p>
        <router-link to="/food" class="button">← Zurück</router-link>
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
            <button
              v-if="meal.canManage"
              class="meal-description_edit-btn"
              @click="startDescriptionEdit"
              aria-label="Beschreibung bearbeiten"
            >Bearbeiten</button>
          </div>
          <div v-else class="meal-description_edit">
            <textarea
              v-model="descriptionDraft"
              class="form-control meal-description_textarea"
              rows="4"
              placeholder="Beschreibung der Mahlzeit…"
            ></textarea>
            <div class="meal-description_edit-actions">
              <button class="button" :disabled="descriptionSaving" @click="saveDescription">
                {{ descriptionSaving ? '…' : 'Speichern' }}
              </button>
              <button class="button button--secondary" @click="cancelDescriptionEdit">Abbrechen</button>
            </div>
          </div>
        </div>

        <!-- RSVP -->
        <div class="section_infobox meal-rsvp">
          <div class="meal-rsvp_status">
            Deine Antwort:
            <strong :class="rsvpClass">{{ rsvpLabel }}</strong>
          </div>
          <div class="meal-rsvp_actions">
            <button
              v-if="meal.userResponse !== 'Accept'"
              class="button"
              :disabled="responding"
              @click="respond('Accept')"
            >Zusagen</button>
            <button
              v-if="meal.userResponse !== 'Decline'"
              class="button button--secondary"
              :disabled="responding"
              @click="respond('Decline')"
            >Absagen</button>
          </div>
        </div>

        <!-- Attendees -->
        <div v-if="meal.attendees.length" class="section_infobox">
          <h3 class="hl3">Wer ist dabei ({{ meal.attendees.length }})</h3>
          <ul class="meal-attendee-list">
            <li v-for="a in meal.attendees" :key="a.id" class="meal-attendee">
              <img v-if="a.avatarUrl" :src="a.avatarUrl" :alt="a.name" class="meal-attendee_avatar" />
              <span v-else class="meal-attendee_avatar meal-attendee_avatar--placeholder">{{ a.name[0] }}</span>
              <span>{{ a.name }}</span>
            </li>
          </ul>
        </div>

        <!-- Geplante Gerichte (orderable + regular combined) -->
        <div class="section_infobox">
          <div class="meal-detail-block_heading-row">
            <h3 class="hl3">Geplante Gerichte ({{ allFoods.length }})</h3>
            <div class="meal-detail-heading-actions">
              <button
                v-if="meal.canManage"
                class="meal-suggest-btn"
                @click="addProductOpen = !addProductOpen"
              >{{ addProductOpen ? '× Abbrechen' : '+ Gericht' }}</button>
              <button
                v-if="meal.acceptsContributions"
                class="meal-suggest-btn"
                @click="modalOpen = true"
              >+ Vorschlagen</button>
            </div>
          </div>

          <!-- Add orderable product form (admin/mod) -->
          <div v-if="addProductOpen" class="meal-product-add-form">
            <input
              v-model="addProductTitle"
              type="text"
              placeholder="Bezeichnung (z.B. Burger)"
              class="form-control"
              @keyup.enter="submitProduct"
            />
            <div class="meal-product-add-row">
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
              <button
                class="button"
                :disabled="!addProductTitle.trim() || addProductSaving"
                @click="submitProduct"
              >{{ addProductSaving ? '…' : 'Speichern' }}</button>
            </div>
          </div>

          <ul v-if="allFoods.length" class="meal-food-list">
            <li
              v-for="item in allFoods"
              :key="item.id"
              class="meal-food"
              :class="{ 'meal-food--orderable': item.isOrderable }"
            >
              <template v-if="item.isOrderable">
                <span class="meal-food_title">{{ item.title }}</span>
                <span v-if="item.maxQuantity > 0" class="meal-product-item_max">max. {{ item.maxQuantity }} pro Person</span>
                <div class="meal-food_right">
                  <div v-if="meal.userResponse === 'Accept'" class="meal-product-qty">
                    <button
                      class="meal-product-qty_btn"
                      :disabled="(userOrders[item.id] ?? 0) <= 0"
                      @click="changeQty(item, -1)"
                    >−</button>
                    <span class="meal-product-qty_val">{{ userOrders[item.id] ?? 0 }}</span>
                    <button
                      class="meal-product-qty_btn"
                      :disabled="item.maxQuantity > 0 && (userOrders[item.id] ?? 0) >= item.maxQuantity"
                      @click="changeQty(item, 1)"
                    >+</button>
                  </div>
                  <button
                    v-if="meal.canManage"
                    class="meal-product-delete-btn"
                    :disabled="deletingProductId === item.id"
                    @click="deleteProduct(item.id)"
                    aria-label="Produkt löschen"
                  >×</button>
                </div>
                <div v-if="item.totalOrdered > 0" class="meal-product-item_summary meal-food_full-row">
                  <span class="meal-product-item_total">{{ item.totalOrdered }}× bestellt</span>
                  <span
                    v-for="o in item.orders"
                    :key="o.memberId"
                    class="meal-product-item_order"
                  >{{ o.name }} ({{ o.quantity }})</span>
                </div>
              </template>
              <template v-else>
                <span
                  class="food-status-dot"
                  :class="`food-status-dot--${(item.status || 'new').toLowerCase()}`"
                  :title="statusLabel(item.status)"
                ></span>
                <span class="meal-food_title">{{ item.title }}</span>
                <span v-if="item.preference !== 'None'" class="meal-food_pref">
                  {{ item.preference === 'Vegetarian' ? '🥗 Vegetarisch' : '🌱 Vegan' }}
                </span>
                <span v-if="item.supplier" class="meal-food_supplier">von {{ item.supplier }}</span>
              </template>
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
            <button class="food-modal_close" aria-label="Schließen" @click="modalOpen = false">
              <svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor">
                <path d="M3.72 3.72a.75.75 0 011.06 0L8 6.94l3.22-3.22a.75.75 0 111.06 1.06L9.06 8l3.22 3.22a.75.75 0 11-1.06 1.06L8 9.06l-3.22 3.22a.75.75 0 01-1.06-1.06L6.94 8 3.72 4.78a.75.75 0 010-1.06z"/>
              </svg>
            </button>
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
            <button class="button button--secondary" @click="modalOpen = false">Abbrechen</button>
            <button
              class="button"
              :disabled="!modalForm.title.trim() || modalForm.submitting"
              @click="submitSuggestion"
            >{{ modalForm.submitting ? 'Wird eingereicht…' : 'Vorschlagen' }}</button>
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

const route = useRoute()
usePageHeaderStore().setHeader('Mahlzeit', '')

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
const addProductMax     = ref(0)
const addProductSaving  = ref(false)
const deletingProductId     = ref(null)
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

const allFoods = computed(() => {
  if (!meal.value) return []
  const orderable = (meal.value.products ?? []).map(p => ({ ...p, isOrderable: true }))
  const regular   = (meal.value.foods ?? []).map(f => ({ ...f, isOrderable: false }))
  return [...orderable, ...regular].sort((a, b) => a.id - b.id)
})

async function load() {
  loading.value = true
  error.value   = null
  try {
    const data = await apiGet(`/food/mealdetail/${route.params.id}`, false)
    meal.value  = data.meal
    usePageHeaderStore().setHeader(data.meal.title, '')
    const initial = {}
    for (const p of data.meal.products ?? []) initial[p.id] = p.userQuantity
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
      maxQuantity: addProductMax.value ?? 0,
    })
    meal.value.products.push(result.data.product)
    userOrders.value = { ...userOrders.value, [result.data.product.id]: 0 }
    addProductTitle.value = ''
    addProductMax.value   = 0
    addProductOpen.value  = false
  } catch (e) {
    alert('Fehler: ' + e.message)
  } finally {
    addProductSaving.value = false
  }
}

async function deleteProduct(productId) {
  if (!confirm('Produkt wirklich löschen?')) return
  deletingProductId.value = productId
  try {
    await apiDelete(`/food/mealProduct/${productId}`)
    meal.value.products = meal.value.products.filter(p => p.id !== productId)
    const { [productId]: _, ...rest } = userOrders.value
    userOrders.value = rest
  } catch (e) {
    alert('Fehler: ' + e.message)
  } finally {
    deletingProductId.value = null
  }
}

onMounted(load)
</script>
