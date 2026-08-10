@extends('layouts.app')

@section('title', 'AI-Powered Competitor Analysis')

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
            <a href="#" class="login-link">Log in</a>

            <a href="#analysis-form" class="trial-button">
                Start Free Analysis
            </a>
        </div>

    </div>
</header>

<main>
    <section class="hero">
        <div class="container hero-inner">

            <div class="hero-copy">

                <div class="eyebrow">
                    <span class="eyebrow-dot"></span>
                    AI-POWERED MARKET INTELLIGENCE
                </div>

                <h1>
                    Know What Changed
                    <span>Before Your Next Meeting.</span>
                </h1>

                <p class="hero-description">
                    Understand your market, discover the competitors that matter
                    most, and see where your business stands — powered by intelligent
                    business and website analysis.
                </p>

                <form
                    id="analysis-form"
                    class="analysis-form"
                    action="{{ route('analysis.start') }}"
                    method="POST"
                >
                    @csrf

                    <div class="form-step">
                        <div class="step-number">1</div>

                        <div class="step-content">
                            <label for="website">
                                Your Website
                            </label>

                            <p>
                                Enter your website to get started
                            </p>

                            <div class="input-shell">
                                <span class="input-icon">↗</span>

                                <input
                                    id="website"
                                    name="website"
                                    type="url"
                                    value="{{ old('website') }}"
                                    placeholder="https://yourwebsite.com"
                                    autocomplete="url"
                                    required
                                >
                            </div>

                            @error('website')
                                <div class="field-error">
                                    {{ $message }}
                                </div>
                            @enderror
                        </div>
                    </div>

                    <div class="form-divider"></div>

                    <div class="form-step">
                        <div class="step-number">2</div>

                        <div class="step-content">
                            <label for="google_business">
                                Your Google Business Profile
                            </label>

                            <p>
                                Search for your business and select the correct
                                Google Business Profile
                            </p>

                            <div
                                class="business-search-wrap"
                                id="business-search-wrap"
                            >
                                <div class="input-shell">
                                    <span class="input-icon">⌖</span>

                                    <input
                                        id="google_business"
                                        name="google_business"
                                        type="text"
                                        value="{{ old('google_business') }}"
                                        placeholder="Search for your business on Google Maps"
                                        autocomplete="off"
                                        spellcheck="false"
                                        role="combobox"
                                        aria-autocomplete="list"
                                        aria-controls="google-business-suggestions"
                                        aria-expanded="false"
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
                                    value="{{ old('google_place_id') }}"
                                >

                                <input
                                    id="google_places_session_token"
                                    name="google_places_session_token"
                                    type="hidden"
                                    value="{{ old('google_places_session_token') }}"
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
                                <div class="field-error">
                                    {{ $message }}
                                </div>
                            @enderror

                            @error('google_place_id')
                                <div class="field-error">
                                    {{ $message }}
                                </div>
                            @enderror

                            @error('google_places_session_token')
                                <div class="field-error">
                                    {{ $message }}
                                </div>
                            @enderror
                        </div>
                    </div>

                    <button type="submit" class="analysis-button">
                        <span>Start My Free Analysis</span>
                        <span class="button-arrow">→</span>
                    </button>

                </form>

                <div class="hero-trust">
                    <span>✓ No credit card required</span>
                    <span>✓ Website scan included</span>
                    <span>✓ Free initial analysis</span>
                </div>

            </div>

        </div>
    </section>
</main>

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