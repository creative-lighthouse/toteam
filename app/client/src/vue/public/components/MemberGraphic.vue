<template>
  <div class="member-graphic" aria-hidden="true">
    <svg class="member-graphic__lines" viewBox="0 0 100 100" preserveAspectRatio="none">
      <line
        v-for="member in members"
        :key="member.id"
        class="member-graphic__line"
        :class="[`member-graphic__line--${member.role}`, { 'is-hidden': !member.visible }]"
        x1="50" y1="50"
        :x2="(member.attribute || member).x" :y2="(member.attribute || member).y"
      />
    </svg>

    <div class="member-graphic__hub">
      <svg xmlns="http://www.w3.org/2000/svg" width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 21h18"/><path d="M5 21V7l7-4 7 4v14"/><path d="M9 9h1"/><path d="M9 13h1"/><path d="M14 9h1"/><path d="M14 13h1"/><path d="M10 21v-4a2 2 0 0 1 2-2v0a2 2 0 0 1 2 2v4"/></svg>
      <span>Deine Organisation</span>
    </div>

    <div
      v-for="member in members"
      :key="member.id"
      class="member-graphic__member"
      :class="[`member-graphic__member--${member.role}`, { 'is-hidden': !member.visible }]"
      :style="{ left: `${member.x}%`, top: `${member.y}%` }"
    >
      <span class="member-graphic__avatar">
        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
      </span>
      <span class="member-graphic__role-badge">{{ roleLabels[member.role] }}</span>
    </div>

    <div
      v-for="member in membersWithAttribute"
      :key="`attr-${member.id}`"
      class="member-graphic__bubble"
      :class="[`member-graphic__bubble--${member.attribute.kind}`, { 'is-hidden': !member.visible }]"
      :style="{ left: `${member.attribute.x}%`, top: `${member.attribute.y}%` }"
    >
      <span class="member-graphic__bubble-icon" v-html="attributeIcons[member.attribute.kind]"></span>
      <span>{{ member.attribute.label }}</span>
    </div>
  </div>
</template>

<script setup>
import { reactive, computed, onMounted, onUnmounted } from 'vue'

const roleLabels = {
  vorstand: 'Vorstand',
  kassenwart: 'Kassenwart',
  mitglied: 'Mitglied',
}

const attributeIcons = {
  beitritt: '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4"/><path d="M8 2v4"/><path d="M3 10h18"/></svg>',
  allergien: '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 9v4"/><path d="M12 17h.01"/><path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0Z"/></svg>',
  essen: '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 2v7c0 1.1.9 2 2 2h4a2 2 0 0 0 2-2V2"/><path d="M7 2v20"/><path d="M21 15V2a5 5 0 0 0-5 5v6c0 1.1.9 2 2 2h3Zm0 0v7"/></svg>',
}

const memberRadius = 30
const attributeRadius = 46
const center = 50

// Fixed positions around the hub – who sits where is decided separately below,
// so a role isn't glued to one spot (e.g. "Kassenwart" always bottom-right).
const slotAngles = [-90, -30, 30, 90, 150, 210]

function point(angleDeg, radius) {
  const rad = (angleDeg * Math.PI) / 180
  return {
    x: center + radius * Math.cos(rad),
    y: center + radius * Math.sin(rad),
  }
}

// Not every person shows the same details – attributes are spread out
// differently across the group, just like in a real member list.
const people = [
  { role: 'vorstand', attribute: { kind: 'beitritt', label: 'seit 2019' } },
  { role: 'mitglied', attribute: { kind: 'allergien', label: 'Nüsse' } },
  { role: 'kassenwart', attribute: { kind: 'essen', label: 'vegan' } },
  { role: 'mitglied', attribute: null },
  { role: 'mitglied', attribute: { kind: 'allergien', label: 'Laktose' } },
  { role: 'mitglied', attribute: { kind: 'essen', label: 'vegetarisch' } },
]

// order[slotIndex] = index into `people` currently occupying that slot.
const order = reactive(people.map((_, i) => i))
// visible[personIndex] = is this person currently shown (join/leave state).
const visible = reactive(people.map(() => true))

const members = computed(() =>
  order.map((personIndex, slotIndex) => {
    const person = people[personIndex]
    const angle = slotAngles[slotIndex]
    return {
      id: personIndex,
      slotIndex,
      visible: visible[personIndex],
      ...person,
      ...point(angle, memberRadius),
      attribute: person.attribute ? { ...person.attribute, ...point(angle, attributeRadius) } : null,
    }
  })
)

const membersWithAttribute = computed(() => members.value.filter((m) => m.attribute))

const timers = []

function randomBetween(min, max) {
  return min + Math.random() * (max - min)
}

// Each person independently leaves for a while and rejoins – this is also
// the only moment a position swap (see below) is allowed to happen, so the
// reshuffle is never visible.
function scheduleVisibility(personIndex, initialDelay = 0) {
  const isVisible = visible[personIndex]
  const holdSeconds = isVisible ? randomBetween(6, 11) : randomBetween(2, 3.5)
  const delayMs = (initialDelay || holdSeconds) * 1000

  timers[personIndex] = window.setTimeout(() => {
    visible[personIndex] = !visible[personIndex]
    scheduleVisibility(personIndex)
  }, delayMs)
}

// Every couple of seconds, if at least two people currently happen to be
// hidden at once, swap their slots. Since both are invisible, the swap
// itself is never seen – only the "new" arrangement once they reappear.
function maybeSwapHiddenSlots() {
  const hiddenSlots = order
    .map((personIndex, slotIndex) => ({ personIndex, slotIndex }))
    .filter((s) => !visible[s.personIndex])

  if (hiddenSlots.length < 2) return

  const [a, b] = hiddenSlots.sort(() => Math.random() - 0.5).slice(0, 2)
  const tmp = order[a.slotIndex]
  order[a.slotIndex] = order[b.slotIndex]
  order[b.slotIndex] = tmp
}

let swapTimer = null

onMounted(() => {
  const prefersReducedMotion = window.matchMedia?.('(prefers-reduced-motion: reduce)').matches
  if (prefersReducedMotion) return

  people.forEach((_, i) => scheduleVisibility(i, randomBetween(4, 12)))
  swapTimer = window.setInterval(maybeSwapHiddenSlots, 1500)
})

onUnmounted(() => {
  timers.forEach((t) => window.clearTimeout(t))
  if (swapTimer) window.clearInterval(swapTimer)
})
</script>
