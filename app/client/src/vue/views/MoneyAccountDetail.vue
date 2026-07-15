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
              <MoneyEntryRow :entry="entry" />
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
            <AppButton v-if="account.Permissions.canManageBudgets" size="small" variant="secondary" @click="openBudgetModal(null)">+ Budget</AppButton>
          </div>

          <div v-if="account.Budgets.length === 0" class="section_infobox"><p>Noch keine Budgets angelegt.</p></div>

          <div v-else class="money-budget-list">
            <button
              v-for="budget in account.Budgets"
              :key="budget.ID"
              class="money-budget"
              :disabled="!account.Permissions.canManageBudgets"
              @click="openBudgetModal(budget)"
            >
              <div class="money-budget_header">
                <span class="money-budget_title">{{ budget.Title }}</span>
                <span class="money-budget_amount">
                  {{ formatCurrency(budget.Spent) }}<span v-if="budget.HasBudget"> / {{ formatCurrency(budget.Budget) }}</span>
                </span>
              </div>
              <div v-if="budget.HasBudget" class="money-progress money-progress--budget">
                <div class="money-progress_bar">
                  <div class="money-progress_fill" :class="{ 'money-progress_fill--over': budget.Remaining < 0 }" :style="{ width: budgetPercent(budget) + '%' }"></div>
                </div>
              </div>
            </button>
          </div>
        </section>

        <!-- Buchungsverlauf -->
        <section class="money-section">
          <h3 class="hl3 money-section_title">Buchungsverlauf</h3>

          <div v-if="account.History.length === 0" class="section_infobox"><p>Noch keine Buchungen erfasst.</p></div>

          <div v-else class="money-entry-list">
            <div v-for="entry in account.History" :key="entry.ID" class="money-entry">
              <MoneyEntryRow :entry="entry" />
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
  </div>
</template>

<script setup>
import { ref, computed, defineComponent, h, onMounted, onUnmounted, nextTick, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import GLightbox from 'glightbox'
import { useMoneyStore } from '@stores/money'
import { usePageHeaderStore } from '@stores/pageHeader'
import MoneyEntryModal from '@components/MoneyEntryModal.vue'
import MoneyAccountModal from '@components/MoneyAccountModal.vue'
import MoneyBudgetModal from '@components/MoneyBudgetModal.vue'
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
const approving = ref(null)

const targetPercent = computed(() => {
  if (!account.value?.TargetAmount) return 0
  return Math.max(0, Math.min(100, (account.value.CachedCurrentBalance / account.value.TargetAmount) * 100))
})

function formatCurrency(value) {
  return new Intl.NumberFormat('de-DE', { style: 'currency', currency: 'EUR' }).format(value || 0)
}

function formatDate(dateStr) {
  if (!dateStr) return ''
  return new Intl.DateTimeFormat('de-DE', { day: '2-digit', month: '2-digit', year: 'numeric' }).format(new Date(dateStr))
}

function budgetPercent(budget) {
  if (!budget.Budget) return 0
  return Math.max(0, Math.min(100, (budget.Spent / budget.Budget) * 100))
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

// ── MoneyEntryRow sub-component ─────────────────────────────────────────
const MoneyEntryRow = defineComponent({
  name: 'MoneyEntryRow',
  props: { entry: { type: Object, required: true } },
  setup(props) {
    return () => {
      const e = props.entry
      const sign = e.ChangeType === 'Deposit' ? '+' : '-'
      const amountClass = e.ChangeType === 'Deposit' ? 'money-entry_amount--deposit' : 'money-entry_amount--withdrawal'

      return h('div', { class: 'money-entry_row' }, [
        h('div', { class: 'money-entry_main' }, [
          h('div', { class: 'money-entry_top' }, [
            h('span', { class: 'money-entry_reason' }, e.ChangeReason),
            h('span', { class: ['money-entry_amount', amountClass] }, `${sign} ${formatCurrency(e.ChangeAmount)}`),
          ]),
          h('div', { class: 'money-entry_meta' }, [
            h('span', formatDate(e.ChangeDate)),
            e.User ? h('span', `· ${e.User.Name}`) : null,
            e.Budget ? h('span', { class: 'money-entry_budget-tag' }, e.Budget.Title) : null,
            e.ReceiptURL ? h('a', {
              class: 'money-entry_receipt',
              href: e.ReceiptURL,
              'data-type': e.ReceiptURL.toLowerCase().endsWith('.pdf') ? 'iframe' : 'image',
            }, 'Beleg') : null,
            !e.Approved ? h('span', { class: 'money-entry_pending-badge' }, 'Ausstehend') : null,
          ]),
        ]),
      ])
    }
  },
})
</script>
