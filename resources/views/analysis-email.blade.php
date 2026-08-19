@extends('layouts.app')

@section('title', 'Save analysis details')
@section('meta_description', 'Save a contact email with the competitor shortlist kept in the current analysis session.')

@section('content')

@php
    $websiteDisplay = parse_url((string) $website, PHP_URL_HOST);

    if (!is_string($websiteDisplay) || trim($websiteDisplay) === '') {
        $websiteDisplay = (string) $website;
    }
@endphp

@include('partials.site-header', ['headerCtaLabel' => 'New analysis'])

<main id="main-content" class="step3-page">
    <section class="step3-hero">
        <div class="container step3-container">
            <nav class="workflow-progress" aria-label="Analysis progress">
                <ol>
                    <li class="is-complete"><span>1</span>Business</li>
                    <li class="is-complete"><span>2</span>Shortlist</li>
                    <li class="is-current" aria-current="step"><span>3</span>Contact</li>
                </ol>
            </nav>

            @if (session('analysis_email_saved'))
                <div class="step3-card step3-success">
                    <span class="workflow-step-label">STEP 3 OF 3</span>
                    <div class="step3-success-icon" aria-hidden="true">Saved</div>
                    <h1>Contact saved for this session.</h1>
                    <p>
                        Your email now sits alongside
                        {{ $competitorCount }}
                        {{ \Illuminate\Support\Str::plural('competitor', $competitorCount) }}
                        in the current analysis session.
                    </p>

                    <dl class="step3-summary-grid">
                        <div>
                            <dt>Contact email</dt>
                            <dd>{{ $email }}</dd>
                        </div>
                        <div>
                            <dt>Competitors selected</dt>
                            <dd>{{ $competitorCount }}</dd>
                        </div>
                    </dl>

                    <a href="{{ route('competitors') }}" class="step3-secondary-button">
                        Back to competitors
                    </a>
                </div>
            @else
                <div class="step3-layout">
                    <div class="step3-heading">
                        <span class="workflow-step-label">STEP 3 OF 3</span>
                        <h1>Keep a contact with this analysis.</h1>
                        <p>
                            The shortlist is ready. Save an email address alongside it for the current session.
                        </p>

                        <dl class="step3-analysis-summary">
                            <div>
                                <dt>Business</dt>
                                <dd>{{ $googleBusiness }}</dd>
                                <span>{{ $websiteDisplay }}</span>
                            </div>
                            <div>
                                <dt>Final shortlist</dt>
                                <dd>
                                    {{ $competitorCount }}
                                    {{ \Illuminate\Support\Str::plural('competitor', $competitorCount) }}
                                </dd>
                                <span>Selected in this session</span>
                            </div>
                        </dl>

                        <a href="{{ route('competitors') }}" class="step3-back-link">
                            Back to shortlist
                        </a>
                    </div>

                    <div class="step3-card">
                        <div class="step3-card-heading">
                            <span>Contact details</span>
                            <h2>Save analysis email</h2>
                            <p>This records the address in the current Laravel session. It does not send a report.</p>
                        </div>

                        <form
                            action="{{ route('analysis.email.store') }}"
                            method="POST"
                            class="step3-form"
                        >
                            @csrf

                            <label for="analysis-email">Email address</label>

                            <input
                                id="analysis-email"
                                name="email"
                                type="email"
                                value="{{ old('email', $email) }}"
                                placeholder="you@company.com"
                                autocomplete="email"
                                aria-describedby="analysis-email-helper"
                                required
                                autofocus
                            >

                            <p id="analysis-email-helper" class="step3-helper">
                                Use the address you want associated with this analysis.
                            </p>

                            @error('email')
                                <div class="step3-error" role="alert">
                                    {{ $message }}
                                </div>
                            @enderror

                            <button type="submit">
                                Save Email
                                <span aria-hidden="true">↗</span>
                            </button>

                            <div class="step3-trust">
                                <span>No account required</span>
                                <span>Session-backed storage</span>
                            </div>
                        </form>
                    </div>
                </div>
            @endif
        </div>
    </section>
</main>

@include('partials.site-footer')


@endsection
