<template>
  <div class="section section--MoneyAccountDetailPage">
    <div class="section_content">

      <div v-if="loading" class="section_infobox"><p>Lade Kasse…</p></div>

      <div v-else-if="!account" class="section_infobox">
        <p>Kasse nicht gefunden.</p>
      </div>

      <div v-else class="money-detail">

        <!-- Header -->
        <div class="money-detail_header">
          <div class="money-detail_org">
            <img v-if="account.Organization?.LogoURL" :src="account.Organization.LogoURL" :alt="account.Organization.Title" class="money-detail_org-logo">
            {{ account.Organization?.Title }}
          </div>
          <div class="money-detail_header-actions">
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
              <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/></svg>
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
        <section class="money-section">
          <div class="money-section_heading-row">
            <h3 class="hl3 money-section_title">Budgets</h3>
            <AppButton v-if="account && (account.Permissions.canEnterDeposit || account.Permissions.canEnterWithdrawal)" title="Ausgabe/Einnahme erfassen" @click="openEntryModal(null)">+ Buchung</AppButton>
          </div>

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
                <div class="money-budget_header-actions">
                  <span class="money-budget_amount">
                    {{ formatCurrency(budget.Spent) }}<span v-if="budget.HasBudget"> / {{ formatCurrency(budget.Budget) }}</span>
                  </span>
                  <AppIconButton
                    v-if="account.Permissions.canManageBudgets"
                    variant="primary"
                    aria-label="Budget bearbeiten"
                    @click.stop="openBudgetModal(budget)"
                  >
                    <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                  </AppIconButton>
                </div>
              </div>
              <MoneyBudgetProgress :budget="budget" class="money-progress--budget" />
            </div>
          </div>
          <div class="money-section_bottom-row">
            <AppButton v-if="account.Permissions.canManageBudgets" size="small" variant="secondary" @click="openBudgetModal(null)">+ Budget</AppButton>
          </div>
        </section>

        <!-- Buchungsverlauf -->
        <section class="money-section">
          <h3 class="hl3 money-section_title">Buchungsverlauf</h3>

          <div v-if="account.History.length > 0" class="money-filter-bar">
            <div class="money-filter-row">
              <input type="search" class="input money-filter-search" v-model="filters.search" placeholder="Nach Titel suchen…" aria-label="Suche nach Titel">

              <select class="input" v-model="filters.userId" aria-label="Nutzer">
                <option value="">Alle Nutzer</option>
                <option v-for="u in historyUsers" :key="u.ID" :value="String(u.ID)">{{ u.Name }}</option>
              </select>

              <select class="input" v-model="filters.budgetId" aria-label="Kategorie">
                <option value="">Alle Budgets</option>
                <option value="none">Ohne Budget</option>
                <option v-for="b in account.Budgets" :key="b.ID" :value="String(b.ID)">{{ b.Title }}</option>
              </select>

              <select class="input" v-model="filters.status" aria-label="Status">
                <option value="">Alle Status</option>
                <option value="approved">Freigegeben</option>
                <option value="pending">Ausstehend</option>
              </select>

              <AppButton v-if="hasActiveFilters" variant="secondary" size="small" class="money-filter-reset" @click="resetFilters">Filter zurücksetzen</AppButton>
            </div>

            <div class="money-filter-row">
              <span class="money-filter-text">Reihenfolge</span>
              <select class="input" v-model="sortOrder" aria-label="Reihenfolge">
                <option v-for="opt in SORT_OPTIONS" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
              </select>
            </div>
          </div>

          <div v-if="account.History.length === 0" class="section_infobox"><p>Noch keine Buchungen erfasst.</p></div>
          <div v-else-if="filteredHistory.length === 0" class="section_infobox"><p>Keine Buchungen entsprechen den gewählten Filtern.</p></div>

          <div v-else class="money-entry-list">
            <div v-for="entry in filteredHistory" :key="entry.ID" class="money-entry">
              <MoneyEntryRow :entry="entry" :can-settle="account.Permissions.canApprove" @settle="openSettleModal" />
              <div v-if="canEditEntry(entry) || canDeleteEntry(entry)" class="money-entry_actions">
                <AppIconButton
                  v-if="canEditEntry(entry)"
                  variant="primary"
                  aria-label="Buchung bearbeiten"
                  @click="openEntryModal(entry)"
                >
                  <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                </AppIconButton>
                <AppIconButton
                  v-if="canDeleteEntry(entry)"
                  variant="danger"
                  aria-label="Buchung löschen"
                  @click="remove(entry.ID)"
                >
                  <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/></svg>
                </AppIconButton>
              </div>
            </div>
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
    <MoneySettleModal
      ref="settleModal"
      @saved="onSettleSaved"
    />
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted, onUnmounted, nextTick, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import GLightbox from 'glightbox'
import { useMoneyStore } from '@stores/money'
import { usePageHeaderStore } from '@stores/pageHeader'
import MoneyEntryModal from '@components/MoneyEntryModal.vue'
import MoneyAccountModal from '@components/MoneyAccountModal.vue'
import MoneyBudgetModal from '@components/MoneyBudgetModal.vue'
import MoneyEntryRow from '@components/MoneyEntryRow.vue'
import MoneyBudgetProgress from '@components/MoneyBudgetProgress.vue'
import MoneySettleModal from '@components/MoneySettleModal.vue'
import AppButton from '@components/AppButton.vue'
import AppIconButton from '@components/AppIconButton.vue'

const route = useRoute()
const router = useRouter()
const store = useMoneyStore()
const pageHeaderStore = usePageHeaderStore()

pageHeaderStore.setHeader('Kasse')

const loading = computed(() => store.loading)
const account = computed(() => store.currentAccount)

const entryModal = ref(null)
const accountModal = ref(null)
const budgetModal = ref(null)
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

const historyUsers = computed(() => {
  const map = new Map()
  for (const entry of account.value?.History ?? []) {
    if (entry.User) map.set(entry.User.ID, entry.User.Name)
  }
  return [...map.entries()]
    .map(([ID, Name]) => ({ ID, Name }))
    .sort((a, b) => a.Name.localeCompare(b.Name))
})

const filteredHistory = computed(() => {
  const history = account.value?.History ?? []
  const filtered = history.filter(entry => {
    if (filters.search && !entry.ChangeReason.toLowerCase().includes(filters.search.toLowerCase())) return false
    if (filters.userId && String(entry.User?.ID) !== filters.userId) return false
    if (filters.budgetId === 'none' && entry.Budget) return false
    if (filters.budgetId && filters.budgetId !== 'none' && String(entry.Budget?.ID) !== filters.budgetId) return false
    if (filters.status === 'approved' && !entry.Approved) return false
    if (filters.status === 'pending' && entry.Approved) return false
    return true
  })

  const sorted = [...filtered]
  if (sortOrder.value === 'alphabetical') {
    sorted.sort((a, b) => a.ChangeReason.localeCompare(b.ChangeReason))
  } else if (sortOrder.value === 'submission') {
    sorted.sort((a, b) => (b.Created ?? '').localeCompare(a.Created ?? ''))
  } else {
    sorted.sort((a, b) => (b.ChangeDate ?? '').localeCompare(a.ChangeDate ?? ''))
  }
  return sorted
})

function formatCurrency(value) {
  return new Intl.NumberFormat('de-DE', { style: 'currency', currency: 'EUR' }).format(value || 0)
}

function canDeleteEntry(entry) {
  if (!account.value) return false
  if (account.value.Permissions.canManageBudgets) return true
  return !entry.Approved
}

// Wer eine Buchung löschen darf, darf sie auch bearbeiten (gleiche Regel wie im Backend).
function canEditEntry(entry) {
  return canDeleteEntry(entry)
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

async function remove(id) {
  if (!confirm('Diese Buchung wirklich löschen?')) return
  await store.removeEntry(id)
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

watch(account, val => {
  pageHeaderStore.setTitle(val?.Title ?? 'Kasse')
})

let lightbox = null

onMounted(() => {
  store.fetchAccount(route.params.id)
  lightbox = GLightbox({ selector: '.money-entry_receipt', touchNavigation: true })
})

onUnmounted(() => {
  lightbox?.destroy()
})

watch(() => route.params.id, id => { if (id) store.fetchAccount(id) })

// Re-scan the DOM for receipt links whenever the entry list changes
watch(account, () => {
  nextTick(() => lightbox?.reload())
}, { deep: true })
</script>
