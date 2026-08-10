<?php

namespace App\Services;

use InvalidArgumentException;

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

    public function __construct(
        private readonly GooglePlacesService $googlePlaces
    ) {
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

        $pool = [];

        foreach ($queries as $query) {
            [
                $places,
                $searchMode,
                $executedQuery,
            ] = $this->executeSearch(
                $query,
                $stage,
                $marketScope,
                $latitude,
                $longitude,
                $searchProfile
            );

            foreach ($places as $place) {
                if (! is_array($place)) {
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

    private function executeSearch(
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
            $places = $this->googlePlaces
                ->searchBusinessesNear(
                    $query,
                    (float) $latitude,
                    (float) $longitude,
                    50,
                    self::RESULTS_PER_QUERY
                );

            return [
                $places,
                'local_50km',
                $query,
            ];
        }

        /*
         * Broader businesses are relevance-first.
         * We intentionally do not force geographic
         * expansion stages on them.
         */
        if (! $isLocalScope) {
            $places = $this->googlePlaces
                ->searchBusinesses(
                    $query,
                    self::RESULTS_PER_QUERY
                );

            return [
                $places,
                'relevance',
                $query,
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

            $places = $this->googlePlaces
                ->searchBusinesses(
                    $executedQuery,
                    self::RESULTS_PER_QUERY
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
                $places,
                $searchMode,
                $executedQuery,
            ];
        }

        /*
         * If the Google Place does not expose enough
         * address information, fall back to relevance
         * rather than constructing a fake location.
         */
        $places = $this->googlePlaces
            ->searchBusinesses(
                $query,
                self::RESULTS_PER_QUERY
            );

        return [
            $places,
            $stage === self::STAGE_INITIAL
                ? 'relevance'
                : 'relevance_fallback',
            $query,
        ];
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
                'Unsupported competitor search stage.'
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

        $excludedName = $this->normalizeName(
            data_get(
                $searchProfile,
                'exclude.business_name'
            )
        );

        $candidateName = $this->normalizeName(
            data_get(
                $place,
                'displayName.text'
            )
        );

        return
            $excludedName !== null
            && $candidateName !== null
            && $excludedName === $candidateName;
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