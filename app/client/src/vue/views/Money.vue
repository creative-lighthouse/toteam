<template>
  <div class="section section--MoneyPage">
    <div class="section_content">

      <div v-if="loading" class="section_infobox"><p>Lade Kassen…</p></div>

      <div v-else-if="error" class="section_infobox error">
        <p>Fehler: {{ error }}</p>
        <button class="button" @click="load">Erneut versuchen</button>
      </div>

      <template v-else>
        <div class="money-toolbar">
          <button v-if="adminOrgs.length" class="button button--primary" @click="accountModal?.open()">
            + Neue Kasse
          </button>
        </div>

        <div v-if="store.accounts.length === 0" class="section_infobox">
          <p>Du bist noch keiner Kasse zugeordnet.</p>
          <p v-if="adminOrgs.length === 0" class="money-hint">
            Nur Admins einer Organisation können neue Kassen anlegen – frag einen Admin deiner Organisation, dir eine Kasse einzurichten.
          </p>
        </div>

        <div v-else class="money-account-list">
          <button
            v-for="account in store.accounts"
            :key="account.ID"
            class="money-account-card"
            @click="openAccount(account.ID)"
          >
            <div class="money-account-card_header">
              <div class="money-account-card_org">
                <img v-if="account.Organization?.LogoURL" :src="account.Organization.LogoURL" :alt="account.Organization.Title" class="money-account-card_org-logo">
                <span v-else class="money-account-card_org-initial">{{ (account.Organization?.Title || '?')[0] }}</span>
                <span class="money-account-card_org-name">{{ account.Organization?.Title }}</span>
              </div>
              <span v-if="account.PendingCount" class="money-badge money-badge--pending">{{ account.PendingCount }} offen</span>
            </div>

            <h3 class="money-account-card_title">{{ account.Title }}</h3>
            <p class="money-account-card_balance" :class="{ 'money-account-card_balance--negative': account.CachedCurrentBalance < 0 }">
              {{ formatCurrency(account.CachedCurrentBalance) }}
            </p>

            <div v-if="account.TargetAmount > 0" class="money-progress">
              <div class="money-progress_bar">
                <div class="money-progress_fill" :style="{ width: targetPercent(account) + '%' }"></div>
              </div>
              <span class="money-progress_label">Ziel: {{ formatCurrency(account.TargetAmount) }}</span>
            </div>
          </button>
        </div>
      </template>
    </div>

    <MoneyAccountModal ref="accountModal" mode="create" :admin-orgs="adminOrgs" @saved="onAccountCreated" />
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useMoneyStore } from '@stores/money'
import { useOrganizationsStore } from '@stores/organizations'
import { usePageHeaderStore } from '@stores/pageHeader'
import MoneyAccountModal from '@components/MoneyAccountModal.vue'

const router = useRouter()
const store = useMoneyStore()
const organizationsStore = useOrganizationsStore()
usePageHeaderStore().setHeader('Geld', 'Kassen, Budgets und Buchungen deiner Organisationen.')

const accountModal = ref(null)
const loading = computed(() => store.loading)
const error = computed(() => store.error)

const adminOrgs = computed(() =>
  organizationsStore.organizations.filter(o => o.Permissions?.includes('MONEY_ACCOUNTS_CREATE'))
)

function formatCurrency(value) {
  return new Intl.NumberFormat('de-DE', { style: 'currency', currency: 'EUR' }).format(value || 0)
}

function targetPercent(account) {
  if (!account.TargetAmount) return 0
  return Math.max(0, Math.min(100, (account.CachedCurrentBalance / account.TargetAmount) * 100))
}

function openAccount(id) {
  router.push({ name: 'MoneyAccountDetail', params: { id } })
}

function onAccountCreated(account) {
  store.fetchOverview(true)
  router.push({ name: 'MoneyAccountDetail', params: { id: account.ID } })
}

async function load() {
  await Promise.all([
    store.fetchOverview(),
    organizationsStore.fetchOrganizations(),
  ])
}

onMounted(load)
</script>
