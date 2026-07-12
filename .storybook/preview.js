import { setup } from '@storybook/vue3-vite'
import { createPinia } from 'pinia'

import '../app/client/src/scss/main.scss'

setup((app) => {
  app.use(createPinia())
})

/** @type {import('@storybook/vue3-vite').Preview} */
const preview = {
  parameters: {
    controls: {
      matchers: {
        color: /(background|color)$/i,
        date: /Date$/i,
      },
    },
  },
}

export default preview
