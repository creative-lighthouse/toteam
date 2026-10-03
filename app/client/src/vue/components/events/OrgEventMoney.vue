<template>
  <!-- Kassen-Karte der Event-Seite: pro Kasse Kontostand, Füllstand zum Ziel und
       Gesamtbudget — keine Buchungen; "Zur Kasse" führt in den Geld-Bereich -->
  <div class="org-event-money">
    <article v-for="account in state.accounts" :key="account.ID" class="org-event-money_account">
      <header class="org-event-money_head">
        <h3 class="org-event-money_title">{{ account.Title }}</h3>
        <AppIconButton
          v-if="account.CanUnlink"
          variant="ghost"
          aria-label="Kasse vom Event lösen"
          title="Vom Event lösen"
          :disabled="busy"
          @click="detach(account)"
        >
          <span class="icon-mask" :style="trashIconStyle" aria-hidden="true" />
        </AppIconButton>
      </header>

      <p class="org-event-money_balance" :class="{ 'org-event-money_balance--negative': account.CachedCurrentBalance < 0 }">
        {{ formatCurrency(account.CachedCurrentBalance) }}
      </p>

      <!-- Füllstand: Kontostand im Verhältnis zum Zielbetrag -->
      <div v-if="account.TargetAmount > 0" class="money-progress">
        <div class="money-progress_bar">
          <div class="money-progress_fill" :style="{ width: targetPercent(account) + '%' }"></div>
        </div>
        <span class="money-progress_label">Ziel: {{ formatCurrency(account.TargetAmount) }}</span>
      </div>

      <!-- Gesamtbudget wie in der Kasse (nur Budgets mit Limit) -->
      <div v-if="totalBudget(account)" class="org-event-money_budget">
        <MoneyBudgetProgress :budget="totalBudget(account)" />
        <span class="money-progress_label" :class="{ 'money-progress_label--over': totalBudget(account).Remaining < 0 }">
          Budget: {{ formatCurrency(totalBudget(account).Spent) }} / {{ formatCurrency(totalBudget(account).Budget) }}
        </span>
      </div>

      <AppButton
        variant="secondary"
        size="small"
        :to="{ name: 'MoneyAccountDetail', params: { id: account.ID } }"
        class="org-event-money_open"
      >Zur Kasse</AppButton>
    </article>

    <p v-if="error" class="org-event-money_error">{{ error }}</p>
  </div>
</template>

<script setup>
import { ref } from 'vue'
import { useMoneyStore } from '@stores/money'
import AppButton from '@components/ui/AppButton.vue'
import AppIconButton from '@components/ui/AppIconButton.vue'
import MoneyBudgetProgress from '@components/money/MoneyBudgetProgress.vue'
import actionTrash from '../../../../icons/actions/action_trash.svg'

const trashIconStyle = { maskImage: `url("${actionTrash}")`, WebkitMaskImage: `url("${actionTrash}")` }

const props = defineProps({
  event: { type: Object, required: true },
  // Stand aus GET /money/eventAccounts/{id}: { accounts, availableAccounts, CanCreate }
  state: { type: Object, required: true },
})

// Neuer Gesamtstand nach jeder Änderung — die Event-Seite hält ihn
const emit = defineEmits(['update'])

const store = useMoneyStore()
const busy = ref(false)
const error = ref(null)

function formatCurrency(value) {
  return new Intl.NumberFormat('de-DE', { style: 'currency', currency: 'EUR' }).format(value || 0)
}

function targetPercent(account) {
  return Math.max(0, Math.min(100, (account.CachedCurrentBalance / account.TargetAmount) * 100))
}

// Summe aller Budgets mit Limit im Format eines einzelnen Budgets (wie in MoneyAccountDetail)
function totalBudget(account) {
  const limited = account.Budgets.filter(b => b.HasBudget)
  if (!limited.length) return null
  const sum = key => limited.reduce((acc, b) => acc + (Number(b[key]) || 0), 0)
  const budget = sum('Budget')
  const spent = sum('Spent')
  return { HasBudget: true, Budget: budget, Spent: spent, PendingAmount: sum('PendingAmount'), Remaining: budget - spent }
}

async function detach(account) {
  if (!confirm(`Kasse „${account.Title}“ von diesem Event lösen? Die Kasse samt Buchungen bleibt erhalten.`)) return
  busy.value = true
  error.value = null
  try {
    emit('update', await store.detachEventAccount(props.event.ID, account.ID))
  } catch (e) {
    error.value = e.message
  } finally {
    busy.value = false
  }
}
</script>
