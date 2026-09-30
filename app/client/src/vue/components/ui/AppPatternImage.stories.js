import AppPatternImage from './AppPatternImage.vue'

export default {
  title: 'Design System/AppPatternImage',
  component: AppPatternImage,
  tags: ['autodocs'],
  decorators: [() => ({ template: '<div style="width: 320px; aspect-ratio: 16 / 9"><story /></div>' })],
}

export const Default = { args: { seed: 'Halloweenhaus 2026' } }

// Jeder Titel ergibt ein eigenes, aber immer gleiches Muster
export const Variety = {
  decorators: [() => ({ template: '<story />' })],
  render: () => ({
    components: { AppPatternImage },
    setup: () => ({
      seeds: ['Halloweenhaus 2026', 'Sommerfest', 'Weihnachtsmarkt', 'Kinderdisco', 'Vereinsausflug', 'Theaterabend', 'Flohmarkt', 'Mitgliederversammlung'],
    }),
    template: `
      <div style="display: grid; grid-template-columns: repeat(4, 200px); gap: 12px">
        <figure v-for="seed in seeds" :key="seed" style="margin: 0">
          <div style="aspect-ratio: 16 / 9; border-radius: 8px; overflow: hidden"><AppPatternImage :seed="seed" /></div>
          <figcaption style="font-size: 12px; margin-top: 4px">{{ seed }}</figcaption>
        </figure>
      </div>`,
  }),
}
