/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './resources/views/**/*.blade.php',
        './resources/js/**/*.{js,jsx}',
    ],
    theme: {
        extend: {
            colors: {
                pm: {
                    deep: '#0F172A',   // shell background (matches PWA manifest)
                    brand: '#1A9E2D',  // brand green
                    accent: '#7CFC8F', // bright accent
                },
            },
            fontFamily: {
                display: ['ui-sans-serif', 'system-ui', 'Segoe UI', 'sans-serif'],
            },
            boxShadow: {
                ring: '0 0 0 3px rgba(124, 252, 143, 0.18)',
            },
        },
    },
    plugins: [],
};