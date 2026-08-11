@extends('layouts.app')

@section('title', 'Get Your Free Competitor Analysis')

@section('content')

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

<main class="step3-page">
    <section class="step3-hero">
        <div class="container step3-container">
            <div class="step-badge">STEP 3 OF 3</div>

            @if (session('analysis_email_saved'))
                <div class="step3-card step3-success">
                    <div class="step3-success-icon" aria-hidden="true">✓</div>
                    <h1>Your Details Are Saved</h1>
                    <p>
                        We’ve saved your email and
                        {{ $competitorCount }}
                        {{ \Illuminate\Support\Str::plural('competitor', $competitorCount) }}
                        for this analysis.
                    </p>

                    <div class="step3-summary">
                        <span>Report email</span>
                        <strong>{{ $email }}</strong>
                    </div>

                    <a href="{{ route('competitors') }}" class="step3-secondary-button">
                        ← Back to competitors
                    </a>
                </div>
            @else
                <div class="step3-heading">
                    <h1>
                        Where Should We Send
                        <strong>Your Free Report?</strong>
                    </h1>
                    <p>
                        Your competitor list is ready. Enter your email to continue
                        with your free analysis.
                    </p>
                </div>

                <div class="step3-card">
                    <div class="step3-summary-grid">
                        <div>
                            <span>Business</span>
                            <strong>{{ $googleBusiness }}</strong>
                        </div>
                        <div>
                            <span>Competitors selected</span>
                            <strong>{{ $competitorCount }}</strong>
                        </div>
                    </div>

                    <form
                        action="{{ route('analysis.email.store') }}"
                        method="POST"
                        class="step3-form"
                    >
                        @csrf

                        <label for="analysis-email">
                            Email address
                        </label>

                        <input
                            id="analysis-email"
                            name="email"
                            type="email"
                            value="{{ old('email', $email) }}"
                            placeholder="you@company.com"
                            autocomplete="email"
                            required
                            autofocus
                        >

                        @error('email')
                            <div class="step3-error" role="alert">
                                {{ $message }}
                            </div>
                        @enderror

                        <button type="submit">
                            <span>Get My Free Report</span>
                            <span aria-hidden="true">→</span>
                        </button>

                        <div class="step3-trust">
                            <span>🔒 No credit card required</span>
                            <span>✓ Your competitor list is saved</span>
                        </div>
                    </form>
                </div>

                <a href="{{ route('competitors') }}" class="step3-back-link">
                    ← Back to customize competitors
                </a>
            @endif
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
                AI-powered market intelligence that helps you see what others miss
                and act with confidence.
            </p>
        </div>

        <div class="footer-column">
            <h3>Platform</h3>
            <a href="#">Features</a>
            <a href="#">How It Works</a>
            <a href="#">Integrations</a>
        </div>

        <div class="footer-column">
            <h3>Solutions</h3>
            <a href="#">For Marketing Teams</a>
            <a href="#">For Agencies</a>
            <a href="#">For Enterprises</a>
        </div>

        <div class="footer-column">
            <h3>Resources</h3>
            <a href="#">Blog</a>
            <a href="#">Case Studies</a>
            <a href="#">Help Center</a>
        </div>

        <div class="footer-column">
            <h3>Company</h3>
            <a href="#">About Us</a>
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
    .step3-page {
        min-height: 720px;
        background:
            radial-gradient(circle at 15% 10%, rgba(126, 91, 198, 0.08), transparent 34%),
            #fbfafc;
    }

    .step3-hero {
        padding: 84px 0 96px;
    }

    .step3-container {
        width: min(760px, calc(100% - 32px));
        margin: 0 auto;
        text-align: center;
    }

    .step3-heading h1,
    .step3-success h1 {
        margin: 18px 0 0;
        color: #211a2c;
        font-size: clamp(34px, 5vw, 54px);
        line-height: 1.06;
        letter-spacing: -0.04em;
    }

    .step3-heading h1 strong {
        display: block;
        color: #7d5ac7;
        font-weight: inherit;
    }

    .step3-heading > p {
        max-width: 570px;
        margin: 18px auto 34px;
        color: #716a7e;
        font-size: 16px;
        line-height: 1.65;
    }

    .step3-card {
        max-width: 620px;
        margin: 0 auto;
        padding: 32px;
        border: 1px solid rgba(72, 58, 96, 0.11);
        border-radius: 24px;
        background: #ffffff;
        box-shadow: 0 24px 65px rgba(45, 34, 65, 0.09);
        text-align: left;
    }

    .step3-summary-grid {
        display: grid;
        grid-template-columns: 1fr 170px;
        gap: 12px;
        margin-bottom: 26px;
        padding: 18px;
        border-radius: 15px;
        background: #f8f6fa;
    }

    .step3-summary-grid div {
        min-width: 0;
    }

    .step3-summary-grid span,
    .step3-summary span {
        display: block;
        margin-bottom: 4px;
        color: #857e90;
        font-size: 11px;
        font-weight: 700;
        letter-spacing: 0.06em;
        text-transform: uppercase;
    }

    .step3-summary-grid strong,
    .step3-summary strong {
        display: block;
        overflow: hidden;
        color: #2c2438;
        font-size: 14px;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .step3-form label {
        display: block;
        margin-bottom: 8px;
        color: #332a40;
        font-size: 13px;
        font-weight: 700;
    }

    .step3-form input {
        width: 100%;
        min-height: 54px;
        padding: 0 16px;
        border: 1px solid rgba(72, 58, 96, 0.18);
        border-radius: 13px;
        outline: none;
        color: #241d30;
        font: inherit;
    }

    .step3-form input:focus {
        border-color: rgba(125, 90, 199, 0.68);
        box-shadow: 0 0 0 4px rgba(125, 90, 199, 0.09);
    }

    .step3-form button {
        display: flex;
        width: 100%;
        min-height: 56px;
        align-items: center;
        justify-content: center;
        gap: 12px;
        margin-top: 16px;
        border: 0;
        border-radius: 14px;
        background: #7d5ac7;
        color: #fff;
        font: inherit;
        font-weight: 800;
        cursor: pointer;
        box-shadow: 0 12px 26px rgba(125, 90, 199, 0.24);
    }

    .step3-error {
        margin-top: 7px;
        color: #b42318;
        font-size: 12px;
    }

    .step3-trust {
        display: flex;
        flex-wrap: wrap;
        justify-content: center;
        gap: 10px 20px;
        margin-top: 16px;
        color: #7a7484;
        font-size: 11px;
    }

    .step3-back-link,
    .step3-secondary-button {
        display: inline-flex;
        margin-top: 22px;
        color: #6e5a92;
        font-size: 13px;
        font-weight: 700;
        text-decoration: none;
    }

    .step3-success {
        text-align: center;
    }

    .step3-success-icon {
        display: grid;
        width: 58px;
        height: 58px;
        margin: 0 auto 10px;
        place-items: center;
        border-radius: 50%;
        background: #eef8f1;
        color: #397551;
        font-size: 27px;
        font-weight: 900;
    }

    .step3-success p {
        max-width: 490px;
        margin: 18px auto 26px;
        color: #716a7e;
        line-height: 1.6;
    }

    .step3-summary {
        padding: 16px;
        border-radius: 14px;
        background: #f8f6fa;
        text-align: left;
    }

    @media (max-width: 640px) {
        .step3-hero {
            padding: 54px 0 70px;
        }

        .step3-card {
            padding: 22px 18px;
            border-radius: 20px;
        }

        .step3-summary-grid {
            grid-template-columns: 1fr;
        }

        .step3-heading h1,
        .step3-success h1 {
            font-size: 36px;
        }
    }
</style>

@endsection
