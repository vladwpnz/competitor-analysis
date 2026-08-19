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

    private const DISCOVERY_MODES = [
        'local_physical',
        'broader_physical',
        'digital_global',
        'hybrid',
    ];

    private const CONFIDENCE_LEVELS = [
        'high',
        'medium',
        'low',
    ];

    private readonly AnalysisDeadline $analysisDeadline;

    public function __construct(
        private readonly BusinessClassifier $fallbackClassifier,
        private readonly AiBusinessClassifierManager $aiManager,
        ?AnalysisDeadline $analysisDeadline = null
    ) {
        $this->analysisDeadline = $analysisDeadline
            ?? new AnalysisDeadline();
    }

    public function classify(
        array $businessProfile
    ): array {
        if (
            ! $this->analysisDeadline->canStart(
                (float) config(
                    'analysis.minimum_provider_window_seconds',
                    1
                )
            )
        ) {
            return $this->fallbackClassifier
                ->classify(
                    $businessProfile
                );
        }

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

        $businessModel = $this->cleanString(
            data_get(
                $classification,
                'business_model'
            ),
            140
        );

        $industry = $this->cleanString(
            data_get(
                $classification,
                'industry'
            ),
            160
        );

        $targetCustomers = $this->cleanStringList(
            data_get(
                $classification,
                'target_customers',
                []
            ),
            6,
            120
        );

        $competitorTypes = $this->cleanStringList(
            data_get(
                $classification,
                'competitor_types',
                []
            ),
            6,
            120
        );

        if (
            data_get(
                $classification,
                'vertical'
            ) === 'healthcare_local'
        ) {
            $queries = $this->prioritizeLocalHealthcareQueries(
                $businessType,
                $queries
            );
        }

        if ($businessType !== null) {
            $searchProfile['business_type']
                = $businessType;
        }

        if ($queries !== []) {
            $searchProfile['search_queries']
                = $queries;
        }

        if ($businessModel !== null) {
            $searchProfile['business_model']
                = $businessModel;
        }

        if ($industry !== null) {
            $searchProfile['industry']
                = $industry;
        }

        $searchProfile['target_customers']
            = $targetCustomers;

        $searchProfile['competitor_types']
            = $competitorTypes;

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

        $searchProfile['discovery_mode']
            = data_get(
                $classification,
                'discovery_mode',
                $searchProfile['discovery_mode']
                    ?? null
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

        /*
         * Gemini can legitimately vary between hybrid and broader for the
         * same national business. Keep broad-market signals deterministic so
         * one model response cannot turn a coast-to-coast / SaaS business back
         * into distance-first matching.
         */
        $marketScope = $this->stabilizeMarketScope(
            $marketScope,
            $vertical,
            $businessModel,
            $businessType,
            $businessProfile
        );

        $discoveryMode = $this->requiredEnum(
            $classification,
            'discovery_mode',
            self::DISCOVERY_MODES
        );

        $discoveryMode = $this->stabilizeDiscoveryMode(
            $discoveryMode,
            $marketScope,
            $vertical
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

            'discovery_mode' => $discoveryMode,

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

    private function stabilizeMarketScope(
        string $marketScope,
        string $vertical,
        string $businessModel,
        string $businessType,
        array $businessProfile
    ): string {
        /*
         * Patient-facing healthcare practices are normally chosen by area.
         * Keep clinics, dentists, dermatology and similar practices local
         * even when a model over-generalizes a multi-location brand.
         */
        if ($vertical === 'healthcare_local') {
            return 'local';
        }

        if ($marketScope === 'broader') {
            return 'broader';
        }

        $scopeText = implode(' ', array_filter([
            data_get(
                $businessProfile,
                'classification_input.website_title'
            ),
            data_get(
                $businessProfile,
                'classification_input.website_description'
            ),
            data_get(
                $businessProfile,
                'classification_input.homepage_text'
            ),
        ], static fn (mixed $value): bool =>
            is_string($value) && trim($value) !== ''
        ));

        $scopeText = mb_strtolower(
            mb_substr($scopeText, 0, 12000)
        );

        $broaderSignals = [
            'coast-to-coast',
            'coast to coast',
            'nationwide',
            'across canada',
            'throughout canada',
            'across the united states',
            'throughout the united states',
            'across the usa',
            'throughout the usa',
            'across the country',
            'worldwide',
            'global customers',
            'international customers',
            'locations across',
        ];

        foreach ($broaderSignals as $signal) {
            if (str_contains($scopeText, $signal)) {
                return 'broader';
            }
        }

        if ($vertical === 'technology') {
            $modelText = mb_strtolower(
                $businessModel . ' ' . $businessType
            );

            foreach ([
                'saas',
                'software platform',
                'cloud platform',
                'customer platform',
                'crm platform',
            ] as $signal) {
                if (str_contains($modelText, $signal)) {
                    return 'broader';
                }
            }
        }

        /*
         * A physical office GBP can make global payment infrastructure look
         * local/hybrid. Stabilize clear digital-finance signals only; local
         * advisors, branches and brokers remain location-sensitive.
         */
        if ($vertical === 'financial_services') {
            $financeText = mb_strtolower(
                implode(' ', array_filter([
                    $businessModel,
                    $businessType,
                    data_get(
                        $businessProfile,
                        'classification_input.website_title'
                    ),
                    data_get(
                        $businessProfile,
                        'classification_input.website_description'
                    ),
                ], static fn (mixed $value): bool =>
                    is_string($value) && trim($value) !== ''
                ))
            );

            foreach ([
                'payment processor',
                'payment processing',
                'payment platform',
                'payments platform',
                'payment infrastructure',
                'payments infrastructure',
                'financial infrastructure',
                'fintech platform',
                'online payments',
                'payment gateway',
                'payments api',
                'payment api',
                'merchant payments',
                'commerce infrastructure',
            ] as $signal) {
                if (str_contains($financeText, $signal)) {
                    return 'broader';
                }
            }
        }

        return $marketScope;
    }

    private function prioritizeLocalHealthcareQueries(
        ?string $businessType,
        array $queries
    ): array {
        if ($businessType === null) {
            return $queries;
        }

        $normalizedType = mb_strtolower(
            trim($businessType)
        );

        $genericTypes = [
            'clinic',
            'medical clinic',
            'medical center',
            'doctor',
            'physician',
            'health clinic',
            'healthcare provider',
            'primary care clinic',
            'family practice',
        ];

        $isSpecialized = ! in_array(
            $normalizedType,
            $genericTypes,
            true
        );

        if (! $isSpecialized) {
            return $queries;
        }

        $genericQueries = [
            'clinic',
            'medical clinic',
            'medical center',
            'doctor',
            'physician',
            'health clinic',
            'healthcare provider',
            'primary care clinic',
            'family practice',
            'family doctor',
        ];

        $focused = [
            $businessType,
        ];

        foreach ($queries as $query) {
            $normalized = mb_strtolower(
                trim($query)
            );

            if (
                in_array(
                    $normalized,
                    $genericQueries,
                    true
                )
            ) {
                continue;
            }

            $focused[] = $query;
        }

        return array_slice(
            array_values(
                array_unique(
                    $focused
                )
            ),
            0,
            4
        );
    }

    private function stabilizeDiscoveryMode(
        string $discoveryMode,
        string $marketScope,
        string $vertical
    ): string {
        /*
         * Discovery mode describes where competitors should be discovered,
         * while market scope still controls geography. Keep impossible
         * combinations out of the downstream pipeline without hard-coding
         * any specific company or industry.
         */
        if ($marketScope === 'local') {
            return $discoveryMode === 'hybrid'
                ? 'hybrid'
                : 'local_physical';
        }

        if ($marketScope === 'broader') {
            /*
             * National/global finance businesses compete by offering and
             * brand rather than by nearby office location. Reuse semantic AI
             * discovery for mortgage, insurance, banking and similar markets.
             */
            if ($vertical === 'financial_services') {
                return 'digital_global';
            }

            if ($discoveryMode === 'local_physical') {
                return 'broader_physical';
            }

            return $discoveryMode;
        }

        return 'hybrid';
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
