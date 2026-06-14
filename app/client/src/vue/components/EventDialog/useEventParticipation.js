import { ref, computed, watch } from 'vue'
import { useEventsStore } from '@stores/events'

export function useEventParticipation(props, emit) {
  const eventsStore = useEventsStore()

  const submitting = ref(false)
  const timeStart = ref('')
  const timeEnd = ref('')
  const showTimeInput = ref(false)
  const showNoteInput = ref(false)
  const noteText = ref('')

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

  async function saveTime() {
    if (!timeStart.value || !timeEnd.value || submitting.value) return
    submitting.value = true
    try {
      const response = await eventsStore.changeParticipationTime(
        props.event.ID,
        timeStart.value + ':00',
        timeEnd.value + ':00'
      )
      showTimeInput.value = false
      emit('time-changed', props.event.ID, response)
      showStatus('Zeiten gespeichert')
    } catch (err) {
      console.error('Error saving time:', err)
      showStatus('Fehler beim Speichern', 'error')
    } finally {
      submitting.value = false
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

  async function saveNote() {
    if (submitting.value) return
    submitting.value = true
    try {
      const response = await eventsStore.changeParticipationNotes(props.event.ID, noteText.value || null)
      showNoteInput.value = false
      emit('notes-changed', props.event.ID, response)
      showStatus('Notiz gespeichert')
    } catch (err) {
      console.error('Error saving note:', err)
      showStatus('Fehler beim Speichern', 'error')
    } finally {
      submitting.value = false
    }
  }

  async function clearNote() {
    if (submitting.value) return
    submitting.value = true
    try {
      const response = await eventsStore.changeParticipationNotes(props.event.ID, null)
      showNoteInput.value = false
      noteText.value = ''
      emit('notes-changed', props.event.ID, response)
      showStatus('Notiz entfernt')
    } catch (err) {
      console.error('Error clearing note:', err)
      showStatus('Fehler beim Entfernen', 'error')
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
    userParticipationType,
    changeParticipation,
    startAddTime,
    saveTime,
    clearTime,
    startAddNote,
    saveNote,
    clearNote,
  }
}
