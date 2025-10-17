
import 'dotenv/config';
import {defineConfig} from 'vite'

console.log('DDEV_PRIMARY_URL:', process.env.DDEV_PRIMARY_URL);

// https://vitejs.dev/config/
export default defineConfig(({ command }) => {
    const primary_url = process.env.DDEV_PRIMARY_URL;
    const origin = primary_url.replace(/:\d+$/, "") + `:5173`;
    return {
        server: {
            // Respond to all network requests
            host: "0.0.0.0",
            port: 5173,
            strictPort: true,
            // Defines the origin of the generated asset URLs during development, this must be set to the
            // Vite dev server URL and selected port. In general, `process.env.DDEV_PRIMARY_URL` will give
            // us the primary URL of the DDEV project, e.g. "https://test-vite.ddev.site". But since DDEV
            // can be configured to use another port (via `router_https_port`), the output can also be
            // "https://test-vite.ddev.site:1234". Therefore we need to strip a port number like ":1234"
            // before adding Vites port to achieve the desired output of "https://test-vite.ddev.site:5173".
            origin: `${process.env.DDEV_PRIMARY_URL.replace(/:\d+$/, "")}:5173`,
            // Configure CORS securely for the Vite dev server to allow requests from *.ddev.site domains,
            // supports additional hostnames (via regex). If you use another `project_tld`, adjust this.
            cors: {
            origin: /https?:\/\/([A-Za-z0-9\-\.]+)?(\.ddev\.site)(?::\d+)?$/,
            },
        },
        alias: {
            alias: [{find: '@', replacement: './app/client/src'}],
        },
        // base: (command === 'build') ? '/_resources/app/client/dist/' : '/', // TODO: .env variable, only on build
        base: './',
        publicDir: 'app/client/public',
        build: {
            // cssCodeSplit: false,
            outDir: './app/client/dist',
            manifest: true,
            sourcemap: true,
            rollupOptions: {
                input: {
                'main.js': './app/client/src/js/main.js',
                'main.scss': './app/client/src/scss/main.scss',
                'editor.scss': './app/client/src/scss/editor.scss',
                },
            },
        },
        css: {
            devSourcemap: true,
        },
        plugins: []
    }
})
