<?php

namespace Tests\Feature;

use App\Services\GooglePlacesService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use RuntimeException;
use Tests\TestCase;

class GooglePlacesServiceTest extends TestCase
{
    public function test_service_is_not_configured_without_api_key(): void
    {
        config([
            'services.google_places.key' => null,
        ]);

        $service = app(
            GooglePlacesService::class
        );

        $this->assertFalse(
            $service->isConfigured()
        );

        $this->expectException(
            RuntimeException::class
        );

        $this->expectExceptionMessage(
            'Google Places API is not configured.'
        );

        $service->searchBusinesses(
            'test'
        );
    }

    public function test_search_businesses_returns_places(): void
    {
        $this->configureGoogle();

        Http::preventStrayRequests();

        Http::fake([
            'https://places.googleapis.com/v1/places:searchText'
                => Http::response([
                    'places' => [
                        [
                            'id' => 'test-place-1',

                            'displayName' => [
                                'text'
                                    => 'Acme Plumbing',
                                'languageCode'
                                    => 'en',
                            ],

                            'formattedAddress'
                                => '100 Main St, Example City',

                            'primaryType'
                                => 'plumber',

                            'types' => [
                                'plumber',
                            ],
                        ],
                    ],
                ], 200),
        ]);

        $service = app(
            GooglePlacesService::class
        );

        $places = $service->searchBusinesses(
            'plumber Example City',
            5
        );

        $this->assertCount(
            1,
            $places
        );

        $this->assertSame(
            'test-place-1',
            $places[0]['id']
        );

        $this->assertSame(
            'Acme Plumbing',
            $places[0]['displayName']['text']
        );

        Http::assertSent(
            function (Request $request): bool {
                return
                    $request->url()
                        === 'https://places.googleapis.com/v1/places:searchText'

                    && $request->hasHeader(
                        'X-Goog-Api-Key',
                        'test-api-key'
                    )

                    && $request->hasHeader(
                        'X-Goog-FieldMask'
                    )

                    && $request['textQuery']
                        === 'plumber Example City'

                    && $request['pageSize']
                        === 5

                    && $request[
                        'includePureServiceAreaBusinesses'
                    ] === true;
            }
        );
    }

    public function test_search_businesses_near_uses_location_bias(): void
    {
        $this->configureGoogle();

        Http::preventStrayRequests();

        Http::fake([
            'https://places.googleapis.com/v1/places:searchText'
                => Http::response([
                    'places' => [
                        [
                            'id'
                                => 'local-place-1',

                            'displayName' => [
                                'text'
                                    => 'Toronto Plumbing Co.',
                            ],

                            'primaryType'
                                => 'plumber',

                            'location' => [
                                'latitude'
                                    => 43.6532,

                                'longitude'
                                    => -79.3832,
                            ],
                        ],
                    ],
                ], 200),
        ]);

        $service = app(
            GooglePlacesService::class
        );

        $places = $service->searchBusinessesNear(
            'plumber',
            43.6532,
            -79.3832,
            50,
            12
        );

        $this->assertCount(
            1,
            $places
        );

        $this->assertSame(
            'local-place-1',
            $places[0]['id']
        );

        Http::assertSent(
            function (Request $request): bool {
                return
                    $request['textQuery']
                        === 'plumber'

                    && $request['pageSize']
                        === 12

                    && $request['rankPreference']
                        === 'RELEVANCE'

                    && $request[
                        'includePureServiceAreaBusinesses'
                    ] === true

                    && $request[
                        'locationBias'
                    ]['circle']['center']['latitude']
                        === 43.6532

                    && $request[
                        'locationBias'
                    ]['circle']['center']['longitude']
                        === -79.3832

                    && (float) $request[
                        'locationBias'
                    ]['circle']['radius']
                        === 50000.0;
            }
        );
    }

    public function test_local_search_rejects_radius_above_google_limit(): void
    {
        $this->configureGoogle();

        $service = app(
            GooglePlacesService::class
        );

        $this->expectException(
            InvalidArgumentException::class
        );

        $this->expectExceptionMessage(
            'Google Places location bias radius must be greater than 0 and no more than 50 km.'
        );

        $service->searchBusinessesNear(
            'plumber',
            43.6532,
            -79.3832,
            100,
            10
        );
    }

    public function test_local_search_rejects_invalid_coordinates(): void
    {
        $this->configureGoogle();

        $service = app(
            GooglePlacesService::class
        );

        $this->expectException(
            InvalidArgumentException::class
        );

        $service->searchBusinessesNear(
            'plumber',
            95.0,
            -79.3832,
            50,
            10
        );
    }

    public function test_place_details_returns_business_data(): void
    {
        $this->configureGoogle();

        Http::preventStrayRequests();

        Http::fake([
            'https://places.googleapis.com/v1/places/test-place-1'
                => Http::response([
                    'id'
                        => 'test-place-1',

                    'displayName' => [
                        'text'
                            => 'Acme Plumbing',
                        'languageCode'
                            => 'en',
                    ],

                    'formattedAddress'
                        => '100 Main St, Example City',

                    'primaryType'
                        => 'plumber',

                    'types' => [
                        'plumber',
                    ],

                    'location' => [
                        'latitude'
                            => 40.7128,

                        'longitude'
                            => -74.0060,
                    ],

                    'businessStatus'
                        => 'OPERATIONAL',

                    'websiteUri'
                        => 'https://example.test',

                    'rating'
                        => 4.8,

                    'userRatingCount'
                        => 127,
                ], 200),
        ]);

        $service = app(
            GooglePlacesService::class
        );

        $place = $service->getPlaceDetails(
            'test-place-1'
        );

        $this->assertSame(
            'test-place-1',
            $place['id']
        );

        $this->assertSame(
            'Acme Plumbing',
            $place['displayName']['text']
        );

        $this->assertSame(
            4.8,
            $place['rating']
        );

        $this->assertSame(
            127,
            $place['userRatingCount']
        );

        Http::assertSent(
            function (Request $request): bool {
                return
                    $request->url()
                        === 'https://places.googleapis.com/v1/places/test-place-1'

                    && $request->hasHeader(
                        'X-Goog-Api-Key',
                        'test-api-key'
                    )

                    && $request->hasHeader(
                        'X-Goog-FieldMask'
                    )
                    && ! str_contains(
                        (string) (
                            $request->header(
                                'X-Goog-FieldMask'
                            )[0] ?? ''
                        ),
                        'editorialSummary'
                    );
            }
        );
    }

    public function test_place_details_can_include_editorial_summary_for_reference_company(): void
    {
        $this->configureGoogle();

        Http::preventStrayRequests();

        Http::fake([
            'https://places.googleapis.com/v1/places/test-place-1*'
                => Http::response([
                    'id'
                        => 'test-place-1',

                    'displayName' => [
                        'text'
                            => 'Acme Plumbing',
                    ],

                    'editorialSummary' => [
                        'text'
                            => 'Local plumbing company providing emergency repairs, drain cleaning and water heater service.',

                        'languageCode'
                            => 'en',
                    ],
                ], 200),
        ]);

        $sessionToken =
            '550e8400-e29b-41d4-a716-446655440000';

        $place = app(
            GooglePlacesService::class
        )->getPlaceDetails(
            'test-place-1',
            $sessionToken,
            true
        );

        $this->assertSame(
            'Local plumbing company providing emergency repairs, drain cleaning and water heater service.',
            $place['editorialSummary']['text']
        );

        Http::assertSent(
            function (Request $request) use (
                $sessionToken
            ): bool {
                $fieldMask = (string) (
                    $request->header(
                        'X-Goog-FieldMask'
                    )[0] ?? ''
                );

                return
                    str_contains(
                        $fieldMask,
                        'editorialSummary'
                    )
                    && str_contains(
                        $request->url(),
                        'sessionToken=' . $sessionToken
                    );
            }
        );
    }

    public function test_independent_account_searches_are_sent_as_one_batch(): void
    {
        $this->configureGoogle();
        Http::preventStrayRequests();

        Http::fake([
            'https://places.googleapis.com/v1/places:searchText'
                => Http::sequence()
                    ->push([
                        'places' => [[
                            'id' => 'plumber-1',
                            'displayName' => [
                                'text' => 'Alpha Plumbing',
                            ],
                        ]],
                    ])
                    ->push([
                        'places' => [[
                            'id' => 'drain-1',
                            'displayName' => [
                                'text' => 'Bravo Drain Service',
                            ],
                        ]],
                    ]),
        ]);

        $results = app(
            GooglePlacesService::class
        )->searchBusinessesBatch([
            [
                'query' => 'Plumber',
                'max_results' => 15,
            ],
            [
                'query' => 'Drain Cleaning',
                'max_results' => 15,
            ],
        ]);

        $this->assertSame(
            'plumber-1',
            $results[0][0]['id']
        );
        $this->assertSame(
            'drain-1',
            $results[1][0]['id']
        );
        Http::assertSentCount(2);
    }

    public function test_batch_details_preserve_successful_peers_when_one_request_fails(): void
    {
        $this->configureGoogle();
        Http::preventStrayRequests();

        Http::fake([
            'https://places.googleapis.com/v1/places/place-1'
                => Http::response([
                    'id' => 'place-1',
                    'rating' => 4.8,
                ]),
            'https://places.googleapis.com/v1/places/place-2'
                => Http::response([], 504),
        ]);

        $details = app(
            GooglePlacesService::class
        )->getPlaceDetailsBatch([
            'place-1',
            'place-2',
        ]);

        $this->assertSame(
            4.8,
            $details['place-1']['rating']
        );
        $this->assertArrayNotHasKey(
            'place-2',
            $details
        );
        Http::assertSentCount(2);
    }

    private function configureGoogle(): void
    {
        config([
            'services.google_places.key'
                => 'test-api-key',

            'services.google_places.base_url'
                => 'https://places.googleapis.com/v1',
        ]);
    }
}
