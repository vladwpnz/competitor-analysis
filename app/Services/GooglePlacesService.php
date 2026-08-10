<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use RuntimeException;

class GooglePlacesService
{
    private const MAX_PAGE_SIZE = 20;

    private const MAX_LOCATION_BIAS_RADIUS_KM = 50;

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

    public function isConfigured(): bool
    {
        return filled(
            config('services.google_places.key')
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
            || $radiusKm > self::MAX_LOCATION_BIAS_RADIUS_KM
        ) {
            throw new InvalidArgumentException(
                'Google Places location bias radius must be greater than 0 and no more than 50 km.'
            );
        }

        return $this->searchText(
            $query,
            $maxResults,
            [
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
            ]
        );
    }

    public function getPlaceDetails(
        string $placeId
    ): array {
        $placeId = trim($placeId);

        if ($placeId === '') {
            throw new RuntimeException(
                'Google Place ID cannot be empty.'
            );
        }

        $response = $this->request(
            'places/' . rawurlencode($placeId),
            self::DETAILS_FIELD_MASK
        );

        return $response->json();
    }

    private function searchText(
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

        $payload = [
            'textQuery' => $query,
            'pageSize' => $pageSize,
            'includePureServiceAreaBusinesses' => true,
        ];

        $payload = array_merge(
            $payload,
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

    private function request(
        string $path,
        string $fieldMask,
        ?array $payload = null
    ): Response {
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

        $baseUrl = rtrim(
            (string) config(
                'services.google_places.base_url',
                'https://places.googleapis.com/v1'
            ),
            '/'
        );

        try {
            $request = Http::acceptJson()
                ->withHeaders([
                    'X-Goog-Api-Key'
                        => $apiKey,
                    'X-Goog-FieldMask'
                        => $fieldMask,
                ])
                ->connectTimeout(5)
                ->timeout(12);

            $response = $payload === null
                ? $request->get(
                    $baseUrl . '/' . $path
                )
                : $request->post(
                    $baseUrl . '/' . $path,
                    $payload
                );
        } catch (ConnectionException $exception) {
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
}