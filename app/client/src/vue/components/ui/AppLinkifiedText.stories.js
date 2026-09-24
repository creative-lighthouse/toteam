import AppLinkifiedText from './AppLinkifiedText.vue'

export default {
  title: 'Design System/AppLinkifiedText',
  component: AppLinkifiedText,
  tags: ['autodocs'],
  render: (args) => ({
    components: { AppLinkifiedText },
    setup: () => ({ args }),
    template: '<p style="white-space: pre-wrap; max-width: 520px"><AppLinkifiedText v-bind="args" /></p>',
  }),
}

export const Links = {
  args: {
    text: 'Checkliste unter https://toteam.de/aufgaben. Mehr Infos auf www.beispiel.de/info?a=1, oder (siehe https://example.org/pfad)',
  },
}

export const HtmlBleibtText = {
  args: {
    text: '<b>kein fett</b> <script>alert(1)</script> — aber https://ok.de wird verlinkt',
  },
}
