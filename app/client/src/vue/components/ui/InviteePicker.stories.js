import { ref } from 'vue'
import InviteePicker from './InviteePicker.vue'

// Hinweis: Diese Komponente lädt Mitglieder über den events-Store (echter API-Call).
// In Storybook (ohne Backend-Proxy) schlägt der Request fehl und die Liste bleibt leer –
// die Story dient hier vor allem der Props-/Struktur-Übersicht, nicht der vollen Funktion.
export default {
  title: 'Design System/InviteePicker',
  component: InviteePicker,
  tags: ['autodocs'],
  argTypes: {
    autoSelectAll: { control: 'boolean' },
  },
  args: {
    availableOrgs: [
      { ID: 1, Title: 'Musterverein e.V.' },
      { ID: 2, Title: 'Jugendgruppe' },
    ],
    autoSelectAll: true,
  },
}

export const Playground = {
  render: (args) => ({
    components: { InviteePicker },
    setup() {
      const organizationIds = ref([])
      const invitedMemberIds = ref([])
      return { args, organizationIds, invitedMemberIds }
    },
    template: `
      <InviteePicker
        v-bind="args"
        v-model:organization-ids="organizationIds"
        v-model:invited-member-ids="invitedMemberIds"
      />
    `,
  }),
}
