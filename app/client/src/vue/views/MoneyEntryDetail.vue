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
  </div>
</template>

<script setup>
import { computed, onMounted, onUnmounted, nextTick, watch } from 'vue'
import { useRoute } from 'vue-router'
import GLightbox from 'glightbox'
import { useMoneyStore } from '@stores/money'
import { usePageHeaderStore } from '@stores/pageHeader'

const route = useRoute()
const store = useMoneyStore()
const pageHeaderStore = usePageHeaderStore()

pageHeaderStore.setHeader('Buchung')

const loading = computed(() => store.loading)
const entry = computed(() => store.currentEntry?.entry ?? null)
const account = computed(() => store.currentEntry?.account ?? null)

const sign = computed(() => (entry.value?.ChangeType === 'Deposit' ? '+' : '-'))

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
