import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/frontend/app.css',
                'resources/js/frontend/app.js',
                'resources/css/admin/app.css',
                'resources/js/admin/app.js',
            ],
            refresh: ['resources/views/**/*.blade.php'],
        }),
    ],
    build: { cssMinify: false },
});

