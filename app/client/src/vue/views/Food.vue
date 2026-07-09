<template>
  <div class="section section--FoodPage" :class="{ 'has-food-nav': canManage }">
    <div class="section_content">

      <div v-if="loading" class="section_infobox"><p>Lade Essensplan…</p></div>

      <div v-else-if="error" class="section_infobox error">
        <p>Fehler: {{ error }}</p>
        <button class="button" @click="load">Erneut versuchen</button>
      </div>

      <template v-else>

        <!-- ── Essensinfos ─────────────────────────────────────────────── -->
        <template v-if="activeTab === 'info'">

          <!-- 1. Meine Vorschläge -->
          <section v-if="myFoods.length" class="meal-section">
            <h2 class="meal-section_title">Meine Vorschläge</h2>

            <div v-if="activeFoods.length" class="my-food-list">
              <div v-for="f in activeFoods" :key="`${f.id}-${f.mealId}`" class="my-food-item">
                <span class="food-status-dot" :class="`food-status-dot--${f.status.toLowerCase()}`" :title="statusLabel(f.status)"></span>
                <div class="my-food-info">
                  <span class="my-food-title">{{ f.title }}</span>
                  <span class="my-food-context">{{ f.mealTitle }} · {{ formatDate(f.date) }}, {{ f.mealTime }} Uhr · {{ f.organizationTitle }}</span>
                </div>
                <span class="my-food-badge" :class="`my-food-badge--${f.status.toLowerCase()}`">{{ statusLabel(f.status) }}</span>
              </div>
            </div>

            <div v-if="rejectedFoods.length">
              <button class="meal-section_toggle meal-section_toggle--compact" @click="showRejected = !showRejected">
                <span>Abgelehnte Vorschläge ({{ rejectedFoods.length }})</span>
                <svg :class="{ 'is-open': showRejected }" width="14" height="14" viewBox="0 0 16 16" fill="currentColor">
                  <path d="M4.427 7.427l3.396 3.396a.25.25 0 00.354 0l3.396-3.396A.25.25 0 0011.396 7H4.604a.25.25 0 00-.177.427z"/>
                </svg>
              </button>
              <div v-if="showRejected" class="my-food-list my-food-list--rejected">
                <div v-for="f in rejectedFoods" :key="`${f.id}-${f.mealId}`" class="my-food-item my-food-item--muted">
                  <span class="food-status-dot food-status-dot--rejected" title="Abgelehnt"></span>
                  <div class="my-food-info">
                    <span class="my-food-title">{{ f.title }}</span>
                    <span class="my-food-context">{{ f.mealTitle }} · {{ formatDate(f.date) }}, {{ f.mealTime }} Uhr · {{ f.organizationTitle }}</span>
                  </div>
                  <span class="my-food-badge my-food-badge--rejected">Abgelehnt</span>
                </div>
              </div>
            </div>
          </section>

          <!-- 2. Meine Zusagen -->
          <section class="meal-section">
            <h2 class="meal-section_title">Meine Zusagen</h2>
            <div v-if="acceptedMeals.length" class="meal-list">
              <MealCard
                v-for="meal in acceptedMeals" :key="meal.id"
                :meal="meal" :expanded="expanded.has(meal.id)"
                @toggle="toggle(meal.id)" @open-suggest-modal="openModal(meal.id)"
              />
            </div>
            <div v-else class="section_infobox"><p>Du hast noch keiner Mahlzeit zugesagt.</p></div>
          </section>

          <!-- 3. Weitere Mahlzeiten -->
          <section v-if="otherMeals.length" class="meal-section meal-section--other">
            <button class="meal-section_toggle" @click="showOther = !showOther">
              <span>Weitere Mahlzeiten ({{ otherMeals.length }})</span>
              <svg :class="{ 'is-open': showOther }" width="16" height="16" viewBox="0 0 16 16" fill="currentColor">
                <path d="M4.427 7.427l3.396 3.396a.25.25 0 00.354 0l3.396-3.396A.25.25 0 0011.396 7H4.604a.25.25 0 00-.177.427z"/>
              </svg>
            </button>
            <div v-if="showOther" class="meal-list meal-list--muted">
              <MealCard
                v-for="meal in otherMeals" :key="meal.id"
                :meal="meal" :expanded="expanded.has(meal.id)"
                @toggle="toggle(meal.id)" @open-suggest-modal="openModal(meal.id)"
              />
            </div>
          </section>

          <!-- 4. Vergangene Mahlzeiten -->
          <section v-if="pastMeals.length" class="meal-section meal-section--past">
            <button class="meal-section_toggle" @click="showPast = !showPast">
              <span>Vergangene Mahlzeiten ({{ pastMeals.length }})</span>
              <svg :class="{ 'is-open': showPast }" width="16" height="16" viewBox="0 0 16 16" fill="currentColor">
                <path d="M4.427 7.427l3.396 3.396a.25.25 0 00.354 0l3.396-3.396A.25.25 0 0011.396 7H4.604a.25.25 0 00-.177.427z"/>
              </svg>
            </button>
            <div v-if="showPast" class="meal-list meal-list--muted">
              <MealCard
                v-for="meal in pastMeals" :key="meal.id"
                :meal="meal" :expanded="expanded.has(meal.id)"
                @toggle="toggle(meal.id)" @open-suggest-modal="openModal(meal.id)"
              />
            </div>
          </section>

        </template>

        <!-- ── Essen planen: offene Vorschläge bestätigen ──────────────────── -->
        <template v-else>
          <section class="meal-section">
            <h2 class="meal-section_title">Offene Vorschläge</h2>

            <div v-if="pendingLoading" class="section_infobox"><p>Lade Vorschläge…</p></div>

            <div v-else-if="pendingFoods.length" class="my-food-list">
              <div v-for="f in pendingFoods" :key="f.id" class="my-food-item">
                <span class="food-status-dot food-status-dot--new" title="Neu vorgeschlagen"></span>
                <div class="my-food-info">
                  <span class="my-food-title">{{ f.title }}</span>
                  <span class="my-food-context">
                    {{ f.mealTitle }} · {{ formatDate(f.date) }}, {{ f.mealTime }} Uhr · {{ f.organizationTitle }}
                    <template v-if="f.supplier"> · von {{ f.supplier }}</template>
                  </span>
                </div>
                <div class="pending-food-actions">
                  <button
                    class="meal-suggest-btn"
                    :disabled="decidingFoodId === f.id"
                    @click="decidePending(f.id, 'Accepted')"
                  >Bestätigen</button>
                  <button
                    class="meal-suggest-btn meal-suggest-btn--reject"
                    :disabled="decidingFoodId === f.id"
                    @click="decidePending(f.id, 'Rejected')"
                  >Ablehnen</button>
                </div>
              </div>
            </div>

            <div v-else class="section_infobox"><p>Keine offenen Vorschläge.</p></div>
          </section>
        </template>

      </template>
    </div>

    <!-- ── Gericht-vorschlagen Modal ──────────────────────────────────── -->
    <Transition name="food-modal">
      <div v-if="modalMealId !== null" class="food-modal-overlay" @click.self="closeModal">
        <div class="food-modal" role="dialog" aria-modal="true">
          <div class="food-modal_header">
            <h3>Gericht vorschlagen</h3>
            <button class="food-modal_close" aria-label="Schließen" @click="closeModal">
              <svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor">
                <path d="M3.72 3.72a.75.75 0 011.06 0L8 6.94l3.22-3.22a.75.75 0 111.06 1.06L9.06 8l3.22 3.22a.75.75 0 11-1.06 1.06L8 9.06l-3.22 3.22a.75.75 0 01-1.06-1.06L6.94 8 3.72 4.78a.75.75 0 010-1.06z"/>
              </svg>
            </button>
          </div>
          <div class="food-modal_body">
            <div class="edit-field">
              <label for="modalTitle">Name des Gerichts *</label>
              <input id="modalTitle" v-model="modalForm.title" type="text" class="form-control"
                placeholder="z.B. Nudelsalat" @keyup.enter="submitModal" />
            </div>
            <div class="edit-field">
              <label for="modalPref">Essenspräferenz</label>
              <select id="modalPref" v-model="modalForm.preference" class="form-control">
                <option value="None">Keine Angabe</option>
                <option value="Vegetarian">🥗 Vegetarisch</option>
                <option value="Vegan">🌱 Vegan</option>
              </select>
            </div>
          </div>
          <div class="food-modal_actions">
            <button class="button button--secondary" @click="closeModal">Abbrechen</button>
            <button class="button" :disabled="!modalForm.title.trim() || modalForm.submitting" @click="submitModal">
              {{ modalForm.submitting ? 'Wird eingereicht…' : 'Vorschlagen' }}
            </button>
          </div>
        </div>
      </div>
    </Transition>

    <!-- ── Sticky bottom tab nav ─────────────────────────────────────── -->
    <nav v-if="canManage || canApprove" class="food-tab-nav">
      <button class="food-tab-nav_item" :class="{ 'is-active': activeTab === 'info' }" @click="activeTab = 'info'">
        <svg width="20" height="20" viewBox="0 0 16 16" fill="currentColor">
          <path d="M8 1a2 2 0 110 4 2 2 0 010-4zm0 6a5 5 0 100 10A5 5 0 008 7zm0 1.5a3.5 3.5 0 110 7 3.5 3.5 0 010-7z"/>
        </svg>
        Essensinfos
      </button>
      <button class="food-tab-nav_item" :class="{ 'is-active': activeTab === 'plan' }" @click="selectPlanTab">
        <svg width="20" height="20" viewBox="0 0 16 16" fill="currentColor">
          <path d="M1 2.75A.75.75 0 011.75 2h12.5a.75.75 0 010 1.5H1.75A.75.75 0 011 2.75zm0 5A.75.75 0 011.75 7h12.5a.75.75 0 010 1.5H1.75A.75.75 0 011 7.75zM1.75 12a.75.75 0 000 1.5h12.5a.75.75 0 000-1.5H1.75z"/>
        </svg>
        Essen planen
      </button>
    </nav>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, defineComponent, h } from 'vue'
import { useRouter } from 'vue-router'
import { usePageHeaderStore } from '@stores/pageHeader'
import { apiGet, apiPost, apiPut } from '@utils/api'

const router = useRouter()

usePageHeaderStore().setHeader('Essensplan', '')

// ── State ──────────────────────────────────────────────────────────────────

const acceptedMeals = ref([])
const otherMeals    = ref([])
const pastMeals     = ref([])
const myFoods       = ref([])
const canManage     = ref(false)
const canApprove      = ref(false)
const pendingFoods    = ref([])
const pendingLoading  = ref(false)
const pendingLoaded   = ref(false)
const decidingFoodId  = ref(null)
const loading       = ref(true)
const error         = ref(null)
const activeTab     = ref('info')
const showOther     = ref(false)
const showPast      = ref(false)
const showRejected  = ref(false)
const expanded      = ref(new Set())

const modalMealId = ref(null)
const modalForm   = ref({ title: '', preference: 'None', submitting: false })

// ── Computed ───────────────────────────────────────────────────────────────

const activeFoods   = computed(() => myFoods.value.filter(f => f.status !== 'Rejected'))
const rejectedFoods = computed(() => myFoods.value.filter(f => f.status === 'Rejected'))

// ── Helpers ────────────────────────────────────────────────────────────────

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

// ── Data loading ───────────────────────────────────────────────────────────

async function load() {
  loading.value = true
  error.value   = null
  try {
    const data          = await apiGet('/food', false)
    acceptedMeals.value = data.acceptedMeals || []
    otherMeals.value    = data.otherMeals    || []
    pastMeals.value     = data.pastMeals     || []
    myFoods.value       = data.myFoods       || []
    canManage.value     = data.canManage     || false
    canApprove.value    = data.canApprove    || false
  } catch (e) {
    error.value = e.message
  } finally {
    loading.value = false
  }
}

function selectPlanTab() {
  activeTab.value = 'plan'
  if (!pendingLoaded.value) loadPending()
}

async function loadPending() {
  pendingLoading.value = true
  try {
    const data = await apiGet('/food/pending', false)
    pendingFoods.value = data.pending || []
    pendingLoaded.value = true
  } catch (e) {
    console.error('Failed to load pending foods:', e)
  } finally {
    pendingLoading.value = false
  }
}

async function decidePending(foodId, status) {
  if (decidingFoodId.value) return
  decidingFoodId.value = foodId
  try {
    await apiPut(`/food/foodStatus/${foodId}`, { status })
    pendingFoods.value = pendingFoods.value.filter(f => f.id !== foodId)
  } catch (e) {
    alert('Fehler: ' + e.message)
  } finally {
    decidingFoodId.value = null
  }
}

// ── Interactions ───────────────────────────────────────────────────────────

function toggle(id) {
  const s = new Set(expanded.value)
  s.has(id) ? s.delete(id) : s.add(id)
  expanded.value = s
}

function openModal(mealId) {
  modalMealId.value = mealId
  modalForm.value   = { title: '', preference: 'None', submitting: false }
}

function closeModal() { modalMealId.value = null }

async function submitModal() {
  const form = modalForm.value
  if (!form.title.trim() || form.submitting) return
  form.submitting = true
  try {
    const result  = await apiPost(`/food/suggest/${modalMealId.value}`, {
      title: form.title.trim(), preference: form.preference,
    })
    const newFood = result.data.food
    // Update meal food list
    const all  = [...acceptedMeals.value, ...otherMeals.value, ...pastMeals.value]
    const meal = all.find(m => m.id === modalMealId.value)
    if (meal) meal.foods.push(newFood)
    // Add to myFoods
    const mealContext = all.find(m => m.id === modalMealId.value)
    if (mealContext) {
      myFoods.value.unshift({
        id: newFood.id, title: newFood.title, preference: newFood.preference,
        status: 'New',
        mealId: mealContext.id, mealTitle: mealContext.title, mealTime: mealContext.time,
        date: mealContext.date, appointmentTitle: mealContext.appointmentTitle,
        organizationTitle: mealContext.organizationTitle,
        organizationLogoUrl: mealContext.organizationLogoUrl,
      })
    }
    closeModal()
  } catch (e) {
    alert('Fehler beim Vorschlagen: ' + e.message)
    form.submitting = false
  }
}

onMounted(load)

// ── MealCard sub-component ─────────────────────────────────────────────────

const MealCard = defineComponent({
  name: 'MealCard',
  props: {
    meal:     { type: Object,  required: true },
    expanded: { type: Boolean, default: false },
  },
  emits: ['toggle', 'open-suggest-modal'],
  setup(props, { emit }) {
    return () => {
      const m = props.meal

      const orgLogo = m.organizationLogoUrl
        ? h('img', { src: m.organizationLogoUrl, alt: m.organizationTitle })
        : h('span', { class: 'meal-card_org-initial' }, (m.organizationTitle || '?')[0])

      const header = h('button', { class: 'meal-card_header', onClick: () => emit('toggle') }, [
        h('div', { class: 'meal-card_org-logo' }, [orgLogo]),
        h('div', { class: 'meal-card_meta' }, [
          h('span', { class: 'meal-card_date' }, formatDate(m.date)),
          h('span', { class: 'meal-card_name' }, m.title),
          h('span', { class: 'meal-card_sub' }, `${m.time} Uhr · ${m.appointmentTitle}`),
        ]),
        h('div', { class: 'meal-card_counts' }, [
          m.attendees.length ? h('span', `${m.attendees.length} dabei`)    : null,
          m.foods.length     ? h('span', `${m.foods.length} Gerichte`) : null,
        ]),
        h('svg', { class: 'meal-card_chevron', width: 16, height: 16, viewBox: '0 0 16 16', fill: 'currentColor' }, [
          h('path', { d: 'M4.427 7.427l3.396 3.396a.25.25 0 00.354 0l3.396-3.396A.25.25 0 0011.396 7H4.604a.25.25 0 00-.177.427z' }),
        ]),
      ])

      if (!props.expanded) return h('div', { class: 'meal-card' }, [header])

      // Attendees
      const attendeesBlock = m.attendees.length
        ? h('div', { class: 'meal-detail-block' }, [
            h('h4', `Wer ist dabei (${m.attendees.length})`),
            h('ul', { class: 'meal-attendee-list' }, m.attendees.map(a =>
              h('li', { key: a.id, class: 'meal-attendee' }, [
                a.avatarUrl
                  ? h('img', { src: a.avatarUrl, alt: a.name, class: 'meal-attendee_avatar' })
                  : h('span', { class: 'meal-attendee_avatar meal-attendee_avatar--placeholder' }, a.name[0]),
                h('span', a.name),
              ])
            )),
          ])
        : null

      // Foods with status dots
      const foodsList = m.foods.length
        ? h('ul', { class: 'meal-food-list' }, m.foods.map(f =>
            h('li', { key: f.id, class: 'meal-food' }, [
              h('span', {
                class: `food-status-dot food-status-dot--${(f.status || 'new').toLowerCase()}`,
                title: statusLabel(f.status),
              }),
              h('span', { class: 'meal-food_title' }, f.title),
              f.preference !== 'None'
                ? h('span', { class: 'meal-food_pref' }, f.preference === 'Vegetarian' ? '🥗 Vegetarisch' : '🌱 Vegan')
                : null,
              f.supplier ? h('span', { class: 'meal-food_supplier' }, `von ${f.supplier}`) : null,
            ])
          ))
        : h('p', { class: 'meal-card_empty' }, 'Noch keine Gerichte geplant.')

      const foodsHeadingRow = h('div', { class: 'meal-detail-block_heading-row' }, [
        h('h4', `Geplante Gerichte (${m.foods.length})`),
        h('div', { class: 'meal-detail-heading-actions' }, [
          h('button', {
            class: 'meal-suggest-btn',
            onClick: e => { e.stopPropagation(); router.push(`/food/meal/${m.id}`) },
          }, 'Details'),
          m.acceptsContributions
            ? h('button', {
                class: 'meal-suggest-btn',
                onClick: e => { e.stopPropagation(); emit('open-suggest-modal') },
              }, '+ Vorschlagen')
            : null,
        ]),
      ])

      const foodsBlock = h('div', { class: 'meal-detail-block' }, [foodsHeadingRow, foodsList])

      const details = h('div', { class: 'meal-card_details' }, [
        h('div', { class: 'meal-card_details-inner' }, [attendeesBlock, foodsBlock]),
      ])

      return h('div', { class: ['meal-card', props.expanded && 'is-expanded'] }, [header, details])
    }
  },
})
</script>
