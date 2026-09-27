<template>
    <!-- Eine Gericht-Zeile einer Mahlzeit mit Bestell-Stepper (Termin-Dialog, Mahlzeit-Detailansicht) -->
    <div class="meal-food-row">
        <span class="meal-food-row_title">
            <slot name="leading" />{{ title }}
        </span>

        <span v-if="supplier" class="meal-food-row_supplier">von {{ supplier }}
            <span v-if="preference && preference !== 'None'" class="meal-food-row_pref">
                {{ preference === 'Vegetarian' ? '(Vegetarisch)' : '(Vegan)' }}
            </span>
        </span>
        <span v-else class="meal-food-row_supplier"></span>

        <div class="meal-food-row_trailing">
            <div v-if="orderable" class="meal-product-qty">
                <span v-if="maxQuantity > 0" class="meal-food-row_max">(max. {{ maxQuantity }})</span>
                <template v-if="canOrder">
                    <AppIconButton
                        variant="neutral"
                        aria-label="Menge verringern"
                        :disabled="disabled || quantity <= 0"
                        @click="$emit('decrement')"
                    >−</AppIconButton>
                    <span class="meal-product-qty_val">{{ quantity }}</span>
                    <AppIconButton
                        variant="neutral"
                        aria-label="Menge erhöhen"
                        :disabled="disabled || (maxQuantity > 0 && quantity >= maxQuantity)"
                        @click="$emit('increment')"
                    >+</AppIconButton>
                </template>
            </div>
            <slot name="trailing" />
        </div>

        <div v-if="$slots.footer" class="meal-food-row_footer">
            <slot name="footer" />
        </div>
    </div>
</template>

<script setup>
import AppIconButton from '@components/ui/AppIconButton.vue'

defineProps({
  title: { type: String, required: true },
  preference: { type: String, default: 'None' },
  supplier: { type: String, default: null },
  maxQuantity: { type: Number, default: 0 },
  orderable: { type: Boolean, default: false },
  // Ob die Bestell-Stepper interaktiv sind (z. B. nur wenn der Betrachter selbst
  // zugesagt hat) — unabhängig davon, ob der Eintrag grundsätzlich bestellbar ist,
  // damit der Max.-Badge weiterhin sichtbar bleibt, auch ohne Zusage.
  canOrder: { type: Boolean, default: true },
  quantity: { type: Number, default: 0 },
  disabled: { type: Boolean, default: false },
})

defineEmits(['increment', 'decrement'])
</script>
