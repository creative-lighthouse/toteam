<template>
    <main>
        <section class="landingpage__hero">
            <h1>Teamarbeit, die einfach funktioniert.</h1>
            <p>
                ToTeam ist ein Verwaltungstool für Vereine, Initiativen und andere Organisationen, das dir hilft,
                den Überblick über Mitglieder, Termine und Aufgaben zu behalten. Mit ToTeam kannst du dich auf
                das konzentrieren, was wirklich zählt: dein Team.
            </p>
            <div class="landingpage__hero-actions">
                <a class="app-button app-button--default app-button--primary" href="/app/login">Jetzt loslegen</a>
                <a class="app-button app-button--default app-button--secondary" href="/app/login">Login</a>
            </div>
        </section>

        <section class="landingpage__feature">
            <h2>Mitgliederverwaltung, die den Überblick behält</h2>
            <p>
                Verwalte die Mitglieder deiner Organisation an einem Ort – mit Namen,
                Allergien, Beitrittsterminen und den Rollen, die sie im Team übernehmen. Egal ob Vorstand,
                Kassenwart oder einfaches Mitglied: Neue Gesichter treten bei, andere verlassen das Team
                wieder – ToTeam hält die Mitglieder übersichtlich, damit du dich um dein Team
                kümmern kannst statt um Excel-Tabellen.
            </p>
            <MemberGraphic />
        </section>

        <section class="landingpage__feature">
            <h2>Kalender, der auf einen Blick zeigt, wer dabei ist</h2>
            <p>
                Behalte alle Termine deiner Organisation in einer Monatsansicht im Blick –
                und sieh direkt, wer zu- oder abgesagt hat. Kommt jemand später oder muss früher los? Auch
                angepasste Uhrzeiten pro Person werden übersichtlich angezeigt, damit du immer weißt, wer
                wann wirklich vor Ort ist.
            </p>
            <CalendarGraphic />
        </section>

        <section class="landingpage__cta">
            <h2>
                Ordnung für
                <Transition name="cta-word" mode="out-in">
                    <span class="landingpage__cta-word" :key="wordIndex">{{ currentWord.article }} {{ currentWord.noun }}</span>
                </Transition>
            </h2>
            <a class="app-button app-button--default app-button--primary" href="/app/login">Jetzt registrieren</a>
        </section>
    </main>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import MemberGraphic from '../components/MemberGraphic.vue'
import CalendarGraphic from '../components/CalendarGraphic.vue'

const ctaWords = [
  { article: 'deinen', noun: 'Verein' },
  { article: 'dein', noun: 'Team' },
  { article: 'deine', noun: 'Arbeitsgruppe' },
  { article: 'deine', noun: 'Organisation' },
  { article: 'deinen', noun: 'Verband' },
]

const wordIndex = ref(0)
const currentWord = computed(() => ctaWords[wordIndex.value])

let ctaTimer = null

onMounted(() => {
  const prefersReducedMotion = window.matchMedia?.('(prefers-reduced-motion: reduce)').matches
  if (prefersReducedMotion) return

  ctaTimer = window.setInterval(() => {
    wordIndex.value = (wordIndex.value + 1) % ctaWords.length
  }, 2200)
})

onUnmounted(() => {
  if (ctaTimer) window.clearInterval(ctaTimer)
})
</script>
