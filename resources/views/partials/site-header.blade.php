@php
    $headerCtaLabel = $headerCtaLabel ?? 'Analyze a business';
@endphp

<header class="site-header">
    <div class="container header-inner">
        <a href="{{ route('home') }}" class="brand" aria-label="Competitor Intelligence home">
            <span class="brand-mark" aria-hidden="true">
                <i></i><i></i><i></i><i></i>
            </span>
            <span class="brand-name">Competitor Intelligence</span>
        </a>

        <nav class="main-nav" aria-label="Primary navigation">
            <a href="{{ route('home') }}#how-it-works">How it works</a>
            <a href="{{ route('home') }}#capabilities">Capabilities</a>
        </nav>

        <a href="{{ route('home') }}#analysis-form" class="header-cta">
            {{ $headerCtaLabel }}
            <span aria-hidden="true">↗</span>
        </a>
    </div>
</header>
