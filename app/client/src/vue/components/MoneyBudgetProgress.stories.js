import MoneyBudgetProgress from './MoneyBudgetProgress.vue'

export default {
  title: 'Design System/MoneyBudgetProgress',
  component: MoneyBudgetProgress,
  tags: ['autodocs'],
}

export const WithinBudget = {
  args: {
    budget: { HasBudget: true, Budget: 1000, Spent: 400, Remaining: 600, PendingAmount: 0 },
  },
}

export const WithPendingEntries = {
  args: {
    budget: { HasBudget: true, Budget: 1000, Spent: 400, Remaining: 600, PendingAmount: 250 },
  },
}

export const OverBudget = {
  args: {
    budget: { HasBudget: true, Budget: 1000, Spent: 1300, Remaining: -300, PendingAmount: 0 },
  },
}

export const OverBudgetWithPending = {
  args: {
    budget: { HasBudget: true, Budget: 1000, Spent: 1100, Remaining: -100, PendingAmount: 150 },
  },
}

export const NoBudgetSet = {
  args: {
    budget: { HasBudget: false, Budget: 0, Spent: 0, Remaining: 0, PendingAmount: 0 },
  },
}
