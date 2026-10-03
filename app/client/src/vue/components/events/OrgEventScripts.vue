<template>
  <!-- Skript-Karte der Event-Seite: pro Skript eine Karte mit seinem Rollenplan
       (Tage × Rollen). Im Bearbeiten-Modus öffnet ein Klick auf eine Zelle die Zuteilung. -->
  <div class="org-event-scripts">
    <p v-if="hasRoles && !days.length" class="org-event-scripts_hint">
      Noch kein Termin mit Rollenplan.
      <template v-if="state.CanAssign">
        Schalte bei den Terminen des Events „Rollenplan“ ein, dann können hier für deren Tage Rollen zugeteilt werden.
      </template>
    </p>

    <article v-for="script in state.scripts" :key="script.ID" class="org-event-scripts_script">
      <header class="org-event-scripts_script-head">
        <router-link :to="{ name: 'SkriptDetail', params: { hash: script.Hash } }" class="org-event-scripts_script-title">
          {{ script.Title }}
        </router-link>
        <AppIconButton
          v-if="state.CanManageScripts"
          variant="ghost"
          aria-label="Skript vom Event lösen"
          title="Vom Event lösen"
          :disabled="busy"
          @click="detach(script)"
        >
          <span class="icon-mask" :style="trashIconStyle" aria-hidden="true" />
        </AppIconButton>
      </header>
      <p v-if="script.OtherEvents.length" class="org-event-scripts_script-meta">
        Auch in: {{ script.OtherEvents.join(', ') }}
      </p>

      <!-- Rollen werden im Skript selbst gepflegt (Modus "Rollen") -->
      <p v-if="!script.Roles.length" class="org-event-scripts_hint">
        Noch keine Rollen<template v-if="script.CanEdit"> —
          <router-link :to="{ name: 'SkriptDetail', params: { hash: script.Hash } }">im Skript anlegen</router-link></template>.
      </p>

      <OrgEventRoleTable
        v-else-if="days.length"
        :script="script"
        :assignments="state.assignments"
        :days="days"
        :role-titles="roleTitles"
        :editable="state.CanAssign"
        @cell="(role, day) => assignModal?.open(role, day)"
      />
    </article>

    <p v-if="error" class="org-event-scripts_error">{{ error }}</p>

    <OrgEventRoleAssignModal
      v-if="state.CanAssign"
      ref="assignModal"
      :event-id="event.ID"
      :assignments="state.assignments"
      :members="state.members"
      :role-titles="roleTitles"
      @update="emit('update', $event)"
    />
  </div>
</template>

<script setup>
import { ref, computed } from 'vue'
import { useSkriptStore } from '@stores/skript'
import AppIconButton from '@components/ui/AppIconButton.vue'
import OrgEventRoleTable from '@components/events/OrgEventRoleTable.vue'
import OrgEventRoleAssignModal from '@components/events/OrgEventRoleAssignModal.vue'
import { castingDayList } from '@utils/roleCasting'
import actionTrash from '../../../../icons/actions/action_trash.svg'

const trashIconStyle = { maskImage: `url("${actionTrash}")`, WebkitMaskImage: `url("${actionTrash}")` }

const props = defineProps({
  // Event aus GET /calendar/orgEvent/{id} (toApiSummary)
  event: { type: Object, required: true },
  // Stand aus GET /skript/eventScripts/{id}: { scripts, castingDays, assignments, members, Can… }
  state: { type: Object, required: true },
})

// Neuer Gesamtstand nach jeder Änderung — die Event-Seite hält ihn
const emit = defineEmits(['update'])

const store = useSkriptStore()
const busy = ref(false)
const error = ref(null)
const assignModal = ref(null)

const hasRoles = computed(() => props.state.scripts.some(s => s.Roles.length))
const days = computed(() => castingDayList(props.state.castingDays, props.state.assignments))
const roleTitles = computed(() => Object.fromEntries(props.state.scripts.flatMap(s => s.Roles.map(r => [r.ID, r.Title]))))

async function detach(script) {
  if (!confirm(`Skript „${script.Title}“ von diesem Event lösen? Das Skript bleibt erhalten, die Rollenzuteilung für dieses Event wird gelöscht.`)) return
  busy.value = true
  error.value = null
  try {
    emit('update', await store.detachEventScript(props.event.ID, script.ID))
  } catch (e) {
    error.value = e.message
  } finally {
    busy.value = false
  }
}
</script>
