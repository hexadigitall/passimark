/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './resources/views/**/*.blade.php',
        './resources/js/**/*.{js,jsx}',
    ],
    theme: {
        extend: {
            // The Passimark palette, per docs/v4-worldwide-catalog-spec.md §2.2.
            // The spec named four tokens (Primary #1A9E2D, Deep #0F5D2F,
            // Accent #7CFC8F, Dark #0F172A) but shipped none of them, so the UI
            // fell back to stock Tailwind emerald at hue 160-163. These are the
            // spec's greens expressed as a usable scale, anchored so that
            //   brand-600 === #1A9E2D  (spec Primary)
            //   brand-800 === #0F5D2F  (spec Deep, also the PWA icon field)
            // Primary is deliberately the 600 step, not the 500: as a button fill
            // with slate-950 ink it only reaches 5.74:1, whereas brand-500
            // reaches 7.76:1. Steps 800+ are fill/border only -- they fall below
            // 3:1 as text on slate-900 and must never carry a label.
            // Verified: 50-600 are >=5.08:1 on #0F172A; 700 is 3.27:1 (AA large).
            colors: {
                brand: {
                    50: '#F0FBF1',
                    100: '#DCF7DE',
                    200: '#B8EDBC',
                    300: '#7BD98F',
                    400: '#52C86B',
                    500: '#2FB84A', // primary interactive fill on dark surfaces
                    600: '#1A9E2D', // spec Primary
                    700: '#157A24',
                    800: '#0F5D2F', // spec Deep -- icon field, borders, fills
                    900: '#0B4522',
                    950: '#06240F',
                },
                // Spec Accent. Highlights, focus rings, success emphasis only.
                // 13.75:1 on #0F172A and 15.54:1 with slate-950 ink, so it is
                // never a large fill behind body text.
                accent: '#7CFC8F',
                // Spec Dark. The shell surface and the PWA background_color.
                deep: '#0F172A',
            },
            fontFamily: {
                // Resolved through the custom property in resources/css/app.css so
                // the utility classes and the raw-CSS fault screen cannot drift
                // apart. The var() fallback keeps text readable in a sans-serif
                // even if the stylesheet that defines it is ever dropped --
                // without it, an unresolved var() would fall back to serif.
                sans: ['var(--pm-font-sans, ui-sans-serif, system-ui, sans-serif)'],
            },
            // The old `shadow-ring` token and the `display` font family are gone.
            // Focus indication is expressed with ring-* utilities (38 call sites)
            // and there is a single family, so both were dead config advertising a
            // design system the app did not implement -- which is how the emerald
            // drift went unnoticed in the first place. See BrandPaletteTest.
        },
    },
    plugins: [],
};
