<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Throwable;

class CompetitorAnalysisService
{
    private const TARGET_STRONG_MATCHES = 5;

    private const CANDIDATES_PER_STAGE = 30;

    public function __construct(
        private readonly BusinessProfileBuilder $businessProfileBuilder,
        private readonly BusinessClassifier $businessClassifier,
        private readonly SearchProfileBuilder $searchProfileBuilder,
        private readonly CompetitorSearchService $competitorSearchService,
        private readonly CompetitorRelevanceScorer $relevanceScorer,
        private readonly CompetitorEnrichmentService $competitorEnrichmentService
    ) {
    }

    public function analyze(
        array $websiteScan,
        ?array $googlePlace = null
    ): array {
        $businessProfile = $this->businessProfileBuilder->build(
            $websiteScan,
            $googlePlace
        );

        /*
         * AI classification is optional. BusinessIntelligenceService falls
         * back to the existing BusinessClassifier when no provider/key is
         * configured or when the AI request fails.
         *
         * Resolve it here instead of changing this constructor so existing
         * tests/manual service construction and the current pipeline remain
         * backwards compatible.
         */
        $businessIntelligence = app(
            BusinessIntelligenceService::class
        );

        $classification = $businessIntelligence->classify(
            $businessProfile
        );

        $searchProfile = $this->searchProfileBuilder->build(
            $businessProfile,
            $classification
        );

        $searchProfile = $businessIntelligence->applySearchIntent(
            $searchProfile,
            $classification
        );

        $isDigitalGlobal = data_get(
            $classification,
            'discovery_mode'
        ) === 'digital_global';

        if ($isDigitalGlobal) {
            $digitalCompetitors = $this->discoverDigitalCompetitors(
                $businessProfile,
                $classification
            );

            if ($digitalCompetitors !== null) {
                return $this->digitalAnalysisResult(
                    $businessProfile,
                    $classification,
                    $searchProfile,
                    $digitalCompetitors
                );
            }
        }

        $candidatePool = [];
        $completedStages = [];
        $topCompetitors = [];
        $strongMatchCount = 0;

        $plannedStages = $this->searchStages(
            $searchProfile
        );

        foreach ($plannedStages as $stage) {
            $stageCandidates = $this->competitorSearchService
                ->findCandidates(
                    $searchProfile,
                    self::CANDIDATES_PER_STAGE,
                    $stage
                );

            $candidatePool = $this->mergeCandidatePools(
                $candidatePool,
                $stageCandidates
            );

            $completedStages[] = $stage;

            $rankedCandidates = $this->relevanceScorer->rank(
                array_values($candidatePool),
                $searchProfile,
                self::CANDIDATES_PER_STAGE
            );

            $topCompetitors = $this->distinctCompanies(
                $rankedCandidates,
                $searchProfile,
                self::TARGET_STRONG_MATCHES
            );

            $strongMatchCount = $this->countStrongMatches(
                $topCompetitors
            );

            if (
                $strongMatchCount
                >= self::TARGET_STRONG_MATCHES
            ) {
                break;
            }
        }

        $topCompetitors = $this->competitorEnrichmentService->enrich(
            $topCompetitors
        );

        return [
            'business_profile' => $businessProfile,

            'classification' => $classification,

            'search_profile' => $searchProfile,

            'candidate_count' => count($candidatePool),

            'top_competitors' => $topCompetitors,

            'strong_match_count' => $strongMatchCount,

            'has_competitors' => $topCompetitors !== [],

            'search_stages' => $completedStages,

            'search_stage_count' => count($completedStages),

            'search_exhausted' =>
                $strongMatchCount < self::TARGET_STRONG_MATCHES
                && count($completedStages) === count($plannedStages),

            'discovery_source' =>
                $isDigitalGlobal
                    ? 'google_places_fallback'
                    : 'google_places',
        ];
    }

    private function discoverDigitalCompetitors(
        array $businessProfile,
        array $classification
    ): ?array {
        $discovery = app(
            GeminiCompetitorDiscoveryService::class
        );

        if (! $discovery->isConfigured()) {
            return null;
        }

        try {
            return $discovery->discover(
                $businessProfile,
                $classification
            );
        } catch (Throwable $exception) {
            /*
             * Direct AI discovery is an accuracy enhancement, not a hard
             * dependency. Never log provider payloads, domains, or secrets.
             */
            Log::warning(
                'AI direct competitor discovery failed; using Google Places fallback.',
                [
                    'exception'
                        => get_class(
                            $exception
                        ),
                ]
            );

            return null;
        }
    }

    private function digitalAnalysisResult(
        array $businessProfile,
        array $classification,
        array $searchProfile,
        array $discoveredCompetitors
    ): array {
        $candidatePool = [];

        foreach (
            array_values($discoveredCompetitors)
            as $index => $competitor
        ) {
            if (! is_array($competitor)) {
                continue;
            }

            $candidate = $this->digitalCandidate(
                $competitor,
                $index
            );

            if ($candidate === null) {
                continue;
            }

            $candidatePool[] = $candidate;
        }

        $topCompetitors = array_slice(
            $candidatePool,
            0,
            self::TARGET_STRONG_MATCHES
        );

        $strongMatchCount = count(
            $topCompetitors
        );

        return [
            'business_profile' => $businessProfile,

            'classification' => $classification,

            'search_profile' => $searchProfile,

            'candidate_count' => count($candidatePool),

            'top_competitors' => $topCompetitors,

            /*
             * Keep the full AI discovery pool in the session-backed
             * analysis result. Step 2 shows only the top five, while
             * manual digital search can reuse the remaining candidates
             * without spending another Gemini request.
             */
            'digital_candidate_pool' => $candidatePool,

            'strong_match_count' => $strongMatchCount,

            'has_competitors' => $topCompetitors !== [],

            'search_stages' => [
                'ai_direct',
            ],

            'search_stage_count' => 1,

            'search_exhausted' =>
                $strongMatchCount < self::TARGET_STRONG_MATCHES,

            'discovery_source' => 'ai_direct',
        ];
    }

    private function digitalCandidate(
        array $competitor,
        int $index
    ): ?array {
        $name = data_get(
            $competitor,
            'name'
        );

        $domain = data_get(
            $competitor,
            'domain'
        );

        $reason = data_get(
            $competitor,
            'reason'
        );

        if (
            ! is_string($name)
            || trim($name) === ''
            || ! is_string($domain)
            || trim($domain) === ''
            || ! is_string($reason)
            || trim($reason) === ''
        ) {
            return null;
        }

        $name = trim($name);
        $domain = mb_strtolower(
            trim($domain)
        );
        $reason = trim($reason);

        return [
            'id' =>
                'ai-'
                . substr(
                    hash(
                        'sha256',
                        $domain
                    ),
                    0,
                    24
                ),

            'displayName' => [
                'text' => $name,
            ],

            'primaryType'
                => 'digital_platform',

            'primaryTypeDisplayName' => [
                'text'
                    => 'Direct Competitor',
            ],

            'types' => [
                'digital_platform',
            ],

            'websiteUri'
                => 'https://' . $domain,

            '_match' => [
                'queries' => [],
                'executed_queries' => [],
                'query_hits' => 0,
                'search_modes' => [
                    'ai_direct',
                ],
                'distance_km' => null,
            ],

            '_relevance' => [
                'quality' => 'strong',
                'strong_match' => true,
                'type_compatible' => true,
                'evidence' => [
                    'discovery_source'
                        => 'ai_direct',
                    'direct_rank'
                        => $index + 1,
                ],
            ],

            '_discovery' => [
                'source' => 'ai',
                'domain' => $domain,
                'reason' => $reason,
                'rank' => $index + 1,
            ],
        ];
    }

    private function searchStages(
        array $searchProfile
    ): array {
        $marketScope = (string) data_get(
            $searchProfile,
            'market_scope',
            'hybrid'
        );

        if ($marketScope === 'broader') {
            return [
                CompetitorSearchService::STAGE_INITIAL,
            ];
        }

        $latitude = data_get(
            $searchProfile,
            'location.latitude'
        );

        $longitude = data_get(
            $searchProfile,
            'location.longitude'
        );

        $address = data_get(
            $searchProfile,
            'location.address'
        );

        $hasCoordinates =
            is_numeric($latitude)
            && is_numeric($longitude);

        $hasAddress =
            is_string($address)
            && trim($address) !== '';

        if (! $hasCoordinates && ! $hasAddress) {
            return [
                CompetitorSearchService::STAGE_INITIAL,
            ];
        }

        if ($hasCoordinates && $hasAddress) {
            return [
                CompetitorSearchService::STAGE_INITIAL,
                CompetitorSearchService::STAGE_LOCALITY,
                CompetitorSearchService::STAGE_REGION,
                CompetitorSearchService::STAGE_COUNTRY,
                CompetitorSearchService::STAGE_RELEVANCE,
            ];
        }

        if ($hasCoordinates) {
            return [
                CompetitorSearchService::STAGE_INITIAL,
                CompetitorSearchService::STAGE_RELEVANCE,
            ];
        }

        /*
         * Without coordinates, CompetitorSearchService treats
         * the initial stage as a locality-context search already.
         * Starting directly with region afterwards avoids sending
         * the same locality query twice.
         */
        return [
            CompetitorSearchService::STAGE_INITIAL,
            CompetitorSearchService::STAGE_REGION,
            CompetitorSearchService::STAGE_COUNTRY,
            CompetitorSearchService::STAGE_RELEVANCE,
        ];
    }

    private function mergeCandidatePools(
        array $pool,
        array $incomingCandidates
    ): array {
        foreach ($incomingCandidates as $candidate) {
            if (! is_array($candidate)) {
                continue;
            }

            $key = $this->candidateKey(
                $candidate
            );

            if ($key === null) {
                continue;
            }

            if (! isset($pool[$key])) {
                $pool[$key] = $candidate;
                continue;
            }

            $pool[$key] = $this->mergeCandidate(
                $pool[$key],
                $candidate
            );
        }

        return $pool;
    }

    private function mergeCandidate(
        array $existing,
        array $incoming
    ): array {
        /*
         * Keep metadata from the first discovery, while filling
         * any top-level fields that were absent in that response.
         */
        $merged = $existing + $incoming;

        $existingMatch = data_get(
            $existing,
            '_match',
            []
        );

        $incomingMatch = data_get(
            $incoming,
            '_match',
            []
        );

        $existingMatch = is_array($existingMatch)
            ? $existingMatch
            : [];

        $incomingMatch = is_array($incomingMatch)
            ? $incomingMatch
            : [];

        $queries = $this->mergeStringLists(
            data_get($existingMatch, 'queries', []),
            data_get($incomingMatch, 'queries', [])
        );

        $executedQueries = $this->mergeStringLists(
            data_get($existingMatch, 'executed_queries', []),
            data_get($incomingMatch, 'executed_queries', [])
        );

        $searchModes = $this->mergeStringLists(
            data_get($existingMatch, 'search_modes', []),
            data_get($incomingMatch, 'search_modes', [])
        );

        $merged['_match'] = [
            'queries' => $queries,

            'executed_queries' => $executedQueries,

            'query_hits' => count($queries),

            'search_modes' => $searchModes,

            'distance_km' => $this->minimumDistance(
                data_get($existingMatch, 'distance_km'),
                data_get($incomingMatch, 'distance_km')
            ),
        ];

        return $merged;
    }

    private function mergeStringLists(
        mixed $left,
        mixed $right
    ): array {
        $merged = [];

        foreach ([$left, $right] as $values) {
            if (! is_array($values)) {
                continue;
            }

            foreach ($values as $value) {
                if (! is_string($value)) {
                    continue;
                }

                $value = trim($value);

                if ($value === '') {
                    continue;
                }

                $key = mb_strtolower($value);

                if (isset($merged[$key])) {
                    continue;
                }

                $merged[$key] = $value;
            }
        }

        return array_values($merged);
    }

    private function minimumDistance(
        mixed $left,
        mixed $right
    ): ?float {
        $left = is_numeric($left)
            ? (float) $left
            : null;

        $right = is_numeric($right)
            ? (float) $right
            : null;

        if ($left === null) {
            return $right;
        }

        if ($right === null) {
            return $left;
        }

        return min($left, $right);
    }

    private function candidateKey(
        array $candidate
    ): ?string {
        $name = $this->normalizeText(
            data_get(
                $candidate,
                'displayName.text'
            )
        );

        if ($name !== null) {
            return 'name:' . $name;
        }

        $placeId = data_get(
            $candidate,
            'id'
        );

        if (
            is_string($placeId)
            && trim($placeId) !== ''
        ) {
            return 'place:' . trim($placeId);
        }

        return null;
    }

    private function normalizeText(
        mixed $value
    ): ?string {
        if (! is_string($value)) {
            return null;
        }

        $value = mb_strtolower(
            trim($value)
        );

        if ($value === '') {
            return null;
        }

        $value = preg_replace(
            '/[^\p{L}\p{N}]+/u',
            ' ',
            $value
        ) ?? $value;

        $value = preg_replace(
            '/\s+/u',
            ' ',
            $value
        ) ?? $value;

        $value = trim($value);

        return $value === ''
            ? null
            : $value;
    }

    private function distinctCompanies(
        array $rankedCandidates,
        array $searchProfile,
        int $limit
    ): array {
        $distinct = [];
        $seenCompanies = [];

        foreach ($rankedCandidates as $candidate) {
            if (! is_array($candidate)) {
                continue;
            }

            $companyKey = $this->companyIdentityKey(
                $candidate,
                $searchProfile
            );

            if (
                $companyKey !== null
                && isset(
                    $seenCompanies[
                        $companyKey
                    ]
                )
            ) {
                continue;
            }

            if ($companyKey !== null) {
                $seenCompanies[$companyKey]
                    = true;
            }

            $distinct[] = $candidate;

            if (count($distinct) >= $limit) {
                break;
            }
        }

        return $distinct;
    }

    private function companyIdentityKey(
        array $candidate,
        array $searchProfile
    ): ?string {
        /*
         * Website host is the strongest company-level identifier when
         * Google includes it in the search response.
         */
        $website = data_get(
            $candidate,
            'websiteUri'
        );

        if (
            is_string($website)
            && trim($website) !== ''
        ) {
            $website = trim($website);

            if (! str_contains(
                $website,
                '://'
            )) {
                $website =
                    'https://' . $website;
            }

            $host = parse_url(
                $website,
                PHP_URL_HOST
            );

            if (
                is_string($host)
                && trim($host) !== ''
            ) {
                $host = mb_strtolower(
                    trim($host)
                );

                if (str_starts_with(
                    $host,
                    'www.'
                )) {
                    $host = substr(
                        $host,
                        4
                    );
                }

                if ($host !== '') {
                    return 'host:' . $host;
                }
            }
        }

        $name = $this->normalizeText(
            data_get(
                $candidate,
                'displayName.text'
            )
        );

        if ($name === null) {
            return null;
        }

        $noiseTokens = [
            'the' => true,
            'of' => true,
            'at' => true,
            'and' => true,
            'inc' => true,
            'incorporated' => true,
            'llc' => true,
            'ltd' => true,
            'limited' => true,
            'corp' => true,
            'corporation' => true,
            'company' => true,
            'co' => true,
            'plc' => true,
            'pllc' => true,
            'pc' => true,
            'service' => true,
            'services' => true,
            'department' => true,
        ];

        /*
         * Remove industry words that every result naturally shares.
         * For a plumber, "plumbing" should not become part of the brand
         * identity. For a dermatology search, the same applies to
         * "dermatology", etc.
         */
        $intentValues = [
            data_get(
                $searchProfile,
                'business_type'
            ),
        ];

        $services = data_get(
            $searchProfile,
            'services',
            []
        );

        if (is_array($services)) {
            foreach ($services as $service) {
                $intentValues[] = $service;
            }
        }

        foreach ($intentValues as $value) {
            $normalized = $this->normalizeText(
                $value
            );

            if ($normalized === null) {
                continue;
            }

            foreach (
                preg_split(
                    '/\s+/u',
                    $normalized,
                    -1,
                    PREG_SPLIT_NO_EMPTY
                ) ?: []
                as $token
            ) {
                $noiseTokens[$token] = true;
            }
        }

        /*
         * Google branch names frequently append the city/state:
         * "Brand Plumbing Austin TX".
         *
         * The first comma-separated address part is the street address;
         * everything after it is locality/region/country context.
         */
        $address = data_get(
            $searchProfile,
            'location.address'
        );

        if (
            is_string($address)
            && str_contains(
                $address,
                ','
            )
        ) {
            $addressParts = array_map(
                'trim',
                explode(
                    ',',
                    $address
                )
            );

            array_shift($addressParts);

            foreach ($addressParts as $part) {
                $normalized = $this->normalizeText(
                    $part
                );

                if ($normalized === null) {
                    continue;
                }

                foreach (
                    preg_split(
                        '/\s+/u',
                        $normalized,
                        -1,
                        PREG_SPLIT_NO_EMPTY
                    ) ?: []
                    as $token
                ) {
                    if (
                        preg_match(
                            '/^\d+$/u',
                            $token
                        ) === 1
                    ) {
                        continue;
                    }

                    $noiseTokens[$token] = true;
                }
            }
        }

        $tokens = preg_split(
            '/\s+/u',
            $name,
            -1,
            PREG_SPLIT_NO_EMPTY
        ) ?: [];

        $brandTokens = [];

        foreach ($tokens as $token) {
            if (isset($noiseTokens[$token])) {
                continue;
            }

            /*
             * Ignore postal-code fragments containing digits.
             */
            if (
                preg_match(
                    '/\d/u',
                    $token
                ) === 1
            ) {
                continue;
            }

            $brandTokens[] = $token;
        }

        /*
         * Concatenating intentionally makes:
         *
         * Rooterman
         * Rooter-Man
         *
         * resolve to the same stable brand core.
         */
        $brandCore = implode(
            '',
            $brandTokens
        );

        $brandCore = preg_replace(
            '/[^\p{L}\p{N}]+/u',
            '',
            $brandCore
        ) ?? $brandCore;

        /*
         * A very short core such as "ABC" is not safe enough for fuzzy
         * company deduplication. Fall back to the full normalized name.
         */
        if (mb_strlen($brandCore) < 5) {
            $brandCore = preg_replace(
                '/\s+/u',
                '',
                $name
            ) ?? $name;
        }

        if ($brandCore === '') {
            return null;
        }

        return 'brand:' . $brandCore;
    }

    private function countStrongMatches(
        array $competitors
    ): int {
        return count(
            array_filter(
                $competitors,
                static fn (array $competitor): bool =>
                    (bool) data_get(
                        $competitor,
                        '_relevance.strong_match',
                        false
                    )
            )
        );
    }
}
