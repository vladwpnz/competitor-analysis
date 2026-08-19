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


@include('partials.site-header')

<main id="main-content">
    <section class="hero" aria-labelledby="hero-title">
        <div class="container hero-inner">
            <div class="hero-copy">
                <p class="hero-kicker">Competitor research, grounded in evidence</p>

                <h1 id="hero-title">
                    Find competitors <span>that matter.</span>
                </h1>

                <p class="hero-description">
                    Turn website signals and Google Business data into a ranked shortlist you can inspect and refine.
                </p>

                <div class="hero-proof" aria-label="Analysis principles">
                    <span>Market-aware discovery</span>
                    <span>Inspectable ranking</span>
                    <span>Human review</span>
                </div>
            </div>

                <form
                    id="analysis-form"
                    class="analysis-form"
                    action="{{ route('analysis.start') }}"
                    method="POST"
                >
                    @csrf

                    <div class="analysis-form-header">
                        <div>
                            <span class="analysis-form-label">New analysis</span>
                            <h2>Define the business</h2>
                        </div>
                        <span class="analysis-form-status">Two verified inputs</span>
                    </div>

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
                                <span class="form-index" aria-hidden="true">01</span>
                                <label for="website">Business website</label>
                            </div>

                            <p class="field-helper" id="website-helper">
                                Used to read services, positioning and business signals.
                            </p>

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
                                    aria-describedby="website-helper"
                                    @if ($editMode === 'google_business') readonly @endif
                                    @if ($editMode === 'website') autofocus @endif
                                    required
                                >
                            </div>

                            @error('website')
                                <div class="field-error" role="alert">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="analysis-connector" aria-hidden="true">
                            <span></span>
                            <b>+</b>
                            <span></span>
                        </div>

                        <div class="form-step">
                            <div class="form-heading">
                                <span class="form-index" aria-hidden="true">02</span>
                                <label for="google_business">Google Business Profile</label>
                            </div>

                            <p class="field-helper" id="google-business-helper">
                                Confirms identity and adds location context where relevant.
                            </p>

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
                                        aria-describedby="google-business-helper google-business-status"
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
                                <div class="field-error" role="alert">{{ $message }}</div>
                            @enderror

                            @error('google_place_id')
                                <div class="field-error" role="alert">{{ $message }}</div>
                            @enderror

                            @error('google_places_session_token')
                                <div class="field-error" role="alert">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    <button type="submit" class="analysis-button">
                        <span>Build competitor shortlist</span>
                        <span class="button-arrow" aria-hidden="true">↗</span>
                    </button>

                    <div class="analysis-trust">
                        <span>No account required</span>
                        <span>You review every match</span>
                    </div>

                    <div
                        class="analysis-loading"
                        id="analysis-loading"
                        aria-live="polite"
                        hidden
                    >
                        <div class="analysis-loading-head">
                            <span class="analysis-loading-mark" aria-hidden="true"></span>
                            <div>
                                <strong>Building your shortlist</strong>
                                <span>We are combining business and market signals.</span>
                            </div>
                        </div>

                        <ol class="analysis-loading-stages">
                            <li>Reading website signals</li>
                            <li>Understanding the business</li>
                            <li>Identifying market scope</li>
                            <li>Discovering competitor candidates</li>
                            <li>Ranking by relevance</li>
                        </ol>
                    </div>
                </form>
        </div>
    </section>

    <section class="pipeline-section" id="how-it-works" aria-labelledby="pipeline-title">
        <div class="container">
            <div class="pipeline-heading">
                <p>How the analysis works</p>
                <h2 id="pipeline-title">From raw signals to a shortlist you can defend.</h2>
            </div>

            <ol class="pipeline-list">
                <li>
                    <span>01</span>
                    <strong>Website signals</strong>
                    <p>Read services, positioning and business context.</p>
                </li>
                <li>
                    <span>02</span>
                    <strong>Business understanding</strong>
                    <p>Classify the operating model and customer market.</p>
                </li>
                <li>
                    <span>03</span>
                    <strong>Competitor discovery</strong>
                    <p>Search locally or across broader digital markets.</p>
                </li>
                <li>
                    <span>04</span>
                    <strong>Relevance ranking</strong>
                    <p>Score type, services, query evidence and distance.</p>
                </li>
                <li>
                    <span>05</span>
                    <strong>Final shortlist</strong>
                    <p>Remove weak matches and add known competitors.</p>
                </li>
            </ol>
        </div>
    </section>

    <section class="scope-section" aria-labelledby="scope-title">
        <div class="container scope-layout">
            <div class="scope-copy">
                <h2 id="scope-title">The market decides how discovery works.</h2>
                <p>
                    A nearby service business and a global software platform should not be ranked by the same geographic assumptions.
                </p>
            </div>

            <div class="scope-comparison">
                <article>
                    <span>Local and hybrid markets</span>
                    <h3>Relevance with geographic context</h3>
                    <p>Distance supports the ranking without outweighing business type and service fit.</p>
                </article>

                <article>
                    <span>Broader markets</span>
                    <h3>Semantic fit before proximity</h3>
                    <p>Discovery prioritizes direct competitors, product intent and market alignment.</p>
                </article>
            </div>
        </div>
    </section>

    <section class="features-section" id="capabilities">
        <div class="container capabilities-layout">
            <div class="capabilities-heading">
                <h2>Built for evidence, not a black-box list.</h2>
                <p>
                    Every stage contributes to a shortlist that remains inspectable and under your control.
                </p>
            </div>

            <div class="capability-grid">
                <article class="capability-primary">
                    <span>Business intelligence layer</span>
                    <h3>Website scanning and AI-assisted classification</h3>
                    <p>
                        The application combines readable website content with Google Business data to understand services, operating model and search intent.
                    </p>
                    <ul>
                        <li>Website and Google Business matching</li>
                        <li>Gemini classification when configured</li>
                        <li>Deterministic classification fallback</li>
                    </ul>
                </article>

                <article class="capability-detail">
                    <span>Discovery</span>
                    <h3>Staged, market-aware search</h3>
                    <p>Search expands only when the first candidate pool lacks enough strong matches.</p>
                </article>

                <article class="capability-detail capability-detail-tinted">
                    <span>Quality control</span>
                    <h3>Ranking and duplicate removal</h3>
                    <p>Type, service, query and distance evidence shape the order while duplicate companies and the subject business are excluded.</p>
                </article>

                <article class="capability-detail capability-detail-plain">
                    <span>Human review</span>
                    <h3>A shortlist you can edit</h3>
                    <p>Remove weak matches, search manually and keep the final selection in the current session.</p>
                </article>
            </div>
        </div>
    </section>

    <section class="resilience-section" aria-labelledby="resilience-title">
        <div class="container resilience-layout">
            <div>
                <h2 id="resilience-title">Useful results should not depend on one API.</h2>
                <p>
                    AI improves classification and discovery. Deterministic rules keep the analysis useful when an external service is unavailable.
                </p>
            </div>

            <ul class="resilience-list">
                <li>
                    <strong>Blocked website</strong>
                    <span>Continue with verified Google Business data when safe.</span>
                </li>
                <li>
                    <strong>AI unavailable</strong>
                    <span>Fall back to deterministic business classification.</span>
                </li>
                <li>
                    <strong>Thin candidate pool</strong>
                    <span>Expand discovery in controlled stages.</span>
                </li>
            </ul>
        </div>
    </section>

    <section class="review-section" aria-labelledby="review-title">
        <div class="container review-layout">
            <div class="review-copy">
                <h2 id="review-title">Automation finds the shortlist. You make the final call.</h2>
                <p>
                    Ranking evidence stays visible, selected competitors remain editable and manual discovery is part of the same workflow.
                </p>
            </div>

            <div class="review-signals" aria-label="Review controls">
                <span>Relevance score</span>
                <span>Matched search evidence</span>
                <span>Distance when relevant</span>
                <span>Google rating when available</span>
                <span>Add or remove competitors</span>
            </div>
        </div>
    </section>

    <section class="bottom-cta">
        <div class="container cta-inner">
            <div class="cta-copy">
                <h2>Start with the business you know.</h2>
                <p>We will build the competitor shortlist from there.</p>
            </div>

            <a href="#analysis-form" class="cta-button">
                Start an analysis
                <span aria-hidden="true">↗</span>
            </a>
        </div>
    </section>
</main>

@include('partials.site-footer')

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

    const analysisLoading = document.getElementById(
        'analysis-loading'
    );

    const submitButton = form?.querySelector(
        '.analysis-button'
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
            'Google Business Profile selected',
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

                return;
            }

            closeSuggestions();

            if (analysisLoading) {
                analysisLoading.hidden = false;
            }

            form.classList.add(
                'is-analyzing'
            );

            form.setAttribute(
                'aria-busy',
                'true'
            );

            if (submitButton) {
                submitButton.disabled = true;
            }
        }
    );

    if (hasExistingSelection) {
        setStatus(
            'Google Business Profile selected',
            'selected'
        );
    }
});
</script>

@endsection
