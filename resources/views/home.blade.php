@extends('layouts.app')

@section('title', 'AI-Powered Competitor Analysis')

@section('content')
<div class="site-shell">

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
                                    Enter your Google Business Profile to get started
                                </p>

                                <div class="input-shell">
                                    <span class="input-icon">⌖</span>

                                    <input
                                        id="google_business"
                                        name="google_business"
                                        type="text"
                                        value="{{ old('google_business') }}"
                                        placeholder="Search for your business on Google Maps"
                                        autocomplete="off"
                                        required
                                    >
                                </div>

                                @error('google_business')
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

</div>
@endsection