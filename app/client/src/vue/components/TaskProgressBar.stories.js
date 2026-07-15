import TaskProgressBar from './TaskProgressBar.vue'

export default {
  title: 'Design System/TaskProgressBar',
  component: TaskProgressBar,
  tags: ['autodocs'],
  argTypes: {
    showLegend: { control: 'boolean' },
  },
  args: {
    showLegend: true,
    subtasks: [
      { State: 'finished' },
      { State: 'finished' },
      { State: 'in_progress' },
      { State: 'feedback' },
      { State: 'open' },
      { State: 'open' },
    ],
  },
}

export const Playground = {}

export const Compact = {
  args: { showLegend: false },
}

export const AllFinished = {
  args: {
    subtasks: [{ State: 'finished' }, { State: 'finished' }, { State: 'finished' }],
  },
}
