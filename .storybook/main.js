/** @type {import('@storybook/vue3-vite').StorybookConfig} */
const config = {
  stories: ['../app/client/src/vue/**/*.stories.js'],
  addons: [],
  framework: {
    name: '@storybook/vue3-vite',
    options: {},
  },
  viteFinal: async (config) => {
    config.resolve = config.resolve || {}
    config.resolve.alias = {
      ...(config.resolve.alias || {}),
      '@': '/app/client/src',
      '@components': '/app/client/src/vue/components',
      '@views': '/app/client/src/vue/views',
      '@stores': '/app/client/src/vue/stores',
      '@utils': '/app/client/src/vue/utils',
      '@models': '/app/client/src/vue/models',
    }
    return config
  },
}

export default config
