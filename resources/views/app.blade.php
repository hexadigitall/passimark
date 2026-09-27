<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta name="theme-color" content="#1A9E2D">
        <link rel="icon" href="/images/passimark/passimark_favicon.ico" sizes="any">
        <link rel="icon" type="image/png" sizes="16x16" href="/images/passimark/passimark_icon_16x16.png">
        <link rel="icon" type="image/png" sizes="32x32" href="/images/passimark/passimark_icon_32x32.png">
        <link rel="apple-touch-icon" sizes="180x180" href="/images/passimark/passimark_icon_180x180.png">
        {{-- Preload the variable font so the first paint is set in Inter rather
             than swapping to it after the fallback. crossorigin is required even
             same-origin: font fetches are always CORS-mode. --}}
        <link rel="preload" href="/fonts/inter-latin-wght-normal.woff2" as="font" type="font/woff2" crossorigin>
        <link rel="manifest" href="/manifest.json">
        @vite('resources/js/app.jsx')
        @inertiaHead
    </head>
    <body>
        @inertia
        <script>
            if ('serviceWorker' in navigator) {
                window.addEventListener('load', function () {
                    navigator.serviceWorker.register('/sw.js')
                        .catch(function () { /* non-fatal: offline shell is progressive enhancement */ });
                });
            }
        </script>
    </body>
</html>
