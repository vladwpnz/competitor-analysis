<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use RuntimeException;

class CompetitorSearchService
{
    public const STAGE_INITIAL = 'initial';

    public const STAGE_LOCALITY = 'locality';

    public const STAGE_REGION = 'region';

    public const STAGE_COUNTRY = 'country';

    public const STAGE_RELEVANCE = 'relevance';

    private const MAX_SEARCH_QUERIES = 4;

    private const RESULTS_PER_QUERY = 15;

    private const MAX_CANDIDATES = 50;

    private readonly AnalysisDeadline $analysisDeadline;

    public function __construct(
        private readonly GooglePlacesService $googlePlaces,
        ?AnalysisDeadline $analysisDeadline = null
    ) {
        $this->analysisDeadline = $analysisDeadline
            ?? new AnalysisDeadline();
    }

    public function findCandidates(
        array $searchProfile,
        int $maxCandidates = 30,
        string $stage = self::STAGE_INITIAL
    ): array {
        $stage = $this->normalizeStage(
            $stage
        );

        $queries = $this->prepareQueries(
            data_get(
                $searchProfile,
                'search_queries',
                []
            ),
            $this->queryLimitForStage(
                $stage
            )
        );

        if ($queries === []) {
            return [];
        }

        $maxCandidates = max(
            5,
            min(
                $maxCandidates,
                self::MAX_CANDIDATES
            )
        );

        $marketScope = (string) data_get(
            $searchProfile,
            'market_scope',
            'hybrid'
        );

        $latitude = data_get(
            $searchProfile,
            'location.latitude'
        );

        $longitude = data_get(
            $searchProfile,
            'location.longitude'
        );

        if (! $this->hasProviderWindow()) {
            return [];
        }

        $plans = [];

        foreach ($queries as $query) {
            $plans[] = $this->planSearch(
                $query,
                $stage,
                $marketScope,
                $latitude,
                $longitude,
                $searchProfile
            );
        }

        $requests = [];

        foreach ($plans as $key => $plan) {
            $requests[$key] = $plan['request'];
        }

        try {
            $placesByPlan = $this->googlePlaces
                ->searchBusinessesBatch(
                    $requests
                );
        } catch (RuntimeException $exception) {
            $this->logSearchFailure(
                $stage,
                $exception
            );

            return [];
        }

        /*
         * A broader search uses country context to avoid server-location
         * bias. Only try its unscoped fallback when the first concurrent
         * batch did not already yield a usable shortlist.
         */
        if (
            $this->usableCandidateCount(
                $placesByPlan,
                $searchProfile
            ) < 5
            && $this->hasProviderWindow()
        ) {
            $fallbackRequests = [];

            foreach ($plans as $key => $plan) {
                if (
                    ($placesByPlan[$key] ?? []) !== []
                    || ! is_array(
                        $plan['fallback_request']
                            ?? null
                    )
                ) {
                    continue;
                }

                $fallbackRequests[$key]
                    = $plan['fallback_request'];
            }

            if ($fallbackRequests !== []) {
                try {
                    $fallbackResults = $this->googlePlaces
                        ->searchBusinessesBatch(
                            $fallbackRequests
                        );

                    foreach ($fallbackResults as $key => $places) {
                        if (! is_array($places)) {
                            continue;
                        }

                        $placesByPlan[$key] = $places;
                        $plans[$key]['search_mode']
                            = $plans[$key]['fallback_mode'];
                        $plans[$key]['executed_query']
                            = $plans[$key]['fallback_query'];
                    }
                } catch (RuntimeException $exception) {
                    $this->logSearchFailure(
                        $stage,
                        $exception
                    );
                }
            }
        }

        $pool = [];

        foreach ($plans as $key => $plan) {
            $query = $plan['query'];
            $searchMode = $plan['search_mode'];
            $executedQuery = $plan['executed_query'];
            $places = $placesByPlan[$key] ?? [];

            foreach ($places as $place) {
                if (! is_array($place)) {
                    continue;
                }

                if (
                    $this->isUnavailableBusiness(
                        $place
                    )
                ) {
                    continue;
                }

                if (
                    $this->isExcludedBusiness(
                        $place,
                        $searchProfile
                    )
                ) {
                    continue;
                }

                $key = $this->candidateKey(
                    $place
                );

                if ($key === null) {
                    continue;
                }

                if (! isset($pool[$key])) {
                    $pool[$key] = $place;

                    $pool[$key]['_match'] = [
                        'queries' => [],
                        'executed_queries' => [],
                        'query_hits' => 0,
                        'search_modes' => [],
                        'distance_km'
                            => $this->distanceFromOrigin(
                                $place,
                                $latitude,
                                $longitude
                            ),
                    ];
                }

                if (
                    ! in_array(
                        $query,
                        $pool[$key]['_match']['queries'],
                        true
                    )
                ) {
                    $pool[$key]['_match']['queries'][]
                        = $query;
                }

                if (
                    ! in_array(
                        $executedQuery,
                        $pool[$key]['_match']['executed_queries'],
                        true
                    )
                ) {
                    $pool[$key]['_match']['executed_queries'][]
                        = $executedQuery;
                }

                if (
                    ! in_array(
                        $searchMode,
                        $pool[$key]['_match']['search_modes'],
                        true
                    )
                ) {
                    $pool[$key]['_match']['search_modes'][]
                        = $searchMode;
                }

                $pool[$key]['_match']['query_hits']
                    = count(
                        $pool[$key]['_match']['queries']
                    );
            }
        }

        $candidates = array_values(
            $pool
        );

        $preferDistance =
            data_get(
                $searchProfile,
                'geography_weight',
                'medium'
            ) !== 'low';

        usort(
            $candidates,
            function (
                array $left,
                array $right
            ) use ($preferDistance): int {
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

                if (! $preferDistance) {
                    return 0;
                }

                $leftDistance = data_get(
                    $left,
                    '_match.distance_km'
                );

                $rightDistance = data_get(
                    $right,
                    '_match.distance_km'
                );

                if (
                    $leftDistance === null
                    && $rightDistance === null
                ) {
                    return 0;
                }

                if ($leftDistance === null) {
                    return 1;
                }

                if ($rightDistance === null) {
                    return -1;
                }

                return
                    $leftDistance
                    <=> $rightDistance;
            }
        );

        return array_slice(
            $candidates,
            0,
            $maxCandidates
        );
    }

    private function planSearch(
        string $query,
        string $stage,
        string $marketScope,
        mixed $latitude,
        mixed $longitude,
        array $searchProfile
    ): array {
        $isLocalScope = in_array(
            $marketScope,
            [
                'local',
                'hybrid',
            ],
            true
        );

        $hasCoordinates =
            is_numeric($latitude)
            && is_numeric($longitude);

        /*
         * First pass for local/hybrid businesses:
         * use Google's maximum supported circular
         * location bias of 50 km.
         */
        if (
            $stage === self::STAGE_INITIAL
            && $isLocalScope
            && $hasCoordinates
        ) {
            return [
                'query' => $query,
                'request' => [
                    'query' => $query,
                    'max_results'
                        => self::RESULTS_PER_QUERY,
                    'latitude' => (float) $latitude,
                    'longitude' => (float) $longitude,
                    'radius_km' => 50,
                ],
                'search_mode' => 'local_50km',
                'executed_query' => $query,
                'fallback_request' => null,
                'fallback_mode' => null,
                'fallback_query' => null,
            ];
        }

        /*
         * Broader businesses are relevance-first, so distance must not
         * control ranking. A completely unscoped Places text search can
         * otherwise inherit Google's server/IP bias and surface businesses
         * from the wrong country.
         *
         * When the selected Google Business Profile has a real formatted
         * address, use only its country as a weak market context. This keeps
         * broader searches national rather than local while still allowing
         * relevance to decide the winners.
         *
         * If the country-scoped query returns nothing, fall back to the
         * original relevance query instead of returning an empty result set.
         */
        if (! $isLocalScope) {
            $countryContext = $this->geographicContext(
                $searchProfile,
                self::STAGE_COUNTRY
            );

            if ($countryContext !== null) {
                $executedQuery = $this->buildContextualQuery(
                    $query,
                    $countryContext
                );

                return [
                    'query' => $query,
                    'request' => [
                        'query' => $executedQuery,
                        'max_results'
                            => self::RESULTS_PER_QUERY,
                    ],
                    'search_mode'
                        => 'broader_country_context',
                    'executed_query' => $executedQuery,
                    'fallback_request' => [
                        'query' => $query,
                        'max_results'
                            => self::RESULTS_PER_QUERY,
                    ],
                    'fallback_mode' => 'relevance',
                    'fallback_query' => $query,
                ];
            }

            return [
                'query' => $query,
                'request' => [
                    'query' => $query,
                    'max_results'
                        => self::RESULTS_PER_QUERY,
                ],
                'search_mode' => 'relevance',
                'executed_query' => $query,
                'fallback_request' => null,
                'fallback_mode' => null,
                'fallback_query' => null,
            ];
        }

        /*
         * A local/service-area business may occasionally
         * have no usable coordinates. In that situation,
         * the initial request can still use explicit
         * locality context from its formatted address.
         */
        $effectiveStage = $stage;

        if (
            $stage === self::STAGE_INITIAL
            && ! $hasCoordinates
        ) {
            $effectiveStage =
                self::STAGE_LOCALITY;
        }

        $context = $this->geographicContext(
            $searchProfile,
            $effectiveStage
        );

        if ($context !== null) {
            $executedQuery =
                $this->buildContextualQuery(
                    $query,
                    $context
                );

            $searchMode = match (
                $effectiveStage
            ) {
                self::STAGE_LOCALITY
                    => 'locality_context',

                self::STAGE_REGION
                    => 'region_context',

                self::STAGE_COUNTRY
                    => 'country_context',

                default
                    => 'relevance_fallback',
            };

            return [
                'query' => $query,
                'request' => [
                    'query' => $executedQuery,
                    'max_results'
                        => self::RESULTS_PER_QUERY,
                ],
                'search_mode' => $searchMode,
                'executed_query' => $executedQuery,
                'fallback_request' => null,
                'fallback_mode' => null,
                'fallback_query' => null,
            ];
        }

        /*
         * If the Google Place does not expose enough
         * address information, fall back to relevance
         * rather than constructing a fake location.
         */
        return [
            'query' => $query,
            'request' => [
                'query' => $query,
                'max_results'
                    => self::RESULTS_PER_QUERY,
            ],
            'search_mode' => $stage === self::STAGE_INITIAL
                ? 'relevance'
                : 'relevance_fallback',
            'executed_query' => $query,
            'fallback_request' => null,
            'fallback_mode' => null,
            'fallback_query' => null,
        ];
    }

    private function usableCandidateCount(
        array $placesByPlan,
        array $searchProfile
    ): int {
        $unique = [];

        foreach ($placesByPlan as $places) {
            if (! is_array($places)) {
                continue;
            }

            foreach ($places as $place) {
                if (
                    ! is_array($place)
                    || $this->isUnavailableBusiness($place)
                    || $this->isExcludedBusiness(
                        $place,
                        $searchProfile
                    )
                ) {
                    continue;
                }

                $key = $this->candidateKey($place);

                if ($key !== null) {
                    $unique[$key] = true;
                }
            }
        }

        return count($unique);
    }

    private function hasProviderWindow(): bool
    {
        return $this->analysisDeadline->canStart(
            max(
                0.5,
                (float) config(
                    'analysis.minimum_provider_window_seconds',
                    1
                )
            )
        );
    }

    private function logSearchFailure(
        string $stage,
        RuntimeException $exception
    ): void {
        Log::warning(
            'Google Places account search was unavailable.',
            [
                'provider' => 'google_places',
                'stage' => $stage,
                'exception' => $exception::class,
            ]
        );
    }

    private function geographicContext(
        array $searchProfile,
        string $stage
    ): ?string {
        if ($stage === self::STAGE_RELEVANCE) {
            return null;
        }

        $address = data_get(
            $searchProfile,
            'location.address'
        );

        if (
            ! is_string($address)
            || trim($address) === ''
        ) {
            return null;
        }

        $parts = preg_split(
            '/\s*,\s*/u',
            trim($address)
        );

        if (! is_array($parts)) {
            return null;
        }

        $parts = array_values(
            array_filter(
                array_map(
                    static fn (mixed $part): string =>
                        is_string($part)
                            ? trim($part)
                            : '',
                    $parts
                ),
                static fn (string $part): bool =>
                    $part !== ''
            )
        );

        if ($parts === []) {
            return null;
        }

        $count = count(
            $parts
        );

        $country =
            $parts[$count - 1];

        if ($stage === self::STAGE_COUNTRY) {
            return $country;
        }

        if ($count === 1) {
            return $country;
        }

        $region =
            $parts[$count - 2];

        if ($stage === self::STAGE_REGION) {
            return $this->joinLocationParts([
                $region,
                $country,
            ]);
        }

        /*
         * Common Google formatted-address examples:
         *
         * 100 Main St, Toronto, ON, Canada
         * Musterstraße 1, 10115 Berlin, Germany
         *
         * With four or more components, the third
         * component from the end is normally the
         * locality/city level we want.
         *
         * With three components, the second component
         * from the end is the safest useful context.
         */
        $locality = $count >= 4
            ? $parts[$count - 3]
            : $region;

        return $this->joinLocationParts([
            $locality,
            $region,
            $country,
        ]);
    }

    private function joinLocationParts(
        array $parts
    ): ?string {
        $unique = [];

        foreach ($parts as $part) {
            if (
                ! is_string($part)
                || trim($part) === ''
            ) {
                continue;
            }

            $part = trim(
                $part
            );

            $key = mb_strtolower(
                $part
            );

            if (isset($unique[$key])) {
                continue;
            }

            $unique[$key] = $part;
        }

        if ($unique === []) {
            return null;
        }

        return implode(
            ', ',
            array_values($unique)
        );
    }

    private function buildContextualQuery(
        string $query,
        string $context
    ): string {
        return trim(
            $query
            . ' in '
            . $context
        );
    }

    private function queryLimitForStage(
        string $stage
    ): int {
        return match ($stage) {
            self::STAGE_INITIAL
                => self::MAX_SEARCH_QUERIES,

            self::STAGE_LOCALITY
                => 3,

            self::STAGE_REGION
                => 2,

            self::STAGE_COUNTRY,
            self::STAGE_RELEVANCE
                => 1,
        };
    }

    private function normalizeStage(
        string $stage
    ): string {
        $stage = mb_strtolower(
            trim($stage)
        );

        $allowed = [
            self::STAGE_INITIAL,
            self::STAGE_LOCALITY,
            self::STAGE_REGION,
            self::STAGE_COUNTRY,
            self::STAGE_RELEVANCE,
        ];

        if (
            ! in_array(
                $stage,
                $allowed,
                true
            )
        ) {
            throw new InvalidArgumentException(
                'Unsupported account search stage.'
            );
        }

        return $stage;
    }

    private function prepareQueries(
        mixed $queries,
        int $limit
    ): array {
        if (! is_array($queries)) {
            return [];
        }

        $limit = max(
            1,
            min(
                $limit,
                self::MAX_SEARCH_QUERIES
            )
        );

        $prepared = [];

        foreach ($queries as $query) {
            if (! is_string($query)) {
                continue;
            }

            $query = trim(
                $query
            );

            if ($query === '') {
                continue;
            }

            $key = mb_strtolower(
                $query
            );

            if (isset($prepared[$key])) {
                continue;
            }

            $prepared[$key] = $query;

            if (
                count($prepared)
                >= $limit
            ) {
                break;
            }
        }

        return array_values(
            $prepared
        );
    }

    private function isUnavailableBusiness(
        array $place
    ): bool {
        $status = data_get(
            $place,
            'businessStatus'
        );

        if (! is_string($status)) {
            return false;
        }

        $status = mb_strtoupper(
            trim($status)
        );

        return in_array(
            $status,
            [
                'CLOSED_TEMPORARILY',
                'CLOSED_PERMANENTLY',
            ],
            true
        );
    }

    private function isExcludedBusiness(
        array $place,
        array $searchProfile
    ): bool {
        $excludedPlaceId = data_get(
            $searchProfile,
            'exclude.place_id'
        );

        $candidatePlaceId =
            $place['id'] ?? null;

        if (
            is_string($excludedPlaceId)
            && trim($excludedPlaceId) !== ''
            && is_string($candidatePlaceId)
            && hash_equals(
                trim($excludedPlaceId),
                trim($candidatePlaceId)
            )
        ) {
            return true;
        }

        $excludedWebsiteHost = $this->normalizeHost(
            data_get(
                $searchProfile,
                'exclude.website_host'
            )
        );

        $candidateWebsiteHost = $this->normalizeHost(
            data_get(
                $place,
                'websiteUri'
            )
        );

        if (
            $excludedWebsiteHost !== null
            && $candidateWebsiteHost !== null
            && $excludedWebsiteHost === $candidateWebsiteHost
        ) {
            return true;
        }

        $excludedRawName = data_get(
            $searchProfile,
            'exclude.business_name'
        );

        $candidateRawName = data_get(
            $place,
            'displayName.text'
        );

        $excludedName = $this->normalizeName(
            $excludedRawName
        );

        $candidateName = $this->normalizeName(
            $candidateRawName
        );

        if (
            $excludedName !== null
            && $candidateName !== null
            && $excludedName === $candidateName
        ) {
            return true;
        }

        /*
         * Google Places commonly returns another branch of the same brand
         * under a name such as "Brand Name - Downtown". A different Place ID
         * does not make that branch a new account, so compare the stable brand
         * portion of both names before accepting the candidate.
         */
        $excludedBrand = $this->brandNameCore(
            $excludedRawName
        );

        $candidateBrand = $this->brandNameCore(
            $candidateRawName
        );

        return
            $excludedBrand !== null
            && $candidateBrand !== null
            && $excludedBrand === $candidateBrand;
    }

    private function brandNameCore(
        mixed $value
    ): ?string {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        if ($value === '') {
            return null;
        }

        $parts = preg_split(
            '/\s+(?:[-–—|@])\s+|\s+\bat\b\s+/iu',
            $value,
            2
        );

        if (is_array($parts) && $parts !== []) {
            $value = $parts[0];
        }

        return $this->normalizeName(
            $value
        );
    }

    private function normalizeHost(
        mixed $value
    ): ?string {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        if ($value === '') {
            return null;
        }

        if (! str_contains($value, '://')) {
            $value = 'https://' . $value;
        }

        $host = parse_url(
            $value,
            PHP_URL_HOST
        );

        if (! is_string($host) || trim($host) === '') {
            return null;
        }

        $host = mb_strtolower(
            trim($host)
        );

        if (str_starts_with($host, 'www.')) {
            $host = substr($host, 4);
        }

        return $host === ''
            ? null
            : $host;
    }

    private function candidateKey(
        array $place
    ): ?string {
        $placeId =
            $place['id'] ?? null;

        if (
            is_string($placeId)
            && trim($placeId) !== ''
        ) {
            return 'place:'
                . trim($placeId);
        }

        $name = $this->normalizeName(
            data_get(
                $place,
                'displayName.text'
            )
        );

        if ($name === null) {
            return null;
        }

        $address = data_get(
            $place,
            'formattedAddress'
        );

        $address = is_string($address)
            ? mb_strtolower(
                trim($address)
            )
            : '';

        return 'fallback:'
            . $name
            . '|'
            . $address;
    }

    private function normalizeName(
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

        $value = trim(
            $value
        );

        return $value === ''
            ? null
            : $value;
    }

    private function distanceFromOrigin(
        array $place,
        mixed $originLatitude,
        mixed $originLongitude
    ): ?float {
        $candidateLatitude = data_get(
            $place,
            'location.latitude'
        );

        $candidateLongitude = data_get(
            $place,
            'location.longitude'
        );

        if (
            ! is_numeric($originLatitude)
            || ! is_numeric($originLongitude)
            || ! is_numeric($candidateLatitude)
            || ! is_numeric($candidateLongitude)
        ) {
            return null;
        }

        return $this->haversineDistance(
            (float) $originLatitude,
            (float) $originLongitude,
            (float) $candidateLatitude,
            (float) $candidateLongitude
        );
    }

    private function haversineDistance(
        float $lat1,
        float $lon1,
        float $lat2,
        float $lon2
    ): float {
        $earthRadiusKm = 6371.0088;

        $latDelta = deg2rad(
            $lat2 - $lat1
        );

        $lonDelta = deg2rad(
            $lon2 - $lon1
        );

        $lat1Radians = deg2rad(
            $lat1
        );

        $lat2Radians = deg2rad(
            $lat2
        );

        $a =
            sin($latDelta / 2) ** 2
            + cos($lat1Radians)
            * cos($lat2Radians)
            * sin($lonDelta / 2) ** 2;

        $c = 2 * atan2(
            sqrt($a),
            sqrt(1 - $a)
        );

        return round(
            $earthRadiusKm * $c,
            2
        );
    }
}
