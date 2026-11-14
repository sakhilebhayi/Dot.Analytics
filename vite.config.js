import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js', 'resources/css/public.css', 'resources/css/workspace.css', 'resources/css/tokens.css', 'resources/js/public.js', 'resources/js/dashboard.js'],
            refresh: true,
        }),
    ],
});
