import AppCollapse from './AppCollapse.vue'

export default {
  title: 'Design System/AppCollapse',
  component: AppCollapse,
  tags: ['autodocs'],
  args: {
    title: 'Koordinaten',
    subtitle: '(optional)',
    defaultOpen: false,
  },
  // Im .modalform-Kontext rendern, wie in den Modals
  render: (args) => ({
    components: { AppCollapse },
    setup: () => ({ args }),
    template: `
      <form class="modalform" style="max-width: 460px">
        <AppCollapse v-bind="args">
          <div class="modalform">
            <label class="field field--3">Oben links<input type="text" placeholder="53.6371, 10.3829"></label>
            <label class="field field--3">Oben rechts<input type="text" placeholder="53.6369, 10.3834"></label>
          </div>
        </AppCollapse>
      </form>`,
  }),
}

export const Closed = {}

export const Open = {
  args: { defaultOpen: true },
}
