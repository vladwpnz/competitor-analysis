<?php

namespace App\Services;

use App\Contracts\AiBusinessClassifier;
use Illuminate\Support\Facades\Log;
use Throwable;
use UnexpectedValueException;

class BusinessIntelligenceService
{
    private const MARKET_SCOPES = [
        'local',
        'broader',
        'hybrid',
    ];

    private const VERTICALS = [
        'home_services',
        'healthcare_local',
        'beauty_wellness',
        'financial_services',
        'industrial',
        'technology',
        'retail',
        'professional_services',
        'hospitality',
        'other',
    ];

    private const CONFIDENCE_LEVELS = [
        'high',
        'medium',
        'low',
    ];

    public function __construct(
        private readonly BusinessClassifier $fallbackClassifier,
        private readonly AiBusinessClassifierManager $aiManager
    ) {
    }

    public function classify(
        array $businessProfile
    ): array {
        $provider = $this->aiManager->driver();

        if (
            $provider === null
            || ! $provider->isConfigured()
        ) {
            return $this->fallbackClassifier
                ->classify(
                    $businessProfile
                );
        }

        try {
            $classification = $provider->classify(
                $businessProfile
            );

            return $this->normalizeAiClassification(
                $classification,
                $businessProfile,
                $provider
            );
        } catch (Throwable $exception) {
            /*
             * AI is an enhancement, not a hard dependency. Never log the
             * request payload, API key, or provider response body.
             */
            Log::warning(
                'AI business classification failed; using heuristic fallback.',
                [
                    'provider'
                        => $provider->name(),

                    'exception'
                        => get_class(
                            $exception
                        ),
                ]
            );

            return $this->fallbackClassifier
                ->classify(
                    $businessProfile
                );
        }
    }

    /**
     * SearchProfileBuilder remains the deterministic fallback source.
     * We only replace its search intent when an AI classification has
     * successfully passed normalization.
     */
    public function applySearchIntent(
        array $searchProfile,
        array $classification
    ): array {
        if (
            data_get(
                $classification,
                '_classification_source'
            ) !== 'ai'
        ) {
            return $searchProfile;
        }

        $businessType = $this->cleanString(
            data_get(
                $classification,
                'business_type'
            ),
            100
        );

        $queries = $this->cleanStringList(
            data_get(
                $classification,
                'search_queries',
                []
            ),
            4,
            120
        );

        $services = $this->cleanStringList(
            data_get(
                $classification,
                'service_keywords',
                []
            ),
            10,
            100,
            true
        );

        if ($businessType !== null) {
            $searchProfile['business_type']
                = $businessType;
        }

        if ($queries !== []) {
            $searchProfile['search_queries']
                = $queries;
        }

        $searchProfile['services']
            = $services;

        $searchProfile['vertical']
            = data_get(
                $classification,
                'vertical',
                $searchProfile['vertical']
                    ?? null
            );

        $searchProfile['market_scope']
            = data_get(
                $classification,
                'market_scope',
                $searchProfile['market_scope']
                    ?? 'hybrid'
            );

        $searchProfile['geography_weight']
            = data_get(
                $classification,
                'geography_weight',
                $searchProfile['geography_weight']
                    ?? 'medium'
            );

        $searchProfile['radius_strategy_km']
            = data_get(
                $classification,
                'radius_strategy_km',
                $searchProfile['radius_strategy_km']
                    ?? [100, 300, 1000, 3000]
            );

        $searchProfile['classification_confidence']
            = data_get(
                $classification,
                'confidence',
                'unknown'
            );

        return $searchProfile;
    }

    private function normalizeAiClassification(
        array $classification,
        array $businessProfile,
        AiBusinessClassifier $provider
    ): array {
        $businessModel = $this->requiredString(
            $classification,
            'business_model',
            140
        );

        $businessType = $this->requiredString(
            $classification,
            'business_type',
            100
        );

        $industry = $this->requiredString(
            $classification,
            'industry',
            160
        );

        $vertical = $this->requiredEnum(
            $classification,
            'vertical',
            self::VERTICALS
        );

        $marketScope = $this->requiredEnum(
            $classification,
            'market_scope',
            self::MARKET_SCOPES
        );

        $confidence = $this->requiredEnum(
            $classification,
            'confidence',
            self::CONFIDENCE_LEVELS
        );

        $productsServices = $this->cleanStringList(
            $classification[
                'products_services'
            ] ?? [],
            10,
            100,
            true
        );

        if ($productsServices === []) {
            throw new UnexpectedValueException(
                'AI classification has no usable products or services.'
            );
        }

        $targetCustomers = $this->cleanStringList(
            $classification[
                'target_customers'
            ] ?? [],
            6,
            120
        );

        $competitorTypes = $this->cleanStringList(
            $classification[
                'competitor_types'
            ] ?? [],
            6,
            120
        );

        if ($competitorTypes === []) {
            throw new UnexpectedValueException(
                'AI classification has no usable competitor types.'
            );
        }

        $searchQueries = $this->cleanSearchQueries(
            $classification[
                'search_queries'
            ] ?? [],
            $businessProfile
        );

        if (count($searchQueries) < 2) {
            throw new UnexpectedValueException(
                'AI classification has too few usable search queries.'
            );
        }

        return [
            /*
             * Legacy-compatible fields used by the existing pipeline.
             */
            'vertical' => $vertical,

            'market_scope' => $marketScope,

            /*
             * Geography remains deterministic. The AI can understand the
             * market scope, but it cannot accidentally turn national SaaS or
             * industrial matching back into distance-first ranking.
             */
            'geography_weight'
                => $this->geographyWeight(
                    $marketScope
                ),

            'radius_strategy_km'
                => $this->radiusStrategy(
                    $marketScope
                ),

            'service_keywords'
                => $productsServices,

            'confidence' => $confidence,

            /*
             * Additional AI intelligence used to override search intent.
             */
            'business_model'
                => $businessModel,

            'business_type'
                => $businessType,

            'industry'
                => $industry,

            'target_customers'
                => $targetCustomers,

            'competitor_types'
                => $competitorTypes,

            'search_queries'
                => $searchQueries,

            '_classification_source'
                => 'ai',

            '_ai_provider'
                => $provider->name(),
        ];
    }

    private function cleanSearchQueries(
        mixed $queries,
        array $businessProfile
    ): array {
        $queries = $this->cleanStringList(
            $queries,
            8,
            120
        );

        $businessName = $this->cleanString(
            data_get(
                $businessProfile,
                'classification_input.business_name'
            ),
            160
        );

        $websiteHost = $this->websiteHost(
            data_get(
                $businessProfile,
                'website.url'
            )
        );

        $usable = [];

        foreach ($queries as $query) {
            $normalized = mb_strtolower(
                $query
            );

            if (
                str_contains(
                    $normalized,
                    'http://'
                )
                || str_contains(
                    $normalized,
                    'https://'
                )
                || str_contains(
                    $normalized,
                    'www.'
                )
            ) {
                continue;
            }

            if (
                $websiteHost !== null
                && str_contains(
                    $normalized,
                    mb_strtolower(
                        $websiteHost
                    )
                )
            ) {
                continue;
            }

            if (
                $businessName !== null
                && mb_strlen(
                    $businessName
                ) >= 4
                && str_contains(
                    $normalized,
                    mb_strtolower(
                        $businessName
                    )
                )
            ) {
                continue;
            }

            $usable[
                mb_strtolower(
                    $query
                )
            ] = $query;

            if (count($usable) >= 4) {
                break;
            }
        }

        return array_values(
            $usable
        );
    }

    private function requiredString(
        array $classification,
        string $key,
        int $maxLength
    ): string {
        $value = $this->cleanString(
            $classification[$key]
                ?? null,
            $maxLength
        );

        if ($value === null) {
            throw new UnexpectedValueException(
                'AI classification field '
                . $key
                . ' is missing or invalid.'
            );
        }

        return $value;
    }

    private function requiredEnum(
        array $classification,
        string $key,
        array $allowed
    ): string {
        $value = $classification[$key]
            ?? null;

        if (! is_string($value)) {
            throw new UnexpectedValueException(
                'AI classification field '
                . $key
                . ' is missing or invalid.'
            );
        }

        $value = mb_strtolower(
            trim($value)
        );

        if (
            ! in_array(
                $value,
                $allowed,
                true
            )
        ) {
            throw new UnexpectedValueException(
                'AI classification field '
                . $key
                . ' contains an unsupported value.'
            );
        }

        return $value;
    }

    private function cleanStringList(
        mixed $values,
        int $maxItems,
        int $maxLength,
        bool $lowercase = false
    ): array {
        if (! is_array($values)) {
            return [];
        }

        $result = [];

        foreach ($values as $value) {
            $value = $this->cleanString(
                $value,
                $maxLength
            );

            if ($value === null) {
                continue;
            }

            if ($lowercase) {
                $value = mb_strtolower(
                    $value
                );
            }

            $key = mb_strtolower(
                $value
            );

            if (isset($result[$key])) {
                continue;
            }

            $result[$key] = $value;

            if (
                count($result)
                >= $maxItems
            ) {
                break;
            }
        }

        return array_values(
            $result
        );
    }

    private function cleanString(
        mixed $value,
        int $maxLength
    ): ?string {
        if (! is_string($value)) {
            return null;
        }

        $value = html_entity_decode(
            $value,
            ENT_QUOTES | ENT_HTML5,
            'UTF-8'
        );

        $value = preg_replace(
            '/\s+/u',
            ' ',
            $value
        ) ?? $value;

        $value = trim(
            $value
        );

        if ($value === '') {
            return null;
        }

        return mb_substr(
            $value,
            0,
            $maxLength
        );
    }

    private function geographyWeight(
        string $marketScope
    ): string {
        return match ($marketScope) {
            'local' => 'high',
            'broader' => 'low',
            default => 'medium',
        };
    }

    private function radiusStrategy(
        string $marketScope
    ): array {
        return match ($marketScope) {
            'local' => [
                50,
                100,
                300,
            ],

            'broader' => [
                300,
                1000,
                3000,
            ],

            default => [
                100,
                300,
                1000,
                3000,
            ],
        };
    }

    private function websiteHost(
        mixed $url
    ): ?string {
        if (! is_string($url)) {
            return null;
        }

        $host = parse_url(
            $url,
            PHP_URL_HOST
        );

        if (
            ! is_string($host)
            || trim($host) === ''
        ) {
            return null;
        }

        $host = mb_strtolower(
            trim($host)
        );

        if (
            str_starts_with(
                $host,
                'www.'
            )
        ) {
            $host = mb_substr(
                $host,
                4
            );
        }

        return $host === ''
            ? null
            : $host;
    }
}
