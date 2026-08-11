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
    $hasAnalysis = is_array($analysisResult);
    $isPreview = (bool) ($isPreview ?? false);
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

            @if ($hasAnalysis)
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

            @error('competitors')
                <div class="step2-page-error" role="alert">
                    {{ $message }}
                </div>
            @enderror

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
                            @disabled($isPreview)
                        >
                            <span aria-hidden="true">＋</span>
                            Add Competitor
                        </button>
                    </div>

                    @if ($hasAnalysis)
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

                                    $placeId = trim(
                                        (string) data_get(
                                            $competitor,
                                            'id',
                                            ''
                                        )
                                    );

                                    $primaryType = data_get(
                                        $competitor,
                                        'primaryType'
                                    );

                                    $category = data_get(
                                        $competitor,
                                        'primaryTypeDisplayName.text'
                                    );

                                    /* Competitor category display refinement.
                                     * Google may use a broad primary label such as
                                     * "Manufacturer" even when the same Place also
                                     * exposes supplier/distribution signals. We only
                                     * refine broad labels when the selected business is
                                     * being matched as a distributor/supplier and the
                                     * candidate itself supports that role.
                                     */
                                    $targetBusinessType = data_get(
                                        $analysisResult,
                                        'search_profile.business_type',
                                        ''
                                    );

                                    $targetVertical = data_get(
                                        $analysisResult,
                                        'search_profile.vertical',
                                        ''
                                    );

                                    $targetQueries = data_get(
                                        $analysisResult,
                                        'search_profile.search_queries',
                                        []
                                    );

                                    $targetRoleParts = [
                                        is_string($targetBusinessType)
                                            ? $targetBusinessType
                                            : '',
                                    ];

                                    if (is_array($targetQueries)) {
                                        foreach ($targetQueries as $targetQuery) {
                                            if (is_string($targetQuery)) {
                                                $targetRoleParts[] = $targetQuery;
                                            }
                                        }
                                    }

                                    $targetRoleText = mb_strtolower(
                                        implode(' ', $targetRoleParts)
                                    );

                                    $targetsDistributorRole = preg_match(
                                        '/\b(distributor|distribution|supplier|wholesaler|wholesale)\b/u',
                                        $targetRoleText
                                    ) === 1;

                                    $candidateRoleParts = [
                                        $name,
                                        is_string($primaryType) ? $primaryType : '',
                                        is_string($category) ? $category : '',
                                    ];

                                    $candidateTypes = data_get(
                                        $competitor,
                                        'types',
                                        []
                                    );

                                    if (is_array($candidateTypes)) {
                                        foreach ($candidateTypes as $candidateType) {
                                            if (is_string($candidateType)) {
                                                $candidateRoleParts[] = $candidateType;
                                            }
                                        }
                                    }

                                    $candidateRoleText = mb_strtolower(
                                        implode(' ', $candidateRoleParts)
                                    );

                                    $candidateHasDistributorRole = preg_match(
                                        '/\b(distributor|distribution|supplier|wholesaler|wholesale|supply|sales)\b/u',
                                        $candidateRoleText
                                    ) === 1;

                                    $normalizedGoogleCategory = is_string($category)
                                        ? mb_strtolower(trim($category))
                                        : '';

                                    $broadGoogleCategories = [
                                        '',
                                        'manufacturer',
                                        'supplier',
                                        'service',
                                        'point of interest',
                                        'establishment',
                                    ];

                                    if (
                                        $targetsDistributorRole
                                        && $candidateHasDistributorRole
                                        && in_array(
                                            $normalizedGoogleCategory,
                                            $broadGoogleCategories,
                                            true
                                        )
                                    ) {
                                        $matchedQueries = data_get(
                                            $competitor,
                                            '_match.queries',
                                            []
                                        );

                                        if (is_array($matchedQueries)) {
                                            foreach ($matchedQueries as $matchedQuery) {
                                                if (!is_string($matchedQuery)) {
                                                    continue;
                                                }

                                                $displayIntent = mb_strtolower(
                                                    trim($matchedQuery)
                                                );

                                                if (
                                                    preg_match(
                                                        '/\b(distributor|distribution|supplier|wholesaler|wholesale|integrator|integration)\b/u',
                                                        $displayIntent
                                                    ) !== 1
                                                ) {
                                                    continue;
                                                }

                                                $displayIntent = preg_replace(
                                                    '/\bsupplier\s+and\s+integration\b/u',
                                                    'supplier & systems integrator',
                                                    $displayIntent
                                                ) ?? $displayIntent;

                                                $displayIntent = preg_replace(
                                                    '/\s+and\s+/u',
                                                    ' & ',
                                                    $displayIntent
                                                ) ?? $displayIntent;

                                                $category = mb_convert_case(
                                                    $displayIntent,
                                                    MB_CASE_TITLE,
                                                    'UTF-8'
                                                );

                                                break;
                                            }
                                        }

                                        if (
                                            !is_string($category)
                                            || trim($category) === ''
                                            || in_array(
                                                mb_strtolower(trim($category)),
                                                $broadGoogleCategories,
                                                true
                                            )
                                        ) {
                                            $category = $targetVertical === 'industrial'
                                                ? 'Industrial Supplier'
                                                : 'Supplier / Distributor';
                                        }
                                    }

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
                                    data-place-id="{{ $placeId }}"
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

                        <div
                            class="step2-empty-selection"
                            id="step2-empty-selection"
                            @if (!empty($topCompetitors)) hidden @endif
                        >
                            <strong>No competitors selected yet.</strong>
                            <span>Use “Add Competitor” to choose at least one business.</span>
                        </div>

                        <button
                            type="button"
                            class="step2-add-another js-add-competitor"
                            @disabled($isPreview)
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
                            id="step2-start-button"
                            data-step3-url="{{ route('analysis.email') }}"
                            @disabled(empty($topCompetitors) || $isPreview)
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

<div
    class="step2-modal-backdrop"
    id="add-competitor-modal"
    hidden
>
    <section
        class="step2-modal"
        role="dialog"
        aria-modal="true"
        aria-labelledby="add-competitor-title"
    >
        <button
            type="button"
            class="step2-modal-close"
            id="add-competitor-close"
            aria-label="Close"
        >
            ×
        </button>

        <div class="step2-modal-kicker">CUSTOMIZE YOUR LIST</div>
        <h2 id="add-competitor-title">Add a Competitor</h2>
        <p>
            Search Google by business name or website, then choose the correct business.
        </p>

        <div class="step2-modal-search">
            <input
                id="add-competitor-query"
                type="text"
                placeholder="Business name or website"
                autocomplete="off"
                spellcheck="false"
            >
            <span
                class="step2-modal-loader"
                id="add-competitor-loader"
                aria-hidden="true"
            ></span>
        </div>

        <div
            class="step2-modal-status"
            id="add-competitor-status"
            aria-live="polite"
        ></div>

        <div
            class="step2-modal-results"
            id="add-competitor-results"
            role="listbox"
            hidden
        ></div>

        <div class="step2-google-attribution">Google Maps</div>
    </section>
</div>

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


<style>
    .step2-page-error {
        max-width: 980px;
        margin: 18px auto 0;
        padding: 13px 16px;
        border: 1px solid rgba(180, 35, 24, 0.18);
        border-radius: 12px;
        background: #fff5f4;
        color: #9d241b;
        font-size: 14px;
        font-weight: 600;
    }

    .step2-empty-selection {
        display: grid;
        gap: 4px;
        margin-top: 14px;
        padding: 22px;
        border: 1px dashed rgba(93, 80, 120, 0.24);
        border-radius: 16px;
        text-align: center;
        color: #6f687d;
        background: rgba(250, 249, 252, 0.72);
    }

    .step2-empty-selection[hidden] {
        display: none;
    }

    .step2-empty-selection strong {
        color: #292236;
        font-size: 15px;
    }

    .step2-add-button:disabled,
    .step2-add-another:disabled,
    .step2-start-button:disabled {
        opacity: 0.5;
        cursor: not-allowed;
        transform: none !important;
    }

    .step2-modal-backdrop {
        position: fixed;
        inset: 0;
        z-index: 1000;
        display: grid;
        place-items: center;
        padding: 24px;
        background: rgba(26, 20, 38, 0.48);
        backdrop-filter: blur(5px);
    }

    .step2-modal-backdrop[hidden] {
        display: none;
    }

    .step2-modal {
        position: relative;
        width: min(560px, 100%);
        max-height: min(720px, calc(100vh - 48px));
        overflow: auto;
        padding: 32px;
        border: 1px solid rgba(87, 72, 111, 0.12);
        border-radius: 24px;
        background: #ffffff;
        box-shadow: 0 28px 80px rgba(30, 21, 48, 0.24);
    }

    .step2-modal-close {
        position: absolute;
        top: 18px;
        right: 18px;
        display: grid;
        width: 36px;
        height: 36px;
        place-items: center;
        border: 0;
        border-radius: 50%;
        background: #f4f1f7;
        color: #51495e;
        font-size: 24px;
        line-height: 1;
        cursor: pointer;
    }

    .step2-modal-kicker {
        margin-bottom: 8px;
        color: #7d5ac7;
        font-size: 11px;
        font-weight: 800;
        letter-spacing: 0.14em;
    }

    .step2-modal h2 {
        margin: 0;
        color: #211a2c;
        font-size: 28px;
        line-height: 1.15;
    }

    .step2-modal > p {
        margin: 10px 44px 22px 0;
        color: #716a7e;
        font-size: 14px;
        line-height: 1.55;
    }

    .step2-modal-search {
        position: relative;
    }

    .step2-modal-search input {
        width: 100%;
        min-height: 52px;
        padding: 0 48px 0 16px;
        border: 1px solid rgba(72, 58, 96, 0.18);
        border-radius: 13px;
        outline: none;
        background: #fff;
        color: #241d30;
        font: inherit;
    }

    .step2-modal-search input:focus {
        border-color: rgba(125, 90, 199, 0.64);
        box-shadow: 0 0 0 4px rgba(125, 90, 199, 0.09);
    }

    .step2-modal-loader {
        position: absolute;
        top: 50%;
        right: 17px;
        width: 18px;
        height: 18px;
        margin-top: -9px;
        border: 2px solid rgba(125, 90, 199, 0.18);
        border-top-color: #7d5ac7;
        border-radius: 50%;
        opacity: 0;
        animation: step2-spin 0.75s linear infinite;
    }

    .step2-modal-loader.is-visible {
        opacity: 1;
    }

    @keyframes step2-spin {
        to {
            transform: rotate(360deg);
        }
    }

    .step2-modal-status {
        min-height: 20px;
        margin: 8px 2px 4px;
        color: #716a7e;
        font-size: 12px;
    }

    .step2-modal-status.is-error {
        color: #b42318;
    }

    .step2-modal-results {
        overflow: hidden;
        margin-top: 8px;
        border: 1px solid rgba(72, 58, 96, 0.12);
        border-radius: 14px;
        background: #fff;
    }

    .step2-modal-results[hidden] {
        display: none;
    }

    .step2-modal-result {
        display: block;
        width: 100%;
        padding: 13px 15px;
        border: 0;
        border-bottom: 1px solid rgba(72, 58, 96, 0.08);
        background: #fff;
        text-align: left;
        cursor: pointer;
    }

    .step2-modal-result:last-child {
        border-bottom: 0;
    }

    .step2-modal-result:hover,
    .step2-modal-result:focus {
        background: #f8f6fb;
        outline: none;
    }

    .step2-modal-result strong,
    .step2-modal-result span {
        display: block;
    }

    .step2-modal-result strong {
        color: #292236;
        font-size: 14px;
    }

    .step2-modal-result span {
        margin-top: 3px;
        color: #777286;
        font-size: 12px;
        line-height: 1.4;
    }

    .step2-google-attribution {
        margin-top: 10px;
        text-align: right;
        color: #68616f;
        font-family: Roboto, Arial, sans-serif;
        font-size: 11px;
    }

    body.step2-modal-open {
        overflow: hidden;
    }

    @media (max-width: 640px) {
        .step2-modal-backdrop {
            padding: 14px;
            align-items: end;
        }

        .step2-modal {
            width: 100%;
            max-height: 86vh;
            padding: 26px 20px 22px;
            border-radius: 22px 22px 12px 12px;
        }

        .step2-modal h2 {
            font-size: 24px;
        }
    }
</style>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const list = document.getElementById('step2-competitor-list');
    const countHeading = document.getElementById('competitor-count-heading');
    const emptySelection = document.getElementById('step2-empty-selection');
    const startButton = document.getElementById('step2-start-button');

    const modal = document.getElementById('add-competitor-modal');
    const modalClose = document.getElementById('add-competitor-close');
    const queryInput = document.getElementById('add-competitor-query');
    const resultsBox = document.getElementById('add-competitor-results');
    const statusBox = document.getElementById('add-competitor-status');
    const loader = document.getElementById('add-competitor-loader');

    const searchEndpoint = @json(route('competitors.search'));
    const addEndpoint = @json(route('competitors.add'));
    const removeEndpoint = @json(route('competitors.remove'));
    const csrfToken = @json(csrf_token());

    let debounceTimer = null;
    let requestController = null;
    let addingPlaceId = null;

    const rows = () => {
        if (!list) {
            return [];
        }

        return Array.from(
            list.querySelectorAll('[data-competitor-row]')
        );
    };

    const syncState = () => {
        const competitorRows = rows();

        competitorRows.forEach((row, index) => {
            const rank = row.querySelector('[data-competitor-rank]');

            if (rank) {
                rank.textContent = String(index + 1);
            }

            const logo = row.querySelector('.step2-logo');

            if (logo) {
                for (let colorIndex = 1; colorIndex <= 5; colorIndex += 1) {
                    logo.classList.remove(
                        'step2-logo-' + colorIndex
                    );
                }

                logo.classList.add(
                    'step2-logo-' + ((index % 5) + 1)
                );
            }
        });

        if (countHeading) {
            countHeading.textContent = String(
                competitorRows.length
            );
        }

        if (emptySelection) {
            emptySelection.hidden =
                competitorRows.length !== 0;
        }

        if (startButton) {
            startButton.disabled =
                competitorRows.length === 0;
        }
    };

    const setModalStatus = (
        message = '',
        isError = false
    ) => {
        if (!statusBox) {
            return;
        }

        statusBox.textContent = message;
        statusBox.classList.toggle(
            'is-error',
            isError
        );
    };

    const showLoader = (show) => {
        if (loader) {
            loader.classList.toggle(
                'is-visible',
                show
            );
        }
    };

    const clearResults = () => {
        if (!resultsBox) {
            return;
        }

        resultsBox.replaceChildren();
        resultsBox.hidden = true;
    };

    const openModal = () => {
        if (!modal || !queryInput) {
            return;
        }

        clearResults();
        setModalStatus('');
        queryInput.value = '';
        modal.hidden = false;
        document.body.classList.add(
            'step2-modal-open'
        );

        window.setTimeout(
            () => queryInput.focus(),
            20
        );
    };

    const closeModal = () => {
        if (!modal) {
            return;
        }

        if (requestController) {
            requestController.abort();
            requestController = null;
        }

        clearTimeout(debounceTimer);
        showLoader(false);
        clearResults();
        setModalStatus('');
        modal.hidden = true;
        document.body.classList.remove(
            'step2-modal-open'
        );
    };

    const initialsFromName = (name) => {
        const words = String(name)
            .trim()
            .split(/\s+/)
            .filter(Boolean)
            .slice(0, 2);

        const initials = words
            .map(word => word.charAt(0).toUpperCase())
            .join('');

        return initials || 'C';
    };

    const createCompetitorRow = (competitor) => {
        const row = document.createElement('article');
        row.className = 'step2-competitor-row';
        row.dataset.competitorRow = '';
        row.dataset.placeId = competitor.place_id || '';

        const rank = document.createElement('div');
        rank.className = 'step2-rank';
        rank.dataset.competitorRank = '';
        row.appendChild(rank);

        const logo = document.createElement('div');
        logo.className = 'step2-logo';
        logo.setAttribute('aria-hidden', 'true');

        const fallback = document.createElement('span');
        fallback.className = 'step2-logo-fallback is-visible';
        fallback.textContent =
            competitor.initials
            || initialsFromName(competitor.name);

        if (competitor.favicon_url) {
            const image = document.createElement('img');
            image.src = competitor.favicon_url;
            image.alt = '';
            image.loading = 'lazy';

            fallback.classList.remove('is-visible');
            image.addEventListener('error', () => {
                image.style.display = 'none';
                fallback.classList.add('is-visible');
            });

            logo.appendChild(image);
        }

        logo.appendChild(fallback);
        row.appendChild(logo);

        const main = document.createElement('div');
        main.className = 'step2-competitor-main';

        const heading = document.createElement('h3');
        heading.textContent =
            competitor.name || 'Competitor';
        main.appendChild(heading);

        const meta = document.createElement('div');
        meta.className = 'step2-meta';

        const category = document.createElement('span');
        category.textContent =
            competitor.category || 'Relevant business';
        meta.appendChild(category);
        main.appendChild(meta);

        if (competitor.show_distance) {
            const distance = document.createElement('div');
            distance.className = 'step2-distance';

            const icon = document.createElementNS(
                'http://www.w3.org/2000/svg',
                'svg'
            );
            icon.setAttribute('viewBox', '0 0 24 24');

            const path = document.createElementNS(
                'http://www.w3.org/2000/svg',
                'path'
            );
            path.setAttribute(
                'd',
                'M12 21s6-5.1 6-11a6 6 0 1 0-12 0c0 5.9 6 11 6 11Z'
            );

            const circle = document.createElementNS(
                'http://www.w3.org/2000/svg',
                'circle'
            );
            circle.setAttribute('cx', '12');
            circle.setAttribute('cy', '10');
            circle.setAttribute('r', '2');

            icon.append(path, circle);
            distance.appendChild(icon);

            const distanceText =
                document.createElement('span');

            distanceText.textContent =
                competitor.distance_miles !== null
                && competitor.distance_miles !== undefined
                    ? Number(
                        competitor.distance_miles
                    ).toFixed(1)
                        + ' miles away'
                    : 'Location not available';

            distance.appendChild(distanceText);
            main.appendChild(distance);
        }

        row.appendChild(main);

        const rating = document.createElement('div');
        rating.className = 'step2-rating';

        const ratingLine =
            document.createElement('div');
        ratingLine.className = 'step2-rating-line';

        const ratingValue =
            document.createElement('strong');

        const stars =
            document.createElement('span');
        stars.className = 'step2-stars';
        stars.textContent = '★★★★★';

        if (
            competitor.rating !== null
            && competitor.rating !== undefined
        ) {
            const numericRating = Number(
                competitor.rating
            );

            ratingValue.textContent =
                numericRating.toFixed(1);

            stars.style.setProperty(
                '--rating',
                String(
                    Math.min(
                        5,
                        Math.max(0, numericRating)
                    )
                )
            );

            stars.setAttribute(
                'aria-label',
                numericRating.toFixed(1)
                    + ' out of 5 stars'
            );
        } else {
            ratingValue.textContent = '—';
            stars.classList.add(
                'step2-stars-empty'
            );
        }

        ratingLine.append(
            ratingValue,
            stars
        );
        rating.appendChild(ratingLine);

        const reviewCount =
            document.createElement('span');
        reviewCount.className =
            'step2-review-count';

        reviewCount.textContent =
            competitor.review_count !== null
            && competitor.review_count !== undefined
                ? '('
                    + Number(
                        competitor.review_count
                    ).toLocaleString()
                    + ' reviews)'
                : '(reviews unavailable)';

        rating.appendChild(reviewCount);
        row.appendChild(rating);

        const remove =
            document.createElement('button');
        remove.type = 'button';
        remove.className =
            'step2-remove-button';
        remove.dataset.removeCompetitor = '';
        remove.textContent = 'Remove';
        row.appendChild(remove);

        return row;
    };

    const renderSearchResults = (suggestions) => {
        if (!resultsBox) {
            return;
        }

        resultsBox.replaceChildren();

        if (
            !Array.isArray(suggestions)
            || suggestions.length === 0
        ) {
            resultsBox.hidden = true;
            setModalStatus(
                'No new matching businesses found.'
            );
            return;
        }

        suggestions.forEach(suggestion => {
            const button =
                document.createElement('button');

            button.type = 'button';
            button.className =
                'step2-modal-result';
            button.setAttribute(
                'role',
                'option'
            );

            const name =
                document.createElement('strong');
            name.textContent =
                suggestion.name || 'Business';
            button.appendChild(name);

            const secondaryParts = [
                suggestion.category || '',
                suggestion.secondary_text || '',
            ].filter(Boolean);

            if (secondaryParts.length > 0) {
                const secondary =
                    document.createElement('span');

                secondary.textContent =
                    secondaryParts.join(' · ');

                button.appendChild(secondary);
            }

            button.addEventListener(
                'click',
                () => addCompetitor(
                    suggestion.place_id
                )
            );

            resultsBox.appendChild(button);
        });

        resultsBox.hidden = false;
        setModalStatus(
            'Select the correct business from Google.'
        );
    };

    const searchCompetitors = async (query) => {
        if (requestController) {
            requestController.abort();
        }

        const controller =
            new AbortController();
        requestController = controller;

        showLoader(true);

        try {
            const parameters =
                new URLSearchParams({
                    q: query,
                });

            const response = await fetch(
                searchEndpoint
                    + '?'
                    + parameters.toString(),
                {
                    method: 'GET',
                    headers: {
                        Accept: 'application/json',
                    },
                    signal: controller.signal,
                }
            );

            const data = await response
                .json()
                .catch(() => ({}));

            if (!response.ok) {
                throw new Error(
                    data.message
                    || 'Competitor search is temporarily unavailable.'
                );
            }

            renderSearchResults(
                data.suggestions || []
            );
        } catch (error) {
            if (error.name === 'AbortError') {
                return;
            }

            clearResults();
            setModalStatus(
                error.message
                || 'Competitor search is temporarily unavailable.',
                true
            );
        } finally {
            if (requestController === controller) {
                requestController = null;
                showLoader(false);
            }
        }
    };

    const addCompetitor = async (placeId) => {
        if (
            !placeId
            || addingPlaceId !== null
        ) {
            return;
        }

        addingPlaceId = placeId;
        setModalStatus('Adding competitor…');
        showLoader(true);

        try {
            const response = await fetch(
                addEndpoint,
                {
                    method: 'POST',
                    headers: {
                        Accept: 'application/json',
                        'Content-Type':
                            'application/json',
                        'X-CSRF-TOKEN':
                            csrfToken,
                    },
                    body: JSON.stringify({
                        place_id: placeId,
                    }),
                }
            );

            const data = await response
                .json()
                .catch(() => ({}));

            if (!response.ok) {
                throw new Error(
                    data.message
                    || 'Could not add that competitor.'
                );
            }

            if (
                list
                && data.competitor
            ) {
                list.appendChild(
                    createCompetitorRow(
                        data.competitor
                    )
                );
            }

            syncState();
            closeModal();
        } catch (error) {
            setModalStatus(
                error.message
                || 'Could not add that competitor.',
                true
            );
        } finally {
            addingPlaceId = null;
            showLoader(false);
        }
    };

    if (list) {
        list.addEventListener(
            'click',
            async event => {
                const button =
                    event.target.closest(
                        '[data-remove-competitor]'
                    );

                if (!button) {
                    return;
                }

                const row = button.closest(
                    '[data-competitor-row]'
                );

                const placeId =
                    row?.dataset.placeId || '';

                if (!row || placeId === '') {
                    return;
                }

                button.disabled = true;

                try {
                    const response = await fetch(
                        removeEndpoint,
                        {
                            method: 'DELETE',
                            headers: {
                                Accept:
                                    'application/json',
                                'Content-Type':
                                    'application/json',
                                'X-CSRF-TOKEN':
                                    csrfToken,
                            },
                            body: JSON.stringify({
                                place_id: placeId,
                            }),
                        }
                    );

                    const data = await response
                        .json()
                        .catch(() => ({}));

                    if (!response.ok) {
                        throw new Error(
                            data.message
                            || 'Could not remove that competitor.'
                        );
                    }

                    row.remove();
                    syncState();
                } catch (error) {
                    button.disabled = false;
                    window.alert(
                        error.message
                        || 'Could not remove that competitor.'
                    );
                }
            }
        );
    }

    document.querySelectorAll(
        '.js-add-competitor'
    ).forEach(button => {
        button.addEventListener(
            'click',
            () => {
                if (!button.disabled) {
                    openModal();
                }
            }
        );
    });

    if (modalClose) {
        modalClose.addEventListener(
            'click',
            closeModal
        );
    }

    if (modal) {
        modal.addEventListener(
            'click',
            event => {
                if (event.target === modal) {
                    closeModal();
                }
            }
        );
    }

    document.addEventListener(
        'keydown',
        event => {
            if (
                event.key === 'Escape'
                && modal
                && !modal.hidden
            ) {
                closeModal();
            }
        }
    );

    if (queryInput) {
        queryInput.addEventListener(
            'input',
            () => {
                const query =
                    queryInput.value.trim();

                clearTimeout(
                    debounceTimer
                );

                if (query.length < 3) {
                    if (requestController) {
                        requestController.abort();
                        requestController = null;
                    }

                    clearResults();
                    showLoader(false);
                    setModalStatus(
                        query.length === 0
                            ? ''
                            : 'Type at least 3 characters.'
                    );
                    return;
                }

                debounceTimer =
                    window.setTimeout(
                        () => searchCompetitors(
                            query
                        ),
                        320
                    );
            }
        );
    }

    if (startButton) {
        startButton.addEventListener(
            'click',
            () => {
                if (startButton.disabled) {
                    return;
                }

                const url =
                    startButton.dataset.step3Url;

                if (url) {
                    window.location.href = url;
                }
            }
        );
    }

    syncState();
});
</script>

@endsection

