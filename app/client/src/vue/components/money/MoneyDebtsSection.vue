<!--
  "Schulden" auf der Kassenseite (aufklappbar): was die Kasse einem selbst noch für
  ausgelegte Ausgaben auszahlen muss; Freigebende sehen darunter die offenen Beträge
  aller Mitglieder mit Summe. "filter" (Member-ID) filtert den Buchungsverlauf auf die offenen
  Buchungen dieser Person.
-->
<template>
  <section class="money-section money-accordion money-debts" :class="{ 'money-accordion--open': open }">
    <button
      type="button"
      class="money-accordion_toggle"
      :aria-expanded="open"
      aria-controls="money-debts-content"
      @click="open = !open"
    >
      <span class="hl3 money-section_title money-accordion_title">Schulden</span>
      <span class="money-accordion_summary">
        <span class="money-debts_summary" :class="{ 'money-debts_summary--open': summary.Open > 0 }">
          {{ formatCurrency(summary.Open) }} offen
        </span>
      </span>
      <span class="money-accordion_chevron" aria-hidden="true"></span>
    </button>

    <div id="money-debts-content" class="money-accordion_content" :inert="!open">
      <div class="money-accordion_inner">
        <div class="money-debt-list">
          <!-- Eigene offene Beträge -->
          <div class="money-debt">
            <template v-if="debts.Own.Total > 0">
              <div class="money-debt_header">
                <span class="money-debt_title">Noch an dich auszuzahlen</span>
                <span class="money-debt_amount">{{ formatCurrency(debts.Own.Open) }}</span>
              </div>
              <MoneyDebtProgress :debt="debts.Own" />
              <div v-if="debts.Own.Open > 0 && userId" class="money-debt_actions">
                <AppButton size="small" variant="secondary" @click="$emit('filter', userId)">Offene Buchungen anzeigen</AppButton>
              </div>
            </template>
            <p v-else class="money-debt_empty">Du hast in dieser Kasse keine Ausgaben ausgelegt.</p>
          </div>

          <!-- Alle Mitglieder (nur für Freigebende) -->
          <template v-if="debts.Members">
            <h4 class="money-debts_subtitle">Alle Mitglieder</h4>

            <div v-if="debts.Total.Total > 0" class="money-debt money-debt--total">
              <div class="money-debt_header">
                <span class="money-debt_title">Summe offen</span>
                <span class="money-debt_amount">{{ formatCurrency(debts.Total.Open) }}</span>
              </div>
              <MoneyDebtProgress :debt="debts.Total" />
            </div>

            <p v-if="debts.Members.length === 0" class="money-debt_empty">Allen Mitgliedern ist alles ausgezahlt.</p>

            <div v-for="row in debts.Members" :key="row.User.ID" class="money-debt">
              <div class="money-debt_header">
                <span class="money-debt_person">
                  <img v-if="row.User.Avatar" :src="row.User.Avatar" alt="" class="money-debt_avatar" />
                  {{ row.User.Name }}
                </span>
                <span class="money-debt_amount">{{ formatCurrency(row.Open) }}</span>
              </div>
              <MoneyDebtProgress :debt="row" />
              <div class="money-debt_actions">
                <AppButton size="small" variant="secondary" @click="$emit('filter', row.User.ID)">Offene Buchungen anzeigen</AppButton>
              </div>
            </div>
          </template>
        </div>
      </div>
    </div>
  </section>
</template>

<script setup>
import { ref, computed } from 'vue'
import { useAuthStore } from '@stores/auth'
import AppButton from '@components/ui/AppButton.vue'
import MoneyDebtProgress from '@components/money/MoneyDebtProgress.vue'

const props = defineProps({
  // account.Debts: { Own, Members?, Total? } (Members/Total nur für Freigebende)
  debts: { type: Object, required: true },
})
defineEmits(['filter'])

const authStore = useAuthStore()
const userId = computed(() => authStore.user?.ID ?? null)

const open = ref(false)

// Im Kopf: für Freigebende die Summe aller offenen Beträge, sonst die eigenen
const summary = computed(() => props.debts.Total ?? props.debts.Own)

function formatCurrency(value) {
  return new Intl.NumberFormat('de-DE', { style: 'currency', currency: 'EUR' }).format(value || 0)
}
</script>
