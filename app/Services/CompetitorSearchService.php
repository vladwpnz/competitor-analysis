<?php

namespace App\Services;

class CompetitorSearchService
{
    private const MAX_SEARCH_QUERIES = 4;

    private const RESULTS_PER_QUERY = 15;

    private const MAX_CANDIDATES = 50;

    public function __construct(
        private readonly GooglePlacesService $googlePlaces
    ) {
    }

    public function findCandidates(
        array $searchProfile,
        int $maxCandidates = 30
    ): array {
        $queries = $this->prepareQueries(
            data_get(
                $searchProfile,
                'search_queries',
                []
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

        $useLocalSearch =
            in_array(
                $marketScope,
                ['local', 'hybrid'],
                true
            )
            && is_numeric($latitude)
            && is_numeric($longitude);

        $pool = [];

        foreach ($queries as $query) {
            if ($useLocalSearch) {
                $places = $this->googlePlaces
                    ->searchBusinessesNear(
                        $query,
                        (float) $latitude,
                        (float) $longitude,
                        50,
                        self::RESULTS_PER_QUERY
                    );

                $searchMode = 'local_50km';
            } else {
                $places = $this->googlePlaces
                    ->searchBusinesses(
                        $query,
                        self::RESULTS_PER_QUERY
                    );

                $searchMode = 'relevance';
            }

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

                return $leftDistance <=> $rightDistance;
            }
        );

        return array_slice(
            $candidates,
            0,
            $maxCandidates
        );
    }

    private function prepareQueries(
        mixed $queries
    ): array {
        if (! is_array($queries)) {
            return [];
        }

        $prepared = [];

        foreach ($queries as $query) {
            if (! is_string($query)) {
                continue;
            }

            $query = trim($query);

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
                >= self::MAX_SEARCH_QUERIES
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

        $candidatePlaceId = $place['id']
            ?? null;

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
        $placeId = $place['id']
            ?? null;

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

        $value = trim($value);

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