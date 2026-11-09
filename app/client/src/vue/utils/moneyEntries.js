// Rechnen mit Buchungen der Kasse (Format aus MoneyApiController::formatEntry)

/** Betrag in Cent, damit Summen vieler Beträge keine Rundungsfehler ansammeln */
const cents = value => Math.round((Number(value) || 0) * 100)

/**
 * Noch auszuzahlender Rest einer Buchung: nur freigegebene Ausgaben, abzüglich der
 * Begleichungen, nie negativ — wie MoneyApiController::formatDebts/entrySettleBulk.
 */
export function openAmountOf(entry) {
  if (entry?.ChangeType !== 'Withdrawal' || !entry.Approved) return 0
  return Math.max(0, cents(entry.ChangeAmount) - cents(entry.SettledAmount)) / 100
}

/** Summen einer Auswahl: Ausgaben, Einnahmen und offener Rest */
export function sumEntries(entries) {
  let expenses = 0
  let income = 0
  let open = 0
  for (const entry of entries) {
    if (entry.ChangeType === 'Deposit') income += cents(entry.ChangeAmount)
    else expenses += cents(entry.ChangeAmount)
    open += Math.round(openAmountOf(entry) * 100)
  }
  return { expenses: expenses / 100, income: income / 100, open: open / 100 }
}
