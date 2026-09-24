import { ref, computed, watch, onBeforeUnmount } from 'vue'
import { useEventsStore } from '@stores/events'

export function useEventParticipation(props, emit) {
  const eventsStore = useEventsStore()

  const submitting = ref(false)
  const timeStart = ref('')
  const timeEnd = ref('')
  const showTimeInput = ref(false)
  const showNoteInput = ref(false)
  const noteText = ref('')
  const showRideInput = ref(false)
  const rideType = ref(null)
  const rideSeats = ref(1)

  const userParticipationType = computed(() => props.event.UserParticipation?.Type || null)

  function formatTime(timeStr) {
    if (!timeStr) return ''
    return timeStr.substring(0, 5)
  }

  watch(() => props.event.UserParticipation, (newVal) => {
    if (newVal?.CustomTimeframe) {
      timeStart.value = newVal.TimeStart ? formatTime(newVal.TimeStart) : ''
      timeEnd.value = newVal.TimeEnd ? formatTime(newVal.TimeEnd) : ''
    } else {
      timeStart.value = ''
      timeEnd.value = ''
    }
    showTimeInput.value = false
    noteText.value = newVal?.Notes || ''
    showNoteInput.value = false
    rideType.value = (newVal?.RideType && newVal.RideType !== 'None') ? newVal.RideType : null
    rideSeats.value = newVal?.RideType === 'Offer' ? (newVal.RideSeats ?? 0) : 1
    showRideInput.value = false
  }, { immediate: true })

  function showStatus(text, type = 'success') {
    emit('show-status', { text, type })
  }

  async function changeParticipation(type) {
    if (submitting.value) return
    submitting.value = true
    try {
      const response = await eventsStore.changeParticipation(props.event.ID, type)
      if (response.TimeStart && response.TimeEnd) {
        timeStart.value = formatTime(response.TimeStart)
        timeEnd.value = formatTime(response.TimeEnd)
      }
      emit('participation-changed', props.event.ID, response)
      showStatus('Gespeichert')
    } catch (err) {
      console.error('Error changing participation:', err)
      showStatus('Fehler beim Speichern', 'error')
    } finally {
      submitting.value = false
    }
  }

  function startAddTime() {
    const up = props.event.UserParticipation
    if (up?.CustomTimeframe) {
      timeStart.value = up.TimeStart ? formatTime(up.TimeStart) : ''
      timeEnd.value = up.TimeEnd ? formatTime(up.TimeEnd) : ''
    } else {
      timeStart.value = props.event.TimeStart ? formatTime(props.event.TimeStart) : ''
      timeEnd.value = props.event.TimeEnd ? formatTime(props.event.TimeEnd) : ''
    }
    showTimeInput.value = true
  }

  // Läuft unabhängig von `submitting`, damit die Zeit-Felder beim automatischen
  // Speichern (@change) nicht kurz disabled werden und dadurch den Fokus verlieren
  // (z. B. beim Tab-Wechsel von "Von" zu "Bis").
  let timeSaveInFlight = false

  async function saveTime() {
    if (!timeStart.value || !timeEnd.value || timeSaveInFlight) return
    timeSaveInFlight = true
    try {
      const response = await eventsStore.changeParticipationTime(
        props.event.ID,
        timeStart.value + ':00',
        timeEnd.value + ':00'
      )
      emit('time-changed', props.event.ID, response)
      showStatus('Zeiten gespeichert')
    } catch (err) {
      console.error('Error saving time:', err)
      showStatus('Fehler beim Speichern', 'error')
    } finally {
      timeSaveInFlight = false
    }
  }

  async function clearTime() {
    if (submitting.value) return
    submitting.value = true
    try {
      const response = await eventsStore.changeParticipationTime(props.event.ID, null, null)
      showTimeInput.value = false
      timeStart.value = ''
      timeEnd.value = ''
      emit('time-changed', props.event.ID, response)
      showStatus('Zeit entfernt')
    } catch (err) {
      console.error('Error clearing time:', err)
      showStatus('Fehler beim Entfernen', 'error')
    } finally {
      submitting.value = false
    }
  }

  function startAddNote() {
    noteText.value = props.event.UserParticipation?.Notes || ''
    showNoteInput.value = true
  }

  // Läuft unabhängig von `submitting`, damit das Textfeld beim automatischen
  // Speichern nicht (kurz) disabled wird und dadurch den Fokus verliert.
  let noteSaveInFlight = false

  async function saveNote() {
    if (noteSaveInFlight) return
    noteSaveInFlight = true
    try {
      const response = await eventsStore.changeParticipationNotes(props.event.ID, noteText.value || null)
      emit('notes-changed', props.event.ID, response)
      showStatus('Notiz gespeichert')
    } catch (err) {
      console.error('Error saving note:', err)
      showStatus('Fehler beim Speichern', 'error')
    } finally {
      noteSaveInFlight = false
    }
  }

  // Automatisches Speichern der Notiz nach kurzer Tippapuse, solange der Bereich geöffnet ist
  let noteSaveTimer = null
  watch(noteText, () => {
    if (!showNoteInput.value) return
    clearTimeout(noteSaveTimer)
    noteSaveTimer = setTimeout(saveNote, 900)
  })

  onBeforeUnmount(() => clearTimeout(noteSaveTimer))

  function toggleRideInput() {
    showRideInput.value = !showRideInput.value
  }

  async function selectRideNeed() {
    if (submitting.value) return
    submitting.value = true
    try {
      const next = rideType.value === 'Need' ? null : 'Need'
      const response = await eventsStore.changeParticipationRide(props.event.ID, next, null)
      rideType.value = next
      emit('ride-changed', props.event.ID, response)
      showStatus('Anfahrt gespeichert')
    } catch (err) {
      console.error('Error selecting ride need:', err)
      showStatus('Fehler beim Speichern', 'error')
    } finally {
      submitting.value = false
    }
  }

  async function selectRideOffer() {
    if (submitting.value) return
    submitting.value = true
    try {
      const next = rideType.value === 'Offer' ? null : 'Offer'
      const seats = next === 'Offer' ? rideSeats.value : null
      const response = await eventsStore.changeParticipationRide(props.event.ID, next, seats)
      rideType.value = next
      emit('ride-changed', props.event.ID, response)
      showStatus('Anfahrt gespeichert')
    } catch (err) {
      console.error('Error selecting ride offer:', err)
      showStatus('Fehler beim Speichern', 'error')
    } finally {
      submitting.value = false
    }
  }

  async function changeRideSeats(delta) {
    if (submitting.value || rideType.value !== 'Offer') return
    const next = Math.max(0, Math.min(8, rideSeats.value + delta))
    if (next === rideSeats.value) return
    rideSeats.value = next
    submitting.value = true
    try {
      const response = await eventsStore.changeParticipationRide(props.event.ID, 'Offer', next)
      emit('ride-changed', props.event.ID, response)
    } catch (err) {
      console.error('Error changing ride seats:', err)
      showStatus('Fehler beim Speichern', 'error')
    } finally {
      submitting.value = false
    }
  }

  return {
    submitting,
    timeStart,
    timeEnd,
    showTimeInput,
    showNoteInput,
    noteText,
    showRideInput,
    rideType,
    rideSeats,
    userParticipationType,
    changeParticipation,
    startAddTime,
    saveTime,
    clearTime,
    startAddNote,
    toggleRideInput,
    selectRideNeed,
    selectRideOffer,
    changeRideSeats,
  }
}
