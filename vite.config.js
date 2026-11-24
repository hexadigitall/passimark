import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';

export default defineConfig(({ command }) => ({
    base: command === 'build' ? '/build/' : '/',
    plugins: [react()],
    publicDir: false,
    build: {
        manifest: true,
        outDir: 'public/build',
        rollupOptions: {
            input: {
                app: 'resources/js/app.jsx',
                native: 'resources/js/native.jsx',
            },
        },
    },
}));
