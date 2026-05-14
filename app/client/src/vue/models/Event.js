/**
 * Event Model
 * Repräsentiert einen Kalender-Event mit allen relevanten Eigenschaften
 */
export class Event {
  constructor(data = {}) {
    this.ID = data.ID || null
    this.Title = data.Title || ''
    this.Description = data.Description || ''
    this.DateStart = data.DateStart || '' // Format: YYYY-MM-DD
    this.DateEnd = data.DateEnd || data.DateStart || '' // Format: YYYY-MM-DD
    this.TimeStart = data.TimeStart || null
    this.TimeEnd = data.TimeEnd || null
    this.AllDay = data.AllDay || false
    this.Location = data.Location || ''
    this.Color = data.Color || null
    this.Status = data.Status || null
    this.ImageURL = data.ImageURL || null
    this.EventType = data.EventType || null
    this.OrganizationLogoURL = data.OrganizationLogoURL || null

    // Teilnahme des aktuellen Users
    this.UserParticipation = data.UserParticipation ? {
      ID: data.UserParticipation.ID,
      Type: data.UserParticipation.Type, // 'Accept', 'Maybe', 'Decline'
      TimeStart: data.UserParticipation.TimeStart,
      TimeEnd: data.UserParticipation.TimeEnd,
      CustomTimeframe: data.UserParticipation.CustomTimeframe ?? false,
    } : null

    // Alle Teilnahmen
    this.Participations = data.Participations || []

    // Essen/Mahlzeiten
    this.Meals = data.Meals || []

    // Metadata
    this.CreatedAt = data.CreatedAt || null
    this.UpdatedAt = data.UpdatedAt || null
  }

  /**
   * Formatiert das Datum im deutschen Format
   */
  getFormattedDate() {
    if (!this.DateStart) return ''
    const date = new Date(this.DateStart)
    return new Intl.DateTimeFormat('de-DE', {
      weekday: 'long',
      year: 'numeric',
      month: 'long',
      day: 'numeric'
    }).format(date)
  }

  /**
   * Gibt zurück ob der Event heute ist
   */
  isToday() {
    if (!this.DateStart) return false
    const today = new Date()
    today.setHours(0, 0, 0, 0)
    const eventDate = new Date(this.DateStart)
    eventDate.setHours(0, 0, 0, 0)
    return today.getTime() === eventDate.getTime()
  }

  /**
   * Gibt zurück ob der Event in der Zukunft liegt
   */
  isFuture() {
    if (!this.DateStart) return false
    const today = new Date()
    today.setHours(0, 0, 0, 0)
    const eventDate = new Date(this.DateStart)
    eventDate.setHours(0, 0, 0, 0)
    return eventDate > today
  }

  /**
   * Gibt zurück ob der Event in der Vergangenheit liegt
   */
  isPast() {
    if (!this.DateStart) return false
    const today = new Date()
    today.setHours(0, 0, 0, 0)
    const eventDate = new Date(this.DateStart)
    eventDate.setHours(0, 0, 0, 0)
    return eventDate < today
  }

  /**
   * Gibt den Teilnahme-Typ des Users zurück
   */
  getUserParticipationType() {
    return this.UserParticipation?.Type || null
  }

  /**
   * Prüft ob User zugesagt hat
   */
  hasUserAccepted() {
    return this.UserParticipation?.Type === 'Accept'
  }

  /**
   * Prüft ob User vielleicht zugesagt hat
   */
  hasUserMaybe() {
    return this.UserParticipation?.Type === 'Maybe'
  }

  /**
   * Prüft ob User abgesagt hat
   */
  hasUserDeclined() {
    return this.UserParticipation?.Type === 'Decline'
  }

  /**
   * Aktualisiert die User-Teilnahme
   */
  updateUserParticipation(participationData) {
    if (!this.UserParticipation) {
      this.UserParticipation = {}
    }
    Object.assign(this.UserParticipation, participationData)
  }

  /**
   * Erstellt eine Event-Instanz aus API-Daten
   */
  static fromAPI(apiData) {
    return new Event(apiData)
  }

  /**
   * Konvertiert zu Plain Object für API-Requests
   */
  toJSON() {
    return {
      ID: this.ID,
      Title: this.Title,
      Description: this.Description,
      DateStart: this.DateStart,
      DateEnd: this.DateEnd,
      TimeStart: this.TimeStart,
      TimeEnd: this.TimeEnd,
      AllDay: this.AllDay,
      Location: this.Location,
      Color: this.Color,
      EventType: this.EventType,
      UserParticipation: this.UserParticipation,
      Participations: this.Participations,
      Meals: this.Meals
    }
  }
}
