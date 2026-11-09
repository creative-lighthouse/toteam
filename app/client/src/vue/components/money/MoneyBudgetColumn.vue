<!--
  Eine Spalte im Budget-Board (MoneyBudgetBoard): die Buchungen eines Budgets bzw. ohne
  Budget (budget = null), mit Suche/Filtern aus `query`. Lädt selbst seitenweise über
  money/entries und lädt nach, sobald man in der Spalte nach unten scrollt.
-->
<template>
  <section class="money-budget-column" :aria-label="title">
    <header class="money-budget-column_header">
      <div class="money-budget-column_title-row">
        <span class="money-budget-column_title">{{ title }}</span>
        <span class="money-budget-column_count" :title="`${total} Buchungen`">{{ total }}</span>
      </div>
      <template v-if="budget">
        <MoneyBudgetProgress :budget="budget" />
        <span class="money-budget-column_amount">
          {{ formatCurrency(budget.Spent) }}<template v-if="budget.HasBudget"> / {{ formatCurrency(budget.Budget) }}</template> ausgegeben
        </span>
      </template>
    </header>

    <div ref="scrollEl" class="money-budget-column_cards">
      <div
        v-for="entry in entries"
        :key="entry.ID"
        class="money-entry"
        role="button"
        tabindex="0"
        @click="$emit('open', entry)"
        @keydown.enter.space.prevent="$emit('open', entry)"
      >
        <MoneyEntryRow :entry="entry" :can-settle="canSettle" @settle="$emit('settle', $event)" />
      </div>

      <p v-if="error" class="money-budget-column_hint">
        {{ error }}
        <button type="button" class="money-budget-column_retry" @click="loadMore">Erneut versuchen</button>
      </p>
      <p v-else-if="loading && !entries.length" class="money-budget-column_hint">Lade Buchungen…</p>
      <p v-else-if="!entries.length" class="money-budget-column_hint">Keine Buchungen</p>

      <div v-if="hasMore && !error" ref="moreEl" class="money-budget-column_more">
        <span v-if="loading && entries.length">Lade weitere…</span>
      </div>
    </div>
  </section>
</template>

<script setup>
import { ref, computed, watch, onBeforeUnmount } from 'vue'
import { apiGet } from '@utils/api'
import MoneyEntryRow from '@components/money/MoneyEntryRow.vue'
import MoneyBudgetProgress from '@components/money/MoneyBudgetProgress.vue'

const props = defineProps({
  accountId: { type: Number, required: true },
  // Budget aus account.Budgets oder null für "Ohne Budget"
  budget: { type: Object, default: null },
  // Suche/Filter/Sortierung wie im Buchungsverlauf, ohne budget
  query: { type: Object, required: true },
  canSettle: { type: Boolean, default: false },
  // Ändert sich nach jeder Buchungsänderung (Store) → bisher Geladenes neu laden
  version: { type: Number, default: 0 },
})
const emit = defineEmits(['open', 'settle', 'loaded'])

const PAGE_SIZE = 20

const title = computed(() => props.budget?.Title ?? 'Ohne Budget')
const entries = ref([])
const total = ref(0)
const hasMore = ref(false)
const loading = ref(false)
const error = ref(null)
let request = 0

function url(offset, limit) {
  const params = new URLSearchParams({ offset, limit, budget: props.budget ? props.budget.ID : 'none' })
  for (const [key, value] of Object.entries(props.query)) {
    if (value !== '' && value != null) params.set(key, value)
  }
  return `/money/entries/${props.accountId}?${params}`
}

// Lädt ab `offset` bis zu `count` Buchungen (max. 100 pro Anfrage); null, wenn überholt
async function fetchRange(offset, count) {
  const current = ++request
  loading.value = true
  error.value = null
  try {
    const loaded = []
    let more = true
    let sum = 0
    while (more && loaded.length < count) {
      const response = await apiGet(url(offset + loaded.length, Math.min(100, count - loaded.length)), false)
      if (current !== request) return null
      if (response?.success === false) throw new Error(response.error || 'Buchungen konnten nicht geladen werden')
      loaded.push(...(response.entries || []))
      sum = response.total || 0
      more = !!response.hasMore
    }
    return { loaded, total: sum, hasMore: more }
  } catch (err) {
    if (current === request) error.value = err.message
    return null
  } finally {
    if (current === request) loading.value = false
  }
}

function apply(result, append) {
  if (!result) return
  if (append) {
    const known = new Set(entries.value.map(e => e.ID))
    entries.value = [...entries.value, ...result.loaded.filter(e => !known.has(e.ID))]
  } else {
    entries.value = result.loaded
  }
  total.value = result.total
  hasMore.value = result.hasMore
  emit('loaded')
}

async function reload() {
  entries.value = []
  total.value = 0
  hasMore.value = false
  apply(await fetchRange(0, PAGE_SIZE), false)
}

async function loadMore() {
  if (loading.value || (!hasMore.value && !error.value)) return
  apply(await fetchRange(entries.value.length, PAGE_SIZE), true)
}

// Nach Änderungen die bisher geladene Menge neu holen (Scrollposition bleibt)
async function refresh() {
  apply(await fetchRange(0, Math.max(entries.value.length, PAGE_SIZE)), false)
}

watch(() => [props.accountId, props.budget?.ID ?? null, JSON.stringify(props.query)], reload, { immediate: true })
watch(() => props.version, refresh)

// Nachladen, sobald das Spaltenende in die Nähe kommt — beobachtet im eigenen Scrollbereich
const scrollEl = ref(null)
const moreEl = ref(null)
let observer = null
watch(moreEl, el => {
  observer?.disconnect()
  if (!el || !scrollEl.value || typeof IntersectionObserver === 'undefined') return
  observer = new IntersectionObserver(async observed => {
    if (!observed.some(e => e.isIntersecting)) return
    await loadMore()
    // Noch Platz in der Spalte → erneut prüfen
    if (moreEl.value && hasMore.value && !error.value) {
      observer.unobserve(moreEl.value)
      observer.observe(moreEl.value)
    }
  }, { root: scrollEl.value, rootMargin: '200px' })
  observer.observe(el)
})
onBeforeUnmount(() => observer?.disconnect())

function formatCurrency(value) {
  return new Intl.NumberFormat('de-DE', { style: 'currency', currency: 'EUR' }).format(value || 0)
}
</script>
