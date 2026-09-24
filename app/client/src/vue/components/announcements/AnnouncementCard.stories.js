import AnnouncementCard from './AnnouncementCard.vue'

export default {
  title: 'Design System/AnnouncementCard',
  component: AnnouncementCard,
  tags: ['autodocs'],
}

export const Basic = {
  args: {
    announcement: {
      ID: 1,
      Title: 'Neue Öffnungszeiten',
      Created: '05.09.2026',
      ShortText: 'Ab sofort ist das Vereinsheim auch sonntags geöffnet.',
    },
  },
}

export const WithCategory = {
  args: {
    announcement: {
      ID: 2,
      Title: 'Wichtige Mitgliederinfo',
      Created: '01.09.2026',
      ShortText: 'Die Jahreshauptversammlung findet im Oktober statt.',
      Category: { ID: 1, Title: 'Intern' },
    },
  },
}

export const WithOrganizationLogos = {
  args: {
    announcement: {
      ID: 3,
      Title: 'Gemeinsame Aktion',
      Created: '28.08.2026',
      ShortText: 'Zwei Vereine, ein Ziel.',
      Organisations: [
        { ID: 1, Title: 'Musterverein e.V.', LogoURL: null },
        { ID: 2, Title: 'Jugendgruppe', LogoURL: null },
      ],
    },
  },
}

export const WithoutShortText = {
  args: {
    announcement: {
      ID: 4,
      Title: 'Kurzmitteilung',
      Created: '20.08.2026',
    },
  },
}
