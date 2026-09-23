import HistoryEntry from './HistoryEntry.vue'

const member = { ID: 1, Name: 'Anna Beispiel', Avatar: null }

export default {
  title: 'Design System/HistoryEntry',
  component: HistoryEntry,
  tags: ['autodocs'],
  args: {
    createdLabel: 'hat die Aufgabe erstellt',
  },
}

export const Changes = {
  args: {
    entry: {
      ID: 3,
      Type: 'changed',
      Date: '2026-09-23T14:05:00+02:00',
      Member: member,
      Changes: [
        { Field: 'Title', Label: 'Titel', Kind: 'value', Format: 'text', Old: 'Flyer drucken', New: 'Flyer & Plakate drucken' },
        { Field: 'State', Label: 'Status', Kind: 'value', Format: 'text', Old: 'Offen', New: 'In Bearbeitung' },
        { Field: 'Deadline', Label: 'Fälligkeitsdatum', Kind: 'value', Format: 'datetime', Old: null, New: '2026-10-01 00:00:00' },
        {
          Field: 'Supporters', Label: 'Unterstützer', Kind: 'set',
          Added: [{ ID: 2, Label: 'Ben Muster' }],
          Removed: [{ ID: 3, Label: 'Carla Test' }],
        },
      ],
    },
  },
}

export const Description = {
  args: {
    entry: {
      ID: 2,
      Type: 'changed',
      Date: '2026-09-22T09:30:00+02:00',
      Member: { ID: 2, Name: 'Ben Muster', Avatar: null },
      Changes: [
        {
          Field: 'Description', Label: 'Beschreibung', Kind: 'value', Format: 'longtext',
          Old: 'Bitte 200 Flyer in A5 drucken.',
          New: 'Bitte 200 Flyer in A5 drucken.\nZusätzlich 20 Plakate in A2 für die Innenstadt.',
        },
      ],
    },
  },
}

export const Subtasks = {
  args: {
    entry: {
      ID: 4,
      Type: 'changed',
      Date: '2026-09-23T15:12:00+02:00',
      Member: member,
      Changes: [
        { Field: 'SubTasks', Label: 'Unteraufgaben', Kind: 'set', Added: [{ ID: 10, Label: 'Druckerei anfragen' }], Removed: [] },
      ],
    },
  },
}

export const Created = {
  args: {
    entry: { ID: 1, Type: 'created', Date: '2026-09-20T18:00:00+02:00', Member: member, Changes: [] },
  },
}

export const UnknownMember = {
  args: {
    entry: {
      ID: 5,
      Type: 'changed',
      Date: '2026-09-23T16:00:00+02:00',
      Member: null,
      Changes: [{ Field: 'State', Label: 'Status', Kind: 'value', Format: 'text', Old: 'Feedback', New: 'Abgeschlossen' }],
    },
  },
}
