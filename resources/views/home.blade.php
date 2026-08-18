@extends('layouts.app')

@section('title', 'AI-assisted competitor discovery')
@section('meta_description', 'Build a ranked competitor shortlist from website signals, Google Business data, AI classification, and deterministic fallback logic.')

@section('content')

@php
    $requestedEditMode = old(
        'edit_mode',
        request()->query('edit')
    );

    $editMode = in_array(
        $requestedEditMode,
        ['website', 'google_business'],
        true
    )
        ? $requestedEditMode
        : null;

    $editingExistingAnalysis =
        $editMode !== null;

    $websiteValue = old(
        'website',
        $editingExistingAnalysis
            ? (string) session('analysis.website', '')
            : ''
    );

    $googleBusinessValue = old(
        'google_business',
        $editingExistingAnalysis
            ? (string) session('analysis.google_business', '')
            : ''
    );

    $googlePlaceIdValue = old(
        'google_place_id',
        $editingExistingAnalysis
            ? (string) session('analysis.google_place_id', '')
            : ''
    );

    $googlePlacesSessionTokenValue = old(
        'google_places_session_token',
        $editingExistingAnalysis
            ? (string) session(
                'analysis.google_places_session_token',
                ''
            )
            : ''
    );
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
            <a href="#analysis-form">Analyze</a>
            <a href="#how-it-works">How it works</a>
            <a href="#capabilities">Capabilities</a>
        </nav>

        <div class="header-actions">
            <a href="#analysis-form" class="header-cta">Start analysis</a>
        </div>
    </div>
</header>

<main>
    <section class="hero">
        <div class="hero-dots hero-dots-left" aria-hidden="true"></div>
        <div class="hero-dots hero-dots-right" aria-hidden="true"></div>

        <div class="container hero-inner">
            <div class="hero-copy">
                <div class="eyebrow">
                    <span class="eyebrow-spark">✦</span>
                    AI-ASSISTED COMPETITOR INTELLIGENCE
                </div>

                <h1>
                    Find the Competitors
                    <span>That Actually Matter.</span>
                </h1>

                <p class="hero-description">
                    Combine website signals, Google Business data, and market context to
                    <br class="desktop-break">
                    build a relevant, ranked competitor shortlist.
                </p>

                <form
                    id="analysis-form"
                    class="analysis-form"
                    action="{{ route('analysis.start') }}"
                    method="POST"
                >
                    @csrf

                    @if ($editMode !== null)
                        <input
                            type="hidden"
                            name="edit_mode"
                            value="{{ $editMode }}"
                        >
                    @endif

                    <div class="analysis-fields">
                        <div class="form-step">
                            <div class="form-heading">
                                <span class="form-icon" aria-hidden="true">◎</span>
                                <div>
                                    <label for="website">
                                        <strong>1</strong> Your Website
                                    </label>
                                    <p>Enter your website to get started</p>
                                </div>
                            </div>

                            <div class="input-shell">
                                <input
                                    id="website"
                                    name="website"
                                    type="text"
                                    value="{{ $websiteValue }}"
                                    placeholder="https://yourwebsite.com"
                                    autocomplete="url"
                                    inputmode="url"
                                    autocapitalize="none"
                                    spellcheck="false"
                                    @if ($editMode === 'google_business') readonly @endif
                                    @if ($editMode === 'website') autofocus @endif
                                    required
                                >
                            </div>

                            @error('website')
                                <div class="field-error">{{ $message }}</div>
                            @enderror

                            <div class="field-benefits" aria-label="Website analysis benefits">
                                <span>✓ Extract business signals</span>
                                <span>✓ Identify services and positioning</span>
                                <span>✓ Handle blocked sites safely</span>
                            </div>
                        </div>

                        <div class="form-step">
                            <div class="form-heading">
                                <span class="form-icon form-icon-pin" aria-hidden="true">●</span>
                                <div>
                                    <label for="google_business">
                                        <strong>2</strong> Your Google Business Profile
                                    </label>
                                    <p>Enter your Google Business Profile to get started</p>
                                </div>
                            </div>

                            <div
                                class="business-search-wrap"
                                id="business-search-wrap"
                            >
                                <div class="input-shell">
                                    <input
                                        id="google_business"
                                        name="google_business"
                                        type="text"
                                        value="{{ $googleBusinessValue }}"
                                        placeholder="Search for your business on Google Maps"
                                        autocomplete="off"
                                        spellcheck="false"
                                        role="combobox"
                                        aria-autocomplete="list"
                                        aria-controls="google-business-suggestions"
                                        aria-expanded="false"
                                        @if ($editMode === 'website') readonly @endif
                                        @if ($editMode === 'google_business') autofocus @endif
                                        required
                                    >

                                    <span
                                        id="business-search-loader"
                                        class="business-search-loader"
                                        aria-hidden="true"
                                    ></span>
                                </div>

                                <input
                                    id="google_place_id"
                                    name="google_place_id"
                                    type="hidden"
                                    value="{{ $googlePlaceIdValue }}"
                                >

                                <input
                                    id="google_places_session_token"
                                    name="google_places_session_token"
                                    type="hidden"
                                    value="{{ $googlePlacesSessionTokenValue }}"
                                >

                                <div
                                    id="google-business-suggestions"
                                    class="business-suggestions"
                                    role="listbox"
                                    hidden
                                ></div>
                            </div>

                            <div
                                id="google-business-status"
                                class="business-search-status"
                                aria-live="polite"
                            ></div>

                            @error('google_business')
                                <div class="field-error">{{ $message }}</div>
                            @enderror

                            @error('google_place_id')
                                <div class="field-error">{{ $message }}</div>
                            @enderror

                            @error('google_places_session_token')
                                <div class="field-error">{{ $message }}</div>
                            @enderror

                            <div class="field-benefits" aria-label="Google Business analysis benefits">
                                <span>✓ Confirm business identity</span>
                                <span>✓ Add geographic context</span>
                                <span>✓ Improve local relevance</span>
                            </div>
                        </div>
                    </div>

                    <button type="submit" class="analysis-button">
                        <span>Analyze Competitors</span>
                        <span class="button-arrow">→</span>
                    </button>

                    <div class="analysis-trust">
                        <span><b>▣</b> No account required</span>
                        <span><b>⊙</b> Graceful API fallbacks</span>
                        <span><b>⊙</b> Review every match</span>
                    </div>
                </form>
            </div>
        </div>
    </section>

    <section class="demo-section" id="how-it-works">
        <div class="container">
            <div class="demo-panel">
                <div class="demo-copy">
                    <span class="section-label">HOW THE ANALYSIS WORKS</span>
                    <h2>From Business Signals<br>to a Ranked Shortlist.</h2>
                    <p>
                        The application scans the website, validates the selected
                        business profile, classifies the market, and ranks the most
                        relevant competitor candidates.
                    </p>
                    <a href="#analysis-form" class="primary-small-button">
                        Start an analysis
                    </a>
                </div>

                <div class="dashboard-frame">
                    <div class="dashboard-sidebar">
                        <span class="mini-logo-bars"><i></i><i></i><i></i></span>
                        <b></b><b></b><b></b><b></b><b></b>
                    </div>

                    <div class="dashboard-content">
                        <div class="dashboard-topbar">
                            <strong>Analysis</strong>
                            <div>
                                <span>Ranked competitors</span>
                                <span>Top matches</span>
                            </div>
                        </div>

                        <div class="dashboard-grid">
                            <div class="chart-card">
                                <div class="tiny-title">Relevance scores</div>
                                <svg viewBox="0 0 260 100" role="img" aria-label="Competitor relevance score chart">
                                    <polyline
                                        points="5,78 45,58 83,72 122,42 160,55 202,27 250,42"
                                        fill="none"
                                        stroke="#4b36ff"
                                        stroke-width="4"
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                    />
                                    <line x1="5" y1="88" x2="250" y2="88" stroke="#e9e8f5" />
                                    <line x1="5" y1="61" x2="250" y2="61" stroke="#f0eff7" />
                                    <line x1="5" y1="34" x2="250" y2="34" stroke="#f0eff7" />
                                </svg>
                            </div>

                            <div class="impact-card">
                                <div class="tiny-title">Shortlist</div>
                                <div class="impact-row">
                                    <strong>5</strong>
                                    <span class="donut"></span>
                                </div>
                                <small>ranked competitors</small>
                            </div>
                        </div>

                        <div class="recent-changes">
                            <div class="tiny-title">Analysis stages</div>
                            <div><span class="change-icon purple">▣</span> Website profile extracted <b>Ready</b></div>
                            <div><span class="change-icon green">▣</span> Business profile matched <b>Ready</b></div>
                            <div><span class="change-icon orange">▣</span> Market scope classified <b>Ready</b></div>
                            <div><span class="change-icon blue">▣</span> Duplicates and own business removed <b>Ready</b></div>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </section>

    <section class="trusted-section">
        <div class="container">
            <p>Analysis sources and decision layers</p>
            <div class="trusted-logos" aria-label="Analysis sources and decision layers">
                <span>Website<br><small>signals</small></span>
                <span>Google<br><small>Places</small></span>
                <span>Gemini<br><small>classification</small></span>
                <span>Deterministic<br><small>fallback</small></span>
                <span>Relevance<br><small>scoring</small></span>
                <span>Duplicate<br><small>filtering</small></span>
                <span>Manual<br><small>review</small></span>
            </div>
        </div>
    </section>

    <section class="features-section" id="capabilities">
        <div class="container">
            <h2 class="center-section-title">A Focused Competitor Discovery Pipeline.</h2>

            <div class="feature-grid">
                <article class="feature-card">
                    <span class="feature-icon purple-icon">▣</span>
                    <h3>Website scanning</h3>
                    <p>Extract titles, descriptions, headings, and readable page content.</p>
                </article>

                <article class="feature-card">
                    <span class="feature-icon green-icon">⌁</span>
                    <h3>Business matching</h3>
                    <p>Validate that the selected Google Business Profile belongs to the website.</p>
                </article>

                <article class="feature-card">
                    <span class="feature-icon orange-icon">★</span>
                    <h3>AI classification</h3>
                    <p>Use Gemini to identify the operating model, market scope, and search intent.</p>
                </article>

                <article class="feature-card">
                    <span class="feature-icon red-icon">▣</span>
                    <h3>Deterministic fallback</h3>
                    <p>Continue with rule-based classification when AI is unavailable or unconfigured.</p>
                </article>

                <article class="feature-card">
                    <span class="feature-icon blue-icon">▰</span>
                    <h3>Market-aware discovery</h3>
                    <p>Handle local, hybrid, and broader markets with the right geographic context.</p>
                </article>

                <article class="feature-card">
                    <span class="feature-icon blue-icon">∞</span>
                    <h3>Staged search</h3>
                    <p>Expand discovery only when the initial candidate pool lacks strong matches.</p>
                </article>

                <article class="feature-card">
                    <span class="feature-icon green-icon">▤</span>
                    <h3>Relevance scoring</h3>
                    <p>Rank candidates using business type, services, query evidence, and distance.</p>
                </article>

                <article class="feature-card">
                    <span class="feature-icon purple-icon">✦</span>
                    <h3>Duplicate filtering</h3>
                    <p>Collapse duplicate locations and repeated records into distinct companies.</p>
                </article>

                <article class="feature-card">
                    <span class="feature-icon red-icon">▧</span>
                    <h3>Own-business exclusion</h3>
                    <p>Remove the subject business and related locations from competitor results.</p>
                </article>

                <article class="feature-card">
                    <span class="feature-icon purple-icon">♙</span>
                    <h3>Manual refinement</h3>
                    <p>Add or remove competitors while preserving the curated list in the session.</p>
                </article>
            </div>
        </div>
    </section>

    <section class="morning-section">
        <div class="container morning-grid">
            <div class="dashboard-frame dashboard-frame-secondary">
                <div class="dashboard-sidebar">
                    <span class="mini-logo-bars"><i></i><i></i><i></i></span>
                    <b></b><b></b><b></b><b></b><b></b>
                </div>

                <div class="dashboard-content">
                    <div class="dashboard-topbar">
                        <strong>Analysis</strong>
                        <div>
                            <span>Ranked competitors</span>
                            <span>Top matches</span>
                        </div>
                    </div>

                    <div class="dashboard-grid">
                        <div class="chart-card">
                            <div class="tiny-title">Relevance scores</div>
                            <svg viewBox="0 0 260 100" role="img" aria-label="Competitor relevance score chart">
                                <polyline
                                    points="5,78 45,58 83,72 122,42 160,55 202,27 250,42"
                                    fill="none"
                                    stroke="#4b36ff"
                                    stroke-width="4"
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                />
                                <line x1="5" y1="88" x2="250" y2="88" stroke="#e9e8f5" />
                                <line x1="5" y1="61" x2="250" y2="61" stroke="#f0eff7" />
                                <line x1="5" y1="34" x2="250" y2="34" stroke="#f0eff7" />
                            </svg>
                        </div>

                        <div class="impact-card">
                            <div class="tiny-title">Shortlist</div>
                            <div class="impact-row">
                                <strong>5</strong>
                                <span class="donut"></span>
                            </div>
                            <small>ranked competitors</small>
                        </div>
                    </div>

                    <div class="recent-changes">
                        <div class="tiny-title">Ranking evidence</div>
                        <div><span class="change-icon purple">▣</span> Business type compatibility <b>Strong</b></div>
                        <div><span class="change-icon green">▣</span> Service keyword overlap <b>Strong</b></div>
                        <div><span class="change-icon orange">▣</span> Search query evidence <b>Matched</b></div>
                        <div><span class="change-icon blue">▣</span> Geographic relevance <b>Matched</b></div>
                    </div>
                </div>
            </div>

            <div class="morning-copy">
                <span class="section-label">RESILIENT BY DESIGN</span>
                <h2>
                    Useful Results Shouldn’t<br>
                    Depend on One API.<br>
                    <span>Fallbacks Keep the Flow Moving.</span>
                </h2>
                <p>
                    AI improves classification and discovery, while deterministic
                    rules and staged search preserve a useful path when external
                    services are unavailable.
                </p>
                <ul class="check-list">
                    <li>Classify with Gemini when configured</li>
                    <li>Fall back to deterministic business rules</li>
                    <li>Continue from Google data when a website blocks scanning</li>
                    <li>Return clear errors for unsafe or invalid URLs</li>
                </ul>
                <a href="#analysis-form" class="primary-small-button">Try the analysis flow</a>
            </div>
        </div>
    </section>

    <section class="always-on-section">
        <div class="container">
            <h2 class="center-section-title">Signals Considered at Every Stage.</h2>

            <div class="monitor-grid">
                <div class="monitor-item"><span>▣</span><b>Website<br>Content</b></div>
                <div class="monitor-item"><span>★</span><b>Google<br>Profile</b></div>
                <div class="monitor-item"><span>▰</span><b>Business<br>Type</b></div>
                <div class="monitor-item"><span>⌁</span><b>Market<br>Scope</b></div>
                <div class="monitor-item"><span>∞</span><b>Service<br>Keywords</b></div>
                <div class="monitor-item"><span>◎</span><b>Search<br>Evidence</b></div>
                <div class="monitor-item"><span>◷</span><b>Travel<br>Distance</b></div>
                <div class="monitor-item"><span>◇</span><b>Brand<br>Identity</b></div>
                <div class="monitor-item"><span>▤</span><b>Candidate<br>Quality</b></div>
                <div class="monitor-item"><span>✦</span><b>AI<br>Discovery</b></div>
            </div>

            <p class="always-on-caption">Signals are normalized before candidates are scored and ranked.</p>

            <div class="benefit-grid">
                <article>
                    <span class="benefit-icon">◷</span>
                    <h3>Relevant by default</h3>
                    <p>Type, service, and query evidence outweigh a simple nearest-business search.</p>
                </article>
                <article>
                    <span class="benefit-icon">▥</span>
                    <h3>Market-aware</h3>
                    <p>Local businesses keep distance context while broader markets avoid local bias.</p>
                </article>
                <article>
                    <span class="benefit-icon">◎</span>
                    <h3>Failure-tolerant</h3>
                    <p>External API failures degrade gracefully instead of replacing the analysis with mock data.</p>
                </article>
                <article>
                    <span class="benefit-icon">♙</span>
                    <h3>Human-controlled</h3>
                    <p>Review the shortlist, remove weak matches, and add competitors manually.</p>
                </article>
            </div>
        </div>
    </section>

    <section class="proof-section">
        <div class="container">
            <h2 class="center-section-title proof-title">Built for Inspectable Results.</h2>

            <div class="testimonial-grid">
                <article class="testimonial-card">
                    <p>Each ranked candidate keeps the evidence used to assess relevance.</p>
                    <div class="testimonial-person">
                        <span class="avatar avatar-sarah">01</span>
                        <div><b>Transparent ranking</b><small>Scores, matches, and search modes</small></div>
                    </div>
                </article>

                <article class="testimonial-card">
                    <p>The pipeline keeps distinct paths for local and broader competitor discovery.</p>
                    <div class="testimonial-person">
                        <span class="avatar avatar-michael">02</span>
                        <div><b>Scope-aware search</b><small>Geographic or semantic discovery</small></div>
                    </div>
                </article>

                <article class="testimonial-card">
                    <p>Manual selection remains available after automated ranking completes.</p>
                    <div class="testimonial-person">
                        <span class="avatar avatar-emily">03</span>
                        <div><b>Curated shortlist</b><small>Add, remove, and retain selections</small></div>
                    </div>
                </article>
            </div>

            <div class="stats-bar">
                <div><strong>4</strong><span>maximum search queries</span></div>
                <div><strong>5</strong><span>top ranked matches</span></div>
                <div><strong>20</strong><span>selection limit</span></div>
                <div><strong>2</strong><span>classification paths</span></div>
            </div>
        </div>
    </section>

    <section class="bottom-cta">
        <div class="container cta-inner">
            <div class="cta-illustration" aria-hidden="true">
                <span>✓</span>
            </div>

            <div class="cta-copy">
                <h2>Build a competitor shortlist you can review</h2>
                <p>Start with a website and the matching Google Business Profile.</p>
            </div>

            <a href="#analysis-form" class="cta-button">
                Start an Analysis
                <small>No account required</small>
            </a>
        </div>
    </section>
</main>

<footer class="site-footer">
    <div class="container footer-grid">
        <div class="footer-brand-column">
            <a href="{{ route('home') }}" class="brand footer-brand">
                <span class="brand-mark" aria-hidden="true">
                    <i></i><i></i><i></i><i></i>
                </span>
                <span class="brand-name">Competitor Intelligence</span>
            </a>

            <p>
                AI-assisted competitor discovery with deterministic fallbacks,
                relevance scoring, and manual review.
            </p>
        </div>

        <div class="footer-column">
            <h3>Product</h3>
            <a href="#analysis-form">Analyze</a>
            <a href="#how-it-works">How it works</a>
            <a href="#capabilities">Capabilities</a>
        </div>

        <div class="footer-column">
            <h3>Discovery</h3>
            <a href="#capabilities">Website scanning</a>
            <a href="#capabilities">Google Places</a>
            <a href="#capabilities">AI classification</a>
        </div>

        <div class="footer-column">
            <h3>Quality</h3>
            <a href="#capabilities">Relevance scoring</a>
            <a href="#capabilities">Duplicate filtering</a>
            <a href="#capabilities">Manual refinement</a>
        </div>
    </div>

    <div class="container footer-bottom">
        <span>© {{ now()->year }} Competitor Intelligence.</span>
        <div>
            <span>Independent portfolio project</span>
        </div>
    </div>
</footer>

<style>
    .business-search-wrap {
        position: relative;
        width: 100%;
    }

    .business-search-loader {
        display: none;
        width: 16px;
        height: 16px;
        margin-left: auto;
        flex: 0 0 16px;
        border: 2px solid rgba(91, 64, 180, 0.18);
        border-top-color: currentColor;
        border-radius: 50%;
        animation: business-search-spin 0.7s linear infinite;
    }

    .business-search-loader.is-visible {
        display: block;
    }

    @keyframes business-search-spin {
        to {
            transform: rotate(360deg);
        }
    }

    .business-suggestions {
        position: absolute;
        top: calc(100% + 8px);
        left: 0;
        right: 0;
        z-index: 50;
        max-height: 340px;
        overflow-y: auto;
        background: #ffffff;
        border: 1px solid rgba(25, 20, 45, 0.12);
        border-radius: 14px;
        box-shadow: 0 18px 45px rgba(25, 20, 45, 0.14);
        padding: 6px;
    }

    .business-suggestions[hidden] {
        display: none;
    }

    .business-suggestion {
        width: 100%;
        display: block;
        padding: 12px 14px;
        border: 0;
        border-radius: 10px;
        background: transparent;
        text-align: left;
        cursor: pointer;
        font: inherit;
        color: inherit;
        transition:
            background-color 0.15s ease,
            transform 0.15s ease;
    }

    .business-suggestion:hover,
    .business-suggestion.is-active {
        background: rgba(91, 64, 180, 0.07);
    }

    .business-suggestion:focus-visible {
        outline: 2px solid currentColor;
        outline-offset: -2px;
    }

    .business-suggestion-name {
        display: block;
        font-size: 14px;
        line-height: 1.35;
        font-weight: 700;
        color: #1d1930;
    }

    .business-suggestion-secondary {
        display: block;
        margin-top: 3px;
        font-size: 12px;
        line-height: 1.4;
        font-weight: 400;
        color: #777286;
    }

    .google-maps-attribution {
        display: flex;
        justify-content: flex-end;
        align-items: center;
        min-height: 28px;
        padding: 5px 12px 3px;
        margin-top: 3px;
        border-top: 1px solid rgba(25, 20, 45, 0.08);
        font-family: Roboto, Arial, sans-serif;
        font-size: 12px;
        line-height: 1;
        font-weight: 400;
        color: #5e5e5e;
        white-space: nowrap;
    }

    .business-search-status {
        min-height: 18px;
        margin-top: 7px;
        font-size: 12px;
        line-height: 1.4;
        color: #777286;
    }

    .business-search-status.is-selected {
        color: #3f7d59;
    }

    .business-search-status.is-error {
        color: #b42318;
    }

    @media (max-width: 767px) {
        .business-suggestions {
            max-height: 280px;
        }

        .business-suggestion {
            padding: 11px 12px;
        }
    }
</style>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById(
        'analysis-form'
    );

    const websiteInput = document.getElementById(
        'website'
    );

    const businessInput = document.getElementById(
        'google_business'
    );

    const placeIdInput = document.getElementById(
        'google_place_id'
    );

    const sessionTokenInput = document.getElementById(
        'google_places_session_token'
    );

    const suggestionsBox = document.getElementById(
        'google-business-suggestions'
    );

    const statusBox = document.getElementById(
        'google-business-status'
    );

    const loader = document.getElementById(
        'business-search-loader'
    );

    if (
        !form
        || !websiteInput
        || !businessInput
        || !placeIdInput
        || !sessionTokenInput
        || !suggestionsBox
        || !statusBox
        || !loader
    ) {
        return;
    }

    const searchEndpoint =
        @json(route('google-business.search'));

    const normalizeWebsiteValue = () => {
        const value =
            websiteInput.value.trim();

        if (value === '') {
            return;
        }

        if (!/^https?:\/\//i.test(value)) {
            websiteInput.value =
                'https://' + value;

            return;
        }

        websiteInput.value = value;
    };

    websiteInput.addEventListener(
        'blur',
        normalizeWebsiteValue
    );

    const uuidPattern =
        /^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i;

    let debounceTimer = null;
    let requestController = null;
    let suggestions = [];
    let activeIndex = -1;

    const createSessionToken = () => {
        if (
            window.crypto
            && typeof window.crypto.randomUUID
                === 'function'
        ) {
            return window.crypto.randomUUID();
        }

        const bytes =
            new Uint8Array(16);

        window.crypto.getRandomValues(
            bytes
        );

        bytes[6] =
            (bytes[6] & 0x0f) | 0x40;

        bytes[8] =
            (bytes[8] & 0x3f) | 0x80;

        const hex = Array.from(
            bytes,
            byte => byte
                .toString(16)
                .padStart(2, '0')
        );

        return [
            hex.slice(0, 4).join(''),
            hex.slice(4, 6).join(''),
            hex.slice(6, 8).join(''),
            hex.slice(8, 10).join(''),
            hex.slice(10, 16).join(''),
        ].join('-');
    };

    const getOrCreateSessionToken = () => {
        const current =
            sessionTokenInput.value.trim();

        if (
            current !== ''
            && uuidPattern.test(current)
        ) {
            return current;
        }

        const token =
            createSessionToken();

        sessionTokenInput.value =
            token;

        return token;
    };

    const resetSelectedBusiness = () => {
        placeIdInput.value = '';
        sessionTokenInput.value = '';
        selectedBusinessText = '';
    };

    const hasExistingSelection =
        placeIdInput.value.trim() !== ''
        && uuidPattern.test(
            sessionTokenInput.value.trim()
        )
        && businessInput.value.trim() !== '';

    let selectedBusinessText =
        hasExistingSelection
            ? businessInput.value.trim()
            : '';

    if (!hasExistingSelection) {
        placeIdInput.value = '';

        if (
            !uuidPattern.test(
                sessionTokenInput.value.trim()
            )
        ) {
            sessionTokenInput.value = '';
        }
    }

    const setExpanded = (expanded) => {
        businessInput.setAttribute(
            'aria-expanded',
            expanded ? 'true' : 'false'
        );
    };

    const showLoader = (show) => {
        loader.classList.toggle(
            'is-visible',
            show
        );
    };

    const setStatus = (
        message = '',
        type = ''
    ) => {
        statusBox.textContent =
            message;

        statusBox.classList.remove(
            'is-selected',
            'is-error'
        );

        if (type === 'selected') {
            statusBox.classList.add(
                'is-selected'
            );
        }

        if (type === 'error') {
            statusBox.classList.add(
                'is-error'
            );
        }
    };

    const closeSuggestions = () => {
        suggestions = [];
        activeIndex = -1;

        suggestionsBox.replaceChildren();
        suggestionsBox.hidden = true;

        setExpanded(false);
    };

    const appendGoogleMapsAttribution = () => {
        const attribution =
            document.createElement('div');

        attribution.className =
            'google-maps-attribution';

        attribution.textContent =
            'Google Maps';

        attribution.setAttribute(
            'translate',
            'no'
        );

        attribution.setAttribute(
            'aria-label',
            'Google Maps'
        );

        suggestionsBox.appendChild(
            attribution
        );
    };

    const selectBusiness = (suggestion) => {
        const placeId =
            suggestion.place_id || '';

        if (placeId === '') {
            return;
        }

        businessInput.value =
            suggestion.full_text
            || suggestion.name
            || '';

        placeIdInput.value =
            placeId;

        /*
         * Do not create a new token here.
         *
         * The token that produced this prediction
         * must also be sent to Place Details.
         */
        getOrCreateSessionToken();

        selectedBusinessText =
            businessInput.value.trim();

        closeSuggestions();

        setStatus(
            '✓ Google Business selected',
            'selected'
        );
    };

    const updateActiveSuggestion = () => {
        const buttons =
            suggestionsBox.querySelectorAll(
                '.business-suggestion'
            );

        buttons.forEach(
            (button, index) => {
                button.classList.toggle(
                    'is-active',
                    index === activeIndex
                );

                if (index === activeIndex) {
                    button.scrollIntoView({
                        block: 'nearest',
                    });
                }
            }
        );
    };

    const renderSuggestions = (items) => {
        suggestions =
            Array.isArray(items)
                ? items
                : [];

        activeIndex = -1;

        suggestionsBox.replaceChildren();

        if (suggestions.length === 0) {
            suggestionsBox.hidden = true;

            setExpanded(false);

            setStatus(
                'No matching Google businesses found.'
            );

            return;
        }

        suggestions.forEach(
            (suggestion, index) => {
                const button =
                    document.createElement(
                        'button'
                    );

                button.type = 'button';

                button.className =
                    'business-suggestion';

                button.setAttribute(
                    'role',
                    'option'
                );

                button.dataset.index =
                    String(index);

                const name =
                    document.createElement(
                        'span'
                    );

                name.className =
                    'business-suggestion-name';

                name.textContent =
                    suggestion.name
                    || suggestion.full_text
                    || 'Google Business';

                button.appendChild(
                    name
                );

                const secondaryText =
                    suggestion.secondary_text
                    || '';

                if (
                    secondaryText !== ''
                ) {
                    const secondary =
                        document.createElement(
                            'span'
                        );

                    secondary.className =
                        'business-suggestion-secondary';

                    secondary.textContent =
                        secondaryText;

                    button.appendChild(
                        secondary
                    );
                }

                button.addEventListener(
                    'mousedown',
                    event => {
                        event.preventDefault();
                    }
                );

                button.addEventListener(
                    'click',
                    () => {
                        selectBusiness(
                            suggestion
                        );
                    }
                );

                suggestionsBox.appendChild(
                    button
                );
            }
        );

        appendGoogleMapsAttribution();

        suggestionsBox.hidden = false;

        setExpanded(true);

        setStatus(
            'Select the correct business from the results.'
        );
    };

    const searchBusinesses = async (
        query,
        sessionToken
    ) => {
        if (requestController) {
            requestController.abort();
        }

        const controller =
            new AbortController();

        requestController =
            controller;

        showLoader(true);

        try {
            const parameters =
                new URLSearchParams({
                    q: query,
                    session_token:
                        sessionToken,
                });

            const response =
                await fetch(
                    searchEndpoint
                        + '?'
                        + parameters.toString(),
                    {
                        method: 'GET',

                        headers: {
                            Accept:
                                'application/json',
                        },

                        signal:
                            controller.signal,
                    }
                );

            const data =
                await response
                    .json()
                    .catch(
                        () => ({})
                    );

            if (!response.ok) {
                throw new Error(
                    data.message
                    || 'Google Business search is temporarily unavailable.'
                );
            }

            if (
                businessInput.value.trim()
                    !== query
            ) {
                return;
            }

            renderSuggestions(
                data.suggestions || []
            );
        } catch (error) {
            if (
                error.name
                    === 'AbortError'
            ) {
                return;
            }

            closeSuggestions();

            setStatus(
                error.message
                || 'Google Business search is temporarily unavailable.',
                'error'
            );
        } finally {
            if (
                requestController
                    === controller
            ) {
                requestController =
                    null;

                showLoader(false);
            }
        }
    };

    businessInput.addEventListener(
        'input',
        () => {
            if (businessInput.readOnly) {
                return;
            }

            const query =
                businessInput.value.trim();

            /*
             * Editing a previously selected prediction
             * invalidates both the Place ID and the
             * completed Autocomplete session.
             */
            if (
                selectedBusinessText !== ''
                && query
                    !== selectedBusinessText
            ) {
                resetSelectedBusiness();

                setStatus('');
            }

            clearTimeout(
                debounceTimer
            );

            closeSuggestions();

            if (query.length < 3) {
                if (
                    query.length > 0
                ) {
                    setStatus(
                        'Type at least 3 characters to search.'
                    );
                } else {
                    setStatus('');
                }

                return;
            }

            const sessionToken =
                getOrCreateSessionToken();

            setStatus(
                'Searching Google businesses...'
            );

            debounceTimer =
                setTimeout(
                    () => {
                        searchBusinesses(
                            query,
                            sessionToken
                        );
                    },
                    300
                );
        }
    );

    businessInput.addEventListener(
        'keydown',
        event => {
            if (
                suggestionsBox.hidden
                || suggestions.length === 0
            ) {
                return;
            }

            if (
                event.key
                    === 'ArrowDown'
            ) {
                event.preventDefault();

                activeIndex =
                    activeIndex
                        < suggestions.length - 1
                        ? activeIndex + 1
                        : 0;

                updateActiveSuggestion();

                return;
            }

            if (
                event.key
                    === 'ArrowUp'
            ) {
                event.preventDefault();

                activeIndex =
                    activeIndex > 0
                        ? activeIndex - 1
                        : suggestions.length - 1;

                updateActiveSuggestion();

                return;
            }

            if (
                event.key === 'Enter'
                && activeIndex >= 0
            ) {
                event.preventDefault();

                selectBusiness(
                    suggestions[
                        activeIndex
                    ]
                );

                return;
            }

            if (
                event.key
                    === 'Escape'
            ) {
                closeSuggestions();
            }
        }
    );

    document.addEventListener(
        'click',
        event => {
            if (
                !event.target.closest(
                    '#business-search-wrap'
                )
            ) {
                closeSuggestions();
            }
        }
    );

    form.addEventListener(
        'submit',
        event => {
            normalizeWebsiteValue();

            clearTimeout(
                debounceTimer
            );

            if (requestController) {
                requestController.abort();

                requestController = null;
            }

            showLoader(false);

            /*
             * A manually typed business is temporarily
             * still allowed so the existing website-only
             * development flow keeps working before live
             * Google credentials are connected.
             *
             * If a Place ID exists, however, it must have
             * the Autocomplete session that produced it.
             */
            if (
                placeIdInput.value.trim() !== ''
                && !uuidPattern.test(
                    sessionTokenInput.value.trim()
                )
            ) {
                event.preventDefault();

                resetSelectedBusiness();

                setStatus(
                    'Please search for the business again and select it from the Google results.',
                    'error'
                );
            }
        }
    );

    if (hasExistingSelection) {
        setStatus(
            '✓ Google Business selected',
            'selected'
        );
    }
});
</script>

@endsection
