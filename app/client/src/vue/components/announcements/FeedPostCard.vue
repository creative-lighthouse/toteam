<template>
  <!-- Ein Beitrag im Feed: wer (Person oder Organisation), wann, für wen — und der Text
       mit verlinkten @-Markierungen und Links -->
  <article class="feed-post-card" :class="{ 'feed-post-card--scheduled': scheduled, 'feed-post-card--compact': compact }">
    <header class="feed-post-card_header">
      <AppOrgLogo
        v-if="post.Organization"
        :src="post.Organization.LogoURL"
        :alt="post.Organization.Title"
        :name="post.Organization.Title"
        :size="40"
      />
      <AppAvatar v-else :src="post.Author?.Avatar" :alt="post.Author?.Name ?? ''" :name="post.Author?.Name ?? ''" img-class="feed-post-card_avatar" />

      <div class="feed-post-card_who">
        <component :is="authorLink ? 'router-link' : 'span'" :to="authorLink ?? undefined" class="feed-post-card_name">
          {{ post.Organization?.Title ?? post.Author?.Name ?? 'Unbekannt' }}
        </component>
        <span class="feed-post-card_meta">
          <template v-if="post.Organization && post.Author">von {{ post.Author.Name }} · </template>
          <!-- Die Zeit führt zur Detailseite des Beitrags (wie bei Social Feeds) -->
          <component
            :is="detail ? 'span' : 'router-link'"
            :to="detail ? undefined : detailLink"
            class="feed-post-card_meta-link"
          >
            <time :datetime="post.SortDate" :title="absoluteDate">{{ scheduled ? `erscheint am ${absoluteDate}` : relativeDate }}</time>
          </component>
          <template v-if="expiryText"> · {{ post.Status === 'expired' ? 'abgelaufen' : 'bis' }} {{ expiryText }}</template>
        </span>
      </div>

      <span v-if="scheduled" class="feed-post-card_visibility feed-post-card_visibility--scheduled" title="Noch nicht veröffentlicht — nur für dich sichtbar">Geplant</span>
      <span class="feed-post-card_visibility" :class="`feed-post-card_visibility--${post.Visibility.toLowerCase()}`">
        {{ post.Visibility === 'Internal' ? `Intern · ${post.InternalOrganization?.Title ?? ''}` : 'Öffentlich' }}
      </span>

      <AppIconButton v-if="post.CanDelete && !compact" variant="ghost" aria-label="Beitrag löschen" title="Löschen" @click="emit('delete', post)">
        <span class="icon-mask" :style="trashIconStyle" aria-hidden="true" />
      </AppIconButton>
    </header>

    <!-- Bereinigtes HTML (siehe renderFeedContent); @-Markierungen öffnen das Profil per Router -->
    <div v-if="post.Content" class="feed-post-card_content" @click="onContentClick" v-html="contentHtml"></div>

    <!-- Geteiltes Event: im Feed als breite Karte, kompakt (Dashboard) nur als Zeile -->
    <template v-if="post.HasEvent">
      <template v-if="post.Event">
        <router-link
          v-if="compact"
          :to="{ name: 'EventDetail', params: { segment: post.Event.URLSegment } }"
          class="feed-post-card_event-line"
        >📅 {{ post.Event.Title }}</router-link>
        <OrgEventCard
          v-else
          :event="post.Event"
          :to="{ name: 'EventDetail', params: { segment: post.Event.URLSegment } }"
          show-organization
          wide
          class="feed-post-card_event"
        />
      </template>
      <p v-else class="feed-post-card_event-missing">Das geteilte Event ist nicht (mehr) für dich sichtbar.</p>
    </template>

    <router-link v-if="compact" :to="detailLink" class="feed-post-card_more">Weiterlesen →</router-link>
  </article>
</template>

<script setup>
import { computed } from 'vue'
import AppAvatar from '@components/ui/AppAvatar.vue'
import AppIconButton from '@components/ui/AppIconButton.vue'
import AppOrgLogo from '@components/ui/AppOrgLogo.vue'
import OrgEventCard from '@components/events/OrgEventCard.vue'
import { useRouter } from 'vue-router'
import { renderFeedContent } from '@utils/feedContent'
import actionTrash from '../../../../icons/actions/action_trash.svg'

const trashIconStyle = { maskImage: `url("${actionTrash}")`, WebkitMaskImage: `url("${actionTrash}")` }

const props = defineProps({
  // aus GET /announcements/feed (AnnouncementsApiController::formatPost())
  post: { type: Object, required: true },
  // Kompakt fürs Dashboard: Text gekürzt, ohne Löschen, mit "Weiterlesen"
  compact: { type: Boolean, default: false },
  // Auf der Detailseite selbst: kein Link auf sich selbst
  detail: { type: Boolean, default: false },
})

const emit = defineEmits(['delete'])

const router = useRouter()

const detailLink = computed(() => ({ name: 'AnnouncementDetail', params: { id: props.post.ID } }))

const contentHtml = computed(() => renderFeedContent(props.post.Content, props.post.Mentions))

function onContentClick(e) {
  const link = e.target.closest('a[data-username]')
  if (!link || e.ctrlKey || e.metaKey || e.shiftKey) return
  e.preventDefault()
  router.push({ name: 'PublicProfile', params: { username: link.dataset.username } })
}

// Profil der Organisation bzw. der Person, sofern sie einen Benutzernamen hat
const authorLink = computed(() => {
  if (props.post.Organization) {
    return props.post.Organization.Username ? `/organizations/${props.post.Organization.Username}` : null
  }
  return props.post.Author?.Username ? { name: 'PublicProfile', params: { username: props.post.Author.Username } } : null
})

// Geplante Beiträge: nur für Verfasser sichtbar, bis SortDate (= Veröffentlichung) erreicht ist
const scheduled = computed(() => props.post.Status === 'scheduled')

const expiryText = computed(() => (props.post.ExpiryDate
  ? new Intl.DateTimeFormat('de-DE', { day: '2-digit', month: '2-digit', year: '2-digit', hour: '2-digit', minute: '2-digit' }).format(new Date(props.post.ExpiryDate))
  : null))

const absoluteDate = computed(() =>
  new Intl.DateTimeFormat('de-DE', { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(props.post.SortDate))
)

// "gerade eben", "vor 5 Min.", "vor 3 Std.", danach das Datum
const relativeDate = computed(() => {
  const minutes = Math.floor((Date.now() - new Date(props.post.SortDate).getTime()) / 60000)
  if (minutes < 1) return 'gerade eben'
  if (minutes < 60) return `vor ${minutes} Min.`
  if (minutes < 24 * 60) return `vor ${Math.floor(minutes / 60)} Std.`
  return new Intl.DateTimeFormat('de-DE', { day: '2-digit', month: '2-digit', year: '2-digit', hour: '2-digit', minute: '2-digit' })
    .format(new Date(props.post.SortDate))
})
</script>
