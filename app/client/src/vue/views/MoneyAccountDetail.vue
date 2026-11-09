<template>
  <div class="section section--MoneyAccountDetailPage" :class="{ 'section--MoneyAccountDetailPage-selecting': selected.size }">
    <div class="section_content">

      <div v-if="loading" class="section_infobox"><p>Lade Kasse…</p></div>

      <div v-else-if="!account" class="section_infobox">
        <p>Kasse nicht gefunden.</p>
      </div>

      <div v-else class="money-detail">

        <!-- Header -->
        <div class="money-detail_header">
          <div class="money-detail_org">
            <AppOrgLogo
              :src="account.Organization?.LogoURL"
              :alt="account.Organization?.Title"
              :size="24"
            />
            {{ account.Organization?.Title }}
          </div>
          <div class="money-detail_header-actions">
            <AppIconButton
              variant="neutral"
              aria-label="Verlauf anzeigen"
              title="Verlauf anzeigen"
              @click="historyModal?.open()"
            >
              <span class="icon-mask" :style="historyIconStyle" />
            </AppIconButton>
            <AppIconButton
              v-if="account.Permissions.canManageAccount"
              variant="primary"
              aria-label="Kasse bearbeiten"
              @click="accountModal?.open()"
            >
              <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
            </AppIconButton>
            <AppIconButton
              v-if="account.Permissions.canDeleteAccount"
              variant="danger"
              aria-label="Kasse löschen"
              @click="removeAccount"
            >
              <span class="icon-mask" :style="trashIconStyle" />
            </AppIconButton>
          </div>
        </div>

        <h2 class="hl2 money-detail_title">{{ account.Title }}</h2>

        <p class="money-detail_balance" :class="{ 'money-detail_balance--negative': account.CachedCurrentBalance < 0 }">
          {{ formatCurrency(account.CachedCurrentBalance) }}
        </p>

        <div v-if="account.TargetAmount > 0" class="money-progress">
          <div class="money-progress_bar">
            <div class="money-progress_fill" :style="{ width: targetPercent + '%' }"></div>
          </div>
          <span class="money-progress_label">Ziel: {{ formatCurrency(account.TargetAmount) }}</span>
        </div>

        <!-- Freigaben -->
        <section v-if="account.Permissions.canApprove && account.PendingEntries?.length" class="money-section">
          <h3 class="hl3 money-section_title">Offene Freigaben ({{ account.PendingEntries.length }})</h3>
          <div class="money-entry-list">
            <div v-for="entry in account.PendingEntries" :key="entry.ID" class="money-entry money-entry--pending">
              <MoneyEntryRow :entry="entry" :can-settle="account.Permissions.canApprove" @settle="openSettleModal" />
              <div class="money-entry_approve-actions">
                <AppButton variant="secondary" :disabled="approving === entry.ID" @click="approve(entry.ID, false)">Ablehnen</AppButton>
                <AppButton variant="primary" :disabled="approving === entry.ID" @click="approve(entry.ID, true)">Genehmigen</AppButton>
              </div>
            </div>
          </div>
        </section>

        <!-- Budgets -->
        <section class="money-section money-accordion money-budgets" :class="{ 'money-accordion--open': isBudgetsOpen, 'money-budgets--empty': !hasBudgets }">
          <!-- Kopfzeile: Titel + Gesamtbudget-Leiste + Chevron, klappt die Liste auf/zu -->
          <button
            type="button"
            class="money-accordion_toggle"
            :aria-expanded="isBudgetsOpen"
            aria-controls="money-budgets-content"
            :disabled="!hasBudgets"
            @click="budgetsOpen = !budgetsOpen"
          >
            <span class="hl3 money-section_title money-accordion_title">Budget</span>
            <span v-if="totalBudget" class="money-accordion_summary">
              <MoneyBudgetProgress :budget="totalBudget" />
              <span class="money-budget_amount">
                {{ formatCurrency(totalBudget.Spent) }} / {{ formatCurrency(totalBudget.Budget) }}
              </span>
            </span>
            <span v-if="hasBudgets" class="money-accordion_chevron" aria-hidden="true"></span>
          </button>

          <div id="money-budgets-content" class="money-accordion_content" :inert="!isBudgetsOpen">
            <div class="money-accordion_inner">
              <div v-if="account.Budgets.length === 0" class="section_infobox"><p>Noch keine Budgets angelegt.</p></div>

              <div v-else class="money-budget-list">
                <div
                  v-for="budget in account.Budgets"
                  :key="budget.ID"
                  class="money-budget"
                  role="button"
                  tabindex="0"
                  @click="openBudget(budget.ID)"
                  @keydown.enter.space.prevent="openBudget(budget.ID)"
                >
                  <div class="money-budget_header">
                    <span class="money-budget_title">{{ budget.Title }}</span>
                    <span class="money-budget_amount">
                      {{ formatCurrency(budget.Spent) }}<span v-if="budget.HasBudget"> / {{ formatCurrency(budget.Budget) }}</span>
                    </span>
                  </div>
                  <MoneyBudgetProgress :budget="budget" class="money-progress--budget" />
                </div>
              </div>
              <div class="money-section_bottom-row">
                <AppButton v-if="account.Permissions.canManageBudgets" size="small" variant="secondary" @click="openBudgetModal(null)">+ Budget</AppButton>
              </div>
            </div>
          </div>
        </section>

        <!-- Offene Auszahlungen an einen selbst / an alle Mitglieder -->
        <MoneyDebtsSection v-if="account.Debts" :debts="account.Debts" @filter="showOpenEntriesOf" />

        <!-- Buchungsverlauf -->
        <section ref="historySection" class="money-section money-history">
          <h3 class="hl3 money-section_title">Buchungsverlauf</h3>

          <AppSearchBar
            v-if="canEnterEntries || account.EntryCount > 0"
            v-model="filters.search"
            placeholder="Nach Titel, Person oder Betrag suchen…"
          >
            <template v-if="canEnterEntries" #actions>
              <AppButton variant="primary" title="Ausgabe/Einnahme erfassen" @click="openEntryModal(null)">+ Buchung</AppButton>
            </template>

            <template v-if="account.EntryCount > 0" #filters>
              <select v-model="filters.userId" aria-label="Nutzer">
                <option value="">Alle Nutzer</option>
                <option v-for="u in historyUsers" :key="u.ID" :value="String(u.ID)">{{ u.Name }}</option>
              </select>

              <select v-model="filters.budgetId" aria-label="Kategorie">
                <option value="">Alle Budgets</option>
                <option value="none">Ohne Budget</option>
                <option v-for="b in account.Budgets" :key="b.ID" :value="String(b.ID)">{{ b.Title }}</option>
              </select>

              <select v-model="filters.status" aria-label="Status">
                <option value="">Alle Status</option>
                <option value="approved">Freigegeben</option>
                <option value="pending">Ausstehend</option>
                <option value="open">Offen (nicht ausgezahlt)</option>
              </select>

              <select v-model="sortOrder" aria-label="Reihenfolge" title="Reihenfolge">
                <option v-for="opt in SORT_OPTIONS" :key="opt.value" :value="opt.value">Sortiert nach {{ opt.label }}</option>
              </select>

              <AppButton v-if="hasActiveFilters" variant="secondary" size="small" class="money-filter-reset" @click="resetFilters">Filter zurücksetzen</AppButton>

              <AppViewToggle v-model="viewMode" :options="VIEW_MODES" />
            </template>
          </AppSearchBar>

          <div v-if="account.EntryCount === 0" class="section_infobox"><p>Noch keine Buchungen erfasst.</p></div>

          <!-- Budget-Ansicht: Spalten laden selbst (je Budget seitenweise) -->
          <MoneyBudgetBoard
            v-else-if="viewMode === 'budgets'"
            :account-id="account.ID"
            :budgets="account.Budgets"
            :query="appliedQuery"
            :can-settle="account.Permissions.canApprove"
            :version="store.entriesVersion"
            @open="openEntryDetail"
            @settle="openSettleModal"
            @loaded="reloadLightbox"
          />

          <div v-else-if="store.entriesError && entries.length === 0" class="section_infobox"><p>{{ store.entriesError }}</p></div>
          <div v-else-if="entries.length === 0 && store.entriesLoading" class="section_infobox"><p>Lade Buchungen…</p></div>
          <div v-else-if="entries.length === 0" class="section_infobox"><p>Keine Buchungen entsprechen den gewählten Filtern.</p></div>

          <MoneyEntryTable
            v-else-if="viewMode === 'table'"
            :entries="entries"
            :can-settle="account.Permissions.canApprove"
            :can-manage="account.Permissions.canApprove"
            :selected="selectedIds"
            @open="openEntryDetail"
            @settle="openSettleModal"
            @edit="openEntryModal"
            @toggle-select="toggleSelect"
            @toggle-all="toggleAllLoaded"
          />

          <div v-else class="money-entry-list">
            <div
              v-for="entry in entries"
              :key="entry.ID"
              class="money-entry"
              :class="{ 'money-entry--selected': selected.has(entry.ID) }"
              role="button"
              tabindex="0"
              @click="openEntryDetail(entry)"
              @keydown.enter.space.prevent="openEntryDetail(entry)"
            >
              <span class="money-entry_select" @click.stop @keydown.stop>
                <AppSelectCheckbox
                  :checked="selected.has(entry.ID)"
                  :label="`${entry.ChangeReason} auswählen`"
                  @toggle="toggleSelect(entry, $event)"
                />
              </span>
              <MoneyEntryRow :entry="entry" :can-settle="account.Permissions.canApprove" @settle="openSettleModal" />
            </div>
          </div>

          <!-- Lazyloading: lädt die nächste Seite, sobald dieses Element in die Nähe des sichtbaren Bereichs kommt -->
          <div v-if="viewMode !== 'budgets' && entries.length > 0 && store.entriesHasMore" ref="moreEl" class="money-entry-list_more">
            <p v-if="store.entriesLoading">Lade weitere Buchungen…</p>
            <AppButton v-else-if="store.entriesError" variant="secondary" size="small" @click="store.loadMoreEntries()">Erneut versuchen</AppButton>
          </div>
        </section>

      </div>
    </div>

    <!-- Floating action button -->
    <button
      v-if="account && (account.Permissions.canEnterDeposit || account.Permissions.canEnterWithdrawal)"
      class="money-fab"
      title="Ausgabe/Einnahme erfassen"
      @click="openEntryModal(null)"
    >+ Buchung</button>

    <MoneyEntryModal
      v-if="account"
      ref="entryModal"
      :account-id="account.ID"
      :budgets="account.Budgets"
      :requires-receipt-deposit="account.RequiresReceiptDeposit"
      :requires-receipt-withdrawal="account.RequiresReceiptWithdrawal"
      :can-enter-deposit="account.Permissions.canEnterDeposit"
      :can-enter-withdrawal="account.Permissions.canEnterWithdrawal"
      @saved="onEntrySaved"
    />
    <MoneyAccountModal
      v-if="account"
      ref="accountModal"
      mode="edit"
      :account="account"
      @saved="onAccountSaved"
    />
    <MoneyBudgetModal
      v-if="account"
      ref="budgetModal"
      :account-id="account.ID"
      @saved="onBudgetSaved"
    />
    <HistoryModal
      v-if="account"
      ref="historyModal"
      :endpoint="`/money/accountHistory/${account.ID}`"
      created-label="hat die Kasse erstellt"
    />
    <MoneySettleModal
      v-if="account"
      ref="settleModal"
      :can-settle="account.Permissions.canApprove"
      @saved="onSettleSaved"
    />
    <MoneySettleBulkModal
      v-if="account"
      ref="settleBulkModal"
      :account-id="account.ID"
      @saved="onBulkSettled"
    />

    <MoneySelectionBar
      :count="selected.size"
      :selectable-count="entries.length"
      :expenses="selectionSums.expenses"
      :income="selectionSums.income"
      :open-amount="selectionSums.open"
      :can-settle="!!account?.Permissions.canApprove"
      :deletable-count="deletableSelection.length"
      :busy="removingSelection"
      @select-all="selectAllLoaded"
      @settle="settleBulkModal?.open([...selected.values()])"
      @remove="removeSelection"
      @clear="clearSelection"
    />
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted, onUnmounted, nextTick, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import GLightbox from 'glightbox'
import { useMoneyStore } from '@stores/money'
import { useAuthStore } from '@stores/auth'
import { usePageHeaderStore } from '@stores/pageHeader'
import MoneyEntryModal from '@components/money/MoneyEntryModal.vue'
import MoneyAccountModal from '@components/money/MoneyAccountModal.vue'
import MoneyBudgetModal from '@components/money/MoneyBudgetModal.vue'
import MoneyEntryRow from '@components/money/MoneyEntryRow.vue'
import MoneyBudgetProgress from '@components/money/MoneyBudgetProgress.vue'
import MoneySettleModal from '@components/money/MoneySettleModal.vue'
import MoneyDebtsSection from '@components/money/MoneyDebtsSection.vue'
import MoneyEntryTable from '@components/money/MoneyEntryTable.vue'
import MoneyBudgetBoard from '@components/money/MoneyBudgetBoard.vue'
import AppViewToggle from '@components/ui/AppViewToggle.vue'
import AppSelectCheckbox from '@components/ui/AppSelectCheckbox.vue'
import MoneySelectionBar from '@components/money/MoneySelectionBar.vue'
import MoneySettleBulkModal from '@components/money/MoneySettleBulkModal.vue'
import { sumEntries } from '@utils/moneyEntries'
import AppButton from '@components/ui/AppButton.vue'
import AppSearchBar from '@components/ui/AppSearchBar.vue'
import AppIconButton from '@components/ui/AppIconButton.vue'
import HistoryModal from '@components/history/HistoryModal.vue'
import actionHistory from '../../../icons/actions/action_history.svg'
import AppOrgLogo from '@components/ui/AppOrgLogo.vue'
import actionTrash from '../../../icons/actions/action_trash.svg'

const trashIconStyle = { maskImage: `url("${actionTrash}")`, WebkitMaskImage: `url("${actionTrash}")` }

const route = useRoute()
const router = useRouter()
const store = useMoneyStore()
const authStore = useAuthStore()
const pageHeaderStore = usePageHeaderStore()

pageHeaderStore.setHeader('Kasse')

const loading = computed(() => store.loading)
const account = computed(() => store.currentAccount)

// Budgets-Accordion: standardmäßig eingeklappt. Ohne Budgets immer offen, damit
// der Hinweis und "+ Budget" sichtbar bleiben.
const budgetsOpen = ref(false)
const hasBudgets = computed(() => (account.value?.Budgets?.length ?? 0) > 0)
const isBudgetsOpen = computed(() => budgetsOpen.value || !hasBudgets.value)

// Summe aller Budgets mit festgelegtem Limit, im selben Format wie ein einzelnes
// Budget — damit MoneyBudgetProgress die Gesamtleiste genauso darstellt.
// Budgets ohne Limit (HasBudget: false) zählen nicht mit, sonst liefe die
// Gesamtleiste durch deren Ausgaben scheinbar über.
const totalBudget = computed(() => {
  const limited = (account.value?.Budgets || []).filter(b => b.HasBudget)
  if (!limited.length) return null
  const sum = key => limited.reduce((acc, b) => acc + (Number(b[key]) || 0), 0)
  const budget = sum('Budget')
  const spent = sum('Spent')
  return {
    HasBudget: true,
    Budget: budget,
    Spent: spent,
    PendingAmount: sum('PendingAmount'),
    Remaining: budget - spent,
  }
})

const entryModal = ref(null)
const accountModal = ref(null)
const budgetModal = ref(null)
const historyModal = ref(null)
const historyIconStyle = { maskImage: `url("${actionHistory}")`, WebkitMaskImage: `url("${actionHistory}")` }
const settleModal = ref(null)
const approving = ref(null)

const targetPercent = computed(() => {
  if (!account.value?.TargetAmount) return 0
  return Math.max(0, Math.min(100, (account.value.CachedCurrentBalance / account.value.TargetAmount) * 100))
})

const filters = reactive({
  search: '',
  userId: '',
  budgetId: '',
  status: '',
})

const canEnterEntries = computed(() =>
  !!account.value && (account.value.Permissions.canEnterDeposit || account.value.Permissions.canEnterWithdrawal)
)

const hasActiveFilters = computed(() => Object.values(filters).some(v => v !== ''))

function resetFilters() {
  filters.search = ''
  filters.userId = ''
  filters.budgetId = ''
  filters.status = ''
}

const SORT_OPTIONS = [
  { value: 'invoice', label: 'Rechnungsdatum' },
  { value: 'submission', label: 'Einreichung' },
  { value: 'alphabetical', label: 'Alphabetisch' },
]
const sortOrder = ref('invoice')

// Personen mit Buchungen in dieser Kasse (vom Server, da nicht alle Buchungen geladen sind)
const historyUsers = computed(() => account.value?.EntryUsers ?? [])

// Buchungsverlauf: wird seitenweise vom Server geladen; Suche, Filter und Sortierung
// laufen dort (MoneyApiController::entries), damit sie auch ungeladene Buchungen erfassen
const entries = computed(() => store.entries)
const moreEl = ref(null)

function entriesQuery() {
  return {
    search: filters.search.trim(),
    user: filters.userId,
    budget: filters.budgetId,
    status: filters.status,
    sort: sortOrder.value,
  }
}

// Zuletzt angewandte Suche/Filter — für die Budget-Ansicht, deren Spalten selbst laden
// (erst nach der Tipp-Pause aktualisiert, damit nicht jede Taste alle Spalten neu lädt)
const appliedQuery = ref(entriesQuery())

function loadEntries() {
  const id = route.params.id
  appliedQuery.value = entriesQuery()
  if (id) store.loadEntries(id, appliedQuery.value)
}

// ── Mehrfachauswahl (Liste und Tabelle): Summe sehen, offene gemeinsam begleichen ─────
// Map ID → Buchung, damit Summen auch für Ausgewählte stimmen, die durch Nachladen/Filtern
// gerade nicht in der Liste stehen. Shift-Klick wählt den Bereich bis zum zuletzt Angeklickten.
const selected = ref(new Map())
const selectedIds = computed(() => new Set(selected.value.keys()))
const selectionSums = computed(() => sumEntries([...selected.value.values()]))
const settleBulkModal = ref(null)
let lastSelectedId = null

function toggleSelect(entry, { shift = false } = {}) {
  const next = new Map(selected.value)
  const list = entries.value
  const from = list.findIndex(e => e.ID === lastSelectedId)
  const to = list.findIndex(e => e.ID === entry.ID)
  if (shift && from >= 0 && to >= 0) {
    const [a, b] = from < to ? [from, to] : [to, from]
    list.slice(a, b + 1).forEach(e => next.set(e.ID, e))
  } else if (next.has(entry.ID)) {
    next.delete(entry.ID)
  } else {
    next.set(entry.ID, entry)
  }
  lastSelectedId = entry.ID
  selected.value = next
}

function selectAllLoaded() {
  const next = new Map(selected.value)
  entries.value.forEach(e => next.set(e.ID, e))
  selected.value = next
}

function toggleAllLoaded() {
  if (entries.value.length && entries.value.every(e => selected.value.has(e.ID))) clearSelection()
  else selectAllLoaded()
}

function clearSelection() {
  lastSelectedId = null
  if (selected.value.size) selected.value = new Map()
}

// Nach dem Neuladen (z. B. nach einer Begleichung) die aktuellen Daten übernehmen
watch(entries, list => {
  if (!selected.value.size) return
  const next = new Map(selected.value)
  for (const e of list) if (next.has(e.ID)) next.set(e.ID, e)
  selected.value = next
})

function onBulkSettled() {
  clearSelection()
}

// Löschen: nur, was man auch einzeln löschen darf (wie MoneyEntryDetail / entryRemoveBulk)
function canDeleteEntry(entry) {
  if (account.value?.Permissions.canApprove) return true
  return !entry.Approved && entry.User?.ID === authStore.user?.ID
}
const deletableSelection = computed(() => [...selected.value.values()].filter(canDeleteEntry))
const removingSelection = ref(false)

async function removeSelection() {
  const toRemove = deletableSelection.value
  if (!toRemove.length || removingSelection.value) return
  const { expenses, income } = sumEntries(toRemove)
  const amounts = [expenses && `Ausgaben ${formatCurrency(expenses)}`, income && `Einnahmen ${formatCurrency(income)}`].filter(Boolean).join(', ')
  const skipped = selected.value.size - toRemove.length
  const question = (toRemove.length === 1
    ? `Die ausgewählte Buchung „${toRemove[0].ChangeReason}“ wirklich löschen?`
    : `${toRemove.length} ausgewählte Buchungen wirklich löschen?`)
    + (amounts ? ` (${amounts})` : '')
    + '\n\nZugehörige Zahlungen und Belege werden ebenfalls gelöscht. Das lässt sich nicht rückgängig machen.'
    + (skipped ? `\n\n${skipped === 1 ? '1 weitere ausgewählte Buchung darfst' : `${skipped} weitere ausgewählte Buchungen darfst`} du nicht löschen – sie ${skipped === 1 ? 'bleibt' : 'bleiben'} erhalten.` : '')
  if (!confirm(question)) return

  removingSelection.value = true
  try {
    const response = await store.removeEntries(account.value.ID, toRemove.map(e => e.ID))
    if (response.success) {
      clearSelection()
    } else {
      alert(response.error || 'Fehler beim Löschen der Buchungen.')
    }
  } finally {
    removingSelection.value = false
  }
}

function onSelectionKeydown(event) {
  if (event.key !== 'Escape' || !selected.value.size) return
  if (document.querySelector('dialog[open]')) return
  clearSelection()
}

// ── Ansicht: Liste, Tabelle oder Budget-Board (pro Gerät gemerkt) ─────
const VIEW_KEY = 'toteam_money_view'
const VIEW_MODES = [
  { value: 'list', label: 'Listenansicht', icon: 'list' },
  { value: 'table', label: 'Tabellenansicht', icon: 'table' },
  { value: 'budgets', label: 'Nach Budgets', icon: 'kanban' },
]
function readViewMode() {
  try {
    const stored = localStorage.getItem(VIEW_KEY)
    if (VIEW_MODES.some(m => m.value === stored)) return stored
  } catch {
    // Speicher nicht verfügbar
  }
  return 'list'
}
const viewMode = ref(readViewMode())
watch(appliedQuery, clearSelection)
watch(viewMode, mode => {
  if (mode === 'budgets') clearSelection()
  try {
    localStorage.setItem(VIEW_KEY, mode)
  } catch {
    // egal
  }
})

// Auswahlfelder sofort, die Suche erst nach einer kurzen Tipp-Pause
let searchTimer = null
watch(() => filters.search, () => {
  clearTimeout(searchTimer)
  searchTimer = setTimeout(loadEntries, 300)
})
watch(() => [filters.userId, filters.budgetId, filters.status, sortOrder.value], () => {
  clearTimeout(searchTimer)
  loadEntries()
})

// Nächste Seite laden, sobald das Ende der Liste in die Nähe kommt. Ist danach noch
// Platz (kurze Seite, großer Bildschirm), wird erneut geprüft.
let observer = null
watch(moreEl, el => {
  observer?.disconnect()
  if (!el || typeof IntersectionObserver === 'undefined') return
  observer = new IntersectionObserver(async observed => {
    if (!observed.some(e => e.isIntersecting)) return
    await store.loadMoreEntries()
    if (moreEl.value && store.entriesHasMore && !store.entriesError) {
      observer.unobserve(moreEl.value)
      observer.observe(moreEl.value)
    }
  }, { rootMargin: '400px' })
  observer.observe(el)
})

// Aus "Schulden": Buchungsverlauf auf die offenen Buchungen einer Person filtern
const historySection = ref(null)
function showOpenEntriesOf(userId) {
  filters.search = ''
  filters.budgetId = ''
  filters.userId = String(userId)
  filters.status = 'open'
  historySection.value?.scrollIntoView({ behavior: 'smooth', block: 'start' })
}

function formatCurrency(value) {
  return new Intl.NumberFormat('de-DE', { style: 'currency', currency: 'EUR' }).format(value || 0)
}

function openBudgetModal(budget) {
  budgetModal.value?.open(budget)
}

function openBudget(id) {
  router.push({ name: 'MoneyBudgetDetail', params: { id } })
}

function openEntryModal(entry) {
  entryModal.value?.open(entry)
}

async function approve(id, doApprove) {
  approving.value = id
  try {
    await store.approveEntry(id, doApprove)
  } finally {
    approving.value = null
  }
}

function onEntrySaved() {
  entryModal.value?.close()
}

function onAccountSaved() {
  accountModal.value?.close()
}

async function removeAccount() {
  if (!account.value) return
  if (!confirm(`Kasse "${account.value.Title}" wirklich löschen? Alle Buchungen und Budgets gehen dabei verloren.`)) return

  const response = await store.removeAccount(account.value.ID)
  if (response.success) {
    router.push({ name: 'Money' })
  } else {
    alert(response.error || 'Fehler beim Löschen der Kasse.')
  }
}

function onBudgetSaved() {
  budgetModal.value?.close()
}

function openSettleModal(entry) {
  settleModal.value?.open(entry)
}

function onSettleSaved() {
  settleModal.value?.close()
}

function openEntryDetail(entry) {
  router.push({ name: 'MoneyEntryDetail', params: { id: entry.ID } })
}

watch(account, val => {
  pageHeaderStore.setTitle(val?.Title ?? 'Kasse')
})

let lightbox = null

onMounted(() => {
  window.addEventListener('keydown', onSelectionKeydown)
  store.fetchAccount(route.params.id)
  loadEntries()
  lightbox = GLightbox({ selector: '.money-entry_receipt', touchNavigation: true })
})

onUnmounted(() => {
  window.removeEventListener('keydown', onSelectionKeydown)
  lightbox?.destroy()
  observer?.disconnect()
  clearTimeout(searchTimer)
  store.resetEntries()
})

watch(() => route.params.id, id => {
  if (!id) return
  clearSelection()
  store.fetchAccount(id)
  loadEntries()
})

// Re-scan the DOM for receipt links whenever the entry list changes
function reloadLightbox() {
  nextTick(() => lightbox?.reload())
}
watch([account, entries, viewMode], () => {
  nextTick(() => lightbox?.reload())
}, { deep: true })
</script>
