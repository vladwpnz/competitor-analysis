<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class GooglePlacesService
{
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
        return filled(config('services.google_places.key'));
    }

    public function searchBusinesses(
        string $query,
        int $maxResults = 5
    ): array {
        $query = trim($query);

        if ($query === '') {
            throw new RuntimeException(
                'Google Business search query cannot be empty.'
            );
        }

        $maxResults = max(1, min($maxResults, 10));

        $response = $this->request(
            'places:searchText',
            self::SEARCH_FIELD_MASK,
            [
                'textQuery' => $query,
                'maxResultCount' => $maxResults,

                // Important for plumbers, electricians and other
                // businesses that may not publish a physical address.
                'includePureServiceAreaBusinesses' => true,
            ]
        );

        return $response->json('places', []);
    }

    public function getPlaceDetails(string $placeId): array
    {
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

    private function request(
        string $path,
        string $fieldMask,
        ?array $payload = null
    ): Response {
        $apiKey = config('services.google_places.key');

        if (! is_string($apiKey) || trim($apiKey) === '') {
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
                    'X-Goog-Api-Key' => $apiKey,
                    'X-Goog-FieldMask' => $fieldMask,
                ])
                ->connectTimeout(5)
                ->timeout(12);

            $response = $payload === null
                ? $request->get($baseUrl . '/' . $path)
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