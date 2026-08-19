<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

class GooglePlacesService
{
    private const MAX_PAGE_SIZE = 20;

    private const MAX_LOCATION_BIAS_RADIUS_KM = 50;

    private const AUTOCOMPLETE_FIELD_MASK =
        'suggestions.placePrediction.placeId,' .
        'suggestions.placePrediction.text.text,' .
        'suggestions.placePrediction.structuredFormat.mainText.text,' .
        'suggestions.placePrediction.structuredFormat.secondaryText.text,' .
        'suggestions.placePrediction.types';

    private const SEARCH_FIELD_MASK =
        'places.id,' .
        'places.displayName,' .
        'places.formattedAddress,' .
        'places.primaryType,' .
        'places.primaryTypeDisplayName,' .
        'places.types,' .
        'places.location,' .
        'places.businessStatus,' .
        'places.pureServiceAreaBusiness,' .
        'places.googleMapsUri';

    private const DETAILS_FIELD_MASK =
        'id,' .
        'displayName,' .
        'formattedAddress,' .
        'primaryType,' .
        'primaryTypeDisplayName,' .
        'types,' .
        'location,' .
        'businessStatus,' .
        'pureServiceAreaBusiness,' .
        'googleMapsUri,' .
        'websiteUri,' .
        'rating,' .
        'userRatingCount';

    /*
     * editorialSummary is an Enterprise + Atmosphere field. Keep it out of
     * the default Place Details mask because competitor enrichment calls
     * getPlaceDetails() for the final top five and does not need this field.
     */
    private const EDITORIAL_SUMMARY_FIELD =
        'editorialSummary';

    private readonly AnalysisDeadline $analysisDeadline;

    public function __construct(
        ?AnalysisDeadline $analysisDeadline = null
    ) {
        $this->analysisDeadline = $analysisDeadline
            ?? new AnalysisDeadline();
    }

    public function isConfigured(): bool
    {
        return filled(
            config('services.google_places.key')
        );
    }

    public function autocompleteBusinesses(
        string $input,
        string $sessionToken
    ): array {
        $input = trim($input);

        if ($input === '') {
            throw new RuntimeException(
                'Google Business search query cannot be empty.'
            );
        }

        $this->validateSessionToken(
            $sessionToken
        );

        $response = $this->request(
            'places:autocomplete',
            self::AUTOCOMPLETE_FIELD_MASK,
            [
                'input' => $input,

                'sessionToken'
                    => $sessionToken,

                /*
                 * Needed for businesses such as plumbers,
                 * cleaners and other service-area companies
                 * that may not publish a storefront location.
                 */
                'includePureServiceAreaBusinesses'
                    => true,

                /*
                 * We only need actual Google Places here.
                 * Query suggestions are not useful for selecting
                 * the user's own Google Business Profile.
                 */
                'includeQueryPredictions'
                    => false,
            ]
        );

        return $response->json(
            'suggestions',
            []
        );
    }

    public function searchBusinesses(
        string $query,
        int $maxResults = 5
    ): array {
        return $this->searchText(
            $query,
            $maxResults
        );
    }

    public function searchBusinessesNear(
        string $query,
        float $latitude,
        float $longitude,
        float $radiusKm = 50,
        int $maxResults = 10
    ): array {
        $this->validateCoordinates(
            $latitude,
            $longitude
        );

        if (
            $radiusKm <= 0
            || $radiusKm
                > self::MAX_LOCATION_BIAS_RADIUS_KM
        ) {
            throw new InvalidArgumentException(
                'Google Places location bias radius must be greater than 0 and no more than 50 km.'
            );
        }

        return $this->searchText(
            $query,
            $maxResults,
            [
                'rankPreference'
                    => 'RELEVANCE',

                'locationBias' => [
                    'circle' => [
                        'center' => [
                            'latitude'
                                => $latitude,

                            'longitude'
                                => $longitude,
                        ],

                        'radius'
                            => $radiusKm * 1000,
                    ],
                ],
            ]
        );
    }

    /**
     * Execute independent text-search requests concurrently. Each item accepts
     * query, max_results and, optionally, latitude, longitude and radius_km.
     * Failed requests return an empty result while successful peers are kept.
     */
    public function searchBusinessesBatch(
        array $searches
    ): array {
        $prepared = [];

        foreach ($searches as $key => $search) {
            if (! is_array($search)) {
                continue;
            }

            $query = $search['query'] ?? null;

            if (! is_string($query)) {
                continue;
            }

            $query = trim($query);

            if ($query === '') {
                continue;
            }

            $options = [];
            $latitude = $search['latitude'] ?? null;
            $longitude = $search['longitude'] ?? null;
            $radiusKm = $search['radius_km'] ?? null;

            if (
                is_numeric($latitude)
                && is_numeric($longitude)
                && is_numeric($radiusKm)
            ) {
                $latitude = (float) $latitude;
                $longitude = (float) $longitude;
                $radiusKm = (float) $radiusKm;

                $this->validateCoordinates(
                    $latitude,
                    $longitude
                );

                if (
                    $radiusKm <= 0
                    || $radiusKm
                        > self::MAX_LOCATION_BIAS_RADIUS_KM
                ) {
                    throw new InvalidArgumentException(
                        'Google Places location bias radius must be greater than 0 and no more than 50 km.'
                    );
                }

                $options = [
                    'rankPreference' => 'RELEVANCE',
                    'locationBias' => [
                        'circle' => [
                            'center' => [
                                'latitude' => $latitude,
                                'longitude' => $longitude,
                            ],
                            'radius' => $radiusKm * 1000,
                        ],
                    ],
                ];
            }

            $prepared[$key] = $this->searchPayload(
                $query,
                (int) ($search['max_results'] ?? 5),
                $options
            );
        }

        if ($prepared === []) {
            return [];
        }

        $apiKey = $this->apiKey();
        $baseUrl = $this->baseUrl();
        $requestTimeout = $this->requestTimeout();
        $connectTimeout = $this->connectTimeout(
            $requestTimeout
        );

        $responses = Http::pool(
            function (Pool $pool) use (
                $prepared,
                $apiKey,
                $baseUrl,
                $requestTimeout,
                $connectTimeout
            ): array {
                $requests = [];

                foreach ($prepared as $key => $payload) {
                    $requests[] = $pool
                        ->as((string) $key)
                        ->acceptJson()
                        ->withHeaders([
                            'X-Goog-Api-Key' => $apiKey,
                            'X-Goog-FieldMask'
                                => self::SEARCH_FIELD_MASK,
                        ])
                        ->connectTimeout($connectTimeout)
                        ->timeout($requestTimeout)
                        ->post(
                            $baseUrl . '/places:searchText',
                            $payload
                        );
                }

                return $requests;
            }
        );

        return $this->placesFromBatchResponses(
            $prepared,
            $responses,
            'search'
        );
    }

    public function getPlaceDetails(
        string $placeId,
        ?string $sessionToken = null,
        bool $includeEditorialSummary = false
    ): array {
        $placeId = trim(
            $placeId
        );

        if ($placeId === '') {
            throw new RuntimeException(
                'Google Place ID cannot be empty.'
            );
        }

        $query = [];

        if (
            is_string($sessionToken)
            && trim($sessionToken) !== ''
        ) {
            $sessionToken = trim(
                $sessionToken
            );

            $this->validateSessionToken(
                $sessionToken
            );

            $query['sessionToken']
                = $sessionToken;
        }

        $fieldMask = self::DETAILS_FIELD_MASK;

        if ($includeEditorialSummary) {
            $fieldMask .= ','
                . self::EDITORIAL_SUMMARY_FIELD;
        }

        $response = $this->request(
            'places/' . rawurlencode($placeId),
            $fieldMask,
            null,
            $query
        );

        return $response->json();
    }

    /**
     * Fetch final competitor details concurrently and retain all successful
     * responses when one or more peers fail.
     */
    public function getPlaceDetailsBatch(
        array $placeIds
    ): array {
        $prepared = [];

        foreach ($placeIds as $placeId) {
            if (! is_string($placeId)) {
                continue;
            }

            $placeId = trim($placeId);

            if ($placeId === '') {
                continue;
            }

            $prepared[$placeId] = $placeId;
        }

        if ($prepared === []) {
            return [];
        }

        $apiKey = $this->apiKey();
        $baseUrl = $this->baseUrl();
        $requestTimeout = $this->requestTimeout();
        $connectTimeout = $this->connectTimeout(
            $requestTimeout
        );
        $keys = array_keys($prepared);

        $responses = Http::pool(
            function (Pool $pool) use (
                $keys,
                $apiKey,
                $baseUrl,
                $requestTimeout,
                $connectTimeout
            ): array {
                $requests = [];

                foreach ($keys as $index => $placeId) {
                    $requests[] = $pool
                        ->as((string) $index)
                        ->acceptJson()
                        ->withHeaders([
                            'X-Goog-Api-Key' => $apiKey,
                            'X-Goog-FieldMask'
                                => self::DETAILS_FIELD_MASK,
                        ])
                        ->connectTimeout($connectTimeout)
                        ->timeout($requestTimeout)
                        ->get(
                            $baseUrl
                                . '/places/'
                                . rawurlencode($placeId)
                        );
                }

                return $requests;
            }
        );

        $details = [];

        foreach ($keys as $index => $placeId) {
            $response = $responses[(string) $index]
                ?? $responses[$index]
                ?? null;

            if (
                $response instanceof Response
                && $response->successful()
            ) {
                $payload = $response->json();

                if (is_array($payload)) {
                    $details[$placeId] = $payload;
                }

                continue;
            }

            $this->logBatchFailure(
                'details',
                $response
            );
        }

        return $details;
    }

    private function searchText(
        string $query,
        int $maxResults,
        array $options = []
    ): array {
        $payload = $this->searchPayload(
            $query,
            $maxResults,
            $options
        );

        $response = $this->request(
            'places:searchText',
            self::SEARCH_FIELD_MASK,
            $payload
        );

        return $response->json(
            'places',
            []
        );
    }

    private function searchPayload(
        string $query,
        int $maxResults,
        array $options = []
    ): array {
        $query = trim($query);

        if ($query === '') {
            throw new RuntimeException(
                'Google Business search query cannot be empty.'
            );
        }

        $pageSize = max(
            1,
            min(
                $maxResults,
                self::MAX_PAGE_SIZE
            )
        );

        return array_merge(
            [
                'textQuery' => $query,
                'pageSize' => $pageSize,
                'includePureServiceAreaBusinesses' => true,
            ],
            $options
        );
    }

    private function validateCoordinates(
        float $latitude,
        float $longitude
    ): void {
        if (
            ! is_finite($latitude)
            || $latitude < -90
            || $latitude > 90
        ) {
            throw new InvalidArgumentException(
                'Latitude must be between -90 and 90.'
            );
        }

        if (
            ! is_finite($longitude)
            || $longitude < -180
            || $longitude > 180
        ) {
            throw new InvalidArgumentException(
                'Longitude must be between -180 and 180.'
            );
        }
    }

    private function validateSessionToken(
        string $sessionToken
    ): void {
        $sessionToken = trim(
            $sessionToken
        );

        if (
            $sessionToken === ''
            || strlen($sessionToken) > 36
            || preg_match(
                '/^[A-Za-z0-9_-]+$/',
                $sessionToken
            ) !== 1
        ) {
            throw new InvalidArgumentException(
                'Invalid Google Places session token.'
            );
        }
    }

    private function request(
        string $path,
        string $fieldMask,
        ?array $payload = null,
        array $query = []
    ): Response {
        $apiKey = $this->apiKey();
        $baseUrl = $this->baseUrl();
        $requestTimeout = $this->requestTimeout();
        $connectTimeout = $this->connectTimeout(
            $requestTimeout
        );

        try {
            $request = Http::acceptJson()
                ->withHeaders([
                    'X-Goog-Api-Key'
                        => $apiKey,

                    'X-Goog-FieldMask'
                        => $fieldMask,
                ])
                ->connectTimeout($connectTimeout)
                ->timeout($requestTimeout);

            if ($payload !== null) {
                $response = $request->post(
                    $baseUrl . '/' . $path,
                    $payload
                );
            } else {
                $response = $request->get(
                    $baseUrl . '/' . $path,
                    $query
                );
            }
        } catch (
            ConnectionException $exception
        ) {
            throw new RuntimeException(
                'Could not connect to Google Places.',
                0,
                $exception
            );
        }

        if ($response->failed()) {
            throw new RuntimeException(
                'Google Places request failed with HTTP status '
                . $response->status()
                . '.'
            );
        }

        return $response;
    }

    private function apiKey(): string
    {
        $apiKey = config(
            'services.google_places.key'
        );

        if (
            ! is_string($apiKey)
            || trim($apiKey) === ''
        ) {
            throw new RuntimeException(
                'Google Places API is not configured.'
            );
        }

        return trim($apiKey);
    }

    private function baseUrl(): string
    {
        return rtrim(
            (string) config(
                'services.google_places.base_url',
                'https://places.googleapis.com/v1'
            ),
            '/'
        );
    }

    private function requestTimeout(): float
    {
        $preferred = max(
            1.0,
            min(
                12.0,
                (float) config(
                    'services.google_places.timeout',
                    6
                )
            )
        );

        return $this->analysisDeadline->timeoutFor(
            $preferred,
            0.5
        );
    }

    private function connectTimeout(
        float $requestTimeout
    ): float {
        $preferred = max(
            0.5,
            min(
                5.0,
                (float) config(
                    'services.google_places.connect_timeout',
                    2
                )
            )
        );

        return min(
            $preferred,
            $requestTimeout
        );
    }

    private function placesFromBatchResponses(
        array $prepared,
        array $responses,
        string $operation
    ): array {
        $places = [];

        foreach ($prepared as $key => $_payload) {
            $response = $responses[(string) $key]
                ?? $responses[$key]
                ?? null;

            if (
                $response instanceof Response
                && $response->successful()
            ) {
                $payload = $response->json(
                    'places',
                    []
                );

                $places[$key] = is_array($payload)
                    ? $payload
                    : [];

                continue;
            }

            $places[$key] = [];
            $this->logBatchFailure(
                $operation,
                $response
            );
        }

        return $places;
    }

    private function logBatchFailure(
        string $operation,
        mixed $response
    ): void {
        $context = [
            'provider' => 'google_places',
            'operation' => $operation,
        ];

        if ($response instanceof Response) {
            $context['status'] = $response->status();
        } elseif ($response instanceof Throwable) {
            $context['exception'] = $response::class;
        } else {
            $context['failure'] = 'missing_response';
        }

        Log::warning(
            'A Google Places batch request failed.',
            $context
        );
    }
}
