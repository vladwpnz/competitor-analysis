@extends('layouts.app')

@section('title', 'Review Your Competitors')

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

                <h1>
                    We’re Preparing Your
                    <span>Most Relevant Competitors</span>
                </h1>

                <p class="competitors-intro">
                    Your business information has been received. Competitor matching
                    will use your market, services and location to rank the most
                    relevant businesses.
                </p>

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

                </section>

                <section class="competitor-review-card">

                    <div class="competitor-review-header">

                        <div>
                            <span class="section-kicker">
                                COMPETITOR MATCHING
                            </span>

                            <h2>
                                Review and Customize Your Competitors
                            </h2>

                            <p>
                                We’ll show the businesses that most closely match
                                your industry, services and target market.
                            </p>
                        </div>

                        <button
                            type="button"
                            class="secondary-button"
                        >
                            + Add Competitor
                        </button>

                    </div>

                    <div class="analysis-placeholder">

                        <div class="placeholder-loader">
                            <span></span>
                            <span></span>
                            <span></span>
                        </div>

                        <h3>
                            Competitor matching is being prepared
                        </h3>

                        <p>
                            Real competitor results will be populated here using
                            website analysis and Google Business data.
                        </p>

                    </div>

                </section>

            </div>
        </section>

    </main>

</div>
@endsection