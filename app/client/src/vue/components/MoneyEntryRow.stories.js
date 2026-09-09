import MoneyEntryRow from './MoneyEntryRow.vue'

// Hinweis: MoneyEntryRow liest den aktuellen Nutzer aus dem auth-Store, um zu prüfen,
// ob es die eigene Buchung ist. In Storybook ist kein Nutzer eingeloggt, daher greift
// hier nur canSettle für die Begleichen-Badge, nicht die "eigene Buchung"-Sonderfälle.
export default {
  title: 'Design System/MoneyEntryRow',
  component: MoneyEntryRow,
  tags: ['autodocs'],
  argTypes: {
    canSettle: { control: 'boolean' },
  },
}

export const Deposit = {
  args: {
    entry: {
      ID: 1,
      ChangeReason: 'Mitgliedsbeitrag',
      ChangeAmount: 50,
      ChangeType: 'Deposit',
      ChangeDate: '2026-08-01',
      Approved: true,
      User: { ID: 1, Name: 'Anna Beispiel' },
    },
  },
}

export const Withdrawal = {
  args: {
    entry: {
      ID: 2,
      ChangeReason: 'Getränke Sommerfest',
      ChangeAmount: 84.5,
      ChangeType: 'Withdrawal',
      ChangeDate: '2026-08-03',
      Approved: true,
      User: { ID: 2, Name: 'Ben Muster' },
    },
  },
}

export const PendingApproval = {
  args: {
    entry: {
      ID: 3,
      ChangeReason: 'Materialkosten',
      ChangeAmount: 120,
      ChangeType: 'Withdrawal',
      ChangeDate: '2026-08-05',
      Approved: false,
      User: { ID: 2, Name: 'Ben Muster' },
    },
  },
}

export const WithReceiptAndBudget = {
  args: {
    entry: {
      ID: 4,
      ChangeReason: 'Zeltverleih',
      ChangeAmount: 200,
      ChangeType: 'Withdrawal',
      ChangeDate: '2026-08-07',
      Approved: true,
      User: { ID: 3, Name: 'Carla Test' },
      ReceiptURL: '/assets/receipts/beleg.pdf',
      Budget: { ID: 1, Title: 'Sommerfest' },
    },
  },
}

export const OpenForSettlement = {
  args: {
    entry: {
      ID: 5,
      ChangeReason: 'Fahrtkosten',
      ChangeAmount: 45,
      ChangeType: 'Withdrawal',
      ChangeDate: '2026-08-09',
      Approved: true,
      User: { ID: 4, Name: 'Dennis Vogel' },
      SettledAmount: 0,
    },
    canSettle: true,
  },
}

export const PartiallySettled = {
  args: {
    entry: {
      ID: 6,
      ChangeReason: 'Fahrtkosten',
      ChangeAmount: 45,
      ChangeType: 'Withdrawal',
      ChangeDate: '2026-08-09',
      Approved: true,
      User: { ID: 4, Name: 'Dennis Vogel' },
      SettledAmount: 20,
    },
    canSettle: true,
  },
}

export const FullySettled = {
  args: {
    entry: {
      ID: 7,
      ChangeReason: 'Fahrtkosten',
      ChangeAmount: 45,
      ChangeType: 'Withdrawal',
      ChangeDate: '2026-08-09',
      Approved: true,
      User: { ID: 4, Name: 'Dennis Vogel' },
      SettledAmount: 45,
    },
    canSettle: true,
  },
}
