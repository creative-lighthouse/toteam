<template>
  <div class="section section--FoodPage" :class="{ 'has-food-nav': canManage }">
    <div class="section_content">

      <div v-if="loading" class="section_infobox"><p>Lade Essensplan…</p></div>

      <div v-else-if="error" class="section_infobox error">
        <p>Fehler: {{ error }}</p>
        <AppButton variant="primary" @click="load">Erneut versuchen</AppButton>
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
                  <AppButton
                    size="small"
                    variant="primary"
                    :disabled="decidingFoodId === f.id"
                    @click="decidePending(f.id, 'Accepted')"
                  >Bestätigen</AppButton>
                  <AppButton
                    size="small"
                    variant="secondary"
                    :disabled="decidingFoodId === f.id"
                    @click="decidePending(f.id, 'Rejected')"
                  >Ablehnen</AppButton>
                </div>
              </div>
            </div>

            <div v-else class="section_infobox"><p>Keine offenen Vorschläge.</p></div>
          </section>
        </template>

      </template>
    </div>

    <!-- ── Gericht-vorschlagen Modal ──────────────────────────────────── -->
    <FoodSuggestModal ref="suggestModal" @suggested="onFoodSuggested" />

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

    <ContextMenu ref="attendeeMenu" />
  </div>
</template>

<script setup>
import { ref, computed, onMounted, defineComponent, h } from 'vue'
import { useRouter } from 'vue-router'
import { usePageHeaderStore } from '@stores/pageHeader'
import { apiGet, apiPost, apiPut } from '@utils/api'
import AppButton from '@components/ui/AppButton.vue'
import AppOrgLogo from '@components/ui/AppOrgLogo.vue'
import ContextMenu from '@components/ui/ContextMenu.vue'
import ParticipantCard from '@components/calendar/ParticipantCard.vue'
import FoodSuggestModal from '@components/food/FoodSuggestModal.vue'

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

const suggestModal = ref(null)

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

const attendeeMenu = ref(null)

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

function groupedAttendeesFor(meal) {
  return {
    Accept: (meal.attendees ?? []).map(a => toParticipation(a, 'Accept')),
    Decline: (meal.declinedAttendees ?? []).map(a => toParticipation(a, 'Decline')),
    Pending: (meal.pendingAttendees ?? []).map(a => toParticipation(a, 'Pending')),
  }
}

function onFoodAttendeeContextMenu(event, meal, participation) {
  if (!meal.canRecordRsvp) return

  const options = [
    { value: 'Accept', label: 'Zusagen' },
    { value: 'Decline', label: 'Absagen' },
  ].filter(o => o.value !== participation.Type)

  const menuItems = options.map(o => ({
    label: o.label,
    onClick: () => respondForMeal(meal.id, participation.MemberID, o.value),
  }))

  if (participation.Type !== 'Pending') {
    menuItems.push({
      label: 'Antwort entfernen',
      danger: true,
      onClick: () => respondForMeal(meal.id, participation.MemberID, null),
    })
  }

  attendeeMenu.value?.open(event, menuItems)
}

async function respondForMeal(mealId, targetMemberId, type) {
  try {
    await apiPost(`/calendar/participationFood/${mealId}`, { response: type, targetMemberId })
    await load()
  } catch (e) {
    alert('Fehler: ' + e.message)
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
  suggestModal.value?.open(mealId)
}

function onFoodSuggested({ mealId, food }) {
  // Update meal food list
  const all  = [...acceptedMeals.value, ...otherMeals.value, ...pastMeals.value]
  const mealContext = all.find(m => m.id === mealId)
  if (mealContext) mealContext.foods.push(food)
  // Add to myFoods
  if (mealContext) {
    myFoods.value.unshift({
      id: food.id, title: food.title, preference: food.preference,
      status: 'New',
      mealId: mealContext.id, mealTitle: mealContext.title, mealTime: mealContext.time,
      date: mealContext.date, appointmentTitle: mealContext.appointmentTitle,
      organizationTitle: mealContext.organizationTitle,
      organizationLogoUrl: mealContext.organizationLogoUrl,
    })
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

      const header = h('button', { class: 'meal-card_header', onClick: () => emit('toggle') }, [
        h(AppOrgLogo, { src: m.organizationLogoUrl, alt: m.organizationTitle, size: 36, class: 'meal-card_org-logo' }),
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

      // Teilnehmer (gruppiert wie bei Terminen: Zugesagt/Abgesagt/Ohne Antwort)
      const grouped = groupedAttendeesFor(m)
      const hasParticipants = grouped.Accept.length || grouped.Decline.length || grouped.Pending.length

      function participantGroup(title, list) {
        if (!list.length) return null
        return [
          h('h5', { class: 'participant-group_title' }, [title, ' ', h('span', `(${list.length})`)]),
          ...list.map(p => h(ParticipantCard, {
            key: p.ID,
            participation: p,
            onContextmenu: e => onFoodAttendeeContextMenu(e, m, p),
          })),
        ]
      }

      const attendeesBlock = hasParticipants
        ? h('div', { class: 'meal-detail-block participants-section' }, [
            h('h4', 'Teilnehmer'),
            h('div', { class: 'participants-list' }, [
              ...(participantGroup('Zugesagt', grouped.Accept) || []),
              ...(participantGroup('Abgesagt', grouped.Decline) || []),
              ...(participantGroup('Ohne Antwort', grouped.Pending) || []),
            ]),
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
          h(AppButton, {
            size: 'small',
            variant: 'secondary',
            onClick: e => { e.stopPropagation(); router.push(`/food/meal/${m.id}`) },
          }, () => 'Details'),
          m.acceptsContributions
            ? h(AppButton, {
                size: 'small',
                variant: 'secondary',
                onClick: e => { e.stopPropagation(); emit('open-suggest-modal') },
              }, () => '+ Vorschlagen')
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
