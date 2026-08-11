@extends('layouts.app')

@section('title', 'Review Your Competitors')

@section('content')

@php
    $websiteDisplay = parse_url((string) $website, PHP_URL_HOST);

    if (!is_string($websiteDisplay) || trim($websiteDisplay) === '') {
        $websiteDisplay = (string) $website;
    }

    $googleBusinessDisplay = trim(
        (string) \Illuminate\Support\Str::before(
            (string) $googleBusiness,
            ','
        )
    );

    if ($googleBusinessDisplay === '') {
        $googleBusinessDisplay = (string) $googleBusiness;
    }

    $marketScope = data_get(
        $analysisResult,
        'search_profile.market_scope',
        'hybrid'
    );

    $isBroaderMarket = $marketScope === 'broader';
@endphp

<header class="site-header">
    <div class="container header-inner">
        <a href="{{ route('home') }}" class="brand" aria-label="Intellytics home">
            <span class="intellytics-mark" aria-hidden="true">
                <i></i><i></i><i></i><i></i>
            </span>
            <span class="brand-name">Intellytics</span>
        </a>

        <nav class="main-nav" aria-label="Primary navigation">
            <a href="#">Platform <span class="nav-chevron">⌄</span></a>
            <a href="#">Solutions <span class="nav-chevron">⌄</span></a>
            <a href="#">Resources <span class="nav-chevron">⌄</span></a>
            <a href="#">Pricing</a>
            <a href="#">Company <span class="nav-chevron">⌄</span></a>
        </nav>

        <div class="header-actions">
            <a href="#" class="login-link">Log in</a>
            <a href="{{ route('home') }}#analysis-form" class="trial-button">
                Start Free Trial
            </a>
        </div>
    </div>
</header>

<main class="competitors-page">
    <section class="competitors-hero">
        <div class="container competitors-container">
            <div class="step-badge">STEP 2 OF 3</div>

            @if (!empty($topCompetitors))
                <h1>
                    We’ve Found
                    <span id="competitor-count-heading">
                        {{ count($topCompetitors) }}
                    </span>
                    Competitors
                    <strong>You Might Be Up Against</strong>
                </h1>

                <p class="competitors-intro">
                    We scanned your market and found these businesses
                    <br class="step2-desktop-break">
                    competing for the same customers.
                </p>
            @else
                <h1>
                    We’re Preparing Your
                    <strong>Most Relevant Competitors</strong>
                </h1>

                <p class="competitors-intro">
                    Your business information has been received. We’ll use your
                    website, Google Business data and market signals to find
                    the most relevant competitors.
                </p>
            @endif

            <div class="step2-shell">
                <section class="step2-business-section">
                    <h2>Your Business Information</h2>

                    <div class="step2-business-grid">
                        <div class="step2-business-item">
                            <span class="step2-business-icon" aria-hidden="true">
                                <svg viewBox="0 0 24 24">
                                    <circle cx="12" cy="12" r="8"></circle>
                                    <path d="M4 12h16M12 4c2.3 2.2 3.5 4.9 3.5 8S14.3 17.8 12 20c-2.3-2.2-3.5-4.9-3.5-8S9.7 6.2 12 4Z"></path>
                                </svg>
                            </span>

                            <div class="step2-business-copy">
                                <span>Business Website</span>
                                <strong title="{{ $website }}">
                                    {{ $websiteDisplay }}
                                </strong>
                            </div>

                            <a
                                href="{{ route('home', ['edit' => 'website']) }}#analysis-form"
                                class="step2-change-button"
                            >
                                Change
                                <svg viewBox="0 0 24 24" aria-hidden="true">
                                    <path d="M4 20h4l11-11-4-4L4 16v4Z"></path>
                                    <path d="m13.5 6.5 4 4"></path>
                                </svg>
                            </a>
                        </div>

                        <div class="step2-business-item">
                            <span class="step2-business-icon" aria-hidden="true">
                                <svg viewBox="0 0 24 24">
                                    <path d="M12 21s6-5.1 6-11a6 6 0 1 0-12 0c0 5.9 6 11 6 11Z"></path>
                                    <circle cx="12" cy="10" r="2"></circle>
                                </svg>
                            </span>

                            <div class="step2-business-copy">
                                <span>Google Business Profile</span>
                                <strong title="{{ $googleBusiness }}">
                                    {{ $googleBusinessDisplay }}
                                </strong>
                            </div>

                            <a
                                href="{{ route('home', ['edit' => 'google_business']) }}#analysis-form"
                                class="step2-change-button"
                            >
                                Change
                                <svg viewBox="0 0 24 24" aria-hidden="true">
                                    <path d="M4 20h4l11-11-4-4L4 16v4Z"></path>
                                    <path d="m13.5 6.5 4 4"></path>
                                </svg>
                            </a>
                        </div>
                    </div>

                    <div class="step2-info-note">
                        <span aria-hidden="true">i</span>
                        <p>
                            @if (!empty($websiteScanWarning))
                                {{ $websiteScanWarning }}
                            @else
                                You can update your business information if anything is incorrect.
                            @endif
                        </p>
                    </div>
                </section>

                <section class="step2-review-section">
                    <div class="step2-review-header">
                        <div>
                            <h2>Review and Customize Your Competitors</h2>
                            <p>
                                You can remove any or add others before starting your analysis.
                            </p>
                        </div>

                        <button
                            type="button"
                            class="step2-add-button js-add-competitor"
                        >
                            <span aria-hidden="true">＋</span>
                            Add Competitor
                        </button>
                    </div>

                    @if (!empty($topCompetitors))
                        <div class="step2-competitor-list" id="step2-competitor-list">
                            @foreach ($topCompetitors as $index => $competitor)
                                @php
                                    $name = trim(
                                        (string) data_get(
                                            $competitor,
                                            'displayName.text',
                                            'Competitor'
                                        )
                                    );

                                    if ($name === '') {
                                        $name = 'Competitor';
                                    }

                                    $primaryType = data_get(
                                        $competitor,
                                        'primaryType'
                                    );

                                    $category = data_get(
                                        $competitor,
                                        'primaryTypeDisplayName.text'
                                    );

                                    if (
                                        (!is_string($category) || trim($category) === '')
                                        && is_string($primaryType)
                                        && trim($primaryType) !== ''
                                    ) {
                                        $category = \Illuminate\Support\Str::headline(
                                            $primaryType
                                        );
                                    }

                                    if (!is_string($category) || trim($category) === '') {
                                        $category = 'Relevant business';
                                    }

                                    $rating = data_get(
                                        $competitor,
                                        'rating'
                                    );

                                    $reviewCount = data_get(
                                        $competitor,
                                        'userRatingCount'
                                    );

                                    $websiteUri = data_get(
                                        $competitor,
                                        'websiteUri'
                                    );

                                    $faviconUrl = null;

                                    if (
                                        is_string($websiteUri)
                                        && filter_var($websiteUri, FILTER_VALIDATE_URL)
                                    ) {
                                        $parts = parse_url($websiteUri);

                                        if (
                                            is_array($parts)
                                            && isset($parts['scheme'], $parts['host'])
                                        ) {
                                            $faviconUrl =
                                                $parts['scheme']
                                                . '://'
                                                . $parts['host']
                                                . '/favicon.ico';
                                        }
                                    }

                                    $distanceKm = data_get(
                                        $competitor,
                                        '_match.distance_km'
                                    );

                                    $distanceMiles = is_numeric($distanceKm)
                                        ? (float) $distanceKm * 0.621371
                                        : null;

                                    $initials = collect(
                                        preg_split('/\s+/', $name) ?: []
                                    )
                                        ->filter()
                                        ->take(2)
                                        ->map(
                                            static fn (string $word): string =>
                                                mb_strtoupper(
                                                    mb_substr($word, 0, 1)
                                                )
                                        )
                                        ->implode('');

                                    if ($initials === '') {
                                        $initials = 'C';
                                    }
                                @endphp

                                <article
                                    class="step2-competitor-row"
                                    data-competitor-row
                                >
                                    <div
                                        class="step2-rank"
                                        data-competitor-rank
                                    >
                                        {{ $index + 1 }}
                                    </div>

                                    <div
                                        class="step2-logo step2-logo-{{ ($index % 5) + 1 }}"
                                        aria-hidden="true"
                                    >
                                        @if ($faviconUrl)
                                            <img
                                                src="{{ $faviconUrl }}"
                                                alt=""
                                                loading="lazy"
                                                onerror="this.style.display='none'; this.nextElementSibling.style.display='grid';"
                                            >
                                            <span class="step2-logo-fallback">
                                                {{ $initials }}
                                            </span>
                                        @else
                                            <span class="step2-logo-fallback is-visible">
                                                {{ $initials }}
                                            </span>
                                        @endif
                                    </div>

                                    <div class="step2-competitor-main">
                                        <h3>{{ $name }}</h3>

                                        <div class="step2-meta">
                                            <span>{{ $category }}</span>
                                        </div>

                                        @if (!$isBroaderMarket)
                                            <div class="step2-distance">
                                                <svg viewBox="0 0 24 24" aria-hidden="true">
                                                    <path d="M12 21s6-5.1 6-11a6 6 0 1 0-12 0c0 5.9 6 11 6 11Z"></path>
                                                    <circle cx="12" cy="10" r="2"></circle>
                                                </svg>

                                                @if ($distanceMiles !== null)
                                                    <span>
                                                        {{ number_format($distanceMiles, 1) }}
                                                        miles away
                                                    </span>
                                                @else
                                                    <span>Location not available</span>
                                                @endif
                                            </div>
                                        @endif
                                    </div>

                                    <div class="step2-rating">
                                        @if (is_numeric($rating))
                                            <div class="step2-rating-line">
                                                <strong>
                                                    {{ number_format((float) $rating, 1) }}
                                                </strong>

                                                <span
                                                    class="step2-stars"
                                                    style="--rating: {{ min(5, max(0, (float) $rating)) }}"
                                                    aria-label="{{ number_format((float) $rating, 1) }} out of 5 stars"
                                                >
                                                    ★★★★★
                                                </span>
                                            </div>
                                        @else
                                            <div class="step2-rating-line">
                                                <strong>—</strong>
                                                <span class="step2-stars step2-stars-empty">
                                                    ★★★★★
                                                </span>
                                            </div>
                                        @endif

                                        <span class="step2-review-count">
                                            @if (is_numeric($reviewCount))
                                                ({{ number_format((int) $reviewCount) }} reviews)
                                            @else
                                                (reviews unavailable)
                                            @endif
                                        </span>
                                    </div>

                                    <button
                                        type="button"
                                        class="step2-remove-button"
                                        data-remove-competitor
                                    >
                                        Remove
                                    </button>
                                </article>
                            @endforeach
                        </div>

                        <button
                            type="button"
                            class="step2-add-another js-add-competitor"
                        >
                            <span class="step2-add-another-icon" aria-hidden="true">＋</span>
                            <span class="step2-add-another-copy">
                                <strong>Add Another Competitor</strong>
                                <small>Search by business name or website</small>
                            </span>
                            <span class="step2-add-another-arrow" aria-hidden="true">›</span>
                        </button>

                        <button
                            type="button"
                            class="step2-start-button"
                        >
                            <span>Start Free Analysis</span>
                            <span aria-hidden="true">→</span>
                        </button>

                        <div class="step2-trust">
                            <span>
                                <svg viewBox="0 0 24 24" aria-hidden="true">
                                    <rect x="5" y="10" width="14" height="10" rx="2"></rect>
                                    <path d="M8 10V7a4 4 0 0 1 8 0v3"></path>
                                </svg>
                                No credit card required
                            </span>

                            <span>
                                <svg viewBox="0 0 24 24" aria-hidden="true">
                                    <path d="m13 2-8 12h7l-1 8 8-12h-7l1-8Z"></path>
                                </svg>
                                Get results in 30 seconds
                            </span>
                        </div>
                    @else
                        <div class="step2-empty">
                            <strong>Competitor results are not available yet.</strong>
                            <p>
                                Once enough business signals are available,
                                the most relevant competitor matches will appear here.
                            </p>
                        </div>
                    @endif
                </section>
            </div>
        </div>
    </section>
</main>

<footer class="site-footer">
    <div class="container footer-grid">
        <div class="footer-brand-column">
            <a href="{{ route('home') }}" class="brand footer-brand">
                <span class="intellytics-mark" aria-hidden="true">
                    <i></i><i></i><i></i><i></i>
                </span>
                <span class="brand-name">Intellytics</span>
            </a>

            <p>
                AI-powered market intelligence that helps you see what others miss
                and act with confidence.
            </p>

            <div class="social-links">
                <a href="#" aria-label="LinkedIn">in</a>
                <a href="#" aria-label="X">𝕏</a>
                <a href="#" aria-label="YouTube">▶</a>
                <a href="#" aria-label="Facebook">f</a>
            </div>
        </div>

        <div class="footer-column">
            <h3>Platform</h3>
            <a href="#">Features</a>
            <a href="#">How It Works</a>
            <a href="#">Integrations</a>
            <a href="#">AI Briefings</a>
            <a href="#">Status</a>
        </div>

        <div class="footer-column">
            <h3>Solutions</h3>
            <a href="#">For Marketing Teams</a>
            <a href="#">For Agencies</a>
            <a href="#">For Enterprises</a>
            <a href="#">By Industry</a>
        </div>

        <div class="footer-column">
            <h3>Resources</h3>
            <a href="#">Blog</a>
            <a href="#">Case Studies</a>
            <a href="#">Guides &amp; Templates</a>
            <a href="#">Help Center</a>
            <a href="#">Webinars</a>
        </div>

        <div class="footer-column">
            <h3>Company</h3>
            <a href="#">About Us</a>
            <a href="#">Careers</a>
            <a href="#">Partners</a>
            <a href="#">Contact Us</a>
        </div>
    </div>

    <div class="container footer-bottom">
        <span>© 2025 Intellytics Inc. All rights reserved.</span>
        <div>
            <a href="#">Privacy Policy</a>
            <a href="#">Terms of Service</a>
        </div>
    </div>
</footer>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const list = document.getElementById('step2-competitor-list');
    const countHeading = document.getElementById('competitor-count-heading');

    const renumberRows = () => {
        if (!list) {
            return;
        }

        const rows = Array.from(
            list.querySelectorAll('[data-competitor-row]')
        );

        rows.forEach((row, index) => {
            const rank = row.querySelector('[data-competitor-rank]');

            if (rank) {
                rank.textContent = String(index + 1);
            }
        });

        if (countHeading) {
            countHeading.textContent = String(rows.length);
        }
    };

    document.querySelectorAll('[data-remove-competitor]')
        .forEach(button => {
            button.addEventListener('click', () => {
                const row = button.closest('[data-competitor-row]');

                if (!row) {
                    return;
                }

                row.remove();
                renumberRows();
            });
        });

    document.querySelectorAll('.js-add-competitor')
        .forEach(button => {
            button.addEventListener('click', () => {
                const addAnother = document.querySelector('.step2-add-another');

                if (addAnother) {
                    addAnother.classList.remove('is-pulsing');
                    void addAnother.offsetWidth;
                    addAnother.classList.add('is-pulsing');
                    addAnother.focus();
                }
            });
        });
});
</script>

@endsection