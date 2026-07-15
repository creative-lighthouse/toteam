<template>
    <div class="meal-card">
        <span class="meal-card_title">
            <slot name="leading" />{{ title }}
        </span>

        <span v-if="supplier" class="meal-card_supplier">von {{ supplier }}
            <span v-if="preference && preference !== 'None'" class="meal-card_pref">
                {{ preference === 'Vegetarian' ? '(🥗 Vegetarisch)' : '(🌱 Vegan)' }}
            </span>
        </span>
        <span v-else class="meal-card_supplier"></span>

        <div class="meal-card_trailing">
            <div v-if="orderable" class="meal-product-qty">
                <span v-if="maxQuantity > 0" class="meal-card_max">(max. {{ maxQuantity }})</span>
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

        <div v-if="$slots.footer" class="meal-card_footer">
            <slot name="footer" />
        </div>
    </div>
</template>

<script setup>
import AppIconButton from '@components/AppIconButton.vue'

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
