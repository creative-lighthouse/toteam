<template>
  <!-- Skript-Karte der Event-Seite: alle Skripte des Events, ihre Rollen und die Rollenzuteilung pro Tag -->
  <div class="org-event-scripts">
    <ul class="org-event-scripts_list">
      <li v-for="script in state.scripts" :key="script.ID" class="org-event-scripts_script">
        <div class="org-event-scripts_script-head">
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
        </div>
        <p v-if="script.OtherEvents.length" class="org-event-scripts_script-meta">
          Auch in: {{ script.OtherEvents.join(', ') }}
        </p>

        <div class="org-event-scripts_roles">
          <span v-for="role in script.Roles" :key="role.ID" class="org-event-scripts_role" :title="role.Description || null">
            {{ role.Title }}
          </span>
          <!-- Rollen werden im Skript selbst gepflegt (Modus "Rollen") -->
          <span v-if="!script.Roles.length" class="org-event-scripts_hint">
            Noch keine Rollen<template v-if="script.CanEdit"> —
              <router-link :to="{ name: 'SkriptDetail', params: { hash: script.Hash } }">im Skript anlegen</router-link></template>.
          </span>
        </div>
      </li>
    </ul>

    <p v-if="error" class="org-event-scripts_error">{{ error }}</p>

    <!-- Zuteilen (Bearbeiten-Modus mit Rechten) bzw. Rollenplan als Tabelle (alle anderen) -->
    <template v-if="hasRoles">
      <OrgEventRoleCasting
        v-if="state.CanAssign"
        :event="event"
        :scripts="state.scripts"
        :assignments="state.assignments"
        :casting-days="state.castingDays"
        :members="state.members"
        @update="emit('update', $event)"
      />
      <OrgEventRoleTable
        v-else
        :scripts="state.scripts"
        :assignments="state.assignments"
        :casting-days="state.castingDays"
      />
    </template>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue'
import { useSkriptStore } from '@stores/skript'
import AppIconButton from '@components/ui/AppIconButton.vue'
import OrgEventRoleCasting from '@components/events/OrgEventRoleCasting.vue'
import OrgEventRoleTable from '@components/events/OrgEventRoleTable.vue'
import actionTrash from '../../../../icons/actions/action_trash.svg'

const trashIconStyle = { maskImage: `url("${actionTrash}")`, WebkitMaskImage: `url("${actionTrash}")` }

const props = defineProps({
  // Event aus GET /calendar/orgEvent/{id} (toApiSummary)
  event: { type: Object, required: true },
  // Stand aus GET /skript/eventScripts/{id}: { scripts, availableScripts, assignments, members, Can… }
  state: { type: Object, required: true },
})

// Neuer Gesamtstand nach jeder Änderung — die Event-Seite hält ihn
const emit = defineEmits(['update'])

const store = useSkriptStore()
const busy = ref(false)
const error = ref(null)

const hasRoles = computed(() => props.state.scripts.some(s => s.Roles.length))

async function run(action) {
  busy.value = true
  error.value = null
  try {
    await action()
  } catch (e) {
    error.value = e.message
  } finally {
    busy.value = false
  }
}

function detach(script) {
  if (!confirm(`Skript „${script.Title}“ von diesem Event lösen? Das Skript bleibt erhalten, die Rollenzuteilung für dieses Event wird gelöscht.`)) return
  return run(async () => {
    emit('update', await store.detachEventScript(props.event.ID, script.ID))
  })
}
</script>
