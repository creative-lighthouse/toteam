import EventCard from './EventCard.vue'

export default {
  title: 'Design System/EventCard',
  component: EventCard,
  tags: ['autodocs'],
}

export const Accepted = {
  args: {
    event: {
      ID: 1,
      Title: 'Vereinstreffen',
      EventType: 'default',
      TimeStart: '18:00:00',
      TimeEnd: '20:00:00',
      Location: 'Vereinsheim',
      OrganizationLogoURL: null,
      UserParticipation: { Type: 'Accept' },
      Participations: [
        { ID: 1, MemberName: 'Anna', Type: 'Accept', ProfileImageURL: null },
        { ID: 2, MemberName: 'Ben', Type: 'Accept', ProfileImageURL: null },
        { ID: 3, MemberName: 'Carla', Type: 'Maybe', ProfileImageURL: null },
      ],
    },
  },
}

export const AllDay = {
  args: {
    event: {
      ID: 2,
      Title: 'Sommerfest',
      EventType: 'default',
      AllDay: true,
      Location: 'Festwiese',
      UserParticipation: { Type: 'Pending' },
      Participations: [],
    },
  },
}

export const Suggested = {
  args: {
    event: {
      ID: 3,
      Title: 'Vorstandssitzung',
      Status: 'Suggested',
      TimeStart: '19:00:00',
      UserParticipation: { Type: 'Maybe' },
      Participations: [],
    },
  },
}

export const Cancelled = {
  args: {
    event: {
      ID: 4,
      Title: 'Ausflug',
      Status: 'Cancelled',
      TimeStart: '10:00:00',
      UserParticipation: { Type: 'Decline' },
      Participations: [],
    },
  },
}

export const SchedulingPoll = {
  args: {
    event: {
      ID: 5,
      Title: 'Terminfindung Jahresausflug',
      IsPoll: true,
      UserParticipation: { Type: 'None' },
      Participations: [],
    },
  },
}

export const NoResponseYet = {
  args: {
    event: {
      ID: 6,
      Title: 'Arbeitseinsatz',
      TimeStart: '09:00:00',
      TimeEnd: '13:00:00',
      Participations: [],
    },
  },
}
