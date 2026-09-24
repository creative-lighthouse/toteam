import AppButton from './AppButton.vue'

export default {
  title: 'Design System/AppButton',
  component: AppButton,
  tags: ['autodocs'],
  argTypes: {
    variant: { control: 'select', options: ['primary', 'secondary', 'danger'] },
    size: { control: 'select', options: ['default', 'small'] },
    type: { control: 'select', options: ['button', 'submit'] },
    disabled: { control: 'boolean' },
  },
  args: {
    variant: 'primary',
    size: 'default',
    type: 'button',
    disabled: false,
  },
}

export const Playground = {
  render: (args) => ({
    components: { AppButton },
    setup() {
      return { args }
    },
    template: '<AppButton v-bind="args">Speichern</AppButton>',
  }),
}

export const Variants = {
  render: () => ({
    components: { AppButton },
    template: `
      <div style="display:flex; gap:12px;">
        <AppButton variant="primary">Primary</AppButton>
        <AppButton variant="secondary">Secondary</AppButton>
        <AppButton variant="danger">Danger</AppButton>
      </div>
    `,
  }),
}

export const Sizes = {
  render: () => ({
    components: { AppButton },
    template: `
      <div style="display:flex; gap:12px; align-items:center;">
        <AppButton size="default">Default</AppButton>
        <AppButton size="small">Small</AppButton>
      </div>
    `,
  }),
}

export const Disabled = {
  args: { disabled: true },
  render: (args) => ({
    components: { AppButton },
    setup() {
      return { args }
    },
    template: '<AppButton v-bind="args">Nicht klickbar</AppButton>',
  }),
}
