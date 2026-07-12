import ParticipantCard from './ParticipantCard.vue'

export default {
  title: 'Design System/ParticipantCard',
  component: ParticipantCard,
  tags: ['autodocs'],
  argTypes: {
    noteExpanded: { control: 'boolean' },
  },
}

export const Accepted = {
  args: {
    participation: {
      ID: 1,
      MemberName: 'Anna Beispiel',
      ProfileImageURL: null,
      Type: 'Accept',
      IsCurrentUser: false,
    },
  },
}

export const Maybe = {
  args: {
    participation: {
      ID: 2,
      MemberName: 'Ben Muster',
      ProfileImageURL: null,
      Type: 'Maybe',
      IsCurrentUser: false,
    },
  },
}

export const Declined = {
  args: {
    participation: {
      ID: 3,
      MemberName: 'Carla Test',
      ProfileImageURL: null,
      Type: 'Decline',
      IsCurrentUser: true,
    },
  },
}

export const WithNote = {
  args: {
    participation: {
      ID: 4,
      MemberName: 'Dennis Vogel',
      ProfileImageURL: null,
      Type: 'Accept',
      IsCurrentUser: false,
      Notes: 'Komme etwas später, ca. 18:30 Uhr.',
    },
    noteExpanded: false,
  },
}

export const Pending = {
  args: {
    participation: {
      ID: 5,
      MemberName: 'Erik Sommer',
      ProfileImageURL: null,
      Type: 'Pending',
      IsCurrentUser: false,
    },
  },
}
