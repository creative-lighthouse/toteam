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

export const DeclinedWithLeftoverData = {
  args: {
    participation: {
      ID: 9,
      MemberName: 'Ida Winter',
      ProfileImageURL: null,
      Type: 'Decline',
      IsCurrentUser: false,
      // Zeit/Anfahrt wurden gesetzt, bevor abgesagt wurde — dürfen nach dem
      // Absagen nicht mehr angezeigt werden, obwohl die Daten im Hintergrund
      // erhalten bleiben.
      CustomTimeframe: true,
      TimeStart: '18:00:00',
      TimeEnd: '20:00:00',
      RideType: 'Offer',
      RideSeats: 2,
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

export const OffersRide = {
  args: {
    participation: {
      ID: 6,
      MemberName: 'Frida Weber',
      ProfileImageURL: null,
      Type: 'Accept',
      IsCurrentUser: false,
      RideType: 'Offer',
      RideSeats: 3,
    },
  },
}

export const OffersRideNoSeats = {
  args: {
    participation: {
      ID: 7,
      MemberName: 'Georg Fischer',
      ProfileImageURL: null,
      Type: 'Accept',
      IsCurrentUser: false,
      RideType: 'Offer',
      RideSeats: 0,
    },
  },
}

export const NeedsRide = {
  args: {
    participation: {
      ID: 8,
      MemberName: 'Hanna Klein',
      ProfileImageURL: null,
      Type: 'Maybe',
      IsCurrentUser: false,
      RideType: 'Need',
      RideSeats: 0,
    },
  },
}
