import AppAvatar from './AppAvatar.vue'

// AppAvatar bringt bewusst keine eigenen Größen/Farben mit — jeder Einsatzort steuert
// das Aussehen über seine eigene `img-class` (z. B. `.task-card_avatar` in taskcard.scss,
// inkl. einer `--placeholder`-Variante für den Initialen-Fallback). Für die Story wird
// hier stattdessen eine Inline-Demo-Optik verwendet, damit die Story unabhängig von den
// Seiten-Stylesheets funktioniert.
const demoStyle = {
  width: '48px',
  height: '48px',
  borderRadius: '50%',
  objectFit: 'cover',
  display: 'flex',
  alignItems: 'center',
  justifyContent: 'center',
  background: 'var(--ColorPrimary, #2f6fed)',
  color: '#fff',
  fontWeight: '700',
}

export default {
  title: 'Design System/AppAvatar',
  component: AppAvatar,
  tags: ['autodocs'],
  render: (args) => ({
    components: { AppAvatar },
    setup() {
      return { args, demoStyle }
    },
    template: `<AppAvatar v-bind="args" img-class="storybook-avatar" :style="demoStyle" />`,
  }),
}

export const WithImage = {
  args: {
    src: 'https://i.pravatar.cc/80?img=12',
    alt: 'Anna Beispiel',
  },
}

export const InitialsFallback = {
  args: {
    src: null,
    alt: 'Anna Beispiel',
  },
}

export const SingleInitial = {
  args: {
    src: null,
    alt: 'Anna Beispiel',
    name: 'Anna',
    initialsLength: 1,
  },
}

export const NoNameFallback = {
  args: {
    src: null,
    alt: '',
  },
}
