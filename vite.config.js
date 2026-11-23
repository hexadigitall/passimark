import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';

// Laravel serves the compiled bundle from /build (manifest at public/build/manifest.json).
// The base MUST match that path for the production build, otherwise Vite's dependency-preload
// helper emits /assets/... URLs that 404 and blank lazily-loaded screens after navigation.
export default defineConfig(({ command }) => ({
    base: command === 'build' ? '/build/' : '/',
    plugins: [react()],
    publicDir: false,
    build: {
        manifest: true,
        outDir: 'public/build',
        rollupOptions: {
            input: 'resources/js/app.jsx',
        },
    },
}));
