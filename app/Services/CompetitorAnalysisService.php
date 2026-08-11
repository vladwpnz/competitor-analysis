<?php

namespace App\Services;

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

            $topCompetitors = $this->relevanceScorer->rank(
                array_values($candidatePool),
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