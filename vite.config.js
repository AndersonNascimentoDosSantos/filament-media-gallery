// vite.config.js
import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/js/app.js',
                // Adicione os novos assets do seu pacote
                'resources/css/galeria-midia-field.css',
                'resources/js/galeria-midia-field.js',
            ],
            refresh: true,
        }),
    ],
});
