import { defineConfig } from 'vite';

export default defineConfig({
    build: {
        outDir: 'resources/dist',
        rollupOptions: {
            input: 'resources/css/media-gallery.css',
            output: {
                assetFileNames: 'filament-media-gallery.css',
            },
        },
    },
});
