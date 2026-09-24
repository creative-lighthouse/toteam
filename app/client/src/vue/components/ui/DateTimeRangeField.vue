<!--
  Wiederverwendbares Datum/Uhrzeit-Feld für Modal-Formulare. Deckt drei
  Betriebsarten ab (Prop `time`):
    - "none":   nur Datum, keine Uhrzeit, kein Umschalter (z.B. Abwesenheit)
    - "always": immer Datum+Uhrzeit (datetime-local), kein Umschalter
    - "toggle": "Ganztägig"-Schalter, der zwischen Datum-only und
                Datum+Uhrzeit umschaltet (z.B. Termin erstellen)
  Zusätzlich per `showEndDate` steuerbar, ob ein zweites Enddatum-Feld
  gerendert wird (z.B. Termin: Start+Ende) oder stattdessen — sobald Uhrzeit
  sichtbar ist und `timeRange` true ist — ein einfaches Uhrzeit-von/bis-Paar
  zum selben Datum (z.B. Terminfindungs-Option: ein Datum + eine
  Uhrzeitspanne). Mit `timeRange="false"` bleibt es bei einem einzelnen
  Datum- bzw. Datum+Uhrzeit-Feld ohne jede Ergänzung (z.B. eine Deadline
  oder ein einzelner Zeitpunkt).

  Erwartet/liefert per v-model ein Objekt { dateStart, dateEnd, timeStart,
  timeEnd, allDay }; überzählige Felder im Objekt (z.B. weitere Formularfelder
  desselben Eltern-State) bleiben beim Aktualisieren erhalten.

  Kein eigenes Wrapper-Element (mehrere Root-Knoten) — die einzelnen .field-
  Blöcke fügen sich direkt in das umgebende .modalform-Grid oder einen
  Flex-Container (z.B. .poll-option-row) ein, je nach übergebenen
  *FieldClass-Props.
-->
<template>
  <label :class="startFieldClass">
    {{ startLabelText }}<template v-if="required"> *</template>
    <input
      v-if="showTimeFields"
      type="datetime-local"
      :value="startDateTime"
      :required="required"
      :aria-label="startLabelText"
      @input="setStartDateTime($event.target.value)"
    >
    <input
      v-else
      type="date"
      v-model="localDateStart"
      :required="required"
    >
  </label>

  <label v-if="showEndDate" :class="endFieldClass">
    {{ endLabelText }}<template v-if="required"> *</template>
    <input
      v-if="showTimeFields"
      type="datetime-local"
      :value="endDateTime"
      :aria-label="endLabelText"
      @input="setEndDateTime($event.target.value)"
    >
    <input
      v-else
      type="date"
      v-model="localDateEnd"
      :min="localDateStart"
      :aria-label="endLabelText"
    >
  </label>

  <template v-else-if="showTimeFields && timeRange">
    <label :class="startTimeFieldClass">
      Uhrzeit von
      <input type="time" v-model="localTimeStart">
    </label>
    <label :class="endTimeFieldClass">
      Uhrzeit bis
      <input type="time" v-model="localTimeEnd">
    </label>
  </template>

  <label v-if="time === 'toggle'" :class="toggleFieldClass">
    <span>{{ toggleLabel }}</span>
    <span class="toggle-switch">
      <input type="checkbox" v-model="localAllDay" class="toggle-switch_input">
      <span class="toggle-switch_track"></span>
    </span>
  </label>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  // { dateStart, dateEnd, timeStart, timeEnd, allDay }
  modelValue: { type: Object, required: true },
  showEndDate: { type: Boolean, default: true },
  // Nur relevant, wenn showEndDate=false und Uhrzeit sichtbar ist: ob
  // zusätzlich ein Uhrzeit-von/bis-Paar zum selben Datum gezeigt wird
  // (z.B. Terminfindungs-Option) oder das Start-Feld als einzelnes,
  // bereits kombiniertes Datum+Uhrzeit-Feld für sich steht (z.B. ein
  // einzelner Zeitpunkt ohne Zeitspanne).
  timeRange: { type: Boolean, default: true },
  // 'none' | 'always' | 'toggle'
  time: { type: String, default: 'none' },
  required: { type: Boolean, default: true },
  startLabelDate: { type: String, default: 'Startdatum' },
  startLabelTime: { type: String, default: 'Start' },
  endLabelDate: { type: String, default: 'Enddatum' },
  endLabelTime: { type: String, default: 'Ende' },
  toggleLabel: { type: String, default: 'Ganztägig' },
  startFieldClass: { type: String, default: 'field field--3' },
  endFieldClass: { type: String, default: 'field field--3' },
  startTimeFieldClass: { type: String, default: 'field field--3' },
  endTimeFieldClass: { type: String, default: 'field field--3' },
  toggleFieldClass: { type: String, default: 'field toggle-field' },
})

const emit = defineEmits(['update:modelValue'])

function patch(partial) {
  emit('update:modelValue', { ...props.modelValue, ...partial })
}

const localDateStart = computed({
  get: () => props.modelValue.dateStart ?? '',
  set: (val) => patch({ dateStart: val }),
})
const localDateEnd = computed({
  get: () => props.modelValue.dateEnd ?? '',
  set: (val) => patch({ dateEnd: val }),
})
const localTimeStart = computed({
  get: () => props.modelValue.timeStart ?? '',
  set: (val) => patch({ timeStart: val }),
})
const localTimeEnd = computed({
  get: () => props.modelValue.timeEnd ?? '',
  set: (val) => patch({ timeEnd: val }),
})
const localAllDay = computed({
  get: () => !!props.modelValue.allDay,
  set: (val) => patch({ allDay: val }),
})

const showTimeFields = computed(() => {
  if (props.time === 'always') return true
  if (props.time === 'toggle') return !localAllDay.value
  return false
})

function combine(date, time) {
  if (date && time) return `${date}T${time}`
  if (date) return `${date}T00:00`
  return ''
}

const startDateTime = computed(() => combine(props.modelValue.dateStart, props.modelValue.timeStart))
function setStartDateTime(val) {
  if (val) {
    const [date, time] = val.split('T')
    patch({ dateStart: date ?? '', timeStart: time ?? '' })
  } else {
    patch({ dateStart: '', timeStart: '' })
  }
}

const endDateTime = computed(() => combine(props.modelValue.dateEnd, props.modelValue.timeEnd))
function setEndDateTime(val) {
  if (val) {
    const [date, time] = val.split('T')
    patch({ dateEnd: date ?? '', timeEnd: time ?? '' })
  } else {
    patch({ dateEnd: '', timeEnd: '' })
  }
}

const startLabelText = computed(() => (showTimeFields.value ? props.startLabelTime : props.startLabelDate))
const endLabelText = computed(() => (showTimeFields.value ? props.endLabelTime : props.endLabelDate))
</script>
