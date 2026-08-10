<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>@yield('title', 'Competitor Analysis')</title>

    <meta
        name="description"
        content="@yield('meta_description', 'AI-powered competitor analysis and market intelligence.')"
    >

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body>
    @yield('content')
</body>
</html>