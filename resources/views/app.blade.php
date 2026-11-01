<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>{{ \App\Models\Setting::get('brand_name', 'Isimbi Mart') }}</title>
        @vite(['resources/css/app.css', 'resources/js/app.tsx'])
    </head>
    <body class="font-sans antialiased">
        <script data-page="app" type="application/json">@json($page)</script>
        <div id="app"></div>
    </body>
</html>
