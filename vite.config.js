import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/css/status-pages.css',
                'resources/js/app.js',
                'resources/js/success-animation.js'
            ],
            refresh: true,
        }),
    ],
});
