<!DOCTYPE html>
<html lang="es">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="theme-color" content="#0b5bd3">

        <title inertia>{{ config('app.name', 'Clínica') }}</title>

        @vite(['resources/css/app.css', 'resources/js/app.ts'])
        <x-inertia::head />
    </head>
    <body class="min-h-screen bg-slate-50 antialiased dark:bg-slate-950">
        <x-inertia::app />
    </body>
</html>
