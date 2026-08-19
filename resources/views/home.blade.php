@extends('layouts.app')

@section('title', 'B2B lookalike account discovery')
@section('meta_description', 'Use a reference company to discover similar B2B accounts and build an evidence-based prospect shortlist.')

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
                <p class="hero-kicker">B2B account discovery, grounded in evidence</p>

                <h1 id="hero-title">
                    Find more companies <span>like your best customers.</span>
                </h1>

                <p class="hero-description">
                    Use a strong customer, ideal target, or other reference company to build a business profile, discover similar accounts, and review a ranked prospect shortlist.
                </p>

                <div class="hero-proof" aria-label="Analysis principles">
                    <span>Reference-led profiling</span>
                    <span>Evidence-based fit</span>
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
                            <h2>Choose a reference company</h2>
                        </div>
                        <span class="analysis-form-status">Two matching signals</span>
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
                                <label for="website">Reference company website</label>
                            </div>

                            <p class="field-helper" id="website-helper">
                                Builds the core profile from its business model, services, customers and market signals.
                            </p>

                            <div class="input-shell">
                                <input
                                    id="website"
                                    name="website"
                                    type="text"
                                    value="{{ $websiteValue }}"
                                    placeholder="https://reference-company.com"
                                    autocomplete="url"
                                    inputmode="url"
                                    autocapitalize="none"
                                    spellcheck="false"
                                    aria-describedby="website-helper @error('website') website-error @enderror"
                                    @error('website') aria-invalid="true" @enderror
                                    @if ($editMode === 'google_business') readonly @endif
                                    @if ($editMode === 'website') autofocus @endif
                                    required
                                >
                            </div>

                            @error('website')
                                <div id="website-error" class="field-error" role="alert">{{ $message }}</div>
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
                                <label for="google_business">Reference Google Business Profile</label>
                            </div>

                            <p class="field-helper" id="google-business-helper">
                                Confirms the same company and adds geographic and operating context where relevant.
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
                                        placeholder="Search for the reference company on Google Maps"
                                        autocomplete="off"
                                        spellcheck="false"
                                        role="combobox"
                                        aria-autocomplete="list"
                                        aria-controls="google-business-suggestions"
                                        aria-expanded="false"
                                        aria-describedby="google-business-helper google-business-status @error('google_business') google-business-error @enderror @error('google_place_id') google-place-id-error @enderror @error('google_places_session_token') google-session-token-error @enderror"
                                        @if ($errors->hasAny(['google_business', 'google_place_id', 'google_places_session_token'])) aria-invalid="true" @endif
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
                                <div id="google-business-error" class="field-error" role="alert">{{ $message }}</div>
                            @enderror

                            @error('google_place_id')
                                <div id="google-place-id-error" class="field-error" role="alert">{{ $message }}</div>
                            @enderror

                            @error('google_places_session_token')
                                <div id="google-session-token-error" class="field-error" role="alert">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    <button type="submit" class="analysis-button">
                        <span>Find lookalike accounts</span>
                        <span class="button-arrow" aria-hidden="true">↗</span>
                    </button>

                    <div class="analysis-trust">
                        <span>No account required</span>
                        <span>The reference company is excluded</span>
                    </div>

                    <div
                        class="analysis-loading"
                        id="analysis-loading"
                        role="status"
                        aria-live="polite"
                        aria-atomic="true"
                        hidden
                    >
                        <div class="analysis-loading-head">
                            <span class="analysis-loading-mark" aria-hidden="true"></span>
                            <div>
                                <strong>Building your prospect shortlist</strong>
                                <span>We are combining reference-company and market signals.</span>
                            </div>
                        </div>

                        <ol class="analysis-loading-stages">
                            <li>Reading reference company signals</li>
                            <li>Building the business profile</li>
                            <li>Understanding market context</li>
                            <li>Finding lookalike accounts</li>
                            <li>Ranking account fit</li>
                        </ol>
                    </div>
                </form>
        </div>
    </section>

    <section class="pipeline-section" id="how-it-works" aria-labelledby="pipeline-title">
        <div class="container">
            <div class="pipeline-heading">
                <p>How the analysis works</p>
                <h2 id="pipeline-title">From a reference company to accounts worth reviewing.</h2>
            </div>

            <ol class="pipeline-list">
                <li>
                    <span>01</span>
                    <strong>Reference company signals</strong>
                    <p>Read the website and verified Google Business context.</p>
                </li>
                <li>
                    <span>02</span>
                    <strong>Business understanding</strong>
                    <p>Classify the operating model, offering and customer market.</p>
                </li>
                <li>
                    <span>03</span>
                    <strong>Lookalike discovery</strong>
                    <p>Find companies with a comparable business profile.</p>
                </li>
                <li>
                    <span>04</span>
                    <strong>Fit ranking</strong>
                    <p>Score business type, services, search evidence and geographic context.</p>
                </li>
                <li>
                    <span>05</span>
                    <strong>Prospect shortlist</strong>
                    <p>Remove weak matches and add accounts you already know.</p>
                </li>
            </ol>
        </div>
    </section>

    <section class="scope-section" aria-labelledby="scope-title">
        <div class="container scope-layout">
            <div class="scope-copy">
                <h2 id="scope-title">The market decides how discovery works.</h2>
                <p>
                    Similar physical businesses may share a geographic context. Broader markets need a different signal mix.
                </p>
            </div>

            <div class="scope-comparison">
                <article>
                    <span>Local and hybrid markets</span>
                    <h3>Account fit with geographic context</h3>
                    <p>Location can support the fit of similar physical businesses without outweighing business type and services.</p>
                </article>

                <article>
                    <span>Broader markets</span>
                    <h3>Commercial fit before proximity</h3>
                    <p>Business model, services, target customers and market scope matter more than distance.</p>
                </article>
            </div>
        </div>
    </section>

    <section class="features-section" id="capabilities">
        <div class="container capabilities-layout">
            <div class="capabilities-heading">
                <h2>Built for evidence, not a black-box list.</h2>
                <p>
                    Every stage contributes evidence to a shortlist that stays inspectable and under your control.
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
                    <p>Type, service, search and geographic evidence shape the order while duplicates and the reference company are excluded.</p>
                </article>

                <article class="capability-detail capability-detail-plain">
                    <span>Human review</span>
                    <h3>A shortlist you can edit</h3>
                    <p>Remove weak matches, search manually and keep the prospect shortlist in the current session.</p>
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
                <h2 id="review-title">Discovery builds the shortlist. You make the final call.</h2>
                <p>
                    Fit evidence stays visible, recommended accounts remain editable and manual account discovery uses the same workflow.
                </p>
            </div>

            <div class="review-signals" aria-label="Review controls">
                <span>Fit score</span>
                <span>Matched search evidence</span>
                <span>Distance when relevant</span>
                <span>Google rating when available</span>
                <span>Add or remove accounts</span>
            </div>
        </div>
    </section>

    <section class="bottom-cta">
        <div class="container cta-inner">
            <div class="cta-copy">
                <h2>Start with one company worth finding more of.</h2>
                <p>We will build a ranked prospect shortlist from its business profile.</p>
            </div>

            <a href="#analysis-form" class="cta-button">
                Find lookalike accounts
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
        businessInput.removeAttribute(
            'aria-activedescendant'
        );

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
            'Reference Google Business Profile selected',
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
                const isActive =
                    index === activeIndex;

                button.classList.toggle(
                    'is-active',
                    isActive
                );

                button.setAttribute(
                    'aria-selected',
                    isActive ? 'true' : 'false'
                );

                if (isActive) {
                    businessInput.setAttribute(
                        'aria-activedescendant',
                        button.id
                    );

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

                button.id =
                    'google-business-option-'
                    + String(index);

                button.setAttribute(
                    'aria-selected',
                    'false'
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
                    'Search for the reference company again and select it from the Google results.',
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
            'Reference Google Business Profile selected',
            'selected'
        );
    }
});
</script>

@endsection
