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
    this.TypeID = data.TypeID || null
    this.OrganizationIDs = data.OrganizationIDs || []
    this.OrganizationLogoURL = data.OrganizationLogoURL || null
    this.OrganizationLogos = data.OrganizationLogos || []

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

    // Eingeladene Personen
    this.InvitedMemberIDs = data.InvitedMemberIDs || []
    this.IsInvited = data.IsInvited ?? true
    this.MembersWithoutResponse = data.MembersWithoutResponse || []

    // Terminfindung (Scheduling-Poll)
    this.IsPoll = data.IsPoll ?? false
    this.PollID = data.PollID ?? null
    this.OptionID = data.OptionID ?? null
    this.PollOptions = data.PollOptions || []

    // Feature-Flags
    this.EnableMeals = data.EnableMeals ?? true
    this.EnableAgenda = data.EnableAgenda ?? true

    // Essen/Mahlzeiten
    this.Meals = data.Meals || []

    // Tagesordnung
    this.AgendaPoints = data.AgendaPoints || []

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
      TypeID: this.TypeID,
      OrganizationIDs: this.OrganizationIDs,
      UserParticipation: this.UserParticipation,
      Participations: this.Participations,
      InvitedMemberIDs: this.InvitedMemberIDs,
      IsInvited: this.IsInvited,
      MembersWithoutResponse: this.MembersWithoutResponse,
      IsPoll: this.IsPoll,
      PollID: this.PollID,
      OptionID: this.OptionID,
      PollOptions: this.PollOptions,
      EnableMeals: this.EnableMeals,
      EnableAgenda: this.EnableAgenda,
      Meals: this.Meals,
      AgendaPoints: this.AgendaPoints,
    }
  }
}
