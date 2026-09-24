import { ref } from 'vue'
import AppFileUpload from './AppFileUpload.vue'

export default {
  title: 'Design System/AppFileUpload',
  component: AppFileUpload,
  tags: ['autodocs'],
  argTypes: {
    required: { control: 'boolean' },
    multiple: { control: 'boolean' },
    maxFiles: { control: 'number' },
  },
  args: {
    label: 'Beleg',
    accept: 'image/jpeg,image/png,application/pdf',
    maxSize: 5 * 1024 * 1024,
    hint: 'JPG, PNG oder PDF, max. 5 MB',
  },
  // Im .modalform-Kontext rendern, wie in den Modals
  render: (args) => ({
    components: { AppFileUpload },
    setup() {
      const value = ref(args.multiple ? [] : null)
      return { args, value }
    },
    template: '<form class="modalform" style="max-width: 460px"><AppFileUpload v-bind="args" v-model="value" /></form>',
  }),
}

export const Single = {}

export const Required = {
  args: { required: true, buttonLabel: 'Beleg fotografieren / auswählen' },
}

export const WithExistingFile = {
  args: { existingHint: 'Aktueller Beleg bleibt erhalten, falls kein neuer gewählt wird.' },
}

export const Multiple = {
  args: { label: 'Belege', multiple: true, maxFiles: 5, hint: 'Bis zu 5 Dateien, je max. 5 MB' },
}
