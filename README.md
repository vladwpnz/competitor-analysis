# Competitor Intelligence

Competitor Intelligence is a Laravel portfolio application that turns a business website and Google Business Profile into a ranked, reviewable competitor shortlist. It combines website signals, Google Places data, optional Gemini classification and discovery, deterministic fallback rules, and evidence-based relevance scoring.

The project focuses on the discovery stage of competitor analysis: identifying businesses that are genuinely comparable, filtering weak or duplicate candidates, and giving the user control over the final selection.

## What the application does

1. Accepts a business website and an explicitly selected Google Business Profile.
2. Scans the website for titles, descriptions, headings, and readable homepage content.
3. Confirms that the selected business profile is compatible with the submitted website.
4. Builds a normalized business profile from website and Google Places signals.
5. Classifies the operating model, vertical, market scope, and search intent.
6. Discovers candidates through Google Places, Gemini-assisted website discovery, or both, depending on the market.
7. Scores candidates using type compatibility, service overlap, query evidence, distance, and other business signals.
8. Removes the subject business, related locations, unavailable businesses, and duplicate companies.
9. Presents the strongest matches for manual review, addition, and removal.
10. Stores the selected shortlist and contact email in the current session.

## Major features

- Safe website scanning with URL validation, redirect handling, and public-address checks.
- Google Business autocomplete and place-detail retrieval through Google Places API (New).
- Gemini-based structured business classification when configured.
- Deterministic classification fallback when Gemini is unavailable or returns invalid data.
- Separate strategies for local, hybrid, broader physical, and digital/global markets.
- Staged geographic expansion for local discovery.
- AI-assisted direct competitor discovery for suitable broader and digital businesses.
- Relevance scoring and ranking based on business type, services, search evidence, and geography.
- Duplicate-company filtering and own-business exclusion.
- Manual competitor search, addition, and removal.
- Graceful handling of blocked websites and external API failures.

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
| Normalized business profile construction | `BusinessProfileBuilder` |
| Deterministic classification | `BusinessClassifier` |
| Optional Gemini classification | `GeminiBusinessClassifier` and `AiBusinessClassifierManager` |
| Search intent stabilization | `BusinessIntelligenceService` and `SearchProfileBuilder` |
| Google Places access | `GooglePlacesService` |
| Local and broader candidate search | `CompetitorSearchService` |
| Gemini direct competitor discovery | `GeminiCompetitorDiscoveryService` |
| Candidate enrichment | `CompetitorEnrichmentService` |
| Relevance scoring and ranking | `CompetitorRelevanceScorer` |
| Pipeline orchestration | `CompetitorAnalysisService` |

At a high level, the request flows through website and business-profile validation, profile construction, classification, search-profile generation, candidate discovery, scoring, deduplication, enrichment, and final manual review. External-service failures are handled at their boundaries so the application can use an appropriate fallback instead of substituting mock results.

## External integrations

### Google Places API (New)

Google Places powers business autocomplete, place details, and location-aware competitor search. A valid API key is required for live Google Business selection and Google-based discovery.

Relevant configuration:

- `GOOGLE_PLACES_API_KEY`
- `GOOGLE_PLACES_BASE_URL`

### Gemini

Gemini provides structured business classification and direct competitor discovery for supported market types. It is optional for classification: when no key is configured, the application uses its deterministic classifier. Discovery paths that depend on Gemini degrade to supported alternatives or return a clear unavailable state.

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
APP_NAME="Competitor Intelligence"
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

The example environment file also documents optional timeout and output-limit controls for Gemini. Keep API keys in the local `.env` file or a deployment secret manager.

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

This repository is maintained as an independent technical portfolio project. A dedicated visual redesign and public screenshots are planned separately; the current interface remains focused on preserving the working analysis flow.
