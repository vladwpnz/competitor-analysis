<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>@hasSection('title')@yield('title') · @endif{{ config('app.name') }}</title>

    <meta
        name="description"
        content="@yield('meta_description', 'AI-assisted competitor discovery, business classification, and relevance ranking.')"
    >
    <meta name="application-name" content="{{ config('app.name') }}">
    <meta name="theme-color" content="#3023e7">
    <meta property="og:type" content="website">
    <meta property="og:title" content="@yield('title', config('app.name'))">
    <meta
        property="og:description"
        content="@yield('meta_description', 'AI-assisted competitor discovery, business classification, and relevance ranking.')"
    >
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body>
    @yield('content')
</body>
</html>
