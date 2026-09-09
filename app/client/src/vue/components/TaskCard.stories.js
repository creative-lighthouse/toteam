import TaskCard from './TaskCard.vue'

export default {
  title: 'Design System/TaskCard',
  component: TaskCard,
  tags: ['autodocs'],
}

export const Open = {
  args: {
    task: {
      ID: 1,
      Title: 'Flyer entwerfen',
      Description: 'Für das Sommerfest einen neuen Flyer gestalten.',
      State: 'open',
      DeadlineNice: '15.09.2026',
      Deadline: '2026-09-15',
      Owner: { ID: 1, Name: 'Anna Beispiel', Avatar: null },
    },
  },
}

export const InProgressWithSubtasks = {
  args: {
    task: {
      ID: 2,
      Title: 'Sommerfest organisieren',
      Description: 'Alle Vorbereitungen für das jährliche Sommerfest koordinieren.',
      State: 'in_progress',
      DeadlineNice: '20.09.2026',
      Deadline: '2026-09-20',
      Owner: { ID: 1, Name: 'Anna Beispiel', Avatar: null },
      Supporters: [
        { ID: 2, Name: 'Ben Muster', Avatar: null },
        { ID: 3, Name: 'Carla Test', Avatar: null },
      ],
      SubTasks: [
        { State: 'finished' },
        { State: 'finished' },
        { State: 'in_progress' },
        { State: 'open' },
      ],
    },
  },
}

export const Overdue = {
  args: {
    task: {
      ID: 3,
      Title: 'Rechnungen einreichen',
      State: 'open',
      DeadlineNice: '01.09.2026',
      Deadline: '2026-09-01',
      Owner: { ID: 1, Name: 'Anna Beispiel', Avatar: null },
    },
  },
}

export const ManySupportersWithOverflow = {
  args: {
    task: {
      ID: 4,
      Title: 'Standbetreuung Sommerfest',
      State: 'feedback',
      Owner: { ID: 1, Name: 'Anna Beispiel', Avatar: null },
      Supporters: [
        { ID: 2, Name: 'Ben Muster', Avatar: null },
        { ID: 3, Name: 'Carla Test', Avatar: null },
        { ID: 4, Name: 'Dennis Vogel', Avatar: null },
        { ID: 5, Name: 'Erik Sommer', Avatar: null },
      ],
    },
  },
}

export const Finished = {
  args: {
    task: {
      ID: 5,
      Title: 'Getränke bestellen',
      State: 'finished',
      Owner: { ID: 1, Name: 'Anna Beispiel', Avatar: null },
      SubTasks: [{ State: 'finished' }, { State: 'finished' }],
    },
  },
}

export const WithOrganization = {
  args: {
    task: {
      ID: 6,
      Title: 'Jugendausflug planen',
      State: 'open',
      Organization: { ID: 1, Title: 'Jugendgruppe', LogoURL: null },
      Owner: { ID: 1, Name: 'Anna Beispiel', Avatar: null },
    },
  },
}
