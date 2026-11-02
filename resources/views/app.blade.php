<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="application-name" content="{{ config('app.name') }}">
        <meta name="theme-color" content="#12332e">
        <meta name="description" content="Your food, recipes, and nutrition in one place.">
        <title>Mealplaner</title>
        <link rel="icon" type="image/png" href="/icons/icon-192.png">
        <link rel="apple-touch-icon" href="/icons/icon-192.png">
        @unless (Vite::isRunningHot())
            <link rel="manifest" href="/build/manifest.webmanifest">
        @endunless
        @vite('resources/js/app.ts')
    </head>
    <body>
        <div id="app"></div>
        <noscript>Please enable JavaScript to use this application.</noscript>
    </body>
</html>
