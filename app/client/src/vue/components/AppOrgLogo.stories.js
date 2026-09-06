import AppOrgLogo from './AppOrgLogo.vue'

export default {
  title: 'Design System/AppOrgLogo',
  component: AppOrgLogo,
  tags: ['autodocs'],
  args: {
    size: 56,
  },
}

export const WithLogo = {
  args: {
    src: 'https://i.pravatar.cc/120?img=5',
    alt: 'Musterverein e.V.',
  },
}

export const InitialsFallback = {
  args: {
    src: null,
    alt: 'Musterverein e.V.',
  },
}

export const Small = {
  args: {
    src: null,
    alt: 'Jugendgruppe',
    size: 22,
  },
}

export const Large = {
  args: {
    src: null,
    alt: 'Sportverein',
    size: 64,
  },
}
