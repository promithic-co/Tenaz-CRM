import { fileURLToPath } from 'node:url';
import tailwindcss from '@tailwindcss/vite';
import vue from '@vitejs/plugin-vue';
import { defineConfig } from 'vite';

/**
 * Standalone component harness: renders a page partial against fixture props with no
 * Laravel, no database and no login. Deliberately separate from vite.config.ts so it
 * never pulls in the Laravel or Wayfinder plugins — Wayfinder would rewrite ~90 tracked
 * TypeScript files just to boot a preview.
 *
 *   composer run preview
 */
export default defineConfig({
    root: fileURLToPath(new URL('./.preview', import.meta.url)),
    plugins: [tailwindcss(), vue()],
    resolve: {
        alias: [
            // Must precede the generic @/ rule: the real module opens a Reverb socket.
            {
                find: /^@\/echo$/,
                replacement: fileURLToPath(
                    new URL('./.preview/echo-stub.ts', import.meta.url),
                ),
            },
            {
                find: /^@\//,
                replacement: fileURLToPath(
                    new URL('./resources/js/', import.meta.url),
                ),
            },
        ],
    },
    server: { port: 5199, open: true },
});
