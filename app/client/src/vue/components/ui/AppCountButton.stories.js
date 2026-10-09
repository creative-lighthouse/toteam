import AppCountButton from './AppCountButton.vue'

export default {
  title: 'Design System/AppCountButton',
  component: AppCountButton,
  tags: ['autodocs'],
  argTypes: {
    active: { control: 'boolean' },
    disabled: { control: 'boolean' },
    count: { control: 'number' },
  },
  args: {
    label: 'Interessiert',
    count: 12,
    active: false,
    disabled: false,
    countLabel: 'Personen interessiert',
  },
}

export const Playground = {
  render: (args) => ({
    components: { AppCountButton },
    setup() {
      return { args }
    },
    template: '<AppCountButton v-bind="args" />',
  }),
}

export const Paar = {
  render: () => ({
    components: { AppCountButton },
    template: `
      <div style="display: flex; gap: 8px; flex-wrap: wrap">
        <AppCountButton label="Interessiert" :count="12" />
        <AppCountButton label="Ich bin dabei" :count="5" active />
      </div>
    `,
  }),
}
