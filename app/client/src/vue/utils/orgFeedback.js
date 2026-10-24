// Feedback-Totem: Arten und Datumsformat

// Für AppButtonGroup (tone steuert Farbe und Icon)
export const SENTIMENT_OPTIONS = [
  { value: 'Positive', label: 'Positiv', tone: 'positive' },
  { value: 'Neutral', label: 'Neutral', tone: 'neutral' },
  { value: 'Negative', label: 'Negativ', tone: 'negative' },
]

export const SENTIMENT_LABELS = Object.fromEntries(SENTIMENT_OPTIONS.map(o => [o.value, o.label]))

const dateTimeFormat = new Intl.DateTimeFormat('de-DE', { dateStyle: 'medium', timeStyle: 'short' })

/** "24.10.2026, 14:05" aus einem SilverStripe-Datetime ("2026-10-24 14:05:00") */
export function formatFeedbackDate(value) {
  if (!value) return ''
  const date = new Date(String(value).replace(' ', 'T'))
  return Number.isNaN(date.getTime()) ? '' : dateTimeFormat.format(date)
}

/** Stimmen eines Feedbacks (Details) für MemberListModal: { ID, Name, Avatar, Date } */
export function feedbackVoters(feedback) {
  return (feedback?.Voters || []).map(v => ({ ID: v.ID, Name: v.Name, Avatar: v.Avatar, Date: v.VotedAt }))
}
