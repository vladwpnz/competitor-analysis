<?php

namespace App\Services;

class CompetitorRelevanceScorer
{
    private const MAX_RANK_RESULTS = 20;

    public function rank(
        array $candidates,
        array $searchProfile,
        int $limit = 5
    ): array {
        $limit = max(
            1,
            min($limit, self::MAX_RANK_RESULTS)
        );

        $scored = [];

        foreach ($candidates as $candidate) {
            if (! is_array($candidate)) {
                continue;
            }

            $candidate['_relevance'] = $this->score(
                $candidate,
                $searchProfile
            );

            $scored[] = $candidate;
        }

        $preferDistance = (string) data_get(
            $searchProfile,
            'market_scope',
            'hybrid'
        ) !== 'broader';

        usort(
            $scored,
            function (
                array $left,
                array $right
            ) use ($preferDistance): int {
                $leftCompatible = (bool) data_get(
                    $left,
                    '_relevance.type_compatible',
                    false
                );

                $rightCompatible = (bool) data_get(
                    $right,
                    '_relevance.type_compatible',
                    false
                );

                if ($leftCompatible !== $rightCompatible) {
                    return $rightCompatible <=> $leftCompatible;
                }

                $leftScore = (float) data_get(
                    $left,
                    '_relevance.score',
                    0
                );

                $rightScore = (float) data_get(
                    $right,
                    '_relevance.score',
                    0
                );

                if ($leftScore !== $rightScore) {
                    return $rightScore <=> $leftScore;
                }

                $leftHits = (int) data_get(
                    $left,
                    '_match.query_hits',
                    0
                );

                $rightHits = (int) data_get(
                    $right,
                    '_match.query_hits',
                    0
                );

                if ($leftHits !== $rightHits) {
                    return $rightHits <=> $leftHits;
                }

                if ($preferDistance) {
                    return $this->compareDistance(
                        data_get(
                            $left,
                            '_match.distance_km'
                        ),
                        data_get(
                            $right,
                            '_match.distance_km'
                        )
                    );
                }

                return strcmp(
                    mb_strtolower((string) data_get(
                        $left,
                        'displayName.text',
                        ''
                    )),
                    mb_strtolower((string) data_get(
                        $right,
                        'displayName.text',
                        ''
                    ))
                );
            }
        );

        return array_slice(
            $scored,
            0,
            $limit
        );
    }

    public function score(
        array $candidate,
        array $searchProfile
    ): array {
        $marketScope = (string) data_get(
            $searchProfile,
            'market_scope',
            'hybrid'
        );

        $weights = $this->weights(
            $marketScope
        );

        $roleRatio = $this->businessRoleEvidence(
            $candidate,
            $searchProfile
        );

        $typeRatio = max(
            $this->typeSimilarity(
                $candidate,
                $searchProfile
            ),
            $roleRatio
        );

        $queryRatio = $this->queryEvidence(
            $candidate,
            $searchProfile
        );

        $serviceRatio = $this->serviceEvidence(
            $candidate,
            $searchProfile
        );

        $technologyIntentRatio =
            $this->technologyIntentEvidence(
                $candidate,
                $searchProfile
            );

        $distanceRatio = $this->distanceEvidence(
            data_get(
                $candidate,
                '_match.distance_km'
            ),
            $marketScope
        );

        $breakdown = [
            'business_type' => round(
                $typeRatio * $weights['business_type'],
                2
            ),

            'query_evidence' => round(
                $queryRatio * $weights['query_evidence'],
                2
            ),

            'service_evidence' => round(
                $serviceRatio * $weights['service_evidence'],
                2
            ),

            'distance' => round(
                $distanceRatio * $weights['distance'],
                2
            ),
        ];

        $score = round(
            array_sum($breakdown),
            2
        );

        $score = max(
            0,
            min(100, $score)
        );

        $focusedTechnologySearch =
            $marketScope === 'broader'
            && $this->hasFocusedTechnologyIntent(
                $searchProfile
            );

        /*
         * A broad technology query such as "Software Company" can
         * surface agencies, dev shops and unrelated IT businesses. When
         * the target profile contains stronger product intent (CRM,
         * customer platform, marketing automation, etc.), require that
         * the candidate is connected to at least one of those specific
         * intents. This keeps generic software-development companies
         * below actual product competitors without affecting broad tech
         * searches that genuinely have only generic signals.
         */
        if (
            $focusedTechnologySearch
            && $technologyIntentRatio < 0.25
        ) {
            $score = round(
                $score * 0.55,
                2
            );
        }

        $focusedIndustrialDistributorSearch =
            $marketScope === 'broader'
            && data_get($searchProfile, 'vertical') === 'industrial'
            && $this->targetsDistributorModel($searchProfile);

        /*
         * Google often labels industrial distributors as Manufacturer.
         * For an AI-classified distributor target, prefer candidates that
         * expose supplier/distributor/sales evidence, but keep manufacturer-
         * only results as a fallback when the candidate pool is thin.
         */
        if (
            $focusedIndustrialDistributorSearch
            && $roleRatio < 0.5
        ) {
            $score = round(
                $score * 0.45,
                2
            );
        }

        /*
         * Query hits alone are not enough to make a local business
         * a strong competitor. Google can return adjacent categories
         * for a relevant query, so require intrinsic category/service
         * evidence for local markets. Focused broader technology
         * searches additionally require specific product-intent evidence.
         */
        $typeCompatible = match (true) {
            $focusedTechnologySearch =>
                $technologyIntentRatio >= 0.25,

            $focusedIndustrialDistributorSearch =>
                $roleRatio >= 0.5,

            $marketScope === 'local' =>
                $typeRatio >= 0.45
                || $serviceRatio >= 0.15,

            default => true,
        };

        return [
            'score' => $score,

            /*
             * This is our own matching heuristic.
             * It must not be presented to users as an exact
             * mathematical percentage of business similarity.
             */
            'quality' => $this->quality(
                $score
            ),

            'strong_match' =>
                $score >= 70
                && $typeCompatible,

            'type_compatible' => $typeCompatible,

            'breakdown' => $breakdown,

            'evidence' => [
                'query_hits' => (int) data_get(
                    $candidate,
                    '_match.query_hits',
                    0
                ),

                'matched_queries' => data_get(
                    $candidate,
                    '_match.queries',
                    []
                ),

                'distance_km' => data_get(
                    $candidate,
                    '_match.distance_km'
                ),

                'candidate_primary_type' => data_get(
                    $candidate,
                    'primaryType'
                ),

                'candidate_types' => data_get(
                    $candidate,
                    'types',
                    []
                ),

                'technology_intent_ratio' =>
                    round(
                        $technologyIntentRatio,
                        3
                    ),

                'business_role_ratio' =>
                    round(
                        $roleRatio,
                        3
                    ),
            ],
        ];
    }

    private function targetsDistributorModel(
        array $searchProfile
    ): bool {
        $targetText = [
            data_get($searchProfile, 'business_type'),
            data_get($searchProfile, 'business_model'),
        ];

        foreach ([
            'competitor_types',
            'search_queries',
        ] as $field) {
            $values = data_get($searchProfile, $field, []);

            if (is_array($values)) {
                $targetText = array_merge(
                    $targetText,
                    $values
                );
            }
        }

        $haystack = $this->normalizePhrase(
            implode(
                ' ',
                array_filter(
                    $targetText,
                    fn ($value) => is_string($value)
                        && trim($value) !== ''
                )
            )
        );

        if ($haystack === null) {
            return false;
        }

        foreach ([
            'distributor',
            'distribution',
            'supplier',
            'wholesaler',
            'wholesale',
        ] as $signal) {
            if ($this->containsPhrase($haystack, $signal)) {
                return true;
            }
        }

        return false;
    }

    private function businessRoleEvidence(
        array $candidate,
        array $searchProfile
    ): float {
        if (! $this->targetsDistributorModel($searchProfile)) {
            return 0.0;
        }

        $candidateText = [
            data_get($candidate, 'displayName.text'),
            data_get($candidate, 'primaryType'),
            data_get($candidate, 'primaryTypeDisplayName.text'),
        ];

        $types = data_get($candidate, 'types', []);

        if (is_array($types)) {
            $candidateText = array_merge(
                $candidateText,
                $types
            );
        }

        $haystack = $this->normalizePhrase(
            implode(
                ' ',
                array_filter(
                    $candidateText,
                    fn ($value) => is_string($value)
                        && trim($value) !== ''
                )
            )
        );

        if ($haystack === null) {
            return 0.0;
        }

        foreach ([
            'distributor',
            'distribution',
            'supplier',
            'wholesaler',
            'wholesale',
            'supply',
            'sales',
        ] as $signal) {
            if ($this->containsPhrase($haystack, $signal)) {
                return 0.9;
            }
        }

        if ($this->containsPhrase($haystack, 'manufacturer')) {
            return 0.1;
        }

        return 0.0;
    }

    private function hasFocusedTechnologyIntent(
        array $searchProfile
    ): bool {
        if (
            data_get($searchProfile, 'vertical')
                !== 'technology'
        ) {
            return false;
        }

        $services = data_get(
            $searchProfile,
            'services',
            []
        );

        if (! is_array($services)) {
            return false;
        }

        $specificIntents = [
            'crm',
            'customer relationship management',
            'customer platform',
            'marketing automation',
            'marketing software',
            'sales software',
            'sales platform',
            'customer service software',
            'customer support software',
            'revenue platform',
            'go to market',
        ];

        foreach ($services as $service) {
            $service = $this->normalizePhrase(
                $service
            );

            if (
                $service !== null
                && in_array(
                    $service,
                    $specificIntents,
                    true
                )
            ) {
                return true;
            }
        }

        return false;
    }

    private function technologyIntentEvidence(
        array $candidate,
        array $searchProfile
    ): float {
        if (! $this->hasFocusedTechnologyIntent(
            $searchProfile
        )) {
            return 0.0;
        }

        $specificIntents = [
            'crm',
            'customer relationship management',
            'customer platform',
            'marketing automation',
            'marketing software',
            'sales software',
            'sales platform',
            'customer service software',
            'customer support software',
            'revenue platform',
            'go to market',
        ];

        $candidateText = [
            data_get(
                $candidate,
                'displayName.text'
            ),
            data_get(
                $candidate,
                'primaryType'
            ),
            data_get(
                $candidate,
                'primaryTypeDisplayName.text'
            ),
        ];

        $types = data_get(
            $candidate,
            'types',
            []
        );

        if (is_array($types)) {
            $candidateText = array_merge(
                $candidateText,
                $types
            );
        }

        $matchedQueries = data_get(
            $candidate,
            '_match.queries',
            []
        );

        if (is_array($matchedQueries)) {
            $candidateText = array_merge(
                $candidateText,
                $matchedQueries
            );
        }

        $haystack = $this->normalizePhrase(
            implode(
                ' ',
                array_filter(
                    $candidateText,
                    fn ($value) => is_string($value)
                        && trim($value) !== ''
                )
            )
        );

        if ($haystack === null) {
            return 0.0;
        }

        $matched = 0;

        foreach ($specificIntents as $intent) {
            if (
                $this->containsPhrase(
                    $haystack,
                    $intent
                )
            ) {
                $matched++;
            }
        }

        return min(
            1.0,
            $matched / 2
        );
    }

    private function containsPhrase(
        string $haystack,
        string $needle
    ): bool {
        $needle = $this->normalizePhrase(
            $needle
        );

        if ($needle === null) {
            return false;
        }

        return preg_match(
            '/(?<![\p{L}\p{N}])'
                . preg_quote($needle, '/')
                . '(?![\p{L}\p{N}])/u',
            $haystack
        ) === 1;
    }

    private function weights(
        string $marketScope
    ): array {
        return match ($marketScope) {
            'local' => [
                'business_type' => 40,
                'query_evidence' => 25,
                'service_evidence' => 15,
                'distance' => 20,
            ],

            'broader' => [
                'business_type' => 50,
                'query_evidence' => 35,
                'service_evidence' => 15,
                'distance' => 0,
            ],

            default => [
                'business_type' => 45,
                'query_evidence' => 30,
                'service_evidence' => 15,
                'distance' => 10,
            ],
        };
    }

    private function typeSimilarity(
        array $candidate,
        array $searchProfile
    ): float {
        $businessType = $this->normalizePhrase(
            data_get(
                $searchProfile,
                'business_type'
            )
        );

        if ($businessType === null) {
            return 0.0;
        }

        $candidateTypes = [];

        $primaryType = $this->normalizePhrase(
            data_get(
                $candidate,
                'primaryType'
            )
        );

        if ($primaryType !== null) {
            $candidateTypes[] = $primaryType;
        }

        $primaryTypeName = $this->normalizePhrase(
            data_get(
                $candidate,
                'primaryTypeDisplayName.text'
            )
        );

        if ($primaryTypeName !== null) {
            $candidateTypes[] = $primaryTypeName;
        }

        $types = data_get(
            $candidate,
            'types',
            []
        );

        if (is_array($types)) {
            foreach ($types as $type) {
                $type = $this->normalizePhrase(
                    $type
                );

                if ($type !== null) {
                    $candidateTypes[] = $type;
                }
            }
        }

        $best = 0.0;

        foreach (
            array_unique($candidateTypes)
            as $candidateType
        ) {
            if ($candidateType === $businessType) {
                return 1.0;
            }

            if (
                str_contains(
                    $candidateType,
                    $businessType
                )
                || str_contains(
                    $businessType,
                    $candidateType
                )
            ) {
                $best = max(
                    $best,
                    0.9
                );

                continue;
            }

            $best = max(
                $best,
                $this->tokenSimilarity(
                    $businessType,
                    $candidateType
                )
            );
        }

        return min(
            1.0,
            $best
        );
    }

    private function queryEvidence(
        array $candidate,
        array $searchProfile
    ): float {
        $hits = max(
            0,
            (int) data_get(
                $candidate,
                '_match.query_hits',
                0
            )
        );

        $queries = data_get(
            $searchProfile,
            'search_queries',
            []
        );

        $queryCount = is_array($queries)
            ? count($queries)
            : 0;

        /*
         * CompetitorSearchService currently uses at most
         * four queries, so score against that same effective set.
         */
        $effectiveQueries = max(
            1,
            min(4, $queryCount)
        );

        return min(
            1.0,
            $hits / $effectiveQueries
        );
    }

    private function serviceEvidence(
        array $candidate,
        array $searchProfile
    ): float {
        $services = data_get(
            $searchProfile,
            'services',
            []
        );

        if (! is_array($services) || $services === []) {
            return 0.0;
        }

        $matchedQueries = data_get(
            $candidate,
            '_match.queries',
            []
        );

        $matchedQueries = is_array($matchedQueries)
            ? $matchedQueries
            : [];

        $candidateText = [
            data_get(
                $candidate,
                'displayName.text'
            ),

            data_get(
                $candidate,
                'primaryType'
            ),

            data_get(
                $candidate,
                'primaryTypeDisplayName.text'
            ),
        ];

        $candidateTypes = data_get(
            $candidate,
            'types',
            []
        );

        if (is_array($candidateTypes)) {
            $candidateText = array_merge(
                $candidateText,
                $candidateTypes
            );
        }

        /*
         * Do not include matched search queries here.
         * A business appearing for "plumber" does not prove that
         * the business itself is a plumber.
         */
        $candidateHaystack = implode(
            ' ',
            array_filter(
                array_map(
                    fn ($value) => is_string($value)
                        ? $value
                        : '',
                    $candidateText
                )
            )
        );

        $candidateHaystack = $this->normalizePhrase(
            $candidateHaystack
        );

        if ($candidateHaystack === null) {
            return 0.0;
        }

        $matched = 0;
        $usableServices = 0;

        foreach ($services as $service) {
            $service = $this->normalizePhrase(
                $service
            );

            if ($service === null) {
                continue;
            }

            $usableServices++;

            if (
                str_contains(
                    $candidateHaystack,
                    $service
                )
            ) {
                $matched++;

                continue;
            }

        }

        if ($usableServices === 0) {
            return 0.0;
        }

        return min(
            1.0,
            $matched / min(
                $usableServices,
                6
            )
        );
    }

    private function distanceEvidence(
        mixed $distanceKm,
        string $marketScope
    ): float {
        if ($marketScope === 'broader') {
            return 1.0;
        }

        if (! is_numeric($distanceKm)) {
            return 0.0;
        }

        $distanceKm = max(
            0,
            (float) $distanceKm
        );

        return match (true) {
            $distanceKm <= 10 => 1.0,
            $distanceKm <= 25 => 0.9,
            $distanceKm <= 50 => 0.75,
            $distanceKm <= 100 => 0.5,
            $distanceKm <= 300 => 0.25,
            default => 0.0,
        };
    }

    private function tokenSimilarity(
        string $left,
        string $right
    ): float {
        $leftTokens = $this->tokens(
            $left
        );

        $rightTokens = $this->tokens(
            $right
        );

        if (
            $leftTokens === []
            || $rightTokens === []
        ) {
            return 0.0;
        }

        $intersection = array_intersect(
            $leftTokens,
            $rightTokens
        );

        $union = array_unique(
            array_merge(
                $leftTokens,
                $rightTokens
            )
        );

        if ($union === []) {
            return 0.0;
        }

        return count($intersection)
            / count($union);
    }

    private function tokens(
        string $value
    ): array {
        $parts = preg_split(
            '/\s+/u',
            $value
        );

        if (! is_array($parts)) {
            return [];
        }

        return array_values(
            array_unique(
                array_filter(
                    $parts,
                    fn ($token) =>
                        is_string($token)
                        && mb_strlen($token) >= 3
                )
            )
        );
    }

    private function normalizePhrase(
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

        $value = str_replace(
            ['_', '-', '/', '\\'],
            ' ',
            $value
        );

        $value = preg_replace(
            '/[^\p{L}\p{N}\s]+/u',
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

    private function quality(
        float $score
    ): string {
        return match (true) {
            $score >= 70 => 'high',
            $score >= 50 => 'medium',
            default => 'low',
        };
    }

    private function compareDistance(
        mixed $left,
        mixed $right
    ): int {
        if (
            ! is_numeric($left)
            && ! is_numeric($right)
        ) {
            return 0;
        }

        if (! is_numeric($left)) {
            return 1;
        }

        if (! is_numeric($right)) {
            return -1;
        }

        return (float) $left
            <=> (float) $right;
    }
}