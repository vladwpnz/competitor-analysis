# Account Intelligence

Account Intelligence is a Laravel application that analyzes a reference company and discovers ranked lookalike B2B accounts using website signals, Google Places, optional Gemini assistance, deterministic fallback logic, and evidence-based fit ranking.

The reference company is an example of the kind of account the user wants to find more of. It can be a strong customer, an ideal customer profile example, an attractive target account, or another representative company in the market. Recommendations are based on commercially useful similarities, not only on direct competition.

## What the application does

1. Accepts a reference company website and an explicitly selected reference Google Business Profile.
2. Scans the website for titles, descriptions, headings, and readable homepage content.
3. Confirms that the selected Google Business Profile is compatible with the submitted website.
4. Builds a normalized account profile from website and Google Places signals.
5. Classifies the business model, industry, operating model, market scope, target customers, and search intent.
6. Discovers similar companies through Google Places, Gemini-assisted website discovery, or both, depending on the market.
7. Ranks candidates by account fit using business type, service overlap, search evidence, operating context, and geography where it matters.
8. Removes the reference company, related locations, unavailable businesses, and duplicate companies.
9. Presents the strongest recommended accounts for review, manual addition, and removal.
10. Stores the prospect shortlist and contact email in the current session.

The working flow is:

`Reference company -> website and Google signals -> profile construction -> classification -> account discovery -> fit ranking -> manual shortlist review`

## Major features

- Safe website scanning with URL validation, redirect handling, and public-address checks.
- Google Business autocomplete and place-detail retrieval through Google Places API (New).
- Gemini-based structured business classification when configured.
- Deterministic classification fallback when Gemini is unavailable or returns invalid data.
- Separate discovery strategies for local, hybrid, broader physical, and digital or global markets.
- Staged geographic expansion for similar local businesses.
- AI-assisted lookalike discovery for suitable broader and digital markets.
- Fit ranking based on business type, services, search evidence, operating model signals, and geography.
- Duplicate-company filtering and reference-company exclusion.
- Manual account search, addition, and removal without a fabricated Fit score.
- Graceful handling of blocked websites and external API failures.
- A shared synchronous request deadline with bounded provider calls and reserved response time.

## Technology stack

- PHP 8.2 or newer
- Laravel 12
- Blade templates and custom responsive CSS
- Vite 6 with Tailwind CSS 4 tooling
- Vanilla JavaScript and Axios
- SQLite by default, with Laravel-supported database alternatives available through configuration
- PHPUnit 11 and Laravel's test runner
- Laravel Pint for PHP formatting

## Architecture and analysis flow

The application keeps the discovery pipeline split into focused services:

| Responsibility | Main component |
| --- | --- |
| Website retrieval and signal extraction | `WebsiteScanner` |
| Normalized reference profile construction | `BusinessProfileBuilder` |
| Deterministic classification | `BusinessClassifier` |
| Optional Gemini classification | `GeminiBusinessClassifier` and `AiBusinessClassifierManager` |
| Search-profile stabilization | `BusinessIntelligenceService` and `SearchProfileBuilder` |
| Google Places access | `GooglePlacesService` |
| Local and broader account search | `CompetitorSearchService` |
| Gemini lookalike account discovery | `GeminiCompetitorDiscoveryService` |
| Candidate enrichment | `CompetitorEnrichmentService` |
| Account fit scoring and ranking | `CompetitorRelevanceScorer` |
| Pipeline orchestration | `CompetitorAnalysisService` |

Several internal classes and session fields still use `Competitor*` names for backward compatibility. Their behavior now supports Account Intelligence. Renaming those identifiers is intentionally left for a focused refactor.

At a high level, the request moves through website and Google Business validation, reference profile construction, classification, search-profile generation, candidate discovery, scoring, deduplication, enrichment, and manual review. External-service failures are handled at their boundaries so the application can use a supported fallback without substituting mock results.

## Runtime behavior

The live analysis shares a 24-second request budget and reserves time to persist the session and return the redirect. Provider calls use bounded connection and response timeouts. Independent Google searches and Place Details enrichment run in pools, and the pipeline stops expanding once it has enough strong matches. Partial enrichment failures do not discard otherwise usable accounts.

## External integrations

### Google Places API (New)

Google Places powers reference-company autocomplete, place details, and location-aware account discovery. A valid API key is required for live Google Business selection and Google-based discovery.

Relevant configuration:

- `GOOGLE_PLACES_API_KEY`
- `GOOGLE_PLACES_BASE_URL`

### Gemini

Gemini can provide structured business classification and lookalike account discovery. Classification is optional: when no key is configured, the application uses its deterministic classifier. Discovery paths that depend on Gemini fall back to supported Google Places behavior or return a clear unavailable state.

Relevant configuration:

- `AI_CLASSIFIER_PROVIDER`
- `GEMINI_API_KEY`
- `GEMINI_MODEL`
- `GEMINI_API_ENDPOINT`
- `GEMINI_CONNECT_TIMEOUT`
- `GEMINI_TIMEOUT`
- `GEMINI_DISCOVERY_TIMEOUT`
- `GEMINI_MAX_OUTPUT_TOKENS`
- `GEMINI_DISCOVERY_MAX_OUTPUT_TOKENS`
- `GEMINI_THINKING_LEVEL`

## Local setup

### Prerequisites

- PHP 8.2+
- Composer
- Node.js and npm
- SQLite, unless another supported database is configured
- Google Places API credentials for live business lookup
- Optional Gemini API credentials for AI-assisted classification and discovery

### Installation

```bash
git clone https://github.com/vladwpnz/competitor-analysis.git
cd competitor-analysis
composer install
cp .env.example .env
php artisan key:generate
```

Create an empty `database/database.sqlite` file, then initialize the database:

```bash
php artisan migrate
```

Install and build the frontend assets:

```bash
npm install
npm run build
```

For local development, run Laravel and Vite in separate terminals:

```bash
php artisan serve
```

```bash
npm run dev
```

The application uses database-backed sessions, cache, and queues by default. Run migrations before opening the analysis flow.

## Environment configuration

Start from `.env.example`. The most important values are:

```dotenv
APP_NAME="Account Intelligence"
APP_ENV=local
APP_KEY=
APP_DEBUG=true
APP_URL=http://localhost

DB_CONNECTION=sqlite
SESSION_DRIVER=database
CACHE_STORE=database
QUEUE_CONNECTION=database

GOOGLE_PLACES_API_KEY=
GOOGLE_PLACES_BASE_URL=https://places.googleapis.com/v1

AI_CLASSIFIER_PROVIDER=gemini
GEMINI_API_KEY=
GEMINI_MODEL=gemini-3.6-flash
```

The example environment file also documents the synchronous analysis budget,
provider timeouts, and Gemini output limits. Keep API keys in the local `.env`
file or a deployment secret manager.

## Tests and checks

Run the full automated test suite:

```bash
php artisan test
```

Check PHP formatting without modifying files:

```bash
vendor/bin/pint --test
```

Verify the production frontend build:

```bash
npm run build
```

## Secrets and repository hygiene

Never commit `.env`, API keys, access tokens, mail passwords, private keys, database snapshots containing private data, or deployment archives. The tracked `.env.example` contains only safe defaults and empty credential placeholders.

Before publishing changes, review the Git diff and run a repository-wide secret scan in addition to the automated tests.

## Project status

This repository is maintained as an independent technical portfolio project. It demonstrates a complete, responsive Account Intelligence workflow with real provider integrations, deterministic fallbacks, editable shortlist state, and bounded live-analysis runtime.
