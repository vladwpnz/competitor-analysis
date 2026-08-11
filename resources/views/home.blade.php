@extends('layouts.app')

@section('title', 'AI-Powered Competitor Analysis')

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
            <a href="#analysis-form" class="trial-button">Start Free Trial</a>
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
                    AI-POWERED MARKET INTELLIGENCE
                </div>

                <h1>
                    Know What Changed
                    <span>Before Your Next Meeting.</span>
                </h1>

                <p class="hero-description">
                    Track your competitors, industry and market so you can
                    <br class="desktop-break">
                    make smarter decisions and stay ahead.
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
                                    type="url"
                                    value="{{ $websiteValue }}"
                                    placeholder="https://yourwebsite.com"
                                    autocomplete="url"
                                    @if ($editMode === 'google_business') readonly @endif
                                    @if ($editMode === 'website') autofocus @endif
                                    required
                                >
                            </div>

                            @error('website')
                                <div class="field-error">{{ $message }}</div>
                            @enderror

                            <div class="field-benefits" aria-label="Website analysis benefits">
                                <span>✓ Track website changes</span>
                                <span>✓ Monitor new content</span>
                                <span>✓ Spot strategy shifts</span>
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
                                <span>✓ Track updates &amp; posts</span>
                                <span>✓ Monitor reviews</span>
                                <span>✓ Analyze engagement</span>
                            </div>
                        </div>
                    </div>

                    <button type="submit" class="analysis-button">
                        <span>Start My Free Analysis</span>
                        <span class="button-arrow">→</span>
                    </button>

                    <div class="analysis-trust">
                        <span><b>▣</b> No credit card required</span>
                        <span><b>⊙</b> Get results in 30 seconds</span>
                        <span><b>⊙</b> Cancel anytime</span>
                    </div>
                </form>
            </div>
        </div>
    </section>

    <section class="demo-section">
        <div class="container">
            <div class="demo-panel">
                <div class="demo-copy">
                    <span class="section-label">See Intellytics In Action</span>
                    <h2>Understand Your Market.<br>Make Better Decisions.</h2>
                    <p>
                        Watch a quick 90-second overview to see how Intellytics
                        monitors your competitors and delivers AI-powered insights
                        that give you an edge.
                    </p>
                    <a href="#" class="primary-small-button">
                        <span class="play-mini">▷</span>
                        Watch 90-Second Demo
                    </a>
                </div>

                <div class="dashboard-frame">
                    <div class="dashboard-sidebar">
                        <span class="mini-logo-bars"><i></i><i></i><i></i></span>
                        <b></b><b></b><b></b><b></b><b></b>
                    </div>

                    <div class="dashboard-content">
                        <div class="dashboard-topbar">
                            <strong>Dashboard</strong>
                            <div>
                                <span>All Competitors⌄</span>
                                <span>Last 7 days⌄</span>
                            </div>
                        </div>

                        <div class="dashboard-grid">
                            <div class="chart-card">
                                <div class="tiny-title">Changes Over Time</div>
                                <svg viewBox="0 0 260 100" role="img" aria-label="Changes over time chart">
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
                                <div class="tiny-title">Change Impact</div>
                                <div class="impact-row">
                                    <strong>42</strong>
                                    <span class="donut"></span>
                                </div>
                                <small>Total company changes</small>
                            </div>
                        </div>

                        <div class="recent-changes">
                            <div class="tiny-title">Recent Changes</div>
                            <div><span class="change-icon purple">▣</span> New landing page detected <b>High</b></div>
                            <div><span class="change-icon green">▣</span> 2 new Google Ads detected <b>Medium</b></div>
                            <div><span class="change-icon orange">▣</span> Competitor pricing update <b>High</b></div>
                            <div><span class="change-icon blue">▣</span> 2 new LinkedIn posts <b>Low</b></div>
                        </div>
                    </div>

                    <span class="dashboard-play" aria-hidden="true">▶</span>
                </div>
            </div>
        </div>
    </section>

    <section class="trusted-section">
        <div class="container">
            <p>Trusted by marketing teams at innovative companies</p>
            <div class="trusted-logos" aria-label="Trusted companies">
                <span><b>◉</b> ATS<br><small>Life Sciences</small></span>
                <span class="rexroth">rexroth<br><small>A Bosch Company</small></span>
                <span class="abb">ABB</span>
                <span>SIEMENS</span>
                <span>FANUC</span>
                <span>Schneider<br><small>Electric</small></span>
                <span><b>RA</b> Rockwell<br><small>Automation</small></span>
            </div>
        </div>
    </section>

    <section class="features-section">
        <div class="container">
            <h2 class="center-section-title">Everything You Need. Nothing You Don’t.</h2>

            <div class="feature-grid">
                <article class="feature-card">
                    <span class="feature-icon purple-icon">▣</span>
                    <h3>Website Intelligence</h3>
                    <p>Track changes to pages, content, pricing and landing pages.</p>
                </article>

                <article class="feature-card">
                    <span class="feature-icon green-icon">⌁</span>
                    <h3>Ads &amp; Campaigns</h3>
                    <p>Monitor Google, Meta, LinkedIn and YouTube ads in real time.</p>
                </article>

                <article class="feature-card">
                    <span class="feature-icon orange-icon">★</span>
                    <h3>Reviews &amp; Ratings</h3>
                    <p>Track review growth, ratings and feedback across all your locations.</p>
                </article>

                <article class="feature-card">
                    <span class="feature-icon red-icon">▣</span>
                    <h3>Social Intelligence</h3>
                    <p>Monitor LinkedIn, Facebook, X, Instagram and more for posts and engagement.</p>
                </article>

                <article class="feature-card">
                    <span class="feature-icon blue-icon">▰</span>
                    <h3>Google Business</h3>
                    <p>Monitor profile updates, photos, Q&amp;A, services, reviews and more.</p>
                </article>

                <article class="feature-card">
                    <span class="feature-icon blue-icon">∞</span>
                    <h3>Meta Ads Monitoring</h3>
                    <p>Track new creatives, offers and messaging in targeting.</p>
                </article>

                <article class="feature-card">
                    <span class="feature-icon green-icon">▤</span>
                    <h3>News &amp; Mentions</h3>
                    <p>Stay informed on press releases, news and mentions about your competitors.</p>
                </article>

                <article class="feature-card">
                    <span class="feature-icon purple-icon">✦</span>
                    <h3>AI Executive Briefing</h3>
                    <p>Get a clear weekly summary with insights and recommended actions.</p>
                </article>

                <article class="feature-card">
                    <span class="feature-icon red-icon">▧</span>
                    <h3>Reports &amp; Exports</h3>
                    <p>Create beautiful reports and share with your team or clients.</p>
                </article>

                <article class="feature-card">
                    <span class="feature-icon purple-icon">♙</span>
                    <h3>Team Collaboration</h3>
                    <p>Built for teams to share insights and make better decisions together.</p>
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
                        <strong>Dashboard</strong>
                        <div>
                            <span>All Competitors⌄</span>
                            <span>Last 7 days⌄</span>
                        </div>
                    </div>

                    <div class="dashboard-grid">
                        <div class="chart-card">
                            <div class="tiny-title">Changes Over Time</div>
                            <svg viewBox="0 0 260 100" role="img" aria-label="Changes over time chart">
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
                            <div class="tiny-title">Change Impact</div>
                            <div class="impact-row">
                                <strong>42</strong>
                                <span class="donut"></span>
                            </div>
                            <small>Total company changes</small>
                        </div>
                    </div>

                    <div class="recent-changes">
                        <div class="tiny-title">Recent Changes</div>
                        <div><span class="change-icon purple">▣</span> New landing page detected <b>High</b></div>
                        <div><span class="change-icon green">▣</span> 2 new Google Ads detected <b>Medium</b></div>
                        <div><span class="change-icon orange">▣</span> Competitor pricing update <b>High</b></div>
                        <div><span class="change-icon blue">▣</span> 2 new LinkedIn posts <b>Low</b></div>
                    </div>
                </div>
            </div>

            <div class="morning-copy">
                <span class="section-label">MONDAY MORNING INTELLIGENCE</span>
                <h2>
                    Monday Morning Shouldn’t<br>
                    Start With Research.<br>
                    <span>Start With Answers.</span>
                </h2>
                <p>
                    Get a clear, AI-powered briefing of what changed in your
                    market — so you can make better decisions, faster.
                </p>
                <ul class="check-list">
                    <li>Save hours every week</li>
                    <li>Make better strategic decisions</li>
                    <li>Spot opportunities before your competitors</li>
                    <li>Stay informed with automated monitoring</li>
                </ul>
                <a href="#" class="primary-small-button">See It In Action</a>
            </div>
        </div>
    </section>

    <section class="always-on-section">
        <div class="container">
            <h2 class="center-section-title">Your Competitors Never Stop. Neither Do We.</h2>

            <div class="monitor-grid">
                <div class="monitor-item"><span>▣</span><b>Website<br>Changes</b></div>
                <div class="monitor-item"><span>★</span><b>Google<br>Reviews</b></div>
                <div class="monitor-item"><span>▰</span><b>Google<br>Business</b></div>
                <div class="monitor-item"><span>⌁</span><b>Google<br>Ads</b></div>
                <div class="monitor-item"><span>∞</span><b>Meta<br>Ads</b></div>
                <div class="monitor-item"><span>in</span><b>LinkedIn<br>Updates</b></div>
                <div class="monitor-item"><span>f</span><b>Facebook<br>Updates</b></div>
                <div class="monitor-item"><span>◎</span><b>Instagram<br>Content</b></div>
                <div class="monitor-item"><span>▤</span><b>News &amp;<br>Mentions</b></div>
                <div class="monitor-item"><span>✦</span><b>AI<br>Analysis</b></div>
            </div>

            <p class="always-on-caption">24 hours a day. 7 days a week. 365 days a year.</p>

            <div class="benefit-grid">
                <article>
                    <span class="benefit-icon">◷</span>
                    <h3>Save Time</h3>
                    <p>Automate time-consuming research and monitoring.</p>
                </article>
                <article>
                    <span class="benefit-icon">▥</span>
                    <h3>Make Better Decisions</h3>
                    <p>Understand what changed and why it matters.</p>
                </article>
                <article>
                    <span class="benefit-icon">◎</span>
                    <h3>Stay Ahead</h3>
                    <p>Know about new campaigns and changes before your competitors gain traction.</p>
                </article>
                <article>
                    <span class="benefit-icon">♙</span>
                    <h3>Built for Teams</h3>
                    <p>Share insights across your team and make smarter strategic decisions together.</p>
                </article>
            </div>
        </div>
    </section>

    <section class="proof-section">
        <div class="container">
            <h2 class="center-section-title proof-title">Loved by teams who want to stay ahead</h2>

            <div class="testimonial-grid">
                <article class="testimonial-card">
                    <p>“Intellytics gives me a competitive edge every single week. I walk into every meeting better prepared.”</p>
                    <div class="testimonial-person">
                        <span class="avatar avatar-sarah">SJ</span>
                        <div><b>Sarah Johnson</b><small>Marketing Director, ProMach</small></div>
                    </div>
                </article>

                <article class="testimonial-card">
                    <p>“The weekly briefing is a game changer. It’s like having an analyst on our team without the cost.”</p>
                    <div class="testimonial-person">
                        <span class="avatar avatar-michael">MC</span>
                        <div><b>Michael Chen</b><small>VP Marketing, CloudCoach</small></div>
                    </div>
                </article>

                <article class="testimonial-card">
                    <p>“We reduced manual research by 80%. Now we focus on strategy, not data collection.”</p>
                    <div class="testimonial-person">
                        <span class="avatar avatar-emily">ER</span>
                        <div><b>Emily Roberts</b><small>Head of Growth, Bluewater</small></div>
                    </div>
                </article>
            </div>

            <div class="stats-bar">
                <div><strong>10,000+</strong><span>Businesses monitored</span></div>
                <div><strong>1.2M+</strong><span>Changes detected</span></div>
                <div><strong>95%</strong><span>Time saved every week</span></div>
                <div><strong>4.9/5 <em>★★★★★</em></strong><span>Customer rating</span></div>
            </div>
        </div>
    </section>

    <section class="bottom-cta">
        <div class="container cta-inner">
            <div class="cta-illustration" aria-hidden="true">
                <span>✓</span>
            </div>

            <div class="cta-copy">
                <h2>Never miss another important change</h2>
                <p>Start your free 14-day trial and get your first executive briefing in minutes.</p>
            </div>

            <a href="#analysis-form" class="cta-button">
                Start Free 14-Day Trial
                <small>No credit card required</small>
            </a>
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
                AI market intelligence that helps you see what others miss
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
            <a href="#">AI Briefings</a>
            <a href="#">Integrations</a>
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