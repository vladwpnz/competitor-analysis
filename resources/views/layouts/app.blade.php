<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>@hasSection('title')@yield('title') | @endif Competitor Intelligence</title>

    <meta
        name="description"
        content="@yield('meta_description', 'AI-assisted competitor discovery, business classification, and relevance ranking.')"
    >
    <meta name="application-name" content="Competitor Intelligence">
    <meta name="theme-color" content="#f6f8f7">
    <meta property="og:type" content="website">
    <meta property="og:title" content="@yield('title', 'Competitor Intelligence')">
    <meta
        property="og:description"
        content="@yield('meta_description', 'AI-assisted competitor discovery, business classification, and relevance ranking.')"
    >
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body>
    <a class="skip-link" href="#main-content">Skip to main content</a>

    @yield('content')
</body>
</html>
