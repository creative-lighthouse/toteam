import AppIconButton from './AppIconButton.vue'

export default {
  title: 'Design System/AppIconButton',
  component: AppIconButton,
  tags: ['autodocs'],
  argTypes: {
    variant: { control: 'select', options: ['primary', 'danger', 'neutral', 'ghost'] },
    disabled: { control: 'boolean' },
    ariaLabel: { control: 'text' },
  },
  args: {
    variant: 'primary',
    disabled: false,
    ariaLabel: 'Bearbeiten',
  },
}

export const Playground = {
  render: (args) => ({
    components: { AppIconButton },
    setup() {
      return { args }
    },
    template: '<AppIconButton v-bind="args">✎</AppIconButton>',
  }),
}

export const Variants = {
  render: () => ({
    components: { AppIconButton },
    template: `
      <div style="display:flex; gap:12px;">
        <AppIconButton variant="primary" aria-label="Bearbeiten">✎</AppIconButton>
        <AppIconButton variant="danger" aria-label="Löschen">✕</AppIconButton>
        <AppIconButton variant="neutral" aria-label="Anpassen">−</AppIconButton>
        <AppIconButton variant="ghost" aria-label="Schließen">✕</AppIconButton>
      </div>
    `,
  }),
}

export const Disabled = {
  args: { disabled: true },
  render: (args) => ({
    components: { AppIconButton },
    setup() {
      return { args }
    },
    template: '<AppIconButton v-bind="args">✎</AppIconButton>',
  }),
}
