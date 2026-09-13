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
        <link rel="manifest" href="/manifest.json">
        @vite('resources/js/app.jsx')
        @inertiaHead
    </head>
    <body>
        @inertia
    </body>
</html>
