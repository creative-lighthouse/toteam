import ParticipationIcon from './ParticipationIcon.vue'

export default {
  title: 'Design System/ParticipationIcon',
  component: ParticipationIcon,
  tags: ['autodocs'],
  argTypes: {
    participationType: { control: 'select', options: ['accept', 'maybe', 'decline', 'none'] },
  },
  args: {
    participationType: 'accept',
  },
}

export const Playground = {}

export const AllStates = {
  render: () => ({
    components: { ParticipationIcon },
    template: `
      <div style="display:flex; gap:12px;">
        <ParticipationIcon participation-type="accept" />
        <ParticipationIcon participation-type="maybe" />
        <ParticipationIcon participation-type="decline" />
        <ParticipationIcon participation-type="none" />
      </div>
    `,
  }),
}
