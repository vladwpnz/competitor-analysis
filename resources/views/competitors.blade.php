@extends('layouts.app')

@section('title', 'Review your competitors')
@section('meta_description', 'Review the ranked competitor shortlist, remove weak matches, and add competitors manually.')

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
    $isDigitalGlobal = data_get(
        $analysisResult,
        'classification.discovery_mode'
    ) === 'digital_global';
    $hasAnalysis = is_array($analysisResult);

    $marketScopeLabel = \Illuminate\Support\Str::headline(
        (string) $marketScope
    );

    $businessTypeLabel = \Illuminate\Support\Str::headline(
        (string) data_get(
            $analysisResult,
            'classification.business_type',
            data_get($analysisResult, 'search_profile.business_type', 'Business')
        )
    );

    $discoverySource = (string) data_get(
        $analysisResult,
        'discovery_source',
        'google_places'
    );

    $discoverySourceLabel = match ($discoverySource) {
        'ai_direct' => 'AI direct discovery',
        'google_places_fallback' => 'Google Places fallback',
        default => 'Google Places discovery',
    };

    $candidateCount = (int) data_get(
        $analysisResult,
        'candidate_count',
        count($topCompetitors)
    );
@endphp

@include('partials.site-header', ['headerCtaLabel' => 'New analysis'])

<main id="main-content" class="competitors-page">
    <section class="competitors-hero">
        <div class="container competitors-container">
            <nav class="workflow-progress" aria-label="Analysis progress">
                <ol>
                    <li class="is-complete"><span>1</span>Business</li>
                    <li class="is-current" aria-current="step"><span>2</span>Shortlist</li>
                    <li><span>3</span>Contact</li>
                </ol>
            </nav>

            @if ($hasAnalysis)
                <div class="competitors-heading">
                    <div>
                        <p class="workspace-kicker">Analysis complete</p>
                        <h1>Review the ranked shortlist.</h1>
                        <p class="competitors-intro">
                            Inspect why each business was ranked, then keep the competitors that belong in your final set.
                        </p>
                    </div>

                    <div class="shortlist-count" aria-label="Selected competitor count">
                        <strong id="competitor-count-heading">{{ count($topCompetitors) }}</strong>
                        <span>selected</span>
                    </div>
                </div>
            @else
                <div class="competitors-heading">
                    <div>
                        <p class="workspace-kicker">Analysis in progress</p>
                        <h1>Preparing your shortlist.</h1>
                        <p class="competitors-intro">
                            The application is combining website, Google Business and market signals.
                        </p>
                    </div>
                </div>
            @endif

            @error('competitors')
                <div class="step2-page-error" role="alert">
                    {{ $message }}
                </div>
            @enderror

            <div class="step2-shell">
                <section class="step2-business-section">
                    <div class="subject-heading">
                        <div>
                            <span>Business analyzed</span>
                            <h2 title="{{ $googleBusiness }}">{{ $googleBusinessDisplay }}</h2>
                            <a href="{{ $website }}" target="_blank" rel="noreferrer">
                                {{ $websiteDisplay }}
                                <span aria-hidden="true">↗</span>
                            </a>
                        </div>

                        <div class="subject-actions">
                            <a href="{{ route('home', ['edit' => 'website']) }}#analysis-form">
                                Edit website
                            </a>
                            <a href="{{ route('home', ['edit' => 'google_business']) }}#analysis-form">
                                Edit Google profile
                            </a>
                        </div>
                    </div>

                    <dl class="analysis-facts">
                        <div>
                            <dt>Business type</dt>
                            <dd>{{ $businessTypeLabel }}</dd>
                        </div>
                        <div>
                            <dt>Market scope</dt>
                            <dd>{{ $marketScopeLabel }}</dd>
                        </div>
                        <div>
                            <dt>Discovery path</dt>
                            <dd>{{ $discoverySourceLabel }}</dd>
                        </div>
                        <div>
                            <dt>Candidate pool</dt>
                            <dd>{{ $candidateCount }}</dd>
                        </div>
                    </dl>

                    <div class="step2-info-note">
                        <p>
                            @if (!empty($websiteScanWarning))
                                {{ $websiteScanWarning }}
                            @else
                                Rankings use business type, services, query evidence and distance when geography matters.
                            @endif
                        </p>
                    </div>
                </section>

                <section class="step2-review-section">
                    <div class="step2-review-header">
                        <div>
                            <h2>Ranked competitors</h2>
                            <p>
                                All listed businesses are selected. Remove a weak match or add a competitor you already know.
                            </p>
                        </div>

                        <button
                            type="button"
                            class="step2-add-button js-add-competitor"
                            aria-haspopup="dialog"
                            aria-controls="add-competitor-modal"
                            aria-expanded="false"
                        >
                            Add competitor
                            <span aria-hidden="true">+</span>
                        </button>
                    </div>

                    <div
                        class="step2-operation-status"
                        id="step2-operation-status"
                        aria-live="polite"
                    ></div>

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

                                    $relevanceScore = data_get(
                                        $competitor,
                                        '_relevance.score'
                                    );

                                    $relevanceQuality = trim(
                                        (string) data_get(
                                            $competitor,
                                            '_relevance.quality',
                                            ''
                                        )
                                    );

                                    $matchedQueries = data_get(
                                        $competitor,
                                        '_relevance.evidence.matched_queries',
                                        data_get($competitor, '_match.queries', [])
                                    );

                                    $matchedQuery = collect(
                                        is_array($matchedQueries)
                                            ? $matchedQueries
                                            : []
                                    )
                                        ->filter(
                                            static fn (mixed $query): bool =>
                                                is_string($query)
                                                && trim($query) !== ''
                                        )
                                        ->map(
                                            static fn (string $query): string =>
                                                trim($query)
                                        )
                                        ->first();

                                    $discoveryReason = trim(
                                        (string) data_get(
                                            $competitor,
                                            '_discovery.reason',
                                            ''
                                        )
                                    );

                                    $isManualSelection = data_get(
                                        $competitor,
                                        '_manual_selection'
                                    ) === true;

                                    $matchEvidence = match (true) {
                                        $discoveryReason !== '' => $discoveryReason,
                                        is_string($matchedQuery) => 'Matched search: '.$matchedQuery,
                                        $relevanceQuality !== '' =>
                                            \Illuminate\Support\Str::headline($relevanceQuality)
                                            .' relevance across available signals',
                                        $isManualSelection =>
                                            'Added manually to the shortlist',
                                        default => 'Included from the current analysis',
                                    };

                                    $formattedAddress = trim(
                                        (string) data_get(
                                            $competitor,
                                            'formattedAddress',
                                            ''
                                        )
                                    );

                                    $websiteHost = null;

                                    if (
                                        is_string($websiteUri)
                                        && filter_var($websiteUri, FILTER_VALIDATE_URL)
                                    ) {
                                        $candidateHost = parse_url(
                                            $websiteUri,
                                            PHP_URL_HOST
                                        );

                                        if (is_string($candidateHost)) {
                                            $websiteHost = $candidateHost;
                                        }
                                    }

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
                                    class="step2-competitor-row {{ $index === 0 ? 'is-leading' : '' }}"
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
                                        <div class="step2-competitor-title">
                                            <h3>{{ $name }}</h3>
                                        </div>

                                        <div class="step2-meta">
                                            <span>{{ $category }}</span>
                                        </div>

                                        <p class="step2-evidence">{{ $matchEvidence }}</p>

                                        <div class="step2-context">
                                            @if ($websiteHost && is_string($websiteUri))
                                                <a href="{{ $websiteUri }}" target="_blank" rel="noreferrer">
                                                    {{ $websiteHost }}
                                                    <span aria-hidden="true">↗</span>
                                                </a>
                                            @endif

                                            @if (!$isBroaderMarket)
                                                <span>
                                                    @if ($distanceMiles !== null)
                                                        {{ number_format($distanceMiles, 1) }} miles away
                                                    @elseif ($formattedAddress !== '')
                                                        {{ $formattedAddress }}
                                                    @else
                                                        Location unavailable
                                                    @endif
                                                </span>
                                            @endif
                                        </div>
                                    </div>

                                    <div class="step2-score">
                                        @if (is_numeric($relevanceScore))
                                            <span>Relevance</span>
                                            <div>
                                                <strong>{{ number_format((float) $relevanceScore, 0) }}</strong>
                                                <small>/ 100</small>
                                            </div>
                                            <em>
                                                {{ $relevanceQuality !== ''
                                                    ? \Illuminate\Support\Str::headline($relevanceQuality).' relevance'
                                                    : 'Ranked match' }}
                                            </em>
                                        @else
                                            <span>Source</span>
                                            <strong class="step2-score-manual">
                                                {{ $isManualSelection ? 'Manual add' : 'Ranked' }}
                                            </strong>
                                            <em>Not rescored</em>
                                        @endif
                                    </div>

                                    <div class="step2-rating">
                                        <span>Google rating</span>

                                        @if (is_numeric($rating))
                                            <strong>
                                                {{ number_format((float) $rating, 1) }}
                                                <small>/ 5</small>
                                            </strong>
                                        @else
                                            <strong class="is-unavailable">N/A</strong>
                                        @endif

                                        <span class="step2-review-count">
                                            @if (is_numeric($reviewCount))
                                                {{ number_format((int) $reviewCount) }} reviews
                                            @else
                                                Reviews unavailable
                                            @endif
                                        </span>
                                    </div>

                                    <div class="step2-row-actions">
                                        <span class="step2-selected-state">Selected</span>
                                        <button
                                            type="button"
                                            class="step2-remove-button"
                                            data-remove-competitor
                                            aria-label="Remove {{ $name }} from shortlist"
                                        >
                                            Remove
                                        </button>
                                    </div>
                                </article>
                            @endforeach
                        </div>

                        <div
                            class="step2-empty-selection"
                            id="step2-empty-selection"
                            @if (!empty($topCompetitors)) hidden @endif
                        >
                            <strong>No competitors selected yet.</strong>
                            <span>Add at least one competitor to continue.</span>
                        </div>

                        <button
                            type="button"
                            class="step2-add-another js-add-competitor"
                            aria-haspopup="dialog"
                            aria-controls="add-competitor-modal"
                            aria-expanded="false"
                        >
                            <span class="step2-add-another-icon" aria-hidden="true">+</span>
                            <span class="step2-add-another-copy">
                                <strong>Add competitor</strong>
                                <small>Search by company name or official website</small>
                            </span>
                            <span class="step2-add-another-arrow" aria-hidden="true">↗</span>
                        </button>

                        <button
                            type="button"
                            class="step2-start-button"
                            id="step2-start-button"
                            data-step3-url="{{ route('analysis.email') }}"
                            @disabled(empty($topCompetitors))
                        >
                            <span>Continue to contact</span>
                            <span aria-hidden="true">↗</span>
                        </button>

                        <div class="step2-trust">
                            <span>No account required</span>
                            <span>Selection stays in this session</span>
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
        aria-describedby="add-competitor-description"
        tabindex="-1"
    >
        <button
            type="button"
            class="step2-modal-close"
            id="add-competitor-close"
            aria-label="Close"
        >
            <span aria-hidden="true">×</span>
        </button>

        <div class="step2-modal-kicker">Manual discovery</div>
        <h2 id="add-competitor-title">Add competitor</h2>
        <p id="add-competitor-description">
            {{ $isDigitalGlobal
                ? 'Search direct competitors by company name or official domain.'
                : 'Search Google by business name or website, then choose the correct business.' }}
        </p>

        <div class="step2-modal-search">
            <label for="add-competitor-query">Company name or website</label>
            <input
                id="add-competitor-query"
                type="text"
                placeholder="Search for a competitor"
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

        <div class="step2-google-attribution">
            Source: {{ $isDigitalGlobal ? 'AI direct competitor search' : 'Google Maps' }}
        </div>
    </section>
</div>

@include('partials.site-footer')



<script>
document.addEventListener('DOMContentLoaded', () => {
    const list = document.getElementById('step2-competitor-list');
    const countHeading = document.getElementById('competitor-count-heading');
    const emptySelection = document.getElementById('step2-empty-selection');
    const startButton = document.getElementById('step2-start-button');
    const operationStatus = document.getElementById('step2-operation-status');

    const modal = document.getElementById('add-competitor-modal');
    const modalPanel = modal?.querySelector('.step2-modal');
    const modalClose = document.getElementById('add-competitor-close');
    const queryInput = document.getElementById('add-competitor-query');
    const resultsBox = document.getElementById('add-competitor-results');
    const statusBox = document.getElementById('add-competitor-status');
    const loader = document.getElementById('add-competitor-loader');
    const addButtons = Array.from(
        document.querySelectorAll('.js-add-competitor')
    );

    const searchEndpoint = @json(route('competitors.search'));
    const addEndpoint = @json(route('competitors.add'));
    const removeEndpoint = @json(route('competitors.remove'));
    const csrfToken = @json(csrf_token());

    let debounceTimer = null;
    let requestController = null;
    let addingPlaceId = null;
    let lastAddTrigger = null;

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
            row.classList.toggle(
                'is-leading',
                index === 0
            );

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

    const setOperationStatus = (
        message = '',
        isError = false
    ) => {
        if (!operationStatus) {
            return;
        }

        operationStatus.textContent = message;
        operationStatus.classList.toggle(
            'is-error',
            isError
        );
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

    const openModal = (trigger = null) => {
        if (!modal || !queryInput) {
            return;
        }

        lastAddTrigger = trigger;
        clearResults();
        setModalStatus('');
        setOperationStatus('');
        queryInput.value = '';
        modal.hidden = false;
        document.body.classList.add(
            'step2-modal-open'
        );

        addButtons.forEach(button => {
            button.setAttribute(
                'aria-expanded',
                'true'
            );
        });

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

        addButtons.forEach(button => {
            button.setAttribute(
                'aria-expanded',
                'false'
            );
        });

        if (lastAddTrigger) {
            lastAddTrigger.focus();
            lastAddTrigger = null;
        }
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

        const title = document.createElement('div');
        title.className = 'step2-competitor-title';

        const heading = document.createElement('h3');
        heading.textContent =
            competitor.name || 'Competitor';
        title.appendChild(heading);
        main.appendChild(title);

        const meta = document.createElement('div');
        meta.className = 'step2-meta';

        const category = document.createElement('span');
        category.textContent =
            competitor.category || 'Relevant business';
        meta.appendChild(category);
        main.appendChild(meta);

        const evidence = document.createElement('p');
        evidence.className = 'step2-evidence';
        evidence.textContent =
            competitor.evidence
            || (
                competitor.is_manual
                    ? 'Added manually to the shortlist'
                    : 'Included from the current analysis'
            );
        main.appendChild(evidence);

        const context = document.createElement('div');
        context.className = 'step2-context';

        if (
            competitor.website_url
            && competitor.website_host
        ) {
            const website = document.createElement('a');
            website.href = competitor.website_url;
            website.target = '_blank';
            website.rel = 'noreferrer';
            website.textContent =
                competitor.website_host + ' ↗';
            context.appendChild(website);
        }

        if (competitor.show_distance) {
            const location = document.createElement('span');

            location.textContent =
                competitor.distance_miles !== null
                && competitor.distance_miles !== undefined
                    ? Number(
                        competitor.distance_miles
                    ).toFixed(1)
                        + ' miles away'
                    : (
                        competitor.formatted_address
                        || 'Location unavailable'
                    );

            context.appendChild(location);
        }

        if (context.childElementCount > 0) {
            main.appendChild(context);
        }

        row.appendChild(main);

        const score = document.createElement('div');
        score.className = 'step2-score';

        const scoreLabel = document.createElement('span');
        scoreLabel.textContent =
            competitor.relevance_score !== null
            && competitor.relevance_score !== undefined
                ? 'Relevance'
                : 'Source';
        score.appendChild(scoreLabel);

        if (
            competitor.relevance_score !== null
            && competitor.relevance_score !== undefined
        ) {
            const scoreLine = document.createElement('div');
            const scoreValue = document.createElement('strong');
            scoreValue.textContent = String(
                Math.round(
                    Number(competitor.relevance_score)
                )
            );

            const scoreScale = document.createElement('small');
            scoreScale.textContent = '/ 100';

            scoreLine.append(scoreValue, scoreScale);
            score.appendChild(scoreLine);

            const scoreQuality = document.createElement('em');
            scoreQuality.textContent =
                competitor.relevance_quality
                    ? competitor.relevance_quality
                        .replace(/_/g, ' ')
                        + ' relevance'
                    : 'Ranked match';
            score.appendChild(scoreQuality);
        } else {
            const manual = document.createElement('strong');
            manual.className = 'step2-score-manual';
            manual.textContent =
                competitor.is_manual
                    ? 'Manual add'
                    : 'Ranked';
            score.appendChild(manual);

            const scoreNote = document.createElement('em');
            scoreNote.textContent = 'Not rescored';
            score.appendChild(scoreNote);
        }

        row.appendChild(score);

        const rating = document.createElement('div');
        rating.className = 'step2-rating';

        const ratingLabel = document.createElement('span');
        ratingLabel.textContent = 'Google rating';
        rating.appendChild(ratingLabel);

        const ratingValue =
            document.createElement('strong');

        if (
            competitor.rating !== null
            && competitor.rating !== undefined
        ) {
            const numericRating = Number(
                competitor.rating
            );

            ratingValue.textContent =
                numericRating.toFixed(1);

            const ratingScale =
                document.createElement('small');
            ratingScale.textContent = '/ 5';
            ratingValue.appendChild(ratingScale);
        } else {
            ratingValue.textContent = 'N/A';
            ratingValue.className = 'is-unavailable';
        }

        rating.appendChild(ratingValue);

        const reviewCount =
            document.createElement('span');
        reviewCount.className =
            'step2-review-count';

        reviewCount.textContent =
            competitor.review_count !== null
            && competitor.review_count !== undefined
                ? Number(
                    competitor.review_count
                ).toLocaleString()
                    + ' reviews'
                : 'Reviews unavailable';

        rating.appendChild(reviewCount);
        row.appendChild(rating);

        const actions = document.createElement('div');
        actions.className = 'step2-row-actions';

        const selected = document.createElement('span');
        selected.className = 'step2-selected-state';
        selected.textContent = 'Selected';
        actions.appendChild(selected);

        const remove =
            document.createElement('button');
        remove.type = 'button';
        remove.className =
            'step2-remove-button';
        remove.dataset.removeCompetitor = '';
        remove.textContent = 'Remove';
        remove.setAttribute(
            'aria-label',
            'Remove '
                + (competitor.name || 'competitor')
                + ' from shortlist'
        );
        actions.appendChild(remove);
        row.appendChild(actions);

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
                    secondaryParts.join(', ');

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
            'Select the competitor you want to add.'
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
            setOperationStatus(
                data.message
                || 'Competitor added to the shortlist.'
            );
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
                    setOperationStatus(
                        data.message
                        || 'Competitor removed from the shortlist.'
                    );
                } catch (error) {
                    button.disabled = false;
                    setOperationStatus(
                        error.message
                        || 'Could not remove that competitor.',
                        true
                    );

                    button.focus();
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
                    openModal(button);
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

                return;
            }

            if (
                event.key === 'Tab'
                && modal
                && modalPanel
                && !modal.hidden
            ) {
                const focusable = Array.from(
                    modalPanel.querySelectorAll(
                        'button:not([disabled]), input:not([disabled])'
                    )
                );

                if (focusable.length === 0) {
                    return;
                }

                const first = focusable[0];
                const last = focusable[
                    focusable.length - 1
                ];

                if (
                    event.shiftKey
                    && document.activeElement === first
                ) {
                    event.preventDefault();
                    last.focus();
                } else if (
                    !event.shiftKey
                    && document.activeElement === last
                ) {
                    event.preventDefault();
                    first.focus();
                }
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
