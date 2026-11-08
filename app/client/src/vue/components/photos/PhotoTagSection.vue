<!--
  Eine Art von Tags im Foto-Betrachter (Personen, Charaktere, Schlagworte): Überschrift,
  die vorhandenen Tags als Chips und — mit Recht — ein "+", das direkt darunter eine
  Suche öffnet. Nach dem Hinzufügen bleibt die Suche offen, damit man mehrere nacheinander
  taggen kann; Esc oder Klick daneben schließt sie.

    <PhotoTagSection
      title="Personen"
      :items="[{ id, label, avatar? }]"
      :options="[{ id, label, avatar?, hint? }]"   // wählbar (bereits getaggte werden ausgeblendet)
      :can-add="…"
      :removable="item => …"
      allow-create                                  // Schlagworte: Freitext anlegen
      @add="option | { create: 'Text' }"
      @remove="item"
    />
-->
<template>
  <section class="photo-tag-section" :aria-labelledby="`${uid}-title`">
    <h3 :id="`${uid}-title`" class="photo-tag-section_title">{{ title }}</h3>

    <ul class="photo-tag-section_chips">
      <li v-for="item in items" :key="item.id" class="photo-tag-section_chip">
        <AppAvatar v-if="withAvatars" :src="item.avatar" alt="" img-class="photo-tag-section_avatar" />
        <span>{{ item.label }}</span>
        <button
          v-if="removable(item)"
          type="button"
          class="photo-tag-section_remove"
          :aria-label="`${item.label} entfernen`"
          :disabled="disabled"
          @click="emit('remove', item)"
        >×</button>
      </li>
      <li v-if="canAdd && !adding">
        <button
          type="button"
          class="photo-tag-section_add"
          :aria-label="addLabel"
          :title="addLabel"
          :disabled="disabled"
          @click="openPicker"
        >+</button>
      </li>
      <li v-if="!items.length && !canAdd" class="photo-tag-section_none">–</li>
    </ul>

    <div v-if="adding" class="photo-tag-section_picker">
      <input
        ref="inputEl"
        v-model="query"
        type="search"
        class="photo-tag-section_input"
        autocomplete="off"
        autocorrect="off"
        spellcheck="false"
        data-1p-ignore
        role="combobox"
        aria-autocomplete="list"
        :aria-expanded="choices.length > 0"
        :aria-controls="`${uid}-list`"
        :aria-activedescendant="choices[active] ? `${uid}-opt-${active}` : undefined"
        :aria-label="addLabel"
        :placeholder="placeholder"
        @keydown="onKeydown"
        @blur="close"
      >
      <ul v-if="choices.length" :id="`${uid}-list`" class="photo-tag-section_list" role="listbox" :aria-label="addLabel">
        <li
          v-for="(choice, i) in choices"
          :id="`${uid}-opt-${i}`"
          :key="choice.create ? `new-${choice.create}` : choice.id"
          role="option"
          class="photo-tag-section_option"
          :class="{ 'photo-tag-section_option--active': i === active }"
          :aria-selected="i === active"
          @mousedown.prevent="choose(choice)"
          @mousemove="active = i"
        >
          <template v-if="choice.create">„{{ choice.create }}“ neu anlegen</template>
          <template v-else>
            <AppAvatar v-if="withAvatars" :src="choice.avatar" alt="" img-class="photo-tag-section_avatar" />
            <span>{{ choice.label }}</span>
            <span v-if="choice.hint" class="photo-tag-section_hint">{{ choice.hint }}</span>
          </template>
        </li>
      </ul>
      <p v-else class="photo-tag-section_empty">{{ query ? 'Nichts gefunden' : 'Keine weiteren vorhanden' }}</p>
    </div>
  </section>
</template>

<script setup>
import { ref, computed, watch, nextTick } from 'vue'
import AppAvatar from '@components/ui/AppAvatar.vue'

const props = defineProps({
  title: { type: String, required: true },
  // Vorhandene Tags: { id, label, avatar? }
  items: { type: Array, required: true },
  // Wählbare Tags: { id, label, avatar?, hint? }
  options: { type: Array, default: () => [] },
  canAdd: { type: Boolean, default: false },
  // Darf dieser Tag entfernt werden? (z.B. die eigene Markierung auch ohne Bearbeitungsrecht)
  removable: { type: Function, default: () => false },
  // Freitext als neuen Tag anlegen (Schlagworte)
  allowCreate: { type: Boolean, default: false },
  withAvatars: { type: Boolean, default: false },
  addLabel: { type: String, default: 'Hinzufügen' },
  placeholder: { type: String, default: 'Suchen…' },
  disabled: { type: Boolean, default: false },
})

const emit = defineEmits(['add', 'remove'])

const MAX_CHOICES = 8
const uid = `photo-tag-${Math.random().toString(36).slice(2)}`

const adding = ref(false)
const query = ref('')
const active = ref(0)
const inputEl = ref(null)

const normalized = computed(() => query.value.trim().toLocaleLowerCase('de'))

const choices = computed(() => {
  const taken = new Set(props.items.map(i => i.id))
  const q = normalized.value
  const list = props.options
    .filter(o => !taken.has(o.id) && (!q || o.label.toLocaleLowerCase('de').includes(q)))
    // Treffer am Wortanfang zuerst
    .sort((a, b) => Number(!a.label.toLocaleLowerCase('de').startsWith(q)) - Number(!b.label.toLocaleLowerCase('de').startsWith(q)))
    .slice(0, MAX_CHOICES)
  const text = query.value.trim()
  const exists = text && [...props.options, ...props.items].some(o => o.label.toLocaleLowerCase('de') === normalized.value)
  if (props.allowCreate && text && !exists) list.push({ create: text })
  return list
})

watch(query, () => { active.value = 0 })

async function openPicker() {
  adding.value = true
  query.value = ''
  active.value = 0
  await nextTick()
  inputEl.value?.focus()
}

function close() {
  adding.value = false
  query.value = ''
}

async function choose(choice) {
  if (!choice) return
  emit('add', choice)
  query.value = ''
  await nextTick()
  inputEl.value?.focus()
}

function onKeydown(event) {
  if (event.key === 'ArrowDown') {
    event.preventDefault()
    active.value = Math.min(active.value + 1, choices.value.length - 1)
  } else if (event.key === 'ArrowUp') {
    event.preventDefault()
    active.value = Math.max(active.value - 1, 0)
  } else if (event.key === 'Enter') {
    event.preventDefault()
    choose(choices.value[active.value])
  } else if (event.key === 'Escape') {
    // Nur die Suche schließen, nicht den Betrachter
    event.preventDefault()
    event.stopPropagation()
    close()
  }
}
</script>
