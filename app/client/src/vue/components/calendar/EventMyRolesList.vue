<template>
  <!-- Rollen, die man an dem Tag im Rollenplan des Events spielt, z.B. unter den
       heutigen Termin-Karten auf dem Dashboard; ein Klick öffnet das Skript -->
  <ul v-if="roles.length" class="event-my-roles-list">
    <li v-for="role in roles" :key="role.ID">
      <component
        :is="linkScripts ? 'router-link' : 'div'"
        :to="linkScripts ? { name: 'SkriptDetail', params: { hash: role.ScriptHash } } : undefined"
        class="event-my-role"
      >
        <span class="event-my-role_time">{{ timeLabel(role) }}</span>
        <span class="event-my-role_name">
          <span class="event-my-role_label">Deine Rolle:</span> {{ role.RoleTitle }}
        </span>
        <span class="event-my-role_script">{{ role.ScriptTitle }}</span>
        <svg v-if="linkScripts" width="12" height="12" viewBox="0 0 16 16" fill="currentColor" style="opacity:.4;flex-shrink:0">
          <path d="M6.22 3.22a.75.75 0 011.06 0l4.25 4.25a.75.75 0 010 1.06l-4.25 4.25a.75.75 0 01-1.06-1.06L9.94 8 6.22 4.28a.75.75 0 010-1.06z"/>
        </svg>
      </component>
    </li>
  </ul>
</template>

<script setup>
defineProps({
  // [{ ID, RoleTitle, ScriptTitle, ScriptHash, TimeStart, TimeEnd }]
  roles: { type: Array, default: () => [] },
  // Skript verlinken (nur wenn das Skript-Totem aktiv ist)
  linkScripts: { type: Boolean, default: true },
})

function timeLabel(role) {
  if (!role.TimeStart) return 'Ganztägig'
  return role.TimeEnd ? `${role.TimeStart}–${role.TimeEnd} Uhr` : `ab ${role.TimeStart} Uhr`
}
</script>
