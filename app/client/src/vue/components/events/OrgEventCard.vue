<!--
  Karte eines Events (OrgEvent) für Übersichten. Sieht immer gleich aus — Bild,
  Datum, Titel, Chips und Preis haben feste Plätze und Höhen —, nur die Breite
  folgt dem umgebenden Raster (z.B. .org-event-grid auf der Events-Seite).

    <OrgEventCard :event="event" :to="{ name: 'EventDetail', params: { segment: event.URLSegment } }" />

  Ohne `to` wird die Karte als <article> statt als Link gerendert (z.B. in Storybook).
-->
<template>
  <component
    :is="to ? 'router-link' : 'article'"
    :to="to ?? undefined"
    class="org-event-card"
    :class="{ 'org-event-card--past': isPast }"
  >
    <div class="org-event-card_media">
      <img v-if="event.ImageURL" :src="event.ImageURL" alt="" class="org-event-card_image" loading="lazy">
      <!-- Ohne Hauptbild: aus dem Titel generiertes Muster -->
      <AppPatternImage v-else :seed="event.Title" />
      <span v-if="!event.IsPublic" class="org-event-card_internal">Intern</span>
    </div>

    <div class="org-event-card_body">
      <div class="org-event-card_heading">
        <span v-if="badge" class="org-event-card_badge" aria-hidden="true">
          <span class="org-event-card_badge-month">{{ badge.month }}</span>
          <span class="org-event-card_badge-day">{{ badge.day }}</span>
        </span>
        <span class="org-event-card_date">{{ dateText || 'Datum folgt' }}</span>
      </div>

      <h3 class="org-event-card_title">{{ event.Title }}</h3>

      <!-- Nur der Ort; die volle Adresse steht auf der Event-Seite -->
      <p class="org-event-card_place">
        <template v-if="place">
          <span class="icon-mask org-event-card_place-icon" :style="locationIconStyle" aria-hidden="true" />
          <span class="org-event-card_place-text">{{ place }}</span>
        </template>
      </p>

      <ul class="org-event-card_tags" aria-label="Art und Altersgruppen">
        <li v-if="event.TypeTitle" class="org-event-card_tag org-event-card_tag--type">{{ event.TypeTitle }}</li>
        <li v-for="group in visibleAgeGroups" :key="group.ID" class="org-event-card_tag">{{ group.Title }}</li>
        <li v-if="hiddenAgeGroups.length" class="org-event-card_tag org-event-card_tag--more" :title="hiddenAgeGroups.map(g => g.Title).join(', ')">
          +{{ hiddenAgeGroups.length }}
        </li>
      </ul>

      <div class="org-event-card_footer">
        <span class="org-event-card_price" :class="{ 'org-event-card_price--empty': !priceText }">
          {{ priceText ?? 'Kein Preis angegeben' }}
        </span>
        <!-- Veranstaltende Organisation, klein rechts neben dem Preis -->
        <span v-if="event.OrganizationTitle" class="org-event-card_org">
          <span v-if="showOrganization" class="org-event-card_org-title">{{ event.OrganizationTitle }}</span>
          <AppOrgLogo
            :src="event.OrganizationLogoURL"
            :alt="event.OrganizationTitle"
            :name="event.OrganizationTitle"
            :size="24"
            :title="event.OrganizationTitle"
          />
        </span>
      </div>
    </div>
  </component>
</template>

<script setup>
import { computed } from 'vue'
import { formatOrgEventRange, formatPriceSummary, formatPlace, dateBadge } from '@utils/orgEvents'
import AppOrgLogo from '@components/ui/AppOrgLogo.vue'
import AppPatternImage from '@components/ui/AppPatternImage.vue'
import actionLocation from '../../../../icons/actions/action_location.svg'

const locationIconStyle = { maskImage: `url("${actionLocation}")`, WebkitMaskImage: `url("${actionLocation}")` }

// Mehr Altersgruppen passen nicht verlässlich in eine Zeile, der Rest wird als "+N" gezählt
const MAX_AGE_GROUPS = 2

const props = defineProps({
  // Summary aus GET /calendar/orgEvents (OrgEvent::toApiSummary())
  event: { type: Object, required: true },
  // Linkziel (router-link); ohne wird die Karte nicht klickbar gerendert
  to: { type: [String, Object], default: null },
  // Namen der Organisation zusätzlich zum Icon im Fuß zeigen — sinnvoll, wenn man in mehreren Organisationen ist
  showOrganization: { type: Boolean, default: false },
})

const badge = computed(() => dateBadge(props.event.RangeStart))
const dateText = computed(() => (props.event.RangeStart ? formatOrgEventRange(props.event) : ''))
const priceText = computed(() => formatPriceSummary(props.event))
const place = computed(() => formatPlace(props.event))

// Der Typ belegt schon einen Platz, dann bleibt einer weniger für Altersgruppen
const ageGroupSlots = computed(() => (props.event.TypeTitle ? MAX_AGE_GROUPS : MAX_AGE_GROUPS + 1))
const ageGroups = computed(() => props.event.AgeGroups ?? [])
const visibleAgeGroups = computed(() =>
  ageGroups.value.length > ageGroupSlots.value
    ? ageGroups.value.slice(0, ageGroupSlots.value - 1)
    : ageGroups.value
)
const hiddenAgeGroups = computed(() => ageGroups.value.slice(visibleAgeGroups.value.length))

const isPast = computed(() => {
  const end = props.event.RangeEnd ?? props.event.RangeStart
  return !!end && end < new Date().toISOString().slice(0, 10)
})
</script>
