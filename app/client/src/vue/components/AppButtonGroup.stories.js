import { ref } from 'vue'
import AppButtonGroup from './AppButtonGroup.vue'

export default {
  title: 'Design System/AppButtonGroup',
  component: AppButtonGroup,
  tags: ['autodocs'],
  argTypes: {
    size: { control: 'select', options: ['default', 'compact'] },
    disabled: { control: 'boolean' },
  },
  args: {
    options: [
      { value: 'Accept', label: 'Zusagen', tone: 'positive' },
      { value: 'Maybe', label: 'Vielleicht', tone: 'warning' },
      { value: 'Decline', label: 'Absagen', tone: 'negative' },
    ],
    modelValue: 'Accept',
    size: 'default',
    disabled: false,
  },
}

export const Playground = {
  render: (args) => ({
    components: { AppButtonGroup },
    setup() {
      const selected = ref(args.modelValue)
      return { args, selected }
    },
    template: '<AppButtonGroup v-bind="args" :model-value="selected" @select="v => selected = v" />',
  }),
}

export const Compact = {
  args: { size: 'compact' },
  render: (args) => ({
    components: { AppButtonGroup },
    setup() {
      const selected = ref(args.modelValue)
      return { args, selected }
    },
    template: '<AppButtonGroup v-bind="args" :model-value="selected" @select="v => selected = v" />',
  }),
}

export const NoSelection = {
  args: { modelValue: null },
  render: (args) => ({
    components: { AppButtonGroup },
    setup() {
      const selected = ref(null)
      return { args, selected }
    },
    template: '<AppButtonGroup v-bind="args" :model-value="selected" @select="v => selected = v" />',
  }),
}

// Zwei Optionen statt drei (z.B. Essens-Zusage) — Pill/Icons/Einfärbung passen sich
// automatisch an die Anzahl der Optionen an, keine Sonderbehandlung nötig.
export const TwoWay = {
  args: {
    options: [
      { value: 'Accept', label: 'Dabei', tone: 'positive' },
      { value: 'Decline', label: 'Nicht dabei', tone: 'negative' },
    ],
    modelValue: null,
  },
  render: (args) => ({
    components: { AppButtonGroup },
    setup() {
      const selected = ref(args.modelValue)
      return { args, selected }
    },
    template: '<AppButtonGroup v-bind="args" :model-value="selected" @select="v => selected = v" />',
  }),
}
