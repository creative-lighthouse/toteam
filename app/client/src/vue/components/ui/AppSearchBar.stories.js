import { ref } from 'vue'
import AppSearchBar from './AppSearchBar.vue'
import AppButton from './AppButton.vue'

export default {
  title: 'Design System/AppSearchBar',
  component: AppSearchBar,
  tags: ['autodocs'],
  args: {
    placeholder: 'Aufgaben suchen…',
  },
}

const render = (template) => (args) => ({
  components: { AppSearchBar, AppButton },
  setup() {
    const search = ref('')
    return { args, search }
  },
  template,
})

export const OnlySearch = {
  render: render('<AppSearchBar v-bind="args" v-model="search" />'),
}

export const WithAction = {
  args: { placeholder: 'Organisationen durchsuchen…' },
  render: render(`
    <AppSearchBar v-bind="args" v-model="search">
      <template #actions><AppButton variant="primary">+ Organisation erstellen</AppButton></template>
    </AppSearchBar>`),
}

export const WithActionAndFilters = {
  render: render(`
    <AppSearchBar v-bind="args" v-model="search">
      <template #actions><AppButton variant="primary">Neue Aufgabe</AppButton></template>
      <template #filters>
        <select><option>Alle Organisationen</option></select>
        <select><option>Alle Status</option></select>
      </template>
    </AppSearchBar>`),
}
