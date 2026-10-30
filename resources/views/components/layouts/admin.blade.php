@props(['title' => 'Barama Real Estate — Workspace'])
<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }}</title>
    @vite(['resources/css/admin/app.css', 'resources/js/admin/app.js'])
</head>
<body class="admin-body">
    <main class="admin-main" id="main-content">
        {{ $slot }}
    </main>
</body>
</html>
