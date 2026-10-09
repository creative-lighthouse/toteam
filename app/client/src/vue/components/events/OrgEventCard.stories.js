import OrgEventCard from './OrgEventCard.vue'

// Testbild als Data-URL, damit die Stories ohne Server funktionieren
const image = 'data:image/svg+xml;utf8,' + encodeURIComponent(
  '<svg xmlns="http://www.w3.org/2000/svg" width="640" height="360">'
  + '<rect width="640" height="360" fill="#3f567c"/><circle cx="470" cy="150" r="80" fill="#FDDFB2"/>'
  + '<rect x="60" y="230" width="300" height="44" rx="8" fill="#C7DCFC"/></svg>'
)

const logo = 'data:image/svg+xml;utf8,' + encodeURIComponent(
  '<svg xmlns="http://www.w3.org/2000/svg" width="80" height="80">'
  + '<rect width="80" height="80" fill="#213345"/><path d="M40 14 62 58H18z" fill="#FDDFB2"/></svg>'
)

const base = {
  ID: 1,
  Title: 'Halloweenhaus 2026',
  URLSegment: 'halloweenhaus-2026',
  OrganizationTitle: "Otto's Halloweenhaus",
  OrganizationLogoURL: logo,
  DateStart: '2026-10-30',
  DateEnd: '2026-11-01',
  TimeStart: '18:00:00',
  TimeEnd: '22:00:00',
  AllDay: false,
  RangeStart: '2026-10-30',
  RangeEnd: '2026-11-01',
  IsPublic: true,
  Location: 'Ottos Garten',
  Street: 'Hauptstraße 1',
  PostalCode: '22952',
  City: 'Lütjensee',
  TypeTitle: 'Aufführung',
  AgeGroups: [{ ID: 1, Title: 'Ab 12' }, { ID: 2, Title: 'Erwachsene' }],
  ImageURL: image,
  PriceMode: 'Tiered',
  Prices: [{ ID: 1, Title: 'Kinder', Price: 4 }, { ID: 2, Title: 'Erwachsene', Price: 8.5 }],
}

export default {
  title: 'Events/OrgEventCard',
  component: OrgEventCard,
  tags: ['autodocs'],
}

// Breite ist variabel — die Karte füllt ihre Spalte; einzeln hier in einer 300px-Spalte
const column = [() => ({ template: '<div style="width: 300px"><story /></div>' })]

export const Complete = {
  decorators: column,
  args: { event: base } }

export const WithoutImage = {
  decorators: column,
  args: { event: { ...base, ImageURL: null, IsPublic: false } },
}

export const Minimal = {
  decorators: column,
  args: {
    event: {
      ...base,
      Title: 'Sommerfest',
      DateStart: null, DateEnd: null, RangeStart: null, RangeEnd: null,
      TypeTitle: null, AgeGroups: [], Prices: [], ImageURL: null, IsPublic: false,
      Location: null, Street: null, PostalCode: null, City: null,
    },
  },
}

export const ManyAgeGroupsAndLongTitle = {
  decorators: column,
  args: {
    event: {
      ...base,
      Title: 'Großes Familien- und Nachbarschaftsfest mit Flohmarkt, Musik und Kuchenbuffet',
      AgeGroups: [
        { ID: 1, Title: 'Kinder' }, { ID: 2, Title: 'Jugendliche' },
        { ID: 3, Title: 'Erwachsene' }, { ID: 4, Title: 'Senioren' },
      ],
      Prices: [{ ID: 1, Title: 'Alle', Price: 0 }],
    },
  },
}

export const FixedPrice = {
  decorators: column,
  args: { event: { ...base, PriceMode: 'Fixed', Prices: [{ ID: 1, Title: '', Price: 12 }] } },
}

export const Free = {
  decorators: column,
  args: { event: { ...base, PriceMode: 'Free', Prices: [] } },
}

export const Donation = {
  decorators: column,
  args: { event: { ...base, PriceMode: 'Donation', Prices: [] } },
}

export const Past = {
  decorators: column,
  args: {
    event: { ...base, DateStart: '2025-10-30', DateEnd: '2025-11-01', RangeStart: '2025-10-30', RangeEnd: '2025-11-01' },
  },
}

export const WithOrganization = {
  decorators: column,
  args: { event: base, showOrganization: true },
}

// Wie auf der Events-Seite: gleiche Karten, Spaltenanzahl je nach verfügbarer Breite
export const InGrid = {
  decorators: [() => ({ template: '<div class="section--EventsPage"><story /></div>' })],
  render: () => ({
    components: { OrgEventCard },
    setup: () => ({
      events: [
        Complete.args.event,
        WithoutImage.args.event,
        ManyAgeGroupsAndLongTitle.args.event,
        Minimal.args.event,
        Past.args.event,
      ].map((event, i) => ({ ...event, ID: i + 1 })),
    }),
    template: `
      <ul class="org-event-grid">
        <li v-for="event in events" :key="event.ID"><OrgEventCard :event="event" /></li>
      </ul>`,
  }),
}
