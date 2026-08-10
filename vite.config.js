import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            // app.css is the Filament admin stylesheet; storefront.css is the
            // customer-facing one. They are kept apart on purpose — see the header
            // comment in resources/css/storefront.css.
            input: [
                'resources/css/app.css',
                'resources/js/app.js',
                'resources/css/storefront.css',
            ],
            refresh: true,
        }),
        tailwindcss(),
    ],
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
