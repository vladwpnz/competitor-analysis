@extends('layouts.app')

@section('title', 'Review Your Competitors')

@section('content')

<header class="site-header">
    <div class="container header-inner">

        <a href="{{ route('home') }}" class="brand">
            <span class="brand-mark">C</span>
            <span class="brand-name">Competitor Analysis</span>
        </a>

        <nav class="main-nav">
            <a href="#">Platform</a>
            <a href="#">Solutions</a>
            <a href="#">Resources</a>
            <a href="#">Pricing</a>
            <a href="#">Company</a>
        </nav>

        <div class="header-actions">
            <a href="#" class="login-link">
                Log in
            </a>

            <a href="{{ route('home') }}#analysis-form" class="trial-button">
                Start Free Analysis
            </a>
        </div>

    </div>
</header>

<main class="competitors-page">

    <section class="competitors-hero">

        <div class="container competitors-container">

            <div class="step-badge">
                STEP 2 OF 3
            </div>

            @if (!empty($topCompetitors))
                <h1>
                    We’ve Found {{ count($topCompetitors) }} Competitors
                    <span>You Might Be Up Against</span>
                </h1>

                <p class="competitors-intro">
                    These businesses were ranked using your website signals,
                    Google Business data, market relevance and location.
                </p>
            @else
                <h1>
                    We’re Preparing Your
                    <span>Most Relevant Competitors</span>
                </h1>

                <p class="competitors-intro">
                    Your business information has been received. Competitor matching
                    will use your market, services and location to rank the most
                    relevant businesses.
                </p>
            @endif

            <section class="business-info-card">

                <div class="business-info-heading">
                    <div>
                        <span class="section-kicker">
                            YOUR BUSINESS
                        </span>

                        <h2>
                            Your Business Information
                        </h2>
                    </div>
                </div>

                <div class="business-info-grid">

                    <div class="business-info-item">

                        <div class="business-info-icon">
                            ↗
                        </div>

                        <div>
                            <span class="info-label">
                                Business Website
                            </span>

                            <strong>
                                {{ $website }}
                            </strong>
                        </div>

                        <a href="{{ route('home') }}#analysis-form">
                            Change
                        </a>

                    </div>

                    <div class="business-info-item">

                        <div class="business-info-icon">
                            ⌖
                        </div>

                        <div>
                            <span class="info-label">
                                Google Business Profile
                            </span>

                            <strong>
                                {{ $googleBusiness }}
                            </strong>
                        </div>

                        <a href="{{ route('home') }}#analysis-form">
                            Change
                        </a>

                    </div>

                </div>

                <div class="business-info-note">
                    <span>i</span>

                    You can update your business information if anything is
                    incorrect.
                </div>

                <div class="website-signals">

                    <div class="website-signals-heading">
                        <span class="section-kicker">
                            WEBSITE SCAN
                        </span>

                        <h3>
                            Detected Website Signals
                        </h3>
                    </div>

                    <div class="website-signals-grid">

                        <div class="website-signal">
                            <span>
                                Page Title
                            </span>

                            <strong>
                                {{ $websiteScan['title'] ?? 'Not detected' }}
                            </strong>
                        </div>

                        <div class="website-signal">
                            <span>
                                Meta Description
                            </span>

                            <strong>
                                {{ $websiteScan['meta_description'] ?? 'Not detected' }}
                            </strong>
                        </div>

                        <div class="website-signal website-signal-wide">
                            <span>
                                Main Heading
                            </span>

                            <strong>
                                {{ $websiteScan['h1'][0] ?? 'Not detected' }}
                            </strong>
                        </div>

                    </div>

                </div>

            </section>

            <section class="competitor-review-card">

                <div class="competitor-review-header">

                    <div>
                        <span class="section-kicker">
                            COMPETITOR MATCHING
                        </span>

                        <h2>
                            Review Your Most Relevant Competitors
                        </h2>

                        <p>
                            Results are ranked by business similarity first, with
                            geography used according to the type of market you operate in.
                        </p>
                    </div>

                    <button
                        type="button"
                        class="secondary-button"
                        disabled
                        title="Manual competitor editing will be added in a later step"
                    >
                        + Add Competitor
                    </button>

                </div>

                @if (!empty($topCompetitors))

                    <div class="competitor-summary">
                        <span class="competitor-summary-pill">
                            {{ count($topCompetitors) }} ranked matches
                        </span>

                        <span class="competitor-summary-pill">
                            Relevance-first matching
                        </span>

                        @if (!empty($analysisResult['search_stage_count']))
                            <span class="competitor-summary-pill">
                                {{ $analysisResult['search_stage_count'] }}
                                search {{ $analysisResult['search_stage_count'] === 1 ? 'stage' : 'stages' }}
                            </span>
                        @endif
                    </div>

                    <div class="competitor-list">

                        @foreach ($topCompetitors as $index => $competitor)
                            @php
                                $name = data_get(
                                    $competitor,
                                    'displayName.text',
                                    'Competitor'
                                );

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

                                $score = data_get(
                                    $competitor,
                                    '_relevance.score'
                                );

                                $score = is_numeric($score)
                                    ? (int) round((float) $score)
                                    : null;

                                $strongMatch = (bool) data_get(
                                    $competitor,
                                    '_relevance.strong_match',
                                    false
                                );

                                $rating = data_get(
                                    $competitor,
                                    'rating'
                                );

                                $reviewCount = data_get(
                                    $competitor,
                                    'userRatingCount'
                                );

                                $distance = data_get(
                                    $competitor,
                                    '_match.distance_km'
                                );

                                $address = data_get(
                                    $competitor,
                                    'formattedAddress'
                                );

                                $websiteUrl = data_get(
                                    $competitor,
                                    'websiteUri'
                                );

                                $mapsUrl = data_get(
                                    $competitor,
                                    'googleMapsUri'
                                );

                                $queryHits = data_get(
                                    $competitor,
                                    '_match.query_hits',
                                    0
                                );
                            @endphp

                            <article class="competitor-card">

                                <div class="competitor-card-top">

                                    <div class="competitor-rank">
                                        #{{ $index + 1 }}
                                    </div>

                                    <div>
                                        <h3 class="competitor-name">
                                            {{ $name }}
                                        </h3>

                                        <p class="competitor-category">
                                            {{ $category }}
                                        </p>
                                    </div>

                                    <div class="match-score">
                                        <strong>
                                            {{ $score !== null ? $score . '%' : '—' }}
                                        </strong>

                                        <span>
                                            Match
                                        </span>
                                    </div>

                                </div>

                                <div class="competitor-stats">

                                    <div class="competitor-stat">
                                        <span>
                                            Google Rating
                                        </span>

                                        <strong>
                                            @if (is_numeric($rating))
                                                ★ {{ number_format((float) $rating, 1) }}
                                            @else
                                                Not available
                                            @endif
                                        </strong>
                                    </div>

                                    <div class="competitor-stat">
                                        <span>
                                            Reviews
                                        </span>

                                        <strong>
                                            @if (is_numeric($reviewCount))
                                                {{ number_format((int) $reviewCount) }}
                                            @else
                                                Not available
                                            @endif
                                        </strong>
                                    </div>

                                    <div class="competitor-stat">
                                        <span>
                                            Distance
                                        </span>

                                        <strong>
                                            @if (is_numeric($distance))
                                                {{ number_format((float) $distance, 1) }} km
                                            @else
                                                Not location-based
                                            @endif
                                        </strong>
                                    </div>

                                </div>

                                @if (is_string($address) && trim($address) !== '')
                                    <div class="competitor-address">
                                        <span class="competitor-address-icon">
                                            ⌖
                                        </span>

                                        <span>
                                            {{ $address }}
                                        </span>
                                    </div>
                                @endif

                                <div class="competitor-card-footer">

                                    <div class="competitor-evidence">

                                        @if ($strongMatch)
                                            <span class="evidence-badge evidence-badge-strong">
                                                Strong match
                                            </span>
                                        @endif

                                        @if (is_numeric($queryHits) && (int) $queryHits > 0)
                                            <span class="evidence-badge">
                                                {{ (int) $queryHits }}
                                                {{ (int) $queryHits === 1 ? 'matching query' : 'matching queries' }}
                                            </span>
                                        @endif

                                    </div>

                                    <div class="competitor-links">

                                        @if (is_string($websiteUrl) && trim($websiteUrl) !== '')
                                            <a
                                                href="{{ $websiteUrl }}"
                                                target="_blank"
                                                rel="noopener noreferrer"
                                            >
                                                Website ↗
                                            </a>
                                        @endif

                                        @if (is_string($mapsUrl) && trim($mapsUrl) !== '')
                                            <a
                                                href="{{ $mapsUrl }}"
                                                target="_blank"
                                                rel="noopener noreferrer"
                                            >
                                                Google Maps ↗
                                            </a>
                                        @endif

                                    </div>

                                </div>

                            </article>
                        @endforeach

                    </div>

                @else

                    <div class="analysis-placeholder">

                        <div class="placeholder-loader">
                            <span></span>
                            <span></span>
                            <span></span>
                        </div>

                        <h3>
                            Competitor results are not available yet
                        </h3>

                        <p>
                            Once the Google Business lookup is connected and enough
                            business signals are available, the ranked competitor
                            matches will appear here automatically.
                        </p>

                    </div>

                @endif

            </section>

        </div>

    </section>

</main>

@endsection