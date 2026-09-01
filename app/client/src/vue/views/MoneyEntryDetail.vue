<template>
  <div class="section section--MoneyAccountDetailPage">
    <div class="section_content">

      <div v-if="loading" class="section_infobox"><p>Lade Buchung…</p></div>

      <div v-else-if="!entry" class="section_infobox">
        <p>Buchung nicht gefunden.</p>
      </div>

      <div v-else class="money-detail">

        <!-- Header -->
        <div class="money-detail_header">
          <RouterLink :to="{ name: 'MoneyAccountDetail', params: { id: account.ID } }" class="money-detail_org">
            {{ account.Title }}
          </RouterLink>
          <div v-if="canEditEntry || canDeleteEntry" class="money-detail_header-actions">
            <AppIconButton
              v-if="canEditEntry"
              variant="primary"
              aria-label="Buchung bearbeiten"
              @click="entryModal?.open(entry)"
            >
              <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
            </AppIconButton>
            <AppIconButton
              v-if="canDeleteEntry"
              variant="danger"
              aria-label="Buchung löschen"
              @click="remove"
            >
              <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/></svg>
            </AppIconButton>
          </div>
        </div>

        <h2 class="hl2 money-detail_title">{{ entry.ChangeReason }}</h2>

        <p class="money-detail_balance" :class="{ 'money-detail_balance--negative': entry.ChangeType === 'Withdrawal' }">
          {{ sign }} {{ formatCurrency(entry.ChangeAmount) }}
        </p>

        <!-- Details -->
        <section class="money-section">
          <h3 class="hl3 money-section_title">Details</h3>
          <table class="money-entry-detail_table">
            <tbody>
              <tr>
                <th>Typ</th>
                <td>{{ entry.ChangeType === 'Deposit' ? 'Einnahme' : 'Ausgabe' }}</td>
              </tr>
              <tr>
                <th>Status</th>
                <td>{{ entry.Approved ? 'Freigegeben' : 'Ausstehend' }}</td>
              </tr>
              <tr>
                <th>Rechnungsdatum</th>
                <td>{{ formatDate(entry.ChangeDate) }}</td>
              </tr>
              <tr>
                <th>Erstellt am</th>
                <td>{{ formatDateTime(entry.Created) }}</td>
              </tr>
              <tr>
                <th>Erfasst von</th>
                <td>{{ entry.User?.Name ?? '—' }}</td>
              </tr>
              <tr v-if="entry.Budget">
                <th>Kategorie</th>
                <td>{{ entry.Budget.Title }}</td>
              </tr>
              <tr v-if="entry.ReceiptURL">
                <th>Beleg</th>
                <td>
                  <a
                    class="money-entry_receipt"
                    :href="entry.ReceiptURL"
                    :data-type="entry.ReceiptURL.toLowerCase().endsWith('.pdf') ? 'external' : 'image'"
                  >Beleg ansehen</a>
                </td>
              </tr>
            </tbody>
          </table>
          <div v-if="entry.Notes" class="money-entry-detail_notes">
            <p class="money-entry-detail_notes-label">Anmerkungen</p>
            <p class="money-entry-detail_notes-text">{{ entry.Notes }}</p>
          </div>
        </section>

        <!-- Begleichung -->
        <section v-if="entry.ChangeType === 'Withdrawal'" class="money-section">
          <h3 class="hl3 money-section_title">Begleichung</h3>
          <p class="money-entry-detail_settle-summary">
            {{ formatCurrency(entry.SettledAmount || 0) }} von {{ formatCurrency(entry.ChangeAmount) }} beglichen
          </p>
          <div v-if="!entry.Settlements?.length" class="section_infobox"><p>Noch keine Zahlungen erfasst.</p></div>
          <ul v-else class="money-entry-detail_settle-list">
            <li v-for="s in entry.Settlements" :key="s.ID">
              {{ formatCurrency(s.Amount) }} ({{ s.PaymentMethod }}) am {{ formatDate(s.Date) }}<span v-if="s.User"> · {{ s.User.Name }}</span>
            </li>
          </ul>
        </section>

      </div>
    </div>

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
import AppIconButton from '@components/AppIconButton.vue'

const route = useRoute()
const router = useRouter()
const store = useMoneyStore()
const authStore = useAuthStore()
const pageHeaderStore = usePageHeaderStore()

pageHeaderStore.setHeader('Buchung')

const loading = computed(() => store.loading)
const entry = computed(() => store.currentEntry?.entry ?? null)
const account = computed(() => store.currentEntry?.account ?? null)

const entryModal = ref(null)

const sign = computed(() => (entry.value?.ChangeType === 'Deposit' ? '+' : '-'))

// Deckt sich mit der Backend-Regel in entryUpdate/entry (MoneyApiController):
// Freigeber dürfen jede Buchung bearbeiten/löschen, alle anderen nur ihre eigenen,
// solange diese noch nicht freigegeben sind.
const canDeleteEntry = computed(() => {
  if (!account.value || !entry.value) return false
  if (account.value.Permissions.canApprove) return true
  return !entry.value.Approved && entry.value.User?.ID === authStore.user?.ID
})

const canEditEntry = computed(() => canDeleteEntry.value)

function onEntrySaved() {
  entryModal.value?.close()
  reload()
}

async function remove() {
  if (!confirm('Diese Buchung wirklich löschen?')) return
  await store.removeEntry(entry.value.ID)
  router.push({ name: 'MoneyAccountDetail', params: { id: account.value.ID } })
}

function formatCurrency(value) {
  return new Intl.NumberFormat('de-DE', { style: 'currency', currency: 'EUR' }).format(value || 0)
}

function formatDate(dateStr) {
  if (!dateStr) return ''
  return new Intl.DateTimeFormat('de-DE', { day: '2-digit', month: '2-digit', year: 'numeric' }).format(new Date(dateStr))
}

function formatDateTime(dateStr) {
  if (!dateStr) return '—'
  return new Intl.DateTimeFormat('de-DE', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' }).format(new Date(dateStr))
}

watch(entry, val => {
  pageHeaderStore.setTitle(val?.ChangeReason ?? 'Buchung')
})

let lightbox = null

async function reload() {
  await store.fetchEntryDetail(route.params.id)
}

onMounted(() => {
  reload()
  lightbox = GLightbox({ selector: '.money-entry_receipt', touchNavigation: true })
})

onUnmounted(() => {
  lightbox?.destroy()
})

watch(() => route.params.id, id => { if (id) reload() })

// DOM erneut nach dem Beleg-Link scannen, sobald die Buchung geladen ist
watch(entry, () => {
  nextTick(() => lightbox?.reload())
})
</script>
