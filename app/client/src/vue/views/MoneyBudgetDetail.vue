<template>
  <div class="section section--MoneyAccountDetailPage">
    <div class="section_content">

      <div v-if="loading" class="section_infobox"><p>Lade Budget…</p></div>

      <div v-else-if="!budget" class="section_infobox">
        <p>Budget nicht gefunden.</p>
      </div>

      <div v-else class="money-detail">

        <!-- Header -->
        <div class="money-detail_header">
          <RouterLink :to="{ name: 'MoneyAccountDetail', params: { id: account.ID } }" class="money-detail_org">
            {{ account.Title }}
          </RouterLink>
          <div class="money-detail_header-actions">
            <AppIconButton
              v-if="account.Permissions.canManageBudgets"
              variant="primary"
              aria-label="Budget bearbeiten"
              @click="budgetModal?.open(budget)"
            >
              <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
            </AppIconButton>
            <AppIconButton
              v-if="account.Permissions.canManageBudgets"
              variant="danger"
              aria-label="Budget löschen"
              @click="removeBudget"
            >
              <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/></svg>
            </AppIconButton>
          </div>
        </div>

        <h2 class="hl2 money-detail_title">{{ budget.Title }}</h2>

        <p class="money-detail_balance">
          {{ formatCurrency(budget.Spent) }}<span v-if="budget.HasBudget"> / {{ formatCurrency(budget.Budget) }}</span>
        </p>

        <MoneyBudgetProgress :budget="budget" />

        <!-- Buchungen -->
        <section class="money-section">
          <div class="money-section_heading-row">
            <h3 class="hl3 money-section_title">Buchungen ({{ entries.length }})</h3>
            <AppButton v-if="canEnterEntry" size="default" variant="primary" @click="openEntryModal(null)">+ Buchung</AppButton>
          </div>

          <div v-if="entries.length === 0" class="section_infobox"><p>Noch keine Buchungen für dieses Budget erfasst.</p></div>

          <div v-else class="money-entry-list">
            <div v-for="entry in entries" :key="entry.ID" class="money-entry">
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
      v-if="canEnterEntry"
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
import { ref, computed, onMounted, onUnmounted, nextTick, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import GLightbox from 'glightbox'
import { useMoneyStore } from '@stores/money'
import { useAuthStore } from '@stores/auth'
import { usePageHeaderStore } from '@stores/pageHeader'
import MoneyEntryModal from '@components/MoneyEntryModal.vue'
import MoneyBudgetModal from '@components/MoneyBudgetModal.vue'
import MoneyEntryRow from '@components/MoneyEntryRow.vue'
import MoneyBudgetProgress from '@components/MoneyBudgetProgress.vue'
import MoneySettleModal from '@components/MoneySettleModal.vue'
import AppButton from '@components/AppButton.vue'
import AppIconButton from '@components/AppIconButton.vue'

const route = useRoute()
const router = useRouter()
const store = useMoneyStore()
const authStore = useAuthStore()
const pageHeaderStore = usePageHeaderStore()

pageHeaderStore.setHeader('Budget')

const loading = computed(() => store.loading)
const budget = computed(() => store.currentBudget?.budget ?? null)
const account = computed(() => store.currentBudget?.account ?? null)
const entries = computed(() => store.currentBudget?.entries ?? [])

const entryModal = ref(null)
const budgetModal = ref(null)
const settleModal = ref(null)

const canEnterEntry = computed(() =>
  !!(account.value?.Permissions.canEnterDeposit || account.value?.Permissions.canEnterWithdrawal)
)

function formatCurrency(value) {
  return new Intl.NumberFormat('de-DE', { style: 'currency', currency: 'EUR' }).format(value || 0)
}

// Deckt sich mit der Backend-Regel in entryUpdate/entry (MoneyApiController):
// Freigeber dürfen jede Buchung bearbeiten/löschen, alle anderen nur ihre eigenen,
// solange diese noch nicht freigegeben sind.
function canDeleteEntry(entry) {
  if (!account.value) return false
  if (account.value.Permissions.canApprove) return true
  return !entry.Approved && entry.User?.ID === authStore.user?.ID
}

function canEditEntry(entry) {
  return canDeleteEntry(entry)
}

function openEntryModal(entry) {
  entryModal.value?.open(entry, entry ? null : budget.value?.ID ?? null)
}

async function remove(id) {
  if (!confirm('Diese Buchung wirklich löschen?')) return
  await store.removeEntry(id)
  await reload()
}

function onEntrySaved() {
  entryModal.value?.close()
  reload()
}

function openSettleModal(entry) {
  settleModal.value?.open(entry)
}

function onSettleSaved() {
  settleModal.value?.close()
  reload()
}

async function removeBudget() {
  if (!budget.value) return
  if (!confirm(`Budget "${budget.value.Title}" wirklich löschen? Buchungen bleiben erhalten, verlieren aber die Budget-Zuordnung.`)) return

  const response = await store.removeBudget(budget.value.ID)
  if (response.success) {
    router.push({ name: 'MoneyAccountDetail', params: { id: account.value.ID } })
  } else {
    alert(response.error || 'Fehler beim Löschen des Budgets.')
  }
}

function onBudgetSaved(savedBudget) {
  budgetModal.value?.close()
  if (!savedBudget) {
    // Budget wurde gelöscht – zurück zur Kasse
    router.push({ name: 'MoneyAccountDetail', params: { id: account.value.ID } })
    return
  }
  reload()
}

async function reload() {
  await store.fetchBudgetEntries(route.params.id)
}

watch(budget, val => {
  pageHeaderStore.setTitle(val?.Title ?? 'Budget')
})

let lightbox = null

onMounted(() => {
  reload()
  lightbox = GLightbox({ selector: '.money-entry_receipt', touchNavigation: true })
})

onUnmounted(() => {
  lightbox?.destroy()
})

watch(() => route.params.id, id => { if (id) reload() })

// Re-scan the DOM for receipt links whenever the entry list changes
watch(entries, () => {
  nextTick(() => lightbox?.reload())
}, { deep: true })
</script>
