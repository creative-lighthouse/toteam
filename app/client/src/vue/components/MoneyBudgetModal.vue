<template>
  <Teleport to="body">
    <dialog ref="dialogEl" class="money-budget-modal" @cancel.prevent="close">
      <div class="money-budget-modal_content" @click.stop>

        <div class="money-budget-modal_header">
          <h2 class="hl2 money-budget-modal_title">{{ isEdit ? 'Budget bearbeiten' : 'Neues Budget' }}</h2>
          <AppIconButton variant="ghost" aria-label="Schließen" @click="close">✕</AppIconButton>
        </div>

        <form class="money-budget-modal_body" @submit.prevent="submit">

          <div class="form-field">
            <label class="form-label" for="budget-title">Titel *</label>
            <input id="budget-title" v-model="form.Title" type="text" class="input" placeholder="z.B. Sommerfest" required />
          </div>

          <label class="form-checkbox">
            <input type="checkbox" v-model="form.HasBudget" />
            Budget-Limit festlegen
          </label>

          <div v-if="form.HasBudget" class="form-field">
            <label class="form-label" for="budget-amount">Budget (€)</label>
            <input id="budget-amount" v-model="form.Budget" type="number" step="0.01" min="0" class="input" />
          </div>

          <label v-if="form.HasBudget" class="form-checkbox">
            <input type="checkbox" v-model="form.CanBeOverBudget" />
            Kann über Budget gehen
          </label>

          <div v-if="error" class="money-budget-modal_error">{{ error }}</div>

          <div class="money-budget-modal_actions">
            <AppButton variant="secondary" :disabled="saving" @click="close">Abbrechen</AppButton>
            <AppButton type="submit" variant="primary" :disabled="saving || !form.Title.trim()">
              {{ saving ? 'Speichern…' : (isEdit ? 'Speichern' : 'Erstellen') }}
            </AppButton>
          </div>

        </form>
      </div>
    </dialog>
  </Teleport>
</template>

<script setup>
import { ref, reactive, computed, watch } from 'vue'
import { useMoneyStore } from '@stores/money'
import AppButton from '@components/AppButton.vue'
import AppIconButton from '@components/AppIconButton.vue'

const props = defineProps({
  accountId: { type: Number, required: true },
  budget: { type: Object, default: null },
})
const emit = defineEmits(['saved'])
const store = useMoneyStore()

const dialogEl = ref(null)
const saving = ref(false)
const error = ref(null)

const isEdit = computed(() => !!props.budget)

const defaultForm = () => ({
  Title: '',
  HasBudget: false,
  Budget: 0,
  CanBeOverBudget: false,
})

const form = reactive(defaultForm())

function fillFromBudget(budget) {
  if (!budget) return
  form.Title = budget.Title
  form.HasBudget = budget.HasBudget
  form.Budget = budget.Budget
  form.CanBeOverBudget = budget.CanBeOverBudget
}

watch(() => props.budget, fillFromBudget)

function open() {
  Object.assign(form, defaultForm())
  fillFromBudget(props.budget)
  error.value = null
  dialogEl.value?.showModal()
}

function close() {
  dialogEl.value?.close()
}

async function submit() {
  if (!form.Title.trim()) return

  saving.value = true
  error.value = null

  const payload = {
    Title: form.Title.trim(),
    HasBudget: form.HasBudget,
    Budget: parseFloat(form.Budget) || 0,
    CanBeOverBudget: form.CanBeOverBudget,
  }

  try {
    const response = isEdit.value
      ? await store.updateBudget(props.budget.ID, payload)
      : await store.createBudget({ ...payload, AccountID: props.accountId })

    if (response.success) {
      emit('saved', response.data.budget)
      close()
    } else {
      error.value = response.error || 'Fehler beim Speichern des Budgets.'
    }
  } catch (err) {
    error.value = err.message || 'Unbekannter Fehler.'
  } finally {
    saving.value = false
  }
}

defineExpose({ open, close })
</script>
